import { Router, type IRouter, type Request, type Response } from "express";
import { BOTS, getTarballUrl, type BotConfig } from "../config/bots.js";
import * as heroku from "../services/heroku.js";
import * as queue from "../services/queue.js";

const router: IRouter = Router();

function validateDeployBody(
  body: unknown,
): { botType: string; appName: string; botVars: Record<string, string> } | string {
  if (!body || typeof body !== "object") return "Request body must be a JSON object";
  const b = body as Record<string, unknown>;
  const { botType, appName, botVars } = b;
  if (typeof botType !== "string" || !botType) return "botType is required (string)";
  if (typeof appName !== "string") return "appName is required (string)";
  if (appName.length < 3 || appName.length > 30) return "appName must be between 3 and 30 characters";
  if (!/^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(appName))
    return "appName must use lowercase letters, numbers, and dashes only — cannot start or end with a dash";
  if (!botVars || typeof botVars !== "object" || Array.isArray(botVars))
    return "botVars is required (object of string key-value pairs)";
  const vars = botVars as Record<string, unknown>;
  for (const [k, v] of Object.entries(vars)) {
    if (typeof v !== "string") return `botVars.${k} must be a string`;
  }
  return { botType, appName, botVars: vars as Record<string, string> };
}

function validateBotVars(
  bot: BotConfig,
  botVars: Record<string, string>,
): string | null {
  for (const field of bot.fields) {
    if (field.required && !botVars[field.key]) {
      return `Missing required field: ${field.label} (key: ${field.key})`;
    }
  }
  return null;
}

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
    if (value) {
      vars[field.envVar] = value;
    }
  }
  return vars;
}

async function runDeployment(
  job: queue.DeploymentJob,
  bot: BotConfig,
  tarballUrl: string,
  configVars: Record<string, string>,
): Promise<void> {
  try {
    queue.updateJob(job.id, { status: "creating_app" });
    await heroku.createApp(job.appName);

    queue.updateJob(job.id, { status: "setting_buildpack" });
    if (bot.containerStack) {
      await heroku.setStack(job.appName, "container");
    } else {
      await heroku.setBuildpacks(job.appName, ["heroku/nodejs"]);
    }

    queue.updateJob(job.id, { status: "setting_config" });
    await heroku.setConfigVars(job.appName, configVars);

    queue.updateJob(job.id, { status: "deploying" });
    const build = await heroku.deploySource(job.appName, tarballUrl);
    await heroku.waitForBuild(job.appName, build.id, tarballUrl);

    queue.updateJob(job.id, { status: "scaling" });
    await heroku.scaleDyno(job.appName);

    const logsUrl = await heroku.getLogsUrl(job.appName);
    queue.updateJob(job.id, {
      status: "completed",
      appUrl: `https://${job.appName}.herokuapp.com`,
      logsUrl,
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    queue.updateJob(job.id, { status: "failed", error: message });
  }
}

router.post("/deploy", async (req: Request, res: Response) => {
  const parsed = validateDeployBody(req.body);
  if (typeof parsed === "string") {
    res.status(400).json({ success: false, error: parsed });
    return;
  }

  const { botType, appName, botVars } = parsed;

  const bot = BOTS[botType];
  if (!bot) {
    res.status(400).json({
      success: false,
      error: `Unknown botType "${botType}". Supported: ${Object.keys(BOTS).join(", ")}`,
    });
    return;
  }

  const fieldError = validateBotVars(bot, botVars);
  if (fieldError) {
    res.status(400).json({ success: false, error: fieldError });
    return;
  }

  const existing = queue.getJobByAppName(appName);
  const inProgress = ["queued", "creating_app", "setting_buildpack", "setting_config", "deploying", "scaling"];
  if (existing && inProgress.includes(existing.status)) {
    res.status(409).json({
      success: false,
      error: `App "${appName}" already has an active deployment in progress (status: ${existing.status})`,
    });
    return;
  }

  const job = queue.createJob({ appName, botType, sessionId: "" });
  const tarballUrl = getTarballUrl(bot);
  const configVars = buildHerokuConfigVars(bot, botVars, botType);

  void runDeployment(job, bot, tarballUrl, configVars);

  res.status(202).json({
    success: true,
    jobId: job.id,
    message: "Deployment queued. Use GET /api/deploy/status/:jobId to track progress.",
    appUrl: `https://${appName}.herokuapp.com`,
    dashboard: `https://dashboard.heroku.com/apps/${appName}`,
  });
});

router.get("/deploy/status/:jobId", (req: Request, res: Response) => {
  const job = queue.getJob(req.params["jobId"]!);
  if (!job) {
    res.status(404).json({ success: false, error: "Job not found" });
    return;
  }

  res.json({
    success: true,
    job: {
      id: job.id,
      appName: job.appName,
      botType: job.botType,
      status: job.status,
      appUrl: job.appUrl,
      logsUrl: job.logsUrl,
      error: job.error,
      createdAt: job.createdAt,
      updatedAt: job.updatedAt,
    },
  });
});

router.get("/deploy/jobs", (_req: Request, res: Response) => {
  const jobs = queue.getAllJobs();
  res.json({ success: true, jobs });
});

export default router;
