import fs from "fs";
import path from "path";

const SETTINGS_PATH = path.join(process.cwd(), "data", "bot-deploy-settings.json");

export interface AppSettings {
  herokuApiKey?: string;
  herokuTeam?: string;
  apiSecretKey?: string;
}

function ensureDir(): void {
  const dir = path.dirname(SETTINGS_PATH);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

export function readSettings(): AppSettings {
  try {
    if (!fs.existsSync(SETTINGS_PATH)) return {};
    return JSON.parse(fs.readFileSync(SETTINGS_PATH, "utf8")) as AppSettings;
  } catch {
    return {};
  }
}

export function writeSettings(patch: Partial<AppSettings>): AppSettings {
  ensureDir();
  const updated = { ...readSettings(), ...patch };
  fs.writeFileSync(SETTINGS_PATH, JSON.stringify(updated, null, 2), "utf8");
  return updated;
}

export function getHerokuApiKey(): string | undefined {
  return readSettings().herokuApiKey || process.env["HEROKU_API_KEY"] || undefined;
}

export function getHerokuTeam(): string | undefined {
  return readSettings().herokuTeam || process.env["HEROKU_TEAM"] || undefined;
}

export function getApiSecretKey(): string | undefined {
  return readSettings().apiSecretKey || process.env["API_SECRET_KEY"] || undefined;
}
