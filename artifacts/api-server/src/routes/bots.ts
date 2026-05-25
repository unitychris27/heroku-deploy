import { Router, type IRouter, type Request, type Response } from "express";
import { BOTS } from "../config/bots.js";

const router: IRouter = Router();

router.get("/bots", (_req: Request, res: Response) => {
  const bots = Object.entries(BOTS).map(([id, bot]) => ({
    id,
    name: bot.name,
    fields: bot.fields.map((f) => ({
      key: f.key,
      label: f.label,
      placeholder: f.placeholder,
      required: f.required,
      type: f.type ?? "text",
      ...(f.options ? { options: f.options } : {}),
    })),
  }));
  res.json({ success: true, bots });
});

export default router;
