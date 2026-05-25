import * as heroku from "./heroku.js";
import { logger } from "../lib/logger.js";

export type DeploymentStatus =
  | "queued" | "creating_app" | "setting_buildpack" | "setting_config"
  | "deploying" | "scaling" | "completed" | "failed";

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

export function createJob(params: { appName: string; botType: string; sessionId: string }): DeploymentJob {
  const now = new Date();
  const job: DeploymentJob = {
    id: `${params.appName}-${Date.now()}`,
    ...params,
    status: "queued",
    createdAt: now,
    updatedAt: now,
    scheduledDeletionAt: new Date(now.getTime() + THIRTY_DAYS_MS),
  };
  jobs.set(job.id, job);
  return job;
}

export function updateJob(id: string, updates: Partial<DeploymentJob>): void {
  const job = jobs.get(id);
  if (job) Object.assign(job, updates, { updatedAt: new Date() });
}

export function getJob(id: string): DeploymentJob | undefined { return jobs.get(id); }

export function getJobByAppName(appName: string): DeploymentJob | undefined {
  for (const job of jobs.values()) { if (job.appName === appName) return job; }
}

export function removeJobByAppName(appName: string): boolean {
  for (const [id, job] of jobs.entries()) {
    if (job.appName === appName) { jobs.delete(id); return true; }
  }
  return false;
}

export function getAllJobs(): DeploymentJob[] {
  return Array.from(jobs.values()).sort((a, b) => b.createdAt.getTime() - a.createdAt.getTime());
}

setInterval(() => {
  const now = new Date();
  for (const job of jobs.values()) {
    if (job.status === "completed" && job.scheduledDeletionAt <= now) {
      heroku.deleteApp(job.appName)
        .then(() => { jobs.delete(job.id); logger.info({ appName: job.appName }, "Auto-deleted expired app"); })
        .catch((err) => logger.error({ appName: job.appName, err }, "Auto-delete failed"));
    }
  }
}, 60 * 60 * 1000);
