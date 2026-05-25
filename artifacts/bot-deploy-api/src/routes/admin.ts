import { Router, type IRouter, type Request, type Response, type NextFunction } from "express";
import { readSettings, writeSettings, getApiSecretKey } from "../services/settings.js";
import { registerApp, listApps, getAppKey, deregisterApp } from "../services/appRegistry.js";
import { logger } from "../lib/logger.js";

const router: IRouter = Router();

function requireApiKey(req: Request, res: Response, next: NextFunction): void {
  const secret = getApiSecretKey();
  if (!secret) { next(); return; }
  const fromHeader = (req.headers["x-api-key"] as string | undefined) ??
    (req.headers["authorization"]?.startsWith("Bearer ") ? req.headers["authorization"].slice(7) : undefined);
  const provided = fromHeader ?? (req.query["key"] as string | undefined) ?? "";
  if (provided !== secret) { res.status(401).json({ success: false, error: "Unauthorized." }); return; }
  next();
}

router.get("/admin/settings", requireApiKey, (_req, res) => {
  const s = readSettings();
  res.json({
    success: true,
    settings: {
      herokuApiKey: s.herokuApiKey ? "••••" + s.herokuApiKey.slice(-4) : null,
      herokuTeam: s.herokuTeam ?? null,
      apiSecretKey: s.apiSecretKey ? "••••" + s.apiSecretKey.slice(-4) : null,
      hasHerokuApiKey: !!(s.herokuApiKey || process.env["HEROKU_API_KEY"]),
      hasApiSecretKey: !!(s.apiSecretKey || process.env["API_SECRET_KEY"]),
    },
  });
});

router.patch("/admin/settings", requireApiKey, (req: Request, res: Response) => {
  const body = req.body as Record<string, unknown>;
  const patch: Record<string, string> = {};
  for (const key of ["herokuApiKey", "herokuTeam", "apiSecretKey"] as const) {
    if (key in body) patch[key] = String(body[key] ?? "").trim();
  }
  if (!Object.keys(patch).length) { res.status(400).json({ success: false, error: "No valid fields provided." }); return; }
  writeSettings(patch);
  logger.info({ fields: Object.keys(patch) }, "Admin settings updated");
  res.json({ success: true, message: "Settings saved." });
});

router.post("/admin/test-connection", requireApiKey, async (_req, res) => {
  const { getHerokuApiKey } = await import("../services/settings.js");
  const key = getHerokuApiKey();
  if (!key) { res.json({ success: false, error: "API key not configured." }); return; }
  try {
    const { default: axios } = await import("axios");
    const r = await axios.get("https://api.heroku.com/account", {
      headers: { Authorization: `Bearer ${key}`, Accept: "application/vnd.heroku+json; version=3" },
      timeout: 8000,
    });
    const account = r.data as { email: string; name: string };
    res.json({ success: true, email: account.email, name: account.name });
  } catch (err) {
    res.status(400).json({ success: false, error: err instanceof Error ? err.message : String(err) });
  }
});

router.get("/admin/registry", requireApiKey, (_req, res) => {
  res.json({ success: true, apps: listApps() });
});

router.post("/admin/registry/import", requireApiKey, async (req: Request, res: Response) => {
  const { herokuApiKey = "", teamName = "" } = req.body as { herokuApiKey?: string; teamName?: string };
  if (!herokuApiKey.trim()) { res.status(400).json({ success: false, error: "herokuApiKey is required" }); return; }
  try {
    const { default: axios } = await import("axios");
    const headers = { Authorization: "Bearer " + herokuApiKey, Accept: "application/vnd.heroku+json; version=3" };
    const url = teamName.trim() ? `https://api.heroku.com/teams/${teamName}/apps` : "https://api.heroku.com/apps";
    const r = await axios.get(url, { headers });
    const apps = r.data as Array<{ name: string }>;
    let added = 0;
    for (const app of apps) { if (app.name && !getAppKey(app.name)) { registerApp(app.name, herokuApiKey); added++; } }
    res.json({ success: true, total: apps.length, added, skipped: apps.length - added });
  } catch (err) {
    res.status(500).json({ success: false, error: err instanceof Error ? err.message : String(err) });
  }
});

router.delete("/admin/registry/:appName", requireApiKey, (req: Request, res: Response) => {
  const { appName } = req.params;
  if (!appName) { res.status(400).json({ success: false, error: "appName required" }); return; }
  deregisterApp(appName);
  res.json({ success: true, message: `${appName} removed from registry.` });
});

export default router;
