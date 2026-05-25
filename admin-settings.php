<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
requireLogin();

// ── Config (same as deploy.php / manage.php) ──────────────────────────────────
$API_BASE_URL = "https://panel.xcasper.site/api";
$API_SECRET   = "Digitex2025";

$pageTitle  = 'Admin Settings';
$activePage = 'admin-settings';
include_once __DIR__ . '/includes/header.php';
?>
<style>
.as-mw{max-width:720px;margin:0 auto;}
.as-card{background:rgba(30,41,59,.55);border:1px solid rgba(255,255,255,.07);border-radius:22px;padding:30px;margin-bottom:22px;}
.as-card h2{font-size:15px;font-weight:700;color:#e2e8f0;margin:0 0 6px;}
.as-card .as-sub{font-size:12px;color:#475569;margin:0 0 22px;line-height:1.6;}
.as-label{display:block;font-size:11px;font-weight:600;color:#94a3b8;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px;}
.as-input{width:100%;box-sizing:border-box;padding:10px 14px;font-size:13px;font-family:monospace;background:rgba(10,18,36,.8);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;outline:none;transition:border .2s;}
.as-input:focus{border-color:rgba(0,210,255,.5);}
.as-row{margin-bottom:18px;}
.as-hint{font-size:11px;color:#475569;margin-top:5px;line-height:1.5;}
.as-btn{padding:10px 22px;border:0;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;letter-spacing:.05em;transition:opacity .2s;line-height:1.4;}
.as-btn:hover{opacity:.82;} .as-btn:disabled{opacity:.35;cursor:not-allowed;}
.as-btn-blue{background:linear-gradient(135deg,#00d2ff,#0891b2);color:#000;}
.as-btn-ghost{background:rgba(0,210,255,.1);border:1px solid rgba(0,210,255,.3);color:#00d2ff;}
.as-btn-green{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);color:#4ade80;}
.as-msg{padding:12px 16px;border-radius:10px;font-size:13px;margin-top:14px;line-height:1.6;display:none;}
.as-msg-ok {background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.3);color:#4ade80;}
.as-msg-err{background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.3);color:#f87171;}
.as-status-row{display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(10,18,36,.5);border-radius:8px;border:1px solid rgba(255,255,255,.06);margin-bottom:10px;}
.as-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.as-dot-ok{background:#22c55e;box-shadow:0 0 6px rgba(34,197,94,.6);}
.as-dot-warn{background:#f59e0b;box-shadow:0 0 6px rgba(245,158,11,.6);}
.as-dot-err{background:#ef4444;box-shadow:0 0 6px rgba(239,68,68,.6);}
.as-status-label{font-size:12px;color:#94a3b8;flex:1;}
.as-status-val{font-size:12px;font-weight:600;color:#e2e8f0;font-family:monospace;}
.as-divider{border:0;border-top:1px solid rgba(255,255,255,.06);margin:22px 0;}
.as-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:.05em;}
.as-badge-ok{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3);}
.as-badge-warn{background:rgba(245,158,11,.1);color:#fbbf24;border:1px solid rgba(245,158,11,.3);}
.as-badge-err{background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.3);}
.as-spin{display:inline-block;width:13px;height:13px;border:2px solid rgba(0,210,255,.2);border-top-color:#00d2ff;border-radius:50%;animation:asspin .7s linear infinite;vertical-align:middle;margin-right:5px;}
@keyframes asspin{to{transform:rotate(360deg)}}
</style>

<div class="app-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex:1;min-width:0;">
<?php include __DIR__ . '/includes/topbar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">Admin Settings</h1>
        <div class="page-breadcrumb">Dashboard → Admin Settings</div>
    </div>

    <div class="as-mw">

        <!-- ── Connection Status ───────────────────────────────────────────── -->
        <div class="as-card">
            <h2>Connection Status</h2>
            <p class="as-sub">Live status of your API server and deployment platform credentials.</p>

            <div id="status-section">
                <div style="text-align:center;padding:20px;color:#475569"><span class="as-spin"></span> Loading status…</div>
            </div>

            <button class="as-btn as-btn-green" onclick="testConnection()" id="btn-test" style="margin-top:8px">
                Test Connection
            </button>
            <div id="test-msg" class="as-msg"></div>
        </div>

        <!-- ── API Server ──────────────────────────────────────────────────── -->
        <div class="as-card">
            <h2>API Server URL</h2>
            <p class="as-sub">
                The URL of your running API server. This is the endpoint your PHP files connect to.<br>
                On Replit: your published <code style="font-family:monospace;color:#00d2ff">*.replit.app</code> domain.<br>
                On VPS: your domain (e.g. <code style="font-family:monospace;color:#00d2ff">https://api.example.com</code>) or IP (e.g. <code style="font-family:monospace;color:#00d2ff">http://1.2.3.4:8080</code>).
            </p>
            <div class="as-row">
                <label class="as-label">Current API URL (set in PHP files)</label>
                <input class="as-input" type="text" id="display-api-url" value="<?= htmlspecialchars($API_BASE_URL) ?>" readonly style="opacity:.6;cursor:default">
                <p class="as-hint">To change this, edit <code>deploy.php</code> and <code>manage.php</code> — set <code>$API_BASE_URL</code> at the top of each file.</p>
            </div>
        </div>

        <!-- ── Platform API Key ────────────────────────────────────────────── -->
        <div class="as-card">
            <h2>Platform API Key</h2>
            <p class="as-sub">
                Your deployment platform API key. This is used to create, manage, and delete bot apps on the platform.<br>
                Settings saved here are stored securely on the API server and take priority over environment variables.
            </p>
            <div class="as-row">
                <label class="as-label">API Key</label>
                <input class="as-input" type="password" id="inp-api-key" placeholder="Enter your API key…" autocomplete="new-password">
                <p class="as-hint">Paste your full API key. It will be stored server-side and masked in this panel.</p>
            </div>
            <div class="as-row">
                <label class="as-label">Team / Organization Name <span class="as-badge as-badge-warn">optional</span></label>
                <input class="as-input" type="text" id="inp-team" placeholder="my-team-name (leave blank for personal account)">
                <p class="as-hint">If you deploy to a team/organization account, enter the team name here. Leave blank to deploy to your personal account.</p>
            </div>
            <button class="as-btn as-btn-blue" id="btn-save-platform" onclick="savePlatformSettings()">Save Platform Settings</button>
            <div id="platform-msg" class="as-msg"></div>
        </div>

        <!-- ── API Secret Key ──────────────────────────────────────────────── -->
        <div class="as-card">
            <h2>API Secret Key</h2>
            <p class="as-sub">
                The shared secret that protects all API endpoints. Must match the value set in your PHP files (<code>$API_SECRET</code>) and the <code>API_SECRET_KEY</code> environment variable on the server.
            </p>
            <div class="as-row">
                <label class="as-label">Secret Key</label>
                <input class="as-input" type="password" id="inp-secret" placeholder="Enter new API secret key…" autocomplete="new-password">
                <p class="as-hint">If you update this, you must also update <code>$API_SECRET</code> in <code>deploy.php</code> and <code>manage.php</code>, then re-upload those files to cPanel.</p>
            </div>
            <button class="as-btn as-btn-blue" id="btn-save-secret" onclick="saveSecretKey()">Update Secret Key</button>
            <div id="secret-msg" class="as-msg"></div>
        </div>

    </div><!-- /.as-mw -->
</main>
</div>
</div>

<script>
const API_URL = "<?= htmlspecialchars(rtrim($API_BASE_URL,'/'), ENT_QUOTES) ?>";
const API_KEY = "<?= htmlspecialchars($API_SECRET, ENT_QUOTES) ?>";

async function apiCall(method, path, body) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-API-Key': API_KEY },
    };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(API_URL + path, opts);
    const text = await r.text();
    try { return JSON.parse(text); } catch(_) { return { success: false, error: 'Unexpected response: ' + text.slice(0,120) }; }
}

function showMsg(id, type, html, persist) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'as-msg ' + (type === 'ok' ? 'as-msg-ok' : 'as-msg-err');
    el.innerHTML = html; el.style.display = 'block';
    if (!persist) setTimeout(() => el.style.display = 'none', 7000);
}

// ── Load status ───────────────────────────────────────────────────────────────
async function loadStatus() {
    const sec = document.getElementById('status-section');
    try {
        const d = await apiCall('GET', '/admin/settings');
        if (!d.success) {
            sec.innerHTML = `<div style="color:#f87171;font-size:13px">⚠ Could not load settings: ${d.error || 'unknown error'}</div>`;
            return;
        }
        const s = d.settings;
        const dot = (ok) => `<span class="as-dot ${ok ? 'as-dot-ok' : 'as-dot-err'}"></span>`;
        const badge = (ok) => ok
            ? '<span class="as-badge as-badge-ok">Configured</span>'
            : '<span class="as-badge as-badge-err">Not set</span>';
        const src = s.keySource === 'admin_panel' ? ' <span class="as-badge as-badge-ok">saved in panel</span>'
                  : s.keySource === 'environment'  ? ' <span class="as-badge as-badge-warn">from env var</span>'
                  : '';

        sec.innerHTML = `
            <div class="as-status-row">
                ${dot(true)}
                <span class="as-status-label">API Server</span>
                <span class="as-status-val">Connected</span>
            </div>
            <div class="as-status-row">
                ${dot(s.hasHerokuApiKey)}
                <span class="as-status-label">Platform API Key ${src}</span>
                <span class="as-status-val">${s.hasHerokuApiKey ? (s.herokuApiKey || 'set via env') : 'Not configured'}</span>
            </div>
            <div class="as-status-row">
                ${dot(s.hasHerokuTeam)}
                <span class="as-status-label">Team Name</span>
                <span class="as-status-val">${s.herokuTeam || (s.hasHerokuTeam ? 'set via env' : 'personal account')}</span>
            </div>
            <div class="as-status-row">
                ${dot(s.hasApiSecretKey)}
                <span class="as-status-label">API Secret Key</span>
                <span class="as-status-val">${s.hasApiSecretKey ? (s.apiSecretKey || 'set via env') : 'Not configured'}</span>
            </div>
        `;
    } catch(e) {
        sec.innerHTML = `<div style="color:#f87171;font-size:13px">⚠ Cannot connect to API server: ${e.message}</div>`;
    }
}

// ── Test connection ───────────────────────────────────────────────────────────
async function testConnection() {
    const btn = document.getElementById('btn-test');
    btn.disabled = true; btn.innerHTML = '<span class="as-spin"></span> Testing…';
    try {
        const d = await apiCall('POST', '/admin/test-connection');
        if (d.success) {
            showMsg('test-msg', 'ok', `✓ Connection successful! Account: <strong>${d.email || d.name || 'verified'}</strong>`);
        } else {
            showMsg('test-msg', 'err', '❌ Connection failed: ' + (d.error || 'Unknown error.'));
        }
    } catch(e) {
        showMsg('test-msg', 'err', '❌ Could not reach API: ' + e.message);
    }
    btn.disabled = false; btn.innerHTML = 'Test Connection';
}

// ── Save platform settings ────────────────────────────────────────────────────
async function savePlatformSettings() {
    const key  = document.getElementById('inp-api-key').value.trim();
    const team = document.getElementById('inp-team').value.trim();

    if (!key && !team) {
        showMsg('platform-msg', 'err', '❌ Enter at least an API key or team name to save.');
        return;
    }

    const btn = document.getElementById('btn-save-platform');
    btn.disabled = true; btn.innerHTML = '<span class="as-spin"></span> Saving…';
    const payload = {};
    if (key)  payload.herokuApiKey = key;
    if (team !== undefined) payload.herokuTeam = team;

    try {
        const d = await apiCall('PATCH', '/admin/settings', payload);
        if (d.success) {
            showMsg('platform-msg', 'ok', '✓ Platform settings saved. Clearing fields for security.');
            document.getElementById('inp-api-key').value = '';
            document.getElementById('inp-team').value = '';
            await loadStatus();
        } else {
            showMsg('platform-msg', 'err', '❌ ' + (d.error || 'Failed to save.'));
        }
    } catch(e) {
        showMsg('platform-msg', 'err', '❌ Could not reach API: ' + e.message);
    }
    btn.disabled = false; btn.innerHTML = 'Save Platform Settings';
}

// ── Save secret key ───────────────────────────────────────────────────────────
async function saveSecretKey() {
    const key = document.getElementById('inp-secret').value.trim();
    if (!key) { showMsg('secret-msg', 'err', '❌ Enter a new secret key.'); return; }
    if (key.length < 8) { showMsg('secret-msg', 'err', '❌ Secret key must be at least 8 characters.'); return; }

    const btn = document.getElementById('btn-save-secret');
    btn.disabled = true; btn.innerHTML = '<span class="as-spin"></span> Saving…';
    try {
        const d = await apiCall('PATCH', '/admin/settings', { apiSecretKey: key });
        if (d.success) {
            showMsg('secret-msg', 'ok', '✓ Secret key updated. Remember to update $API_SECRET in deploy.php and manage.php too!', true);
            document.getElementById('inp-secret').value = '';
            await loadStatus();
        } else {
            showMsg('secret-msg', 'err', '❌ ' + (d.error || 'Failed to save.'));
        }
    } catch(e) {
        showMsg('secret-msg', 'err', '❌ Could not reach API: ' + e.message);
    }
    btn.disabled = false; btn.innerHTML = 'Update Secret Key';
}

window.addEventListener('DOMContentLoaded', loadStatus);
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
