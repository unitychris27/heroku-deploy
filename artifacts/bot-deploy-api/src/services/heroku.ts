import axios, { type AxiosInstance, isAxiosError } from "axios";
import { logger } from "../lib/logger.js";
import { getHerokuApiKey, getHerokuTeam } from "./settings.js";

const HEROKU_API_BASE = "https://api.heroku.com";
const BUILD_POLL_INTERVAL_MS = 4000;
const BUILD_POLL_MAX_ATTEMPTS = 60;
const MAX_BUILD_RETRIES = 2;
const RETRY_DELAY_MS = 5000;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function createHerokuClient(overrideKey?: string): AxiosInstance {
  const apiKey = overrideKey ?? getHerokuApiKey();
  if (!apiKey) throw new Error("Heroku API key not configured. Set HEROKU_API_KEY env var or use /api/admin/settings.");
  return axios.create({
    baseURL: HEROKU_API_BASE,
    headers: {
      Authorization: `Bearer ${apiKey}`,
      Accept: "application/vnd.heroku+json; version=3",
      "Content-Type": "application/json",
    },
  });
}

function extractHerokuError(err: unknown): Error {
  if (isAxiosError(err)) {
    const data = err.response?.data as { message?: string } | undefined;
    const msg = data?.message;
    return new Error(msg ? `Heroku (${err.response?.status}): ${msg}` : `Heroku API ${err.response?.status ?? "unknown"}`);
  }
  return err instanceof Error ? err : new Error(String(err));
}

export async function createApp(appName: string, apiKey?: string): Promise<{ id: string; name: string; web_url: string }> {
  const client = createHerokuClient(apiKey);
  const team = getHerokuTeam();
  try {
    if (team) {
      logger.info({ appName, team }, "Creating Heroku team app");
      const r = await client.post("/teams/apps", { name: appName, team });
      return r.data as { id: string; name: string; web_url: string };
    }
    logger.info({ appName }, "Creating Heroku personal app");
    const r = await client.post("/apps", { name: appName });
    return r.data as { id: string; name: string; web_url: string };
  } catch (err) { throw extractHerokuError(err); }
}

export async function setStack(appName: string, stack: string, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  try { await client.patch(`/apps/${appName}`, { build_stack: stack }); }
  catch (err) { throw extractHerokuError(err); }
}

export async function setBuildpacks(appName: string, buildpacks: string[], apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  try { await client.put(`/apps/${appName}/buildpack-installations`, { updates: buildpacks.map((url) => ({ buildpack: url })) }); }
  catch (err) { throw extractHerokuError(err); }
}

export async function setConfigVars(appName: string, vars: Record<string, string>, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  try { await client.patch(`/apps/${appName}/config-vars`, vars); }
  catch (err) { throw extractHerokuError(err); }
}

export async function getConfigVars(appName: string, apiKey?: string): Promise<Record<string, string>> {
  const client = createHerokuClient(apiKey);
  try { const r = await client.get(`/apps/${appName}/config-vars`); return r.data as Record<string, string>; }
  catch (err) { throw extractHerokuError(err); }
}

export async function deploySource(appName: string, tarballUrl: string, apiKey?: string): Promise<{ id: string; status: string; output_stream_url: string }> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName, tarballUrl }, "Deploying source");
  try {
    const r = await client.post(`/apps/${appName}/builds`, { source_blob: { url: tarballUrl } });
    return r.data as { id: string; status: string; output_stream_url: string };
  } catch (err) { throw extractHerokuError(err); }
}

export async function waitForBuild(appName: string, buildId: string, originalTarballUrl: string, apiKey?: string): Promise<{ status: string }> {
  const client = createHerokuClient(apiKey);
  const pollOnce = async (id: string): Promise<{ status: string; output_stream_url: string }> => {
    for (let i = 0; i < BUILD_POLL_MAX_ATTEMPTS; i++) {
      try {
        const r = await client.get(`/apps/${appName}/builds/${id}`);
        const build = r.data as { status: string; output_stream_url: string };
        if (build.status === "succeeded" || build.status === "failed") return build;
      } catch (err) { throw extractHerokuError(err); }
      await sleep(BUILD_POLL_INTERVAL_MS);
    }
    throw new Error("Build timed out");
  };
  let currentId = buildId;
  for (let retry = 0; retry <= MAX_BUILD_RETRIES; retry++) {
    const build = await pollOnce(currentId);
    if (build.status === "succeeded") return build;
    if (retry < MAX_BUILD_RETRIES) {
      await sleep(RETRY_DELAY_MS);
      const r = await client.post(`/apps/${appName}/builds`, { source_blob: { url: originalTarballUrl } });
      currentId = (r.data as { id: string }).id;
    }
  }
  throw new Error(`Build failed after ${MAX_BUILD_RETRIES} retries`);
}

export async function scaleDyno(appName: string, dynos = 1, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  try { await client.patch(`/apps/${appName}/formation`, { updates: [{ type: "web", quantity: dynos }] }); }
  catch (err) { throw extractHerokuError(err); }
}

export async function restartDynos(appName: string, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  try { await client.delete(`/apps/${appName}/dynos`); }
  catch (err) { throw extractHerokuError(err); }
}

export async function deleteApp(appName: string, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  try { await client.delete(`/apps/${appName}`); }
  catch (err) { throw extractHerokuError(err); }
}

export async function getLogsUrl(appName: string, apiKey?: string): Promise<string> {
  const client = createHerokuClient(apiKey);
  try {
    const r = await client.post(`/apps/${appName}/log-sessions`, { lines: 100, tail: false });
    return (r.data as { logplex_url: string }).logplex_url;
  } catch (err) { throw extractHerokuError(err); }
}

export async function fetchLogs(appName: string, lines = 200, apiKey?: string): Promise<string> {
  const client = createHerokuClient(apiKey);
  try {
    const sr = await client.post(`/apps/${appName}/log-sessions`, { lines, tail: false });
    const url = (sr.data as { logplex_url: string }).logplex_url;
    const lr = await axios.get<string>(url, { responseType: "text", headers: { Accept: "text/plain" } });
    return lr.data;
  } catch (err) { throw extractHerokuError(err); }
}

export async function checkApp(appName: string, apiKey?: string): Promise<{ exists: boolean; webUrl?: string; latestBuildStatus?: string }> {
  const client = createHerokuClient(apiKey);
  try {
    const ar = await client.get(`/apps/${appName}`);
    const app = ar.data as { web_url: string };
    try {
      const br = await client.get(`/apps/${appName}/builds`);
      const builds = br.data as Array<{ status: string }>;
      return { exists: true, webUrl: app.web_url, latestBuildStatus: builds[builds.length - 1]?.status };
    } catch { return { exists: true, webUrl: app.web_url }; }
  } catch { return { exists: false }; }
}
