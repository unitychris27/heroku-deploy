import React, { useState } from "react";
import { Terminal } from "lucide-react";
import DeployForm from "@/components/DeployForm";
import JobTracker from "@/components/JobTracker";
import JobsList from "@/components/JobsList";
import LogsViewer from "@/components/LogsViewer";

export default function Home() {
  const [activeJobId, setActiveJobId] = useState<string | null>(null);

  return (
    <div className="min-h-[100dvh] w-full bg-background text-foreground font-sans selection:bg-primary/30 selection:text-primary">
      <div className="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        
        {/* Header */}
        <header className="flex items-center gap-3 pb-6 border-b border-border/50">
          <div className="p-2 bg-primary/10 text-primary rounded-md shadow-[0_0_15px_rgba(0,255,136,0.15)]">
            <Terminal className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-white">HerokuBot Deploy</h1>
            <p className="text-sm text-muted-foreground font-mono">ops_dashboard v1.0.0 // WA_BOT_MANAGER</p>
          </div>
        </header>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          {/* Left Column (Forms & Trackers) */}
          <div className="lg:col-span-4 space-y-8">
            <DeployForm onDeploySuccess={(jobId) => setActiveJobId(jobId)} />
            
            {activeJobId && (
              <JobTracker jobId={activeJobId} />
            )}
            
            <LogsViewer />
          </div>

          {/* Right Column (History) */}
          <div className="lg:col-span-8">
            <JobsList />
          </div>

        </div>
      </div>
    </div>
  );
}