import * as heroku from "./heroku.js";
import { logger } from "../lib/logger.js";

export type DeploymentStatus =
  | "queued"
  | "creating_app"
  | "setting_buildpack"
  | "setting_config"
  | "deploying"
  | "scaling"
  | "completed"
  | "failed";

const THIRTY_DAYS_MS = 30 * 24 * 60 * 60 * 1000;

export interface DeploymentJob {
  id: string;
  appName: string;
  botType: string;
  sessionId: string;
  status: DeploymentStatus;
  appUrl?: string;
  logsUrl?: string;
  error?: string;
  createdAt: Date;
  updatedAt: Date;
  scheduledDeletionAt: Date;
}

const jobs = new Map<string, DeploymentJob>();

export function createJob(params: {
  appName: string;
  botType: string;
  sessionId: string;
}): DeploymentJob {
  const now = new Date();
  const id = `${params.appName}-${Date.now()}`;
  const job: DeploymentJob = {
    id,
    ...params,
    status: "queued",
    createdAt: now,
    updatedAt: now,
    scheduledDeletionAt: new Date(now.getTime() + THIRTY_DAYS_MS),
  };
  jobs.set(id, job);
  return job;
}

export function updateJob(id: string, updates: Partial<DeploymentJob>): void {
  const job = jobs.get(id);
  if (job) {
    Object.assign(job, updates, { updatedAt: new Date() });
  }
}

export function getJob(id: string): DeploymentJob | undefined {
  return jobs.get(id);
}

export function getJobByAppName(appName: string): DeploymentJob | undefined {
  for (const job of jobs.values()) {
    if (job.appName === appName) return job;
  }
  return undefined;
}

export function removeJobByAppName(appName: string): boolean {
  for (const [id, job] of jobs.entries()) {
    if (job.appName === appName) {
      jobs.delete(id);
      return true;
    }
  }
  return false;
}

export function getAllJobs(): DeploymentJob[] {
  return Array.from(jobs.values()).sort(
    (a, b) => b.createdAt.getTime() - a.createdAt.getTime(),
  );
}

async function runAutoDelete(): Promise<void> {
  const now = new Date();
  for (const job of jobs.values()) {
    if (job.status === "completed" && job.scheduledDeletionAt <= now) {
      logger.info({ appName: job.appName }, "Auto-deleting expired app");
      try {
        await heroku.deleteApp(job.appName);
        jobs.delete(job.id);
        logger.info({ appName: job.appName }, "Auto-deleted expired app successfully");
      } catch (err) {
        logger.error({ appName: job.appName, err }, "Failed to auto-delete expired app");
      }
    }
  }
}

const AUTO_DELETE_INTERVAL_MS = 60 * 60 * 1000;
setInterval(() => {
  runAutoDelete().catch((err) => logger.error({ err }, "Auto-delete cycle failed"));
}, AUTO_DELETE_INTERVAL_MS);
