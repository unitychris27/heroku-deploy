import { Router, type IRouter, type Request, type Response } from "express";
import * as heroku from "../services/heroku.js";
import * as queue from "../services/queue.js";
import { getHerokuApiKey } from "../services/settings.js";
import { getAppKey } from "../services/appRegistry.js";

const router: IRouter = Router();

router.get("/apps/:appName/config", async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    const vars = await heroku.getConfigVars(appName!, getAppKey(appName!) ?? getHerokuApiKey());
    res.json({ success: true, appName, configVars: vars });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

router.patch("/apps/:appName/config", async (req: Request, res: Response) => {
  const { appName } = req.params;
  const { configVars } = req.body as { configVars?: unknown };
  if (!configVars || typeof configVars !== "object" || Array.isArray(configVars)) {
    res.status(400).json({ success: false, error: "configVars must be a key-value object" }); return;
  }
  try {
    await heroku.setConfigVars(appName!, configVars as Record<string, string>, getAppKey(appName!) ?? getHerokuApiKey());
    res.json({ success: true, message: "Config vars updated." });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

router.post("/apps/:appName/restart", async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    await heroku.restartDynos(appName!, getAppKey(appName!) ?? getHerokuApiKey());
    res.json({ success: true, message: `Dynos for ${appName} restarted.` });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

router.delete("/apps/:appName", async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    await heroku.deleteApp(appName!, getAppKey(appName!) ?? getHerokuApiKey());
    queue.removeJobByAppName(appName!);
    res.json({ success: true, message: `App ${appName} deleted.` });
  } catch (err) { res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) }); }
});

export default router;
