import React, { useState } from "react";
import { Clock, RefreshCw, Settings, Trash2, AlertTriangle } from "lucide-react";
import { format, formatDistanceToNow, differenceInDays } from "date-fns";
import { useListDeployJobs, useDeleteApp, getListDeployJobsQueryKey } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { StatusBadge } from "./JobTracker";
import ConfigEditor from "./ConfigEditor";
import { useToast } from "@/hooks/use-toast";

export default function JobsList() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const { data, isLoading, isFetching } = useListDeployJobs({
    query: {
      queryKey: getListDeployJobsQueryKey(),
      refetchInterval: 5000,
    },
  });

  const deleteAppMutation = useDeleteApp();

  const [configApp, setConfigApp] = useState<string | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<string | null>(null);

  const handleDelete = () => {
    if (!deleteTarget) return;
    deleteAppMutation.mutate(
      { appName: deleteTarget },
      {
        onSuccess: () => {
          toast({ title: "App deleted", description: `${deleteTarget} has been removed.` });
          void queryClient.invalidateQueries({ queryKey: getListDeployJobsQueryKey() });
          setDeleteTarget(null);
        },
        onError: (err) => {
          toast({ title: "Delete failed", description: err.data?.error ?? err.message ?? "Unknown error", variant: "destructive" });
          setDeleteTarget(null);
        },
      }
    );
  };

  const getDeletionLabel = (scheduledDeletionAt: string) => {
    const d = new Date(scheduledDeletionAt);
    const daysLeft = differenceInDays(d, new Date());
    if (daysLeft <= 3) return { label: `${daysLeft}d left`, urgent: true };
    return { label: `${daysLeft}d left`, urgent: false };
  };

  return (
    <>
      <Card className="border-border/50 bg-card h-full min-h-[500px] flex flex-col">
        <CardHeader className="pb-4 border-b border-border/20 flex flex-row items-center justify-between">
          <CardTitle className="flex items-center gap-2 text-lg text-white">
            <Clock className="w-5 h-5 text-primary" />
            Jobs History
          </CardTitle>
          {isFetching && !isLoading && (
            <RefreshCw className="w-4 h-4 text-muted-foreground animate-spin" />
          )}
        </CardHeader>
        <CardContent className="p-0 flex-1">
          <div className="overflow-x-auto">
            <Table>
              <TableHeader className="bg-background/50">
                <TableRow className="border-border/20 hover:bg-transparent">
                  <TableHead className="font-mono text-xs text-muted-foreground">JOB ID</TableHead>
                  <TableHead className="font-mono text-xs text-muted-foreground">APP NAME</TableHead>
                  <TableHead className="font-mono text-xs text-muted-foreground">BOT TYPE</TableHead>
                  <TableHead className="font-mono text-xs text-muted-foreground">STATUS</TableHead>
                  <TableHead className="font-mono text-xs text-muted-foreground">EXPIRES</TableHead>
                  <TableHead className="font-mono text-xs text-muted-foreground text-right">ACTIONS</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={i} className="border-border/10">
                      <TableCell><Skeleton className="h-4 w-16 bg-muted/50" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-24 bg-muted/50" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-12 bg-muted/50" /></TableCell>
                      <TableCell><Skeleton className="h-6 w-20 bg-muted/50 rounded-full" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-14 bg-muted/50" /></TableCell>
                      <TableCell className="text-right"><Skeleton className="h-7 w-20 bg-muted/50 ml-auto" /></TableCell>
                    </TableRow>
                  ))
                ) : !data?.jobs?.length ? (
                  <TableRow className="border-none hover:bg-transparent">
                    <TableCell colSpan={6} className="h-32 text-center text-muted-foreground font-mono text-sm">
                      No deployment jobs found
                    </TableCell>
                  </TableRow>
                ) : (
                  data.jobs.map((job) => {
                    const isLive = job.status === "completed";
                    const deletion = getDeletionLabel(job.scheduledDeletionAt);
                    return (
                      <TableRow
                        key={job.id}
                        className="border-border/10 hover:bg-white/[0.02] transition-colors group"
                        data-testid={`row-job-${job.id}`}
                      >
                        <TableCell className="font-mono text-xs text-muted-foreground">
                          {job.id.substring(0, 8)}
                        </TableCell>
                        <TableCell className="font-mono text-sm text-white group-hover:text-primary transition-colors">
                          {job.appName}
                        </TableCell>
                        <TableCell className="font-mono text-xs uppercase text-muted-foreground">
                          {job.botType}
                        </TableCell>
                        <TableCell>
                          <StatusBadge status={job.status} />
                        </TableCell>
                        <TableCell>
                          {isLive ? (
                            <Tooltip>
                              <TooltipTrigger asChild>
                                <span className={`font-mono text-xs cursor-default flex items-center gap-1 w-fit ${deletion.urgent ? "text-destructive" : "text-muted-foreground"}`}>
                                  {deletion.urgent && <AlertTriangle className="w-3 h-3" />}
                                  {deletion.label}
                                </span>
                              </TooltipTrigger>
                              <TooltipContent className="font-mono text-xs">
                                Auto-deleted on {format(new Date(job.scheduledDeletionAt), "MMM dd, yyyy")}
                              </TooltipContent>
                            </Tooltip>
                          ) : (
                            <span className="text-muted-foreground/40 font-mono text-xs">—</span>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            {isLive && (
                              <Tooltip>
                                <TooltipTrigger asChild>
                                  <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-7 w-7 text-muted-foreground hover:text-primary hover:bg-primary/10"
                                    onClick={() => setConfigApp(job.appName)}
                                  >
                                    <Settings className="w-3.5 h-3.5" />
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent className="font-mono text-xs">Edit config / Restart</TooltipContent>
                              </Tooltip>
                            )}
                            <Tooltip>
                              <TooltipTrigger asChild>
                                <Button
                                  variant="ghost"
                                  size="icon"
                                  className="h-7 w-7 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                  onClick={() => setDeleteTarget(job.appName)}
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                </Button>
                              </TooltipTrigger>
                              <TooltipContent className="font-mono text-xs">Delete app</TooltipContent>
                            </Tooltip>
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      {/* Config Editor Dialog */}
      {configApp && (
        <ConfigEditor
          appName={configApp}
          open={!!configApp}
          onClose={() => setConfigApp(null)}
        />
      )}

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={!!deleteTarget} onOpenChange={(v) => !v && setDeleteTarget(null)}>
        <AlertDialogContent className="bg-card border-border/50">
          <AlertDialogHeader>
            <AlertDialogTitle className="font-mono text-white flex items-center gap-2">
              <Trash2 className="w-4 h-4 text-destructive" />
              Delete {deleteTarget}?
            </AlertDialogTitle>
            <AlertDialogDescription className="font-mono text-sm text-muted-foreground">
              This will permanently delete the Heroku app and all its data. This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="font-mono text-xs">Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={deleteAppMutation.isPending}
              className="bg-destructive hover:bg-destructive/90 font-mono text-xs"
            >
              {deleteAppMutation.isPending ? "Deleting..." : "Delete permanently"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
