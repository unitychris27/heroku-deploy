import React, { useState } from "react";
import { Terminal, Search } from "lucide-react";
import { useGetAppLogs, getGetAppLogsQueryKey } from "@workspace/api-client-react";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

export default function LogsViewer() {
  const [searchInput, setSearchInput] = useState("");
  const [activeAppName, setActiveAppName] = useState("");

  const { data, isLoading, error } = useGetAppLogs(activeAppName, {
    query: {
      enabled: !!activeAppName,
      queryKey: getGetAppLogsQueryKey(activeAppName),
    }
  });

  const handleFetchLogs = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchInput.trim()) {
      setActiveAppName(searchInput.trim());
    }
  };

  return (
    <Card className="border-border/50 bg-card">
      <CardHeader className="pb-4 border-b border-border/20">
        <CardTitle className="flex items-center gap-2 text-sm font-mono text-white">
          <Terminal className="w-4 h-4 text-primary" />
          Logs Viewer
        </CardTitle>
      </CardHeader>
      <CardContent className="pt-4">
        <form onSubmit={handleFetchLogs} className="flex gap-2">
          <Input 
            placeholder="app-name..." 
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="font-mono text-sm bg-background/50 focus-visible:ring-primary/50 h-9"
            data-testid="input-logs-appname"
          />
          <Button 
            type="submit" 
            variant="secondary"
            size="sm"
            disabled={!searchInput.trim() || isLoading}
            data-testid="button-fetch-logs"
            className="h-9 shrink-0 font-mono text-xs"
          >
            {isLoading ? "FETCHING..." : <><Search className="w-3 h-3 mr-1" /> GET</>}
          </Button>
        </form>

        {activeAppName && (
          <div className="mt-4">
            {error ? (
              <div className="text-xs text-destructive font-mono p-2 bg-destructive/10 rounded">
                Failed to fetch logs. App may not exist.
              </div>
            ) : data ? (
              <div className="p-3 bg-background border border-border/50 rounded flex flex-col gap-2">
                <span className="text-xs text-muted-foreground font-mono">Logplex Session URL:</span>
                <a 
                  href={data.logsUrl} 
                  target="_blank" 
                  rel="noreferrer"
                  data-testid="link-logplex"
                  className="text-sm font-mono text-primary hover:underline break-all"
                >
                  {data.logsUrl}
                </a>
                <span className="text-[10px] text-muted-foreground font-mono mt-1">
                  Note: Log sessions expire. Open the URL to stream live logs.
                </span>
              </div>
            ) : null}
          </div>
        )}
      </CardContent>
    </Card>
  );
}