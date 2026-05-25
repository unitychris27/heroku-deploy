import { Router, type IRouter, type Request, type Response } from "express";
import * as heroku from "../services/heroku.js";
import * as queue from "../services/queue.js";

const router: IRouter = Router();

router.get("/apps/:appName/config", async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    const configVars = await heroku.getConfigVars(appName!);
    res.json({ success: true, appName, configVars });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

router.patch("/apps/:appName/config", async (req: Request, res: Response) => {
  const { appName } = req.params;
  const { configVars } = req.body as { configVars?: unknown };

  if (!configVars || typeof configVars !== "object" || Array.isArray(configVars)) {
    res.status(400).json({ success: false, error: "configVars must be a key-value object" });
    return;
  }

  const vars = configVars as Record<string, unknown>;
  for (const [k, v] of Object.entries(vars)) {
    if (typeof v !== "string") {
      res.status(400).json({ success: false, error: `configVars.${k} must be a string` });
      return;
    }
  }

  try {
    await heroku.setConfigVars(appName!, vars as Record<string, string>);
    res.json({ success: true, message: "Config vars updated. Heroku will restart the dyno automatically." });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

router.post("/apps/:appName/restart", async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    await heroku.restartDynos(appName!);
    res.json({ success: true, message: `All dynos for ${appName} restarted successfully.` });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

router.delete("/apps/:appName", async (req: Request, res: Response) => {
  const { appName } = req.params;
  try {
    await heroku.deleteApp(appName!);
    queue.removeJobByAppName(appName!);
    res.json({ success: true, message: `App ${appName} has been permanently deleted.` });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: message });
  }
});

export default router;
