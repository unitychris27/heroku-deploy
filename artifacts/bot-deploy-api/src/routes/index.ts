import { Router, type IRouter } from "express";
import healthRouter from "./health.js";
import deployRouter from "./deploy.js";
import logsRouter from "./logs.js";
import botsRouter from "./bots.js";
import appsRouter from "./apps.js";
import externalRouter from "./external.js";
import adminRouter from "./admin.js";

const router: IRouter = Router();

router.use(healthRouter);
router.use(deployRouter);
router.use(logsRouter);
router.use(botsRouter);
router.use(appsRouter);
router.use(externalRouter);
router.use(adminRouter);

export default router;
