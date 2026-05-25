import { Router, type IRouter, type Request, type Response, type NextFunction } from "express";
import { BOTS, getTarballUrl, type BotConfig } from "../config/bots.js";
import * as heroku from "../services/heroku.js";
import * as queue from "../services/queue.js";
import { logger } from "../lib/logger.js";
import { getApiSecretKey, getHerokuApiKey } from "../services/settings.js";
import { registerApp, getAppKey, deregisterApp } from "../services/appRegistry.js";

const router: IRouter = Router();

function requireApiKey(req: Request, res: Response, next: NextFunction): void {
  const secret = getApiSecretKey();
  if (!secret) { res.status(503).json({ success: false, error: "API key not configured." }); return; }
  const fromHeader = (req.headers["x-api-key"] as string | undefined) ??
    (req.headers["authorization"]?.startsWith("Bearer ") ? req.headers["authorization"].slice(7) : undefined);
  const provided = fromHeader ?? (req.query["key"] as string | undefined) ?? "";
  if (provided !== secret) { res.status(401).json({ success: false, error: "Unauthorized: invalid API key." }); return; }
  next();
}

router.use((_req, res, next) => {
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Methods", "GET, POST, PATCH, DELETE, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type, X-API-Key, Authorization");
  next();
});

router.options("/*splat", (_req, res) => { res.sendStatus(204); });

function buildConfigVars(bot: BotConfig, botVars: Record<string, string>, botType: string): Record<string, string> {
  const vars: Record<string, string> = { BOT_NAME: botType.toUpperCase(), NODE_ENV: "production" };
  for (const f of bot.fields) { if (botVars[f.key]) vars[f.envVar] = botVars[f.key]!; }
  return vars;
}

async function runDeployment(job: queue.DeploymentJob, bot: BotConfig, tarballUrl: string, configVars: Record<string, string>, apiKey?: string): Promise<void> {
  try {
    queue.updateJob(job.id, { status: "creating_app" });
    await heroku.createApp(job.appName, apiKey);
    queue.updateJob(job.id, { status: "setting_buildpack" });
    if (bot.containerStack) await heroku.setStack(job.appName, "container", apiKey);
    else await heroku.setBuildpacks(job.appName, ["heroku/nodejs"], apiKey);
    queue.updateJob(job.id, { status: "setting_config" });
    await heroku.setConfigVars(job.appName, configVars, apiKey);
    queue.updateJob(job.id, { status: "deploying" });
    const build = await heroku.deploySource(job.appName, tarballUrl, apiKey);
    await heroku.waitForBuild(job.appName, build.id, tarballUrl, apiKey);
    queue.updateJob(job.id, { status: "scaling" });
    await heroku.scaleDyno(job.appName, 1, apiKey);
    const logsUrl = await heroku.getLogsUrl(job.appName, apiKey);
    queue.updateJob(job.id, { status: "completed", appUrl: `https://${job.appName}.herokuapp.com`, logsUrl });
    if (apiKey) registerApp(job.appName, apiKey);
  } catch (err) {
    queue.updateJob(job.id, { status: "failed", error: err instanceof Error ? err.message : String(err) });
  }
}

router.get("/external/bots", (_req, res) => {
  const bots = Object.entries(BOTS).map(([key, bot]) => ({
    key, name: bot.name,
    fields: bot.fields.map((f) => ({ key: f.key, label: f.label, type: f.type ?? "text", required: f.required, placeholder: f.placeholder, options: f.options ?? null })),
  }));
  res.json({ success: true, bots });
});

router.post("/external/deploy", requireApiKey, async (req: Request, res: Response) => {
  const body = req.body as Record<string, unknown>;
  const botType = typeof body["botType"] === "string" ? body["botType"].trim() : "";
  const appName = typeof body["appName"] === "string" ? body["appName"].trim().toLowerCase() : "";
  const botVars = (body["botVars"] && typeof body["botVars"] === "object" && !Array.isArray(body["botVars"])) ? body["botVars"] as Record<string, string> : null;

  if (!botType) { res.status(400).json({ success: false, error: "botType is required" }); return; }
  if (!appName || appName.length < 3 || appName.length > 30 || !/^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(appName)) {
    res.status(400).json({ success: false, error: "appName must be 3–30 lowercase chars, no leading/trailing dash" }); return;
  }
  if (!botVars) { res.status(400).json({ success: false, error: "botVars is required" }); return; }

  const bot = BOTS[botType];
  if (!bot) { res.status(400).json({ success: false, error: `Unknown botType. Supported: ${Object.keys(BOTS).join(", ")}` }); return; }

  for (const f of bot.fields) {
    if (f.required && !botVars[f.key]) { res.status(400).json({ success: false, error: `Missing: ${f.label} (key: ${f.key})` }); return; }
  }

  const existing = queue.getJobByAppName(appName);
  const busy = ["queued", "creating_app", "setting_buildpack", "setting_config", "deploying", "scaling"];
  if (existing && busy.includes(existing.status)) { res.status(409).json({ success: false, error: `"${appName}" already deploying` }); return; }

  const job = queue.createJob({ appName, botType, sessionId: "" });
  void runDeployment(job, bot, getTarballUrl(bot), buildConfigVars(bot, botVars, botType), getHerokuApiKey());
  logger.info({ jobId: job.id, botType, appName }, "External deploy queued");

  res.status(202).json({
    success: true, jobId: job.id,
    statusUrl: `/api/external/status/${job.id}`,
    appUrl: `https://${appName}.herokuapp.com`,
    herokuDashboard: `https://dashboard.heroku.com/apps/${appName}`,
    message: "Deployment queued. Poll statusUrl every 5s.",
  });
});

router.get("/external/status/:jobId", requireApiKey, (req: Request, res: Response) => {
  const job = queue.getJob(req.params["jobId"]!);
  if (!job) { res.status(404).json({ success: false, error: "Job not found." }); return; }
  res.json({ success: true, jobId: job.id, appName: job.appName, botType: job.botType, status: job.status, appUrl: job.appUrl ?? null, logsUrl: job.logsUrl ?? null, error: job.error ?? null });
});

router.get("/external/config/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    const vars = await heroku.getConfigVars(appName!, getAppKey(appName!) ?? getHerokuApiKey());
    res.json({ success: true, appName, configVars: vars });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

router.patch("/external/config/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  const { configVars } = req.body as { configVars?: unknown };
  if (!configVars || typeof configVars !== "object" || Array.isArray(configVars)) { res.status(400).json({ success: false, error: "configVars must be an object" }); return; }
  try {
    await heroku.setConfigVars(appName!, configVars as Record<string, string>, getAppKey(appName!) ?? getHerokuApiKey());
    res.json({ success: true, message: "Config vars updated." });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

router.post("/external/restart/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    await heroku.restartDynos(appName!, getAppKey(appName!) ?? getHerokuApiKey());
    res.json({ success: true, message: `${appName} dynos restarted.` });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

router.delete("/external/delete/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    await heroku.deleteApp(appName!, getAppKey(appName!) ?? getHerokuApiKey());
    deregisterApp(appName!);
    res.json({ success: true, message: `App "${appName}" deleted.` });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

router.get("/external/check/:appName", requireApiKey, async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    const info = await heroku.checkApp(appName!, getAppKey(appName!) ?? getHerokuApiKey());
    if (!info.exists) { res.json({ success: true, exists: false, status: "not_found" }); return; }
    const status = info.latestBuildStatus === "succeeded" ? "completed" : info.latestBuildStatus === "failed" ? "failed" : "deploying";
    res.json({ success: true, exists: true, status, appUrl: info.webUrl ?? `https://${appName}.herokuapp.com` });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

export default router;
