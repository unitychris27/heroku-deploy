import React, { useEffect, useState } from "react";
import { Settings, RefreshCw, Save, Loader2, Plus, Trash2 } from "lucide-react";
import { useGetAppConfig, useUpdateAppConfig, useRestartApp } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";

interface ConfigEditorProps {
  appName: string;
  open: boolean;
  onClose: () => void;
}

type VarRow = { key: string; value: string };

export default function ConfigEditor({ appName, open, onClose }: ConfigEditorProps) {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [rows, setRows] = useState<VarRow[]>([]);

  const { data, isLoading } = useGetAppConfig(appName, {
    query: { enabled: open && !!appName },
  });

  const updateMutation = useUpdateAppConfig();
  const restartMutation = useRestartApp();

  useEffect(() => {
    if (data?.configVars) {
      setRows(
        Object.entries(data.configVars)
          .filter(([k]) => !["PORT", "DYNO"].includes(k))
          .map(([key, value]) => ({ key, value }))
      );
    }
  }, [data?.configVars]);

  const handleChange = (idx: number, field: "key" | "value", val: string) => {
    setRows((prev) => prev.map((r, i) => (i === idx ? { ...r, [field]: val } : r)));
  };

  const handleAdd = () => setRows((prev) => [...prev, { key: "", value: "" }]);

  const handleRemove = (idx: number) => setRows((prev) => prev.filter((_, i) => i !== idx));

  const buildConfigVars = (): Record<string, string> => {
    const result: Record<string, string> = {};
    for (const { key, value } of rows) {
      if (key.trim()) result[key.trim()] = value;
    }
    return result;
  };

  const handleSave = () => {
    updateMutation.mutate(
      { appName, data: { configVars: buildConfigVars() } },
      {
        onSuccess: () => {
          toast({ title: "Config saved", description: "Heroku will restart the dyno automatically." });
          void queryClient.invalidateQueries({ queryKey: [`/api/apps/${appName}/config`] });
        },
        onError: (err) => {
          toast({ title: "Save failed", description: err.data?.error ?? err.message ?? "Unknown error", variant: "destructive" });
        },
      }
    );
  };

  const handleRestart = () => {
    restartMutation.mutate(
      { appName },
      {
        onSuccess: () => {
          toast({ title: "Bot restarted", description: `All dynos for ${appName} have been restarted.` });
        },
        onError: (err) => {
          toast({ title: "Restart failed", description: err.data?.error ?? err.message ?? "Unknown error", variant: "destructive" });
        },
      }
    );
  };

  const isBusy = updateMutation.isPending || restartMutation.isPending;

  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent className="bg-card border-border/50 max-w-2xl max-h-[80vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 font-mono text-white">
            <Settings className="w-4 h-4 text-primary" />
            Config Vars — {appName}
          </DialogTitle>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto space-y-2 py-2 pr-1">
          {isLoading ? (
            Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="flex gap-2">
                <Skeleton className="h-9 flex-1 bg-muted/50" />
                <Skeleton className="h-9 flex-1 bg-muted/50" />
                <Skeleton className="h-9 w-9 bg-muted/50" />
              </div>
            ))
          ) : (
            <>
              <div className="grid grid-cols-[1fr_1.5fr_auto] gap-2 px-1 pb-1">
                <span className="text-xs font-mono text-muted-foreground uppercase tracking-wider">Key</span>
                <span className="text-xs font-mono text-muted-foreground uppercase tracking-wider">Value</span>
                <span />
              </div>
              {rows.map((row, idx) => (
                <div key={idx} className="grid grid-cols-[1fr_1.5fr_auto] gap-2 items-center">
                  <Input
                    value={row.key}
                    onChange={(e) => handleChange(idx, "key", e.target.value)}
                    placeholder="VAR_NAME"
                    className="font-mono text-xs bg-background/50 focus-visible:ring-primary/50 h-9"
                  />
                  <Input
                    value={row.value}
                    onChange={(e) => handleChange(idx, "value", e.target.value)}
                    placeholder="value"
                    className="font-mono text-xs bg-background/50 focus-visible:ring-primary/50 h-9"
                  />
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-9 w-9 text-muted-foreground hover:text-destructive"
                    onClick={() => handleRemove(idx)}
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </Button>
                </div>
              ))}
              <Button
                variant="outline"
                size="sm"
                onClick={handleAdd}
                className="mt-1 font-mono text-xs border-dashed border-border/50 text-muted-foreground hover:text-primary hover:border-primary/50"
              >
                <Plus className="w-3.5 h-3.5 mr-1" /> Add variable
              </Button>
            </>
          )}
        </div>

        <DialogFooter className="flex gap-2 pt-4 border-t border-border/20 sm:justify-between">
          <Button
            variant="outline"
            onClick={handleRestart}
            disabled={isBusy}
            className="font-mono text-xs border-orange-500/30 text-orange-400 hover:bg-orange-500/10 hover:text-orange-300"
          >
            {restartMutation.isPending ? (
              <Loader2 className="w-3.5 h-3.5 mr-1.5 animate-spin" />
            ) : (
              <RefreshCw className="w-3.5 h-3.5 mr-1.5" />
            )}
            Restart Bot
          </Button>
          <div className="flex gap-2">
            <Button variant="outline" onClick={onClose} disabled={isBusy} className="font-mono text-xs">
              Cancel
            </Button>
            <Button
              onClick={handleSave}
              disabled={isBusy || isLoading}
              className="font-mono text-xs"
            >
              {updateMutation.isPending ? (
                <Loader2 className="w-3.5 h-3.5 mr-1.5 animate-spin" />
              ) : (
                <Save className="w-3.5 h-3.5 mr-1.5" />
              )}
              Save & Apply
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
