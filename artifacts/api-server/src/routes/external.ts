import { Router, type IRouter, type Request, type Response, type NextFunction } from "express";
import { BOTS, getTarballUrl, type BotConfig } from "../config/bots.js";
import * as heroku from "../services/heroku.js";
import * as queue from "../services/queue.js";
import { logger } from "../lib/logger.js";
import { getApiSecretKey, getHerokuApiKey } from "../services/settings.js";
import { registerApp, getAppKey, deregisterApp } from "../services/appRegistry.js";

const router: IRouter = Router();

// ── API Key auth middleware ──────────────────────────────────────────────────
function requireApiKey(req: Request, res: Response, next: NextFunction): void {
  const secret = getApiSecretKey();
  if (!secret) {
    res.status(503).json({ success: false, error: "API secret key is not configured. Set it in Admin Settings." });
    return;
  }

  const fromHeader =
    req.headers["x-api-key"] as string | undefined ??
    (req.headers["authorization"]?.startsWith("Bearer ")
      ? req.headers["authorization"].slice(7)
      : undefined);
  const fromQuery = req.query["key"] as string | undefined;
  const provided = fromHeader ?? fromQuery ?? "";

  if (provided !== secret) {
    logger.warn({ ip: req.ip, path: req.path }, "Rejected external API request — invalid API key");
    res.status(401).json({ success: false, error: "Unauthorized: invalid or missing API key. Pass X-API-Key header or ?key= query param." });
    return;
  }
  next();
}

// ── CORS for external callers ─────────────────────────────────────────────────
router.use((_req: Request, res: Response, next: NextFunction) => {
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Methods", "GET, POST, PATCH, DELETE, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type, X-API-Key, Authorization");
  next();
});

router.options("/*splat", (_req: Request, res: Response) => {
  res.sendStatus(204);
});

// ── Helpers (mirrors deploy.ts logic) ────────────────────────────────────────
function buildHerokuConfigVars(
  bot: BotConfig,
  botVars: Record<string, string>,
  botType: string,
): Record<string, string> {
  const vars: Record<string, string> = {
    BOT_NAME: botType.toUpperCase(),
    NODE_ENV: "production",
  };
  for (const field of bot.fields) {
    const value = botVars[field.key];
    if (value) vars[field.envVar] = value;
  }
  return vars;
}

async function runDeployment(
  job: queue.DeploymentJob,
  bot: BotConfig,
  tarballUrl: string,
  configVars: Record<string, string>,
  apiKey?: string,
): Promise<void> {
  try {
    queue.updateJob(job.id, { status: "creating_app" });
    await heroku.createApp(job.appName, apiKey);

    queue.updateJob(job.id, { status: "setting_buildpack" });
    if (bot.containerStack) {
      await heroku.setStack(job.appName, "container", apiKey);
    } else {
      await heroku.setBuildpacks(job.appName, ["heroku/nodejs"], apiKey);
    }

    queue.updateJob(job.id, { status: "setting_config" });
    await heroku.setConfigVars(job.appName, configVars, apiKey);

    queue.updateJob(job.id, { status: "deploying" });
    const build = await heroku.deploySource(job.appName, tarballUrl, apiKey);
    await heroku.waitForBuild(job.appName, build.id, tarballUrl, apiKey);

    queue.updateJob(job.id, { status: "scaling" });
    await heroku.scaleDyno(job.appName, 1, apiKey);

    const logsUrl = await heroku.getLogsUrl(job.appName, apiKey);
    queue.updateJob(job.id, {
      status: "completed",
      appUrl: `https://${job.appName}.herokuapp.com`,
      logsUrl,
    });

    // Save the key used so management endpoints work even after key rotation
    if (apiKey) registerApp(job.appName, apiKey);
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    queue.updateJob(job.id, { status: "failed", error: message });
  }
}

// ── POST /api/external/deploy ─────────────────────────────────────────────────
/**
 * Deploy a bot to Heroku.
 *
 * Headers:
 *   X-API-Key: <your API key>   (or Authorization: Bearer <key>, or ?key=<key>)
 *   Content-Type: application/json
 *
 * Body:
 * {
 *   "botType": "bwm",           // one of: cypherx, bwm, cypherxultra, kingmd, anitav4, atassa
 *   "appName": "my-bot-01",     // unique Heroku app name (lowercase, 3–30 chars, letters/numbers/dashes)
 *   "botVars": {                // fields required by the chosen bot type
 *     "session": "...",
 *     "ownerNumber": "254700000000"
 *   }
 * }
 *
 * Response 202:
 * {
 *   "success": true,
 *   "jobId": "my-bot-01-1712345678",
 *   "statusUrl": "/api/external/status/my-bot-01-1712345678",
 *   "appUrl": "https://my-bot-01.herokuapp.com"
 * }
 */
router.post("/external/deploy", requireApiKey, async (req: Request, res: Response) => {
  const body = req.body as Record<string, unknown>;
  const botType = typeof body["botType"] === "string" ? body["botType"].trim() : "";
  const appName = typeof body["appName"] === "string" ? body["appName"].trim().toLowerCase() : "";
  const botVars = (body["botVars"] && typeof body["botVars"] === "object" && !Array.isArray(body["botVars"]))
    ? body["botVars"] as Record<string, unknown>
    : null;

  if (!botType) { res.status(400).json({ success: false, error: "botType is required." }); return; }
  if (!appName || appName.length < 3 || appName.length > 30 || !/^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(appName)) {
    res.status(400).json({ success: false, error: "appName must be 3–30 chars, lowercase letters/numbers/dashes, no leading or trailing dash." });
    return;
  }
  if (!botVars) { res.status(400).json({ success: false, error: "botVars object is required." }); return; }

  const bot = BOTS[botType];
  if (!bot) {
    res.status(400).json({
      success: false,
      error: `Unknown botType "${botType}". Supported: ${Object.keys(BOTS).join(", ")}`,
    });
    return;
  }

  // Validate required fields
  const vars: Record<string, string> = {};
  for (const field of bot.fields) {
    const val = typeof botVars[field.key] === "string" ? (botVars[field.key] as string).trim() : "";
    if (field.required && !val) {
      res.status(400).json({ success: false, error: `Missing required field: ${field.label} (key: "${field.key}")` });
      return;
    }
    if (val) vars[field.key] = val;
  }

  // Reject duplicate in-progress deployments
  const existing = queue.getJobByAppName(appName);
  const busy = ["queued", "creating_app", "setting_buildpack", "setting_config", "deploying", "scaling"];
  if (existing && busy.includes(existing.status)) {
    res.status(409).json({
      success: false,
      error: `App "${appName}" already has an active deployment (status: ${existing.status})`,
    });
    return;
  }

  const job = queue.createJob({ appName, botType, sessionId: "" });
  const tarballUrl = getTarballUrl(bot);
  const configVars = buildHerokuConfigVars(bot, vars, botType);

  void runDeployment(job, bot, tarballUrl, configVars, getHerokuApiKey());

  logger.info({ jobId: job.id, botType, appName }, "External deploy queued");

  res.status(202).json({
    success: true,
    jobId: job.id,
    statusUrl: `/api/external/status/${job.id}`,
    appUrl: `https://${appName}.herokuapp.com`,
    herokuDashboard: `https://dashboard.heroku.com/apps/${appName}`,
    message: "Deployment queued. Poll statusUrl every 5 seconds to track progress.",
  });
});

// ── GET /api/external/status/:jobId ──────────────────────────────────────────
/**
 * Poll the status of a deployment job.
 *
 * Status values: queued | creating_app | setting_buildpack | setting_config | deploying | scaling | completed | failed
 */
router.get("/external/status/:jobId", requireApiKey, (req: Request, res: Response) => {
  const job = queue.getJob(req.params["jobId"]!);
  if (!job) {
    res.status(404).json({ success: false, error: "Job not found. It may have been removed or the ID is wrong." });
    return;
  }
  res.json({
    success: true,
    jobId: job.id,
    appName: job.appName,
    botType: job.botType,
    status: job.status,
    appUrl: job.appUrl ?? null,
    logsUrl: job.logsUrl ?? null,
    error: job.error ?? null,
    createdAt: job.createdAt,
    updatedAt: job.updatedAt,
    scheduledDeletionAt: job.scheduledDeletionAt,
  });
});

// ── GET /api/external/bots ────────────────────────────────────────────────────
/**
 * List all supported bot types and their required fields.
 * Useful for building dynamic forms on external clients.
 */
router.get("/external/bots", requireApiKey, (_req: Request, res: Response) => {
  const bots = Object.entries(BOTS).map(([key, bot]) => ({
    key,
    name: bot.name,
    fields: bot.fields.map((f) => ({
      key: f.key,
      label: f.label,
      type: f.type ?? "text",
      required: f.required,
      placeholder: f.placeholder,
      options: f.options ?? null,
    })),
  }));
  res.json({ success: true, bots });
});

// ── GET /api/external/config/:appName ────────────────────────────────────────
router.get("/external/config/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  const storedKey = getAppKey(appName!) ?? getHerokuApiKey();
  try {
    const configVars = await heroku.getConfigVars(appName!, storedKey);
    res.json({ success: true, appName, configVars });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

// ── PATCH /api/external/config/:appName ──────────────────────────────────────
router.patch("/external/config/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  const body = req.body as { configVars?: unknown };
  const configVars = body.configVars;

  if (!configVars || typeof configVars !== "object" || Array.isArray(configVars)) {
    res.status(400).json({ success: false, error: "configVars must be a key-value object" });
    return;
  }
  for (const [k, v] of Object.entries(configVars as Record<string, unknown>)) {
    if (typeof v !== "string") {
      res.status(400).json({ success: false, error: `configVars.${k} must be a string` });
      return;
    }
  }
  try {
    const storedKeyPatch = getAppKey(appName!) ?? getHerokuApiKey();
    await heroku.setConfigVars(appName!, configVars as Record<string, string>, storedKeyPatch);
    res.json({ success: true, message: "Config vars updated. Heroku will restart the dyno." });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

// ── POST /api/external/restart/:appName ──────────────────────────────────────
router.post("/external/restart/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  const storedKeyRestart = getAppKey(appName!) ?? getHerokuApiKey();
  try {
    await heroku.restartDynos(appName!, storedKeyRestart);
    res.json({ success: true, message: `All dynos for ${appName} restarted successfully.` });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

// ── GET /api/external/logs/:appName ──────────────────────────────────────────
router.get("/external/logs/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  const storedKeyLogs = getAppKey(appName!) ?? getHerokuApiKey();
  const lines = Math.min(parseInt(String(req.query["lines"] ?? "200"), 10) || 200, 1500);
  try {
    const logText = await heroku.fetchLogs(appName!, lines, storedKeyLogs);
    res.json({ success: true, appName, logText });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

// ── DELETE /api/external/delete/:appName ─────────────────────────────────────
router.delete("/external/delete/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  const storedKeyDel = getAppKey(appName!) ?? getHerokuApiKey();
  try {
    await heroku.deleteApp(appName!, storedKeyDel);
    deregisterApp(appName!);
    res.json({ success: true, message: `App "${appName}" deleted from Heroku.` });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

// ── GET /api/external/check/:appName ─────────────────────────────────────────
// Checks the real deployment status directly from Heroku, bypassing the
// in-memory job queue. Used as a fallback when a job ID is no longer in memory
// (e.g. after a server restart mid-deployment).
router.get("/external/check/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  const storedKeyCheck = getAppKey(appName!) ?? getHerokuApiKey();
  try {
    const info = await heroku.checkApp(appName!, storedKeyCheck);
    if (!info.exists) {
      res.json({ success: true, exists: false, status: "not_found" });
      return;
    }
    const status =
      info.latestBuildStatus === "succeeded" ? "completed" :
      info.latestBuildStatus === "failed"    ? "failed"    :
      "deploying";
    res.json({
      success: true,
      exists: true,
      status,
      appUrl: info.webUrl ?? `https://${appName}.herokuapp.com`,
      buildStatus: info.latestBuildStatus ?? null,
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

export default router;
