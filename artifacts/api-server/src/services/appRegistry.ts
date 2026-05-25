import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const REGISTRY_PATH = path.resolve(__dirname, "../../data/app-registry.json");

type Registry = Record<string, string>; // appName → herokuApiKey

function read(): Registry {
  try {
    if (!fs.existsSync(REGISTRY_PATH)) return {};
    return JSON.parse(fs.readFileSync(REGISTRY_PATH, "utf8")) as Registry;
  } catch {
    return {};
  }
}

function write(reg: Registry): void {
  const dir = path.dirname(REGISTRY_PATH);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(REGISTRY_PATH, JSON.stringify(reg, null, 2), "utf8");
}

/** Save the Heroku API key used to deploy an app so it can be used later. */
export function registerApp(appName: string, herokuApiKey: string): void {
  const reg = read();
  reg[appName] = herokuApiKey;
  write(reg);
}

/** Get the Heroku API key that was used to deploy a specific app. Returns undefined if not found. */
export function getAppKey(appName: string): string | undefined {
  return read()[appName];
}

/** Remove an app from the registry (call when deleting). */
export function deregisterApp(appName: string): void {
  const reg = read();
  delete reg[appName];
  write(reg);
}

/** List all registered app names. */
export function listApps(): string[] {
  return Object.keys(read());
}
