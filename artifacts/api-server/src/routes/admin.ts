import { Router, type IRouter, type Request, type Response, type NextFunction } from "express";
import { readSettings, writeSettings, getApiSecretKey } from "../services/settings.js";
import { registerApp, listApps, getAppKey, deregisterApp } from "../services/appRegistry.js";
import { logger } from "../lib/logger.js";

const router: IRouter = Router();

// Admin endpoints require the API secret key — same as external routes
function requireApiKey(req: Request, res: Response, next: NextFunction): void {
  const secret = getApiSecretKey();
  if (!secret) {
    // Allow unauthenticated access only when no key is configured at all (first-time setup)
    next();
    return;
  }
  const fromHeader =
    (req.headers["x-api-key"] as string | undefined) ??
    (req.headers["authorization"]?.startsWith("Bearer ")
      ? req.headers["authorization"].slice(7)
      : undefined);
  const fromQuery = req.query["key"] as string | undefined;
  const provided = fromHeader ?? fromQuery ?? "";
  if (provided !== secret) {
    res.status(401).json({ success: false, error: "Unauthorized." });
    return;
  }
  next();
}

// ── GET /api/admin/settings ───────────────────────────────────────────────────
// Returns current effective settings (masks the API key for security)
router.get("/admin/settings", requireApiKey, (_req: Request, res: Response) => {
  const s = readSettings();
  res.json({
    success: true,
    settings: {
      herokuApiKey: s.herokuApiKey ? "••••••••" + s.herokuApiKey.slice(-4) : null,
      herokuTeam: s.herokuTeam ?? null,
      apiSecretKey: s.apiSecretKey ? "••••" + s.apiSecretKey.slice(-4) : null,
      hasHerokuApiKey: !!s.herokuApiKey || !!process.env["HEROKU_API_KEY"],
      hasHerokuTeam: !!(s.herokuTeam ?? process.env["HEROKU_TEAM"]),
      hasApiSecretKey: !!(s.apiSecretKey ?? process.env["API_SECRET_KEY"]),
      keySource: s.herokuApiKey ? "admin_panel" : (process.env["HEROKU_API_KEY"] ? "environment" : "not_set"),
    },
  });
});

// ── PATCH /api/admin/settings ─────────────────────────────────────────────────
// Update one or more settings. Send only the fields you want to change.
// To clear a value send an empty string.
router.patch("/admin/settings", requireApiKey, (req: Request, res: Response) => {
  const body = req.body as Record<string, unknown>;
  const patch: Record<string, string> = {};

  if ("herokuApiKey" in body) {
    const v = String(body["herokuApiKey"] ?? "").trim();
    patch["herokuApiKey"] = v;
  }
  if ("herokuTeam" in body) {
    const v = String(body["herokuTeam"] ?? "").trim();
    patch["herokuTeam"] = v;
  }
  if ("apiSecretKey" in body) {
    const v = String(body["apiSecretKey"] ?? "").trim();
    patch["apiSecretKey"] = v;
  }

  if (Object.keys(patch).length === 0) {
    res.status(400).json({ success: false, error: "No valid fields provided." });
    return;
  }

  try {
    writeSettings(patch);
    logger.info({ fields: Object.keys(patch) }, "Admin settings updated");
    res.json({ success: true, message: "Settings saved successfully." });
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: msg });
  }
});

// ── POST /api/admin/test-connection ──────────────────────────────────────────
// Test that the configured platform API key is valid
router.post("/admin/test-connection", requireApiKey, async (_req: Request, res: Response) => {
  const { getHerokuApiKey } = await import("../services/settings.js");
  const key = getHerokuApiKey();
  if (!key) {
    res.json({ success: false, error: "API key not configured." });
    return;
  }
  try {
    const axios = (await import("axios")).default;
    const r = await axios.get("https://api.heroku.com/account", {
      headers: {
        Authorization: `Bearer ${key}`,
        Accept: "application/vnd.heroku+json; version=3",
      },
      timeout: 8000,
    });
    const account = r.data as { email: string; name: string };
    res.json({ success: true, email: account.email, name: account.name });
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    res.status(400).json({ success: false, error: msg });
  }
});


// ── GET /api/admin/registry ───────────────────────────────────────────────────
router.get("/admin/registry", requireApiKey, (_req: Request, res: Response) => {
  const apps = listApps();
  res.json({ success: true, count: apps.length, apps });
});

// ── POST /api/admin/registry/import ──────────────────────────────────────────
router.post("/admin/registry/import", requireApiKey, async (req: Request, res: Response) => {
  const body = req.body as { herokuApiKey?: string; teamName?: string };
  const apiKey = (body.herokuApiKey ?? "").trim();
  const team   = (body.teamName ?? "").trim();

  if (!apiKey) {
    res.status(400).json({ success: false, error: "herokuApiKey is required" });
    return;
  }

  try {
    const axiosMod = await import("axios");
    const ax = axiosMod.default;
    const headers = {
      Authorization: "Bearer " + apiKey,
      Accept: "application/vnd.heroku+json; version=3",
      "Content-Type": "application/json",
    };

    let apps: Array<{ name: string }> = [];
    if (team) {
      const r = await ax.get("https://api.heroku.com/teams/" + team + "/apps", { headers });
      apps = r.data as Array<{ name: string }>;
    } else {
      const r = await ax.get("https://api.heroku.com/apps", { headers });
      apps = r.data as Array<{ name: string }>;
    }

    let added = 0;
    for (const app of apps) {
      if (app.name && !getAppKey(app.name)) {
        registerApp(app.name, apiKey);
        added++;
      }
    }

    res.json({
      success: true,
      total: apps.length,
      added,
      skipped: apps.length - added,
      message: "Imported " + added + " apps (" + (apps.length - added) + " already registered).",
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    res.status(500).json({ success: false, error: "Heroku API error: " + message });
  }
});

// ── DELETE /api/admin/registry/:appName ───────────────────────────────────────
router.delete("/admin/registry/:appName", requireApiKey, (req: Request, res: Response) => {
  const { appName } = req.params;
  if (!appName) { res.status(400).json({ success: false, error: "appName required" }); return; }
  deregisterApp(appName);
  res.json({ success: true, message: appName + " removed from registry." });
});

export default router;
