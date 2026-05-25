import { Router, type IRouter, type Request, type Response } from "express";
import { BOTS, getTarballUrl, type BotConfig } from "../config/bots.js";
import * as heroku from "../services/heroku.js";
import * as queue from "../services/queue.js";
import { getHerokuApiKey } from "../services/settings.js";

const router: IRouter = Router();

function buildConfigVars(bot: BotConfig, botVars: Record<string, string>, botType: string): Record<string, string> {
  const vars: Record<string, string> = { BOT_NAME: botType.toUpperCase(), NODE_ENV: "production" };
  for (const field of bot.fields) {
    const v = botVars[field.key];
    if (v) vars[field.envVar] = v;
  }
  return vars;
}

async function runDeployment(job: queue.DeploymentJob, bot: BotConfig, tarballUrl: string, configVars: Record<string, string>): Promise<void> {
  try {
    const key = getHerokuApiKey();
    queue.updateJob(job.id, { status: "creating_app" });
    await heroku.createApp(job.appName, key);
    queue.updateJob(job.id, { status: "setting_buildpack" });
    if (bot.containerStack) await heroku.setStack(job.appName, "container", key);
    else await heroku.setBuildpacks(job.appName, ["heroku/nodejs"], key);
    queue.updateJob(job.id, { status: "setting_config" });
    await heroku.setConfigVars(job.appName, configVars, key);
    queue.updateJob(job.id, { status: "deploying" });
    const build = await heroku.deploySource(job.appName, tarballUrl, key);
    await heroku.waitForBuild(job.appName, build.id, tarballUrl, key);
    queue.updateJob(job.id, { status: "scaling" });
    await heroku.scaleDyno(job.appName, 1, key);
    const logsUrl = await heroku.getLogsUrl(job.appName, key);
    queue.updateJob(job.id, { status: "completed", appUrl: `https://${job.appName}.herokuapp.com`, logsUrl });
  } catch (err) {
    queue.updateJob(job.id, { status: "failed", error: err instanceof Error ? err.message : String(err) });
  }
}

router.post("/deploy", async (req: Request, res: Response) => {
  const body = req.body as Record<string, unknown>;
  const botType = typeof body["botType"] === "string" ? body["botType"].trim() : "";
  const appName = typeof body["appName"] === "string" ? body["appName"].trim().toLowerCase() : "";
  const botVars = (body["botVars"] && typeof body["botVars"] === "object" && !Array.isArray(body["botVars"]))
    ? body["botVars"] as Record<string, string> : null;

  if (!botType) { res.status(400).json({ success: false, error: "botType is required" }); return; }
  if (!appName || appName.length < 3 || appName.length > 30 || !/^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(appName)) {
    res.status(400).json({ success: false, error: "appName must be 3–30 chars, lowercase, no leading/trailing dash" }); return;
  }
  if (!botVars) { res.status(400).json({ success: false, error: "botVars object is required" }); return; }

  const bot = BOTS[botType];
  if (!bot) { res.status(400).json({ success: false, error: `Unknown botType "${botType}". Supported: ${Object.keys(BOTS).join(", ")}` }); return; }

  for (const field of bot.fields) {
    if (field.required && !botVars[field.key]) {
      res.status(400).json({ success: false, error: `Missing required field: ${field.label} (key: ${field.key})` }); return;
    }
  }

  const existing = queue.getJobByAppName(appName);
  const busy = ["queued", "creating_app", "setting_buildpack", "setting_config", "deploying", "scaling"];
  if (existing && busy.includes(existing.status)) {
    res.status(409).json({ success: false, error: `App "${appName}" already deploying (status: ${existing.status})` }); return;
  }

  const job = queue.createJob({ appName, botType, sessionId: "" });
  void runDeployment(job, bot, getTarballUrl(bot), buildConfigVars(bot, botVars, botType));

  res.status(202).json({
    success: true, jobId: job.id,
    statusUrl: `/api/deploy/status/${job.id}`,
    appUrl: `https://${appName}.herokuapp.com`,
    dashboard: `https://dashboard.heroku.com/apps/${appName}`,
  });
});

router.get("/deploy/status/:jobId", (req: Request, res: Response) => {
  const job = queue.getJob(req.params["jobId"]!);
  if (!job) { res.status(404).json({ success: false, error: "Job not found" }); return; }
  res.json({ success: true, job });
});

router.get("/deploy/jobs", (_req, res) => {
  res.json({ success: true, jobs: queue.getAllJobs() });
});

export default router;
