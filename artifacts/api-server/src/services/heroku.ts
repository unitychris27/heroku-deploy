import axios, { type AxiosInstance, isAxiosError } from "axios";
import { logger } from "../lib/logger.js";
import { getHerokuApiKey, getHerokuTeam } from "./settings.js";

const HEROKU_API_BASE = "https://api.heroku.com";
const MAX_BUILD_RETRIES = 2;
const RETRY_DELAY_MS = 5000;
const BUILD_POLL_INTERVAL_MS = 4000;
const BUILD_POLL_MAX_ATTEMPTS = 60;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function createHerokuClient(overrideKey?: string): AxiosInstance {
  const apiKey = overrideKey ?? getHerokuApiKey();
  if (!apiKey) {
    throw new Error("Platform API key is not configured. Set it in Admin Settings.");
  }
  return axios.create({
    baseURL: HEROKU_API_BASE,
    headers: {
      Authorization: `Bearer ${apiKey}`,
      Accept: "application/vnd.heroku+json; version=3",
      "Content-Type": "application/json",
    },
  });
}

/** Create a Heroku client using a specific API key (e.g. from the app registry). */
export function createClientForApp(apiKey: string): AxiosInstance {
  return createHerokuClient(apiKey);
}

function extractHerokuError(err: unknown): Error {
  if (isAxiosError(err)) {
    const data = err.response?.data as { message?: string; id?: string } | undefined;
    const herokuMessage = data?.message;
    const status = err.response?.status;
    if (herokuMessage) {
      return new Error(`Heroku error (${status}): ${herokuMessage}`);
    }
    return new Error(`Heroku API request failed with status ${status ?? "unknown"}`);
  }
  return err instanceof Error ? err : new Error(String(err));
}

export async function createApp(appName: string, apiKey?: string): Promise<{ id: string; name: string; web_url: string }> {
  const client = createHerokuClient(apiKey);
  const team = getHerokuTeam();

  if (team) {
    logger.info({ appName, team }, "Creating Heroku team app");
    try {
      const response = await client.post("/teams/apps", { name: appName, team });
      return response.data as { id: string; name: string; web_url: string };
    } catch (err) {
      throw extractHerokuError(err);
    }
  }

  logger.info({ appName }, "Creating Heroku personal app");
  try {
    const response = await client.post("/apps", { name: appName });
    return response.data as { id: string; name: string; web_url: string };
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function setStack(appName: string, stack: string, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName, stack }, "Setting app stack");
  try {
    await client.patch(`/apps/${appName}`, { build_stack: stack });
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function setBuildpacks(appName: string, buildpacks: string[], apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName, buildpacks }, "Setting buildpacks");
  try {
    await client.put(`/apps/${appName}/buildpack-installations`, {
      updates: buildpacks.map((url) => ({ buildpack: url })),
    });
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function setConfigVars(
  appName: string,
  vars: Record<string, string>,
  apiKey?: string,
): Promise<void> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName, keys: Object.keys(vars) }, "Setting config vars");
  try {
    await client.patch(`/apps/${appName}/config-vars`, vars);
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function deploySource(
  appName: string,
  tarballUrl: string,
  apiKey?: string,
): Promise<{ id: string; status: string; output_stream_url: string }> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName, tarballUrl }, "Deploying source");
  try {
    const response = await client.post(`/apps/${appName}/builds`, {
      source_blob: { url: tarballUrl },
    });
    return response.data as { id: string; status: string; output_stream_url: string };
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function waitForBuild(
  appName: string,
  buildId: string,
  originalTarballUrl: string,
  apiKey?: string,
): Promise<{ status: string; output_stream_url: string }> {
  const client = createHerokuClient(apiKey);

  const pollOnce = async (): Promise<{ status: string; output_stream_url: string }> => {
    for (let attempt = 0; attempt < BUILD_POLL_MAX_ATTEMPTS; attempt++) {
      logger.info({ appName, buildId, attempt }, "Polling build status");
      try {
        const response = await client.get(`/apps/${appName}/builds/${buildId}`);
        const build = response.data as { status: string; output_stream_url: string };
        if (build.status === "succeeded" || build.status === "failed") {
          return build;
        }
      } catch (err) {
        throw extractHerokuError(err);
      }
      await sleep(BUILD_POLL_INTERVAL_MS);
    }
    throw new Error("Build timed out after polling for too long");
  };

  let lastBuildId = buildId;
  for (let retry = 0; retry <= MAX_BUILD_RETRIES; retry++) {
    const build = await pollOnce();
    if (build.status === "succeeded") {
      return build;
    }
    if (retry < MAX_BUILD_RETRIES) {
      logger.warn({ appName, buildId: lastBuildId, retry }, "Build failed, retrying with original tarball");
      await sleep(RETRY_DELAY_MS);
      try {
        const retryResponse = await client.post(`/apps/${appName}/builds`, {
          source_blob: { url: originalTarballUrl },
        });
        const retried = retryResponse.data as { id: string };
        lastBuildId = retried.id;
        buildId = lastBuildId;
      } catch (err) {
        throw extractHerokuError(err);
      }
    }
  }

  throw new Error(`Build failed after ${MAX_BUILD_RETRIES} retries`);
}

export async function scaleDyno(appName: string, dynos = 1, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName }, "Scaling dyno");
  try {
    await client.patch(`/apps/${appName}/formation`, {
      updates: [{ type: "web", quantity: 1 }],
    });
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function getConfigVars(appName: string, apiKey?: string): Promise<Record<string, string>> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName }, "Getting config vars");
  try {
    const response = await client.get(`/apps/${appName}/config-vars`);
    return response.data as Record<string, string>;
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function restartDynos(appName: string, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName }, "Restarting all dynos");
  try {
    await client.delete(`/apps/${appName}/dynos`);
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function deleteApp(appName: string, apiKey?: string): Promise<void> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName }, "Deleting app");
  try {
    await client.delete(`/apps/${appName}`);
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function checkApp(appName: string, apiKey?: string): Promise<{
  exists: boolean;
  webUrl?: string;
  latestBuildStatus?: "succeeded" | "failed" | "pending";
}> {
  const client = createHerokuClient(apiKey);
  try {
    const appRes = await client.get(`/apps/${appName}`);
    const app = appRes.data as { web_url: string };
    try {
      const buildsRes = await client.get(`/apps/${appName}/builds`);
      const builds = buildsRes.data as Array<{ status: string }>;
      const latest = builds[builds.length - 1];
      return {
        exists: true,
        webUrl: app.web_url,
        latestBuildStatus: latest?.status as "succeeded" | "failed" | "pending" | undefined,
      };
    } catch {
      return { exists: true, webUrl: app.web_url };
    }
  } catch {
    return { exists: false };
  }
}

export async function getLogsUrl(appName: string, apiKey?: string): Promise<string> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName }, "Creating log session");
  try {
    const response = await client.post(`/apps/${appName}/log-sessions`, {
      lines: 100,
      tail: false,
    });
    const session = response.data as { logplex_url: string };
    return session.logplex_url;
  } catch (err) {
    throw extractHerokuError(err);
  }
}

export async function fetchLogs(appName: string, lines = 200, apiKey?: string): Promise<string> {
  const client = createHerokuClient(apiKey);
  logger.info({ appName, lines }, "Fetching log text");
  try {
    const sessionRes = await client.post(`/apps/${appName}/log-sessions`, {
      lines,
      tail: false,
    });
    const { logplex_url } = sessionRes.data as { logplex_url: string };
    const logRes = await axios.get<string>(logplex_url, {
      responseType: "text",
      headers: { Accept: "text/plain" },
    });
    return logRes.data;
  } catch (err) {
    throw extractHerokuError(err);
  }
}
