import { Router, type IRouter, type Request, type Response } from "express";
import * as heroku from "../services/heroku.js";

const router: IRouter = Router();

router.get("/logs/:appName", async (req: Request, res: Response) => {
  const { appName } = req.params;
  if (!appName) {
    res.status(400).json({ success: false, error: "appName is required" });
    return;
  }

  try {
    const logsUrl = await heroku.getLogsUrl(appName);
    res.json({ success: true, appName, logsUrl });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    req.log.error({ err, appName }, "Failed to get logs");
    res.status(500).json({ success: false, error: message });
  }
});

export default router;
