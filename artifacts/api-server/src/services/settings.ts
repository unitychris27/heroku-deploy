import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SETTINGS_PATH = path.resolve(__dirname, "../../data/settings.json");

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
    const raw = fs.readFileSync(SETTINGS_PATH, "utf8");
    return JSON.parse(raw) as AppSettings;
  } catch {
    return {};
  }
}

export function writeSettings(patch: Partial<AppSettings>): AppSettings {
  ensureDir();
  const current = readSettings();
  const updated = { ...current, ...patch };
  fs.writeFileSync(SETTINGS_PATH, JSON.stringify(updated, null, 2), "utf8");
  return updated;
}

// Returns the effective value: settings file takes priority, then env var, then undefined
export function getSetting(key: keyof AppSettings, envVar: string): string | undefined {
  const s = readSettings();
  return s[key] || process.env[envVar] || undefined;
}

export function getHerokuApiKey(): string | undefined {
  return getSetting("herokuApiKey", "HEROKU_API_KEY");
}

export function getHerokuTeam(): string | undefined {
  return getSetting("herokuTeam", "HEROKU_TEAM");
}

export function getApiSecretKey(): string | undefined {
  return getSetting("apiSecretKey", "API_SECRET_KEY");
}
