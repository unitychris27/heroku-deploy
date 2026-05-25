import app from "./app.js";
import { logger } from "./lib/logger.js";

const rawPort = process.env["PORT"];
if (!rawPort) throw new Error("PORT environment variable is required");

const port = Number(rawPort);
if (Number.isNaN(port) || port <= 0) throw new Error(`Invalid PORT: "${rawPort}"`);

app.listen(port, () => {
  logger.info({ port, basePath: process.env["BASE_PATH"] ?? "" }, "Bot Deploy API listening");
});
