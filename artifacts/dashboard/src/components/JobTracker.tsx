import React from "react";
import { Activity, ExternalLink, AlertCircle, CheckCircle, Terminal } from "lucide-react";
import { useGetDeployStatus, getGetDeployStatusQueryKey } from "@workspace/api-client-react";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";

interface JobTrackerProps {
  jobId: string;
}

export function StatusBadge({ status }: { status: string }) {
  const getStatusColor = () => {
    switch (status) {
      case "queued": return "bg-muted text-muted-foreground border-muted-foreground/30";
      case "creating_app":
      case "setting_buildpack":
      case "setting_config": return "bg-blue-500/10 text-blue-400 border-blue-500/30";
      case "deploying": return "bg-orange-500/10 text-orange-400 border-orange-500/30";
      case "scaling": return "bg-yellow-500/10 text-yellow-400 border-yellow-500/30";
      case "completed": return "bg-primary/10 text-primary border-primary/30";
      case "failed": return "bg-destructive/10 text-destructive border-destructive/30";
      default: return "bg-muted text-muted-foreground";
    }
  };

  const isAnimated = !["completed", "failed"].includes(status);

  return (
    <Badge variant="outline" className={`font-mono text-xs uppercase tracking-wider ${getStatusColor()} ${isAnimated ? 'animate-pulse' : ''}`} data-testid={`status-badge-${status}`}>
      {status.replace('_', ' ')}
    </Badge>
  );
}

export default function JobTracker({ jobId }: JobTrackerProps) {
  const { data, isLoading } = useGetDeployStatus(jobId, {
    query: {
      enabled: !!jobId,
      queryKey: getGetDeployStatusQueryKey(jobId),
      refetchInterval: (query) => {
        const currentStatus = query.state.data?.job?.status;
        if (currentStatus === "completed" || currentStatus === "failed") {
          return false;
        }
        return 2000;
      }
    }
  });

  return (
    <Card className="border-border/50 bg-card overflow-hidden">
      <div className="h-1 w-full bg-muted overflow-hidden">
        {(!data || (!["completed", "failed"].includes(data.job.status))) && (
          <div className="h-full bg-primary/50 w-1/3 animate-[translateX_2s_ease-in-out_infinite]" style={{ animationName: 'progress' }} />
        )}
      </div>
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center justify-between text-sm font-mono text-white">
          <span className="flex items-center gap-2">
            <Activity className="w-4 h-4 text-primary" />
            Active Deployment
          </span>
          <span className="text-muted-foreground text-xs">{jobId.substring(0, 8)}...</span>
        </CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading || !data ? (
          <div className="space-y-3">
            <Skeleton className="h-4 w-1/2 bg-muted/50" />
            <Skeleton className="h-4 w-3/4 bg-muted/50" />
            <Skeleton className="h-8 w-full bg-muted/50" />
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="font-mono text-lg text-white">{data.job.appName}</span>
              <StatusBadge status={data.job.status} />
            </div>

            {data.job.error && (
              <div className="p-3 bg-destructive/10 border border-destructive/30 rounded text-destructive text-sm font-mono flex items-start gap-2">
                <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
                <span className="break-all">{data.job.error}</span>
              </div>
            )}

            <div className="flex flex-col gap-2 pt-2">
              {data.job.appUrl && (
                <a 
                  href={data.job.appUrl} 
                  target="_blank" 
                  rel="noreferrer"
                  data-testid="link-appurl"
                  className="flex items-center gap-2 text-sm text-muted-foreground hover:text-primary transition-colors font-mono"
                >
                  <ExternalLink className="w-4 h-4" />
                  Open Application
                </a>
              )}
              {data.job.logsUrl && (
                <a 
                  href={data.job.logsUrl} 
                  target="_blank" 
                  rel="noreferrer"
                  data-testid="link-logsurl"
                  className="flex items-center gap-2 text-sm text-muted-foreground hover:text-primary transition-colors font-mono"
                >
                  <Terminal className="w-4 h-4" />
                  View Build Logs
                </a>
              )}
            </div>
            
            {data.job.status === "completed" && (
              <div className="flex items-center gap-2 text-sm text-primary font-mono bg-primary/5 p-2 rounded">
                <CheckCircle className="w-4 h-4" />
                Deployment successful
              </div>
            )}
          </div>
        )}
      </CardContent>
      <style dangerouslySetInnerHTML={{__html: `
        @keyframes progress {
          0% { transform: translateX(-100%); }
          100% { transform: translateX(300%); }
        }
      `}} />
    </Card>
  );
}