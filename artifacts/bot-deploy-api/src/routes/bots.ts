import { Router, type IRouter } from "express";
import { BOTS } from "../config/bots.js";

const router: IRouter = Router();

router.get("/bots", (_req, res) => {
  const bots = Object.entries(BOTS).map(([key, bot]) => ({
    key, name: bot.name, fields: bot.fields,
  }));
  res.json({ success: true, bots });
});

export default router;
