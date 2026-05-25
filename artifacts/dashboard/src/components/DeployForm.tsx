import React, { useEffect } from "react";
import { useForm, Controller } from "react-hook-form";
import { Rocket } from "lucide-react";
import { useDeployBot, useListBots } from "@workspace/api-client-react";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";

interface DeployFormProps {
  onDeploySuccess: (jobId: string) => void;
}

export default function DeployForm({ onDeploySuccess }: DeployFormProps) {
  const { toast } = useToast();
  const deployMutation = useDeployBot();
  const { data: botsData } = useListBots();

  const bots = botsData?.bots ?? [];

  const { register, handleSubmit, watch, setValue, reset, control, formState: { errors } } = useForm<Record<string, string>>({
    defaultValues: { botType: "", appName: "" },
  });

  const botType = watch("botType");
  const selectedBot = bots.find((b) => b.id === botType);

  useEffect(() => {
    if (bots.length > 0 && !botType) {
      setValue("botType", bots[0]!.id);
    }
  }, [bots, botType, setValue]);

  useEffect(() => {
    if (selectedBot) {
      selectedBot.fields.forEach((f) => {
        if (f.type === "select" && f.options && f.options.length > 0) {
          setValue(f.key, f.options[0]!.value);
        } else {
          setValue(f.key, "");
        }
      });
    }
  }, [selectedBot?.id]);

  const onSubmit = (data: Record<string, string>) => {
    const { botType: bt, appName, ...rest } = data;

    if (!bt) {
      toast({ title: "Error", description: "Please select a bot type", variant: "destructive" });
      return;
    }

    const bot = bots.find((b) => b.id === bt);
    if (!bot) return;

    const missing = bot.fields.filter((f) => f.required && !rest[f.key]);
    if (missing.length > 0) {
      toast({ title: "Missing fields", description: missing.map((f) => f.label).join(", "), variant: "destructive" });
      return;
    }

    const botVars: Record<string, string> = {};
    bot.fields.forEach((f) => {
      if (rest[f.key]) botVars[f.key] = rest[f.key]!;
    });

    deployMutation.mutate(
      { data: { botType: bt as "cypherx" | "bwm" | "cypherxultra" | "kingmd" | "anitav4" | "atassa", appName, botVars } },
      {
        onSuccess: (res) => {
          if (res.success) {
            toast({
              title: "Deployment Queued",
              description: `Job ID: ${res.jobId}`,
              className: "bg-card border-primary/20 text-primary-foreground",
            });
            onDeploySuccess(res.jobId);
            reset({ botType: bt, appName: "" });
            bot.fields.forEach((f) => setValue(f.key, ""));
          } else {
            toast({ title: "Deployment Failed", description: res.message, variant: "destructive" });
          }
        },
        onError: (err) => {
          toast({ title: "Error", description: err.error || "An unknown error occurred", variant: "destructive" });
        },
      }
    );
  };

  return (
    <Card className="border-border/50 bg-card shadow-lg">
      <CardHeader className="pb-4 border-b border-border/20">
        <CardTitle className="flex items-center gap-2 text-lg text-white">
          <Rocket className="w-5 h-5 text-primary" />
          Deploy New Bot
        </CardTitle>
        <CardDescription>Configure and deploy a WhatsApp bot instance to Heroku.</CardDescription>
      </CardHeader>
      <CardContent className="pt-6">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">

          {/* Bot Type Selector */}
          <div className="space-y-2">
            <Label className="text-muted-foreground font-mono text-xs uppercase tracking-wider">Bot Type</Label>
            <Controller
              control={control}
              name="botType"
              render={({ field }) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger data-testid="select-bottype" className="font-mono">
                    <SelectValue placeholder={bots.length === 0 ? "Loading..." : "Select bot type"} />
                  </SelectTrigger>
                  <SelectContent>
                    {bots.map((bot) => (
                      <SelectItem key={bot.id} value={bot.id}>
                        {bot.name.toUpperCase()}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
          </div>

          {/* App Name */}
          <div className="space-y-2">
            <Label className="text-muted-foreground font-mono text-xs uppercase tracking-wider">App Name</Label>
            <Input
              placeholder="my-bot-instance"
              data-testid="input-appname"
              className="font-mono bg-background/50 focus-visible:ring-primary/50"
              {...register("appName", {
                required: "App name is required",
                minLength: { value: 3, message: "At least 3 characters" },
                maxLength: { value: 30, message: "Max 30 characters" },
                pattern: { value: /^[a-z0-9][a-z0-9-]*[a-z0-9]$/, message: "Lowercase, numbers, hyphens only — no leading/trailing dash" },
              })}
            />
            {errors["appName"] && (
              <p className="text-xs text-destructive font-mono">{errors["appName"]?.message}</p>
            )}
          </div>

          {/* Dynamic Bot Fields */}
          {selectedBot?.fields.map((field) => (
            <div key={field.key} className="space-y-2">
              <Label className="text-muted-foreground font-mono text-xs uppercase tracking-wider">
                {field.label}{!field.required ? " (optional)" : ""}
              </Label>

              {field.type === "select" && field.options ? (
                <Controller
                  control={control}
                  name={field.key}
                  rules={field.required ? { required: `${field.label} is required` } : {}}
                  render={({ field: ctrl }) => (
                    <Select value={ctrl.value} onValueChange={ctrl.onChange}>
                      <SelectTrigger data-testid={`select-${field.key}`} className="font-mono">
                        <SelectValue placeholder={`Select ${field.label.toLowerCase()}`} />
                      </SelectTrigger>
                      <SelectContent>
                        {field.options!.map((opt) => (
                          <SelectItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                />
              ) : (
                <Input
                  type={field.type === "password" ? "password" : "text"}
                  placeholder={field.placeholder}
                  data-testid={`input-${field.key}`}
                  className="font-mono bg-background/50 focus-visible:ring-primary/50"
                  {...register(field.key, field.required ? { required: `${field.label} is required` } : {})}
                />
              )}

              {errors[field.key] && (
                <p className="text-xs text-destructive font-mono">{errors[field.key]?.message}</p>
              )}
            </div>
          ))}

          <Button
            type="submit"
            disabled={deployMutation.isPending || bots.length === 0}
            data-testid="button-submit-deploy"
            className="w-full mt-2 font-mono font-bold tracking-wide transition-all shadow-[0_0_10px_rgba(0,255,136,0.1)] hover:shadow-[0_0_20px_rgba(0,255,136,0.3)]"
          >
            {deployMutation.isPending ? "QUEUING..." : "INITIATE DEPLOY"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
