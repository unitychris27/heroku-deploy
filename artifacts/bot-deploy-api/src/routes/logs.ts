import { Router, type IRouter, type Request, type Response } from "express";
import * as heroku from "../services/heroku.js";
import { getHerokuApiKey } from "../services/settings.js";
import { getAppKey } from "../services/appRegistry.js";

const router: IRouter = Router();

router.get("/logs/:appName", async (req: Request, res: Response) => {
  const { appName } = req.params;
  const lines = Math.min(parseInt(String(req.query["lines"] ?? "200"), 10) || 200, 1500);
  try {
    const logText = await heroku.fetchLogs(appName!, lines, getAppKey(appName!) ?? getHerokuApiKey());
    res.json({ success: true, appName, logText });
  } catch (err) {
    res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) });
  }
});

export default router;
