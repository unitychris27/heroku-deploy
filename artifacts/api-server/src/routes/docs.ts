import { Router, type IRouter, type Request, type Response } from "express";

const router: IRouter = Router();

router.get("/docs", (_req: Request, res: Response) => {
  res.setHeader("Content-Type", "text/html; charset=utf-8");
  res.send(HTML);
});

const HTML = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Heroku Deploy Panel — API Docs</title>
<style>
  :root{--bg:#0b1220;--card:#111c2e;--border:rgba(255,255,255,.08);--accent:#00d2ff;--accent2:#0891b2;--text:#e2e8f0;--muted:#475569;--green:#4ade80;--red:#f87171;--amber:#fbbf24;--purple:#a78bfa;--code-bg:#0d1117;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.7;}
  a{color:var(--accent);text-decoration:none;}a:hover{text-decoration:underline;}
  /* Layout */
  .layout{display:flex;min-height:100vh;}
  .sidebar{width:260px;flex-shrink:0;background:var(--card);border-right:1px solid var(--border);padding:24px 0;position:sticky;top:0;height:100vh;overflow-y:auto;}
  .content{flex:1;max-width:860px;padding:40px 32px;margin:0 auto;}
  /* Sidebar */
  .logo{padding:0 20px 24px;border-bottom:1px solid var(--border);margin-bottom:16px;}
  .logo h1{font-size:15px;font-weight:800;color:var(--accent);}
  .logo p{font-size:11px;color:var(--muted);margin-top:3px;}
  .nav-section{padding:6px 20px;font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-top:12px;}
  .nav-link{display:flex;align-items:center;gap:8px;padding:7px 20px;font-size:13px;color:#94a3b8;text-decoration:none;transition:background .15s,color .15s;border-left:2px solid transparent;}
  .nav-link:hover{background:rgba(0,210,255,.06);color:var(--accent);border-left-color:var(--accent);}
  .nav-link.active{background:rgba(0,210,255,.08);color:var(--accent);border-left-color:var(--accent);}
  .method{display:inline-block;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:800;letter-spacing:.05em;flex-shrink:0;}
  .get   {background:rgba(34,197,94,.15);color:#4ade80;}
  .post  {background:rgba(0,210,255,.12);color:#67e8f9;}
  .patch {background:rgba(245,158,11,.12);color:#fbbf24;}
  .delete{background:rgba(239,68,68,.12);color:#f87171;}
  /* Content */
  .hero{margin-bottom:40px;padding-bottom:32px;border-bottom:1px solid var(--border);}
  .hero h1{font-size:28px;font-weight:800;background:linear-gradient(135deg,#00d2ff,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;}
  .hero p{color:var(--muted);font-size:15px;}
  .base-url{display:inline-flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:10px 16px;margin-top:14px;font-family:monospace;font-size:13px;}
  .base-url span{color:var(--muted);font-size:11px;font-family:sans-serif;}
  /* Sections */
  .section{margin-bottom:56px;}
  .section-title{font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:20px;padding-bottom:8px;border-bottom:1px solid var(--border);}
  /* Endpoint */
  .endpoint{background:var(--card);border:1px solid var(--border);border-radius:14px;margin-bottom:18px;overflow:hidden;}
  .ep-header{display:flex;align-items:center;gap:10px;padding:14px 18px;cursor:pointer;user-select:none;transition:background .15s;}
  .ep-header:hover{background:rgba(255,255,255,.02);}
  .ep-path{font-family:monospace;font-size:13px;font-weight:600;color:var(--text);}
  .ep-desc{font-size:12px;color:var(--muted);margin-left:auto;}
  .ep-body{padding:0 18px 18px;border-top:1px solid var(--border);display:none;}
  .ep-body.open{display:block;}
  .ep-meta{padding-top:14px;}
  .ep-label{font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin:14px 0 6px;}
  /* Code */
  pre{background:var(--code-bg);border:1px solid rgba(255,255,255,.06);border-radius:8px;padding:14px 16px;overflow-x:auto;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;}
  code{background:rgba(0,210,255,.08);border:1px solid rgba(0,210,255,.15);border-radius:4px;padding:1px 5px;font-family:monospace;font-size:12px;color:var(--accent);}
  /* Table */
  table{width:100%;border-collapse:collapse;margin:6px 0;}
  th{text-align:left;padding:8px 10px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border);}
  td{padding:8px 10px;border-bottom:1px solid rgba(255,255,255,.03);font-size:12px;vertical-align:top;}
  tr:last-child td{border-bottom:0;}
  td code{font-size:11px;}
  /* Badge */
  .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;}
  .badge-req{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red);}
  .badge-opt{background:rgba(100,116,139,.1);border:1px solid rgba(100,116,139,.3);color:#94a3b8;}
  /* Status flow */
  .flow{display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin:8px 0;}
  .flow-step{background:rgba(0,210,255,.06);border:1px solid rgba(0,210,255,.2);border-radius:6px;padding:4px 10px;font-size:11px;font-family:monospace;color:var(--accent);}
  .flow-arrow{color:var(--muted);font-size:14px;}
  .flow-step.done{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25);color:var(--green);}
  .flow-step.fail{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25);color:var(--red);}
  /* Lang tabs */
  .tabs{display:flex;gap:4px;margin-bottom:-1px;flex-wrap:wrap;}
  .tab{padding:6px 14px;border:1px solid var(--border);border-bottom:0;border-radius:6px 6px 0 0;font-size:11px;font-weight:700;cursor:pointer;color:var(--muted);background:transparent;transition:color .15s,background .15s;}
  .tab.active{color:var(--accent);background:var(--code-bg);border-color:rgba(0,210,255,.3);}
  .tab-panel{display:none;} .tab-panel.active{display:block;}
  /* Auth box */
  .auth-box{background:rgba(0,210,255,.04);border:1px solid rgba(0,210,255,.15);border-radius:10px;padding:16px 18px;margin-bottom:14px;}
  .auth-box p{font-size:13px;color:#94a3b8;margin-bottom:8px;}
  /* Chevron */
  .chev{margin-left:auto;color:var(--muted);transition:transform .2s;font-size:12px;}
  .ep-header.open .chev{transform:rotate(180deg);}
  @media(max-width:700px){.sidebar{display:none;}.content{padding:24px 16px;}}
</style>
</head>
<body>
<div class="layout">

<!-- Sidebar -->
<nav class="sidebar">
  <div class="logo">
    <h1>Deploy Panel API</h1>
    <p>panel.xcasper.site/api</p>
  </div>

  <div class="nav-section">Getting Started</div>
  <a class="nav-link" href="#auth">Authentication</a>
  <a class="nav-link" href="#errors">Error Format</a>
  <a class="nav-link" href="#status-flow">Status Flow</a>

  <div class="nav-section">External (User)</div>
  <a class="nav-link" href="#get-bots"><span class="method get">GET</span> /external/bots</a>
  <a class="nav-link" href="#post-deploy"><span class="method post">POST</span> /external/deploy</a>
  <a class="nav-link" href="#get-status"><span class="method get">GET</span> /external/status/:jobId</a>
  <a class="nav-link" href="#get-check"><span class="method get">GET</span> /external/check/:app</a>
  <a class="nav-link" href="#get-config"><span class="method get">GET</span> /external/config/:app</a>
  <a class="nav-link" href="#patch-config"><span class="method patch">PATCH</span> /external/config/:app</a>
  <a class="nav-link" href="#post-restart"><span class="method post">POST</span> /external/restart/:app</a>
  <a class="nav-link" href="#get-logs"><span class="method get">GET</span> /external/logs/:app</a>
  <a class="nav-link" href="#delete-app"><span class="method delete">DEL</span> /external/delete/:app</a>

  <div class="nav-section">Admin</div>
  <a class="nav-link" href="#get-settings"><span class="method get">GET</span> /admin/settings</a>
  <a class="nav-link" href="#patch-settings"><span class="method patch">PATCH</span> /admin/settings</a>
  <a class="nav-link" href="#post-test"><span class="method post">POST</span> /admin/test-connection</a>
  <a class="nav-link" href="#get-registry"><span class="method get">GET</span> /admin/registry</a>
  <a class="nav-link" href="#post-import"><span class="method post">POST</span> /admin/registry/import</a>
  <a class="nav-link" href="#delete-registry"><span class="method delete">DEL</span> /admin/registry/:app</a>

  <div class="nav-section">Code Examples</div>
  <a class="nav-link" href="#examples">cURL / JS / Python / PHP</a>
</nav>

<!-- Content -->
<main class="content">

  <div class="hero">
    <h1>Heroku Deploy Panel API</h1>
    <p>Deploy and manage WhatsApp bots on Heroku from any application using a simple REST API.</p>
    <div class="base-url">
      <span>Base URL</span>
      <strong>https://panel.xcasper.site/api</strong>
    </div>
  </div>

  <!-- AUTH -->
  <div class="section" id="auth">
    <div class="section-title">Authentication</div>
    <div class="auth-box">
      <p>All endpoints require your API secret key. Pass it as a header or query parameter:</p>
      <pre>X-API-Key: Digitex2025
<span style="color:#475569">-- or --</span>
?key=Digitex2025</pre>
    </div>
    <p style="color:var(--muted);font-size:13px">Requests with an invalid or missing key return <code>401 Unauthorized</code>.</p>
  </div>

  <!-- ERRORS -->
  <div class="section" id="errors">
    <div class="section-title">Error Format</div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:12px">All errors use the same JSON shape:</p>
    <pre>{ "success": false, "error": "Human-readable message" }</pre>
    <table style="margin-top:14px">
      <tr><th>Status</th><th>Meaning</th></tr>
      <tr><td><code>200</code></td><td>Success</td></tr>
      <tr><td><code>202</code></td><td>Accepted — deployment queued</td></tr>
      <tr><td><code>400</code></td><td>Bad request — missing/invalid fields</td></tr>
      <tr><td><code>401</code></td><td>Unauthorized — wrong or missing API key</td></tr>
      <tr><td><code>404</code></td><td>Not found — job ID or app doesn't exist</td></tr>
      <tr><td><code>409</code></td><td>Conflict — duplicate active deployment</td></tr>
      <tr><td><code>500</code></td><td>Server error — usually a Heroku API error</td></tr>
      <tr><td><code>503</code></td><td>Server not configured — API key missing</td></tr>
    </table>
  </div>

  <!-- STATUS FLOW -->
  <div class="section" id="status-flow">
    <div class="section-title">Deployment Status Flow</div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:12px">Poll <code>/external/status/:jobId</code> every 5 seconds until <code>completed</code> or <code>failed</code>.</p>
    <div class="flow">
      <span class="flow-step">queued</span><span class="flow-arrow">→</span>
      <span class="flow-step">creating_app</span><span class="flow-arrow">→</span>
      <span class="flow-step">setting_buildpack</span><span class="flow-arrow">→</span>
      <span class="flow-step">setting_config</span><span class="flow-arrow">→</span>
      <span class="flow-step">deploying</span><span class="flow-arrow">→</span>
      <span class="flow-step">scaling</span><span class="flow-arrow">→</span>
      <span class="flow-step done">completed</span>
    </div>
    <div class="flow" style="margin-top:6px">
      <span style="font-size:12px;color:var(--muted)">Any step can transition to →</span>
      <span class="flow-step fail">failed</span>
    </div>
  </div>

  <!-- EXTERNAL SECTION -->
  <div class="section">
    <div class="section-title">External Endpoints — User Facing</div>

    <!-- GET /external/bots -->
    <div class="endpoint" id="get-bots">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method get">GET</span>
        <span class="ep-path">/external/bots</span>
        <span class="ep-desc">List supported bot types</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <p style="color:var(--muted);font-size:13px">Returns all available bot types with their required configuration fields. Use this to build dynamic deployment forms.</p>
          <div class="ep-label">Response</div>
          <pre>{
  "success": true,
  "bots": [
    {
      "key": "cypherx",
      "name": "CypherX",
      "fields": [
        {
          "key": "sessionId",
          "label": "Session ID",
          "type": "text",
          "required": true,
          "placeholder": "paste WhatsApp session string..."
        }
      ]
    }
  ]
}</pre>
          <div class="ep-label">Available Bot Keys</div>
          <table>
            <tr><th>Key</th><th>Name</th></tr>
            <tr><td><code>cypherx</code></td><td>CypherX</td></tr>
            <tr><td><code>bwm</code></td><td>BWM-XMD</td></tr>
            <tr><td><code>cypherx-ultra</code></td><td>CypherX Ultra</td></tr>
            <tr><td><code>king-md</code></td><td>King MD</td></tr>
            <tr><td><code>queen-anitah</code></td><td>Queen Anitah</td></tr>
            <tr><td><code>atassa-md</code></td><td>Atassa MD</td></tr>
          </table>
        </div>
      </div>
    </div>

    <!-- POST /external/deploy -->
    <div class="endpoint" id="post-deploy">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method post">POST</span>
        <span class="ep-path">/external/deploy</span>
        <span class="ep-desc">Deploy a bot to Heroku</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <p style="color:var(--muted);font-size:13px">Queues a deployment. Returns a <code>jobId</code> to poll with <code>/external/status/:jobId</code>.</p>
          <div class="ep-label">Request Body</div>
          <pre>{
  "botType": "cypherx",
  "appName": "my-whatsapp-bot",
  "botVars": {
    "sessionId": "BAAEF_WA_..."
  }
}</pre>
          <table>
            <tr><th>Field</th><th>Type</th><th>Required</th><th>Notes</th></tr>
            <tr><td><code>botType</code></td><td>string</td><td><span class="badge badge-req">required</span></td><td>Must match a key from <code>/external/bots</code></td></tr>
            <tr><td><code>appName</code></td><td>string</td><td><span class="badge badge-req">required</span></td><td>3–30 chars, lowercase, letters/numbers/dashes, no leading or trailing dash</td></tr>
            <tr><td><code>botVars</code></td><td>object</td><td><span class="badge badge-req">required</span></td><td>Key-value pairs for the bot's required fields</td></tr>
          </table>
          <div class="ep-label">Response 202</div>
          <pre>{
  "success": true,
  "jobId": "abc123",
  "statusUrl": "/api/external/status/abc123",
  "appUrl": "https://my-whatsapp-bot.herokuapp.com",
  "herokuDashboard": "https://dashboard.heroku.com/apps/my-whatsapp-bot",
  "message": "Deployment queued. Poll statusUrl every 5 seconds to track progress."
}</pre>
        </div>
      </div>
    </div>

    <!-- GET /external/status/:jobId -->
    <div class="endpoint" id="get-status">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method get">GET</span>
        <span class="ep-path">/external/status/:jobId</span>
        <span class="ep-desc">Poll deployment progress</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <p style="color:var(--muted);font-size:13px">Poll every 5 seconds after deploying. Stop when <code>status</code> is <code>completed</code> or <code>failed</code>.</p>
          <div class="ep-label">Response</div>
          <pre>{
  "success": true,
  "jobId": "abc123",
  "appName": "my-whatsapp-bot",
  "botType": "cypherx",
  "status": "completed",
  "appUrl": "https://my-whatsapp-bot.herokuapp.com",
  "logsUrl": "https://logplex.heroku.com/...",
  "error": null,
  "createdAt": "2025-04-08T12:00:00.000Z",
  "updatedAt": "2025-04-08T12:03:45.000Z"
}</pre>
        </div>
      </div>
    </div>

    <!-- GET /external/check/:appName -->
    <div class="endpoint" id="get-check">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method get">GET</span>
        <span class="ep-path">/external/check/:appName</span>
        <span class="ep-desc">Live Heroku status check</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <p style="color:var(--muted);font-size:13px">Checks the app status directly from Heroku, bypassing the job queue. Use this as a fallback after server restarts or to check old bots.</p>
          <div class="ep-label">Response</div>
          <pre>{
  "success": true,
  "exists": true,
  "status": "completed",
  "appUrl": "https://my-whatsapp-bot.herokuapp.com",
  "buildStatus": "succeeded"
}</pre>
        </div>
      </div>
    </div>

    <!-- GET /external/config -->
    <div class="endpoint" id="get-config">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method get">GET</span>
        <span class="ep-path">/external/config/:appName</span>
        <span class="ep-desc">Get environment variables</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <div class="ep-label">Response</div>
          <pre>{
  "success": true,
  "appName": "my-whatsapp-bot",
  "configVars": {
    "SESSION_ID": "BAAEF_WA_...",
    "BOT_NAME": "CYPHERX",
    "NODE_ENV": "production"
  }
}</pre>
        </div>
      </div>
    </div>

    <!-- PATCH /external/config -->
    <div class="endpoint" id="patch-config">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method patch">PATCH</span>
        <span class="ep-path">/external/config/:appName</span>
        <span class="ep-desc">Update environment variables</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <p style="color:var(--muted);font-size:13px">Updates config vars. Triggers an automatic dyno restart.</p>
          <div class="ep-label">Request Body</div>
          <pre>{ "configVars": { "SESSION_ID": "new_session", "PREFIX": "!" } }</pre>
          <div class="ep-label">Response</div>
          <pre>{ "success": true, "message": "Config vars updated. App is restarting." }</pre>
        </div>
      </div>
    </div>

    <!-- POST /external/restart -->
    <div class="endpoint" id="post-restart">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method post">POST</span>
        <span class="ep-path">/external/restart/:appName</span>
        <span class="ep-desc">Restart all dynos</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <div class="ep-label">Request Body</div><pre>{}</pre>
          <div class="ep-label">Response</div>
          <pre>{ "success": true, "message": "App restarted." }</pre>
        </div>
      </div>
    </div>

    <!-- GET /external/logs -->
    <div class="endpoint" id="get-logs">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method get">GET</span>
        <span class="ep-path">/external/logs/:appName</span>
        <span class="ep-desc">Fetch recent log output</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <div class="ep-label">Response</div>
          <pre>{
  "success": true,
  "logText": "2025-04-08T12:00:00 app[web.1]: Bot connected...\\n..."
}</pre>
        </div>
      </div>
    </div>

    <!-- DELETE /external/delete -->
    <div class="endpoint" id="delete-app">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method delete">DELETE</span>
        <span class="ep-path">/external/delete/:appName</span>
        <span class="ep-desc">Permanently delete an app</span>
        <span class="chev">▼</span>
      </div>
      <div class="ep-body">
        <div class="ep-meta">
          <p style="color:var(--red);font-size:13px">⚠ Permanently deletes the app from Heroku and removes it from the key registry. Cannot be undone.</p>
          <div class="ep-label">Response</div>
          <pre>{ "success": true, "message": "App \\"my-whatsapp-bot\\" deleted from Heroku." }</pre>
        </div>
      </div>
    </div>
  </div>

  <!-- ADMIN SECTION -->
  <div class="section">
    <div class="section-title">Admin Endpoints</div>

    <div class="endpoint" id="get-settings">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method get">GET</span><span class="ep-path">/admin/settings</span>
        <span class="ep-desc">View server config</span><span class="chev">▼</span>
      </div>
      <div class="ep-body"><div class="ep-meta">
        <pre>{
  "success": true,
  "settings": {
    "hasHerokuApiKey": true,
    "herokuApiKey": "HRKU-****...****iir",
    "herokuTeam": "silvateam20",
    "hasApiSecretKey": true,
    "apiSecretKey": "Digit****25",
    "keySource": "admin_panel"
  }
}</pre>
      </div></div>
    </div>

    <div class="endpoint" id="patch-settings">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method patch">PATCH</span><span class="ep-path">/admin/settings</span>
        <span class="ep-desc">Update active key / secret</span><span class="chev">▼</span>
      </div>
      <div class="ep-body"><div class="ep-meta">
        <div class="ep-label">Request Body (all optional)</div>
        <pre>{ "herokuApiKey": "HRKU-...", "herokuTeam": "myteam", "apiSecretKey": "NewSecret" }</pre>
        <div class="ep-label">Response</div>
        <pre>{ "success": true, "message": "Settings updated." }</pre>
      </div></div>
    </div>

    <div class="endpoint" id="post-test">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method post">POST</span><span class="ep-path">/admin/test-connection</span>
        <span class="ep-desc">Verify Heroku key</span><span class="chev">▼</span>
      </div>
      <div class="ep-body"><div class="ep-meta">
        <div class="ep-label">Response</div>
        <pre>{ "success": true, "email": "user@example.com", "name": "Wycliffe Wanga" }</pre>
      </div></div>
    </div>

    <div class="endpoint" id="get-registry">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method get">GET</span><span class="ep-path">/admin/registry</span>
        <span class="ep-desc">List all registered apps</span><span class="chev">▼</span>
      </div>
      <div class="ep-body"><div class="ep-meta">
        <pre>{ "success": true, "count": 105, "apps": ["alpha", "my-bot", "..."] }</pre>
      </div></div>
    </div>

    <div class="endpoint" id="post-import">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method post">POST</span><span class="ep-path">/admin/registry/import</span>
        <span class="ep-desc">Import old Heroku account</span><span class="chev">▼</span>
      </div>
      <div class="ep-body"><div class="ep-meta">
        <div class="ep-label">Request Body</div>
        <pre>{ "herokuApiKey": "HRKU-old-key", "teamName": "silvateam211" }</pre>
        <div class="ep-label">Response</div>
        <pre>{ "success": true, "total": 100, "added": 95, "skipped": 5, "message": "Imported 95 apps (5 already registered)." }</pre>
      </div></div>
    </div>

    <div class="endpoint" id="delete-registry">
      <div class="ep-header" onclick="toggle(this)">
        <span class="method delete">DELETE</span><span class="ep-path">/admin/registry/:appName</span>
        <span class="ep-desc">Remove from registry</span><span class="chev">▼</span>
      </div>
      <div class="ep-body"><div class="ep-meta">
        <pre>{ "success": true, "message": "my-bot removed from registry." }</pre>
      </div></div>
    </div>
  </div>

  <!-- CODE EXAMPLES -->
  <div class="section" id="examples">
    <div class="section-title">Code Examples</div>
    <div class="tabs">
      <button class="tab active" onclick="switchTab('curl',this)">cURL</button>
      <button class="tab" onclick="switchTab('js',this)">JavaScript</button>
      <button class="tab" onclick="switchTab('python',this)">Python</button>
      <button class="tab" onclick="switchTab('php',this)">PHP</button>
    </div>
    <div id="tab-curl" class="tab-panel active">
<pre><span style="color:#475569"># List bots</span>
curl -s https://panel.xcasper.site/api/external/bots \\
  -H "X-API-Key: Digitex2025"

<span style="color:#475569"># Deploy</span>
curl -s -X POST https://panel.xcasper.site/api/external/deploy \\
  -H "X-API-Key: Digitex2025" \\
  -H "Content-Type: application/json" \\
  -d '{"botType":"cypherx","appName":"my-bot","botVars":{"sessionId":"SESSION..."}}'

<span style="color:#475569"># Poll status</span>
curl -s https://panel.xcasper.site/api/external/status/JOB_ID \\
  -H "X-API-Key: Digitex2025"

<span style="color:#475569"># Check live status</span>
curl -s https://panel.xcasper.site/api/external/check/my-bot \\
  -H "X-API-Key: Digitex2025"

<span style="color:#475569"># Update config</span>
curl -s -X PATCH https://panel.xcasper.site/api/external/config/my-bot \\
  -H "X-API-Key: Digitex2025" -H "Content-Type: application/json" \\
  -d '{"configVars":{"SESSION_ID":"new_session"}}'

<span style="color:#475569"># Restart</span>
curl -s -X POST https://panel.xcasper.site/api/external/restart/my-bot \\
  -H "X-API-Key: Digitex2025" -d '{}'

<span style="color:#475569"># Delete</span>
curl -s -X DELETE https://panel.xcasper.site/api/external/delete/my-bot \\
  -H "X-API-Key: Digitex2025"</pre>
    </div>
    <div id="tab-js" class="tab-panel">
<pre>const API = "https://panel.xcasper.site/api";
const KEY = "Digitex2025";

async function apiCall(method, path, body) {
  const r = await fetch(API + path, {
    method,
    headers: { "X-API-Key": KEY, "Content-Type": "application/json" },
    body: body ? JSON.stringify(body) : undefined,
  });
  return r.json();
}

async function deployBot(appName, sessionId) {
  const deploy = await apiCall("POST", "/external/deploy", {
    botType: "cypherx", appName, botVars: { sessionId },
  });
  if (!deploy.success) throw new Error(deploy.error);

  while (true) {
    await new Promise(r => setTimeout(r, 5000));
    const s = await apiCall("GET", \`/external/status/\${deploy.jobId}\`);
    console.log("Status:", s.status);
    if (s.status === "completed") return s;
    if (s.status === "failed") throw new Error(s.error);
  }
}

deployBot("my-bot-001", "BAAEF_WA_SESSION_...")
  .then(s => console.log("Live at:", s.appUrl))
  .catch(console.error);</pre>
    </div>
    <div id="tab-python" class="tab-panel">
<pre>import requests, time

API = "https://panel.xcasper.site/api"
H   = {"X-API-Key": "Digitex2025", "Content-Type": "application/json"}

def call(method, path, body=None):
    return requests.request(method, API + path, headers=H, json=body).json()

def deploy(app_name, session_id):
    r = call("POST", "/external/deploy", {
        "botType": "cypherx",
        "appName": app_name,
        "botVars": {"sessionId": session_id}
    })
    if not r["success"]: raise Exception(r["error"])

    while True:
        time.sleep(5)
        s = call("GET", f"/external/status/{r['jobId']}")
        print(f"Status: {s['status']}")
        if s["status"] == "completed": return s
        if s["status"] == "failed": raise Exception(s["error"])

# Deploy
result = deploy("my-bot-001", "BAAEF_WA_SESSION_...")
print("Live at:", result["appUrl"])

# Update config
call("PATCH", "/external/config/my-bot-001", {"configVars": {"PREFIX": "!"}})

# Restart
call("POST", "/external/restart/my-bot-001", {})</pre>
    </div>
    <div id="tab-php" class="tab-panel">
<pre>define('API', 'https://panel.xcasper.site/api');
define('KEY', 'Digitex2025');

function api(string $m, string $p, ?array $b = null): array {
    $ch = curl_init(API . $p);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST  => $m,
        CURLOPT_HTTPHEADER     => ['X-API-Key: ' . KEY, 'Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($b) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($b));
    $r = curl_exec($ch); curl_close($ch);
    return json_decode($r, true) ?? [];
}

// Deploy
$r = api('POST', '/external/deploy', [
    'botType' => 'cypherx', 'appName' => 'my-bot-001',
    'botVars' => ['sessionId' => 'BAAEF_WA_SESSION_...'],
]);
do {
    sleep(5);
    $s = api('GET', '/external/status/' . $r['jobId']);
    echo "Status: {$s['status']}\\n";
} while (!in_array($s['status'], ['completed','failed']));

// Update config
api('PATCH', '/external/config/my-bot-001', ['configVars' => ['PREFIX' => '!']]);

// Restart
api('POST', '/external/restart/my-bot-001', []);

// Delete
api('DELETE', '/external/delete/my-bot-001');</pre>
    </div>
  </div>

  <div style="text-align:center;padding:40px 0 20px;color:var(--muted);font-size:12px;">
    Heroku Deploy Panel &nbsp;·&nbsp; panel.xcasper.site &nbsp;·&nbsp; <a href="https://github.com/Digitex-Softwares/heroku-deploy">GitHub</a>
  </div>

</main>
</div>

<script>
function toggle(header) {
  header.classList.toggle('open');
  const body = header.nextElementSibling;
  body.classList.toggle('open');
}

function switchTab(id, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// Highlight current section in sidebar
const links = document.querySelectorAll('.nav-link[href^="#"]');
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      links.forEach(l => {
        l.classList.toggle('active', l.getAttribute('href') === '#' + e.target.id);
      });
    }
  });
}, { rootMargin: '-30% 0px -60% 0px' });
document.querySelectorAll('[id]').forEach(el => observer.observe(el));
</script>
</body>
</html>`;

export default router;
