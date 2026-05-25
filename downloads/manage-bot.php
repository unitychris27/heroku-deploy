<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';

// ── AJAX session guard ────────────────────────────────────────────────────────
// Detect AJAX calls BEFORE requireLogin() so we return JSON instead of an
// HTML redirect when the session has expired (which causes "Network error" in JS).
$isAjaxCall = isset($_GET['action'])
    || ($_SERVER['REQUEST_METHOD'] === 'POST'
        && (isset($_POST['action']) || isset($_GET['action'])));

if ($isAjaxCall && empty($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Session expired. Please refresh the page and log in again.']);
    exit;
}

requireLogin();

$API_BASE_URL = "https://panel.xcasper.site/api";
$API_SECRET   = "Digitex2025";

$pageTitle  = 'Manage Bot';
$activePage = 'deploy-bot';
$userId     = $_SESSION['user_id'];
$db         = getDB();

// ── API helper ────────────────────────────────────────────────────────────────
function apiRequest(string $method, string $path, ?array $body, string $baseUrl, string $secret): array {
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            "X-API-Key: $secret",
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err)  return ['ok' => false, 'code' => 0,     'data' => null, 'error' => "Connection failed: $err"];
    if (!$raw) return ['ok' => false, 'code' => $code, 'data' => null, 'error' => "Empty response (HTTP $code)"];
    $data = json_decode($raw, true);
    if ($data === null) return ['ok' => false, 'code' => $code, 'data' => null, 'error' => "Invalid JSON from API (HTTP $code)"];
    return ['ok' => ($code >= 200 && $code < 300), 'code' => $code, 'data' => $data, 'error' => $data['error'] ?? null];
}

function sendJson(array $data): void {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$appName = trim($_GET['app'] ?? $_POST['app'] ?? '');
if (!$appName || !preg_match('/^[a-z0-9][a-z0-9\-]{0,28}[a-z0-9]$|^[a-z0-9]{1,2}$/', $appName)) {
    if ($isAjaxCall) sendJson(['success' => false, 'error' => 'Invalid or missing app name.']);
    header('Location: deploy.php');
    exit;
}

// ── AJAX: get config vars ─────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_config') {
    $r = apiRequest('GET', "/external/config/$appName", null, $API_BASE_URL, $API_SECRET);
    sendJson($r['ok'] ? $r['data'] : ['success' => false, 'error' => $r['error']]);
}

// ── AJAX: save config vars (JSON body, action via query string) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save_config') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $vars = $body['configVars'] ?? null;
    if (!is_array($vars)) sendJson(['success' => false, 'error' => 'Invalid config vars payload.']);
    $r = apiRequest('PATCH', "/external/config/$appName", ['configVars' => $vars], $API_BASE_URL, $API_SECRET);
    sendJson($r['ok'] ? $r['data'] : ['success' => false, 'error' => $r['error']]);
}

// ── AJAX: restart bot ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restart') {
    $r = apiRequest('POST', "/external/restart/$appName", (object)[], $API_BASE_URL, $API_SECRET);
    sendJson($r['ok'] ? $r['data'] : ['success' => false, 'error' => $r['error']]);
}

// ── AJAX: get logs URL ────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    $r = apiRequest('GET', "/external/logs/$appName", null, $API_BASE_URL, $API_SECRET);
    sendJson($r['ok'] ? $r['data'] : ['success' => false, 'error' => $r['error']]);
}

// ── Load deployment record ────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM deployments WHERE app_name=? AND user_id=? ORDER BY deployed_at DESC LIMIT 1");
$stmt->execute([$appName, $userId]);
$deployment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deployment) {
    $stmt2 = $db->prepare("SELECT * FROM deployments WHERE app_name=? ORDER BY deployed_at DESC LIMIT 1");
    $stmt2->execute([$appName]);
    $deployment = $stmt2->fetch(PDO::FETCH_ASSOC);
}

if (!$deployment) {
    $deployment = [
        'app_name'    => $appName,
        'bot_type'    => '',
        'deployed_at' => date('Y-m-d H:i:s'),
        'status'      => 'unknown',
    ];
}

include_once __DIR__ . '/includes/header.php';
?>
<style>
.mw{max-width:860px;margin:0 auto;}
.card{background:rgba(30,41,59,.55);border:1px solid rgba(255,255,255,.07);border-radius:22px;padding:28px;margin-bottom:22px;}

/* tabs */
.tabs{display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:26px;}
.tab-btn{padding:10px 22px;background:none;border:none;border-bottom:2px solid transparent;color:#475569;cursor:pointer;font-size:13px;font-weight:600;letter-spacing:.04em;transition:all .2s;margin-bottom:-1px;}
.tab-btn:hover{color:#94a3b8;}
.tab-btn.active{color:#00d2ff;border-bottom-color:#00d2ff;}
.tab-panel{display:none;}
.tab-panel.active{display:block;}

/* config table */
.cfg-table{width:100%;border-collapse:collapse;}
.cfg-table th{text-align:left;padding:8px 10px;color:#334155;font-size:10px;text-transform:uppercase;letter-spacing:.07em;border-bottom:1px solid rgba(255,255,255,.05);}
.cfg-table td{padding:6px 4px;border-bottom:1px solid rgba(255,255,255,.03);vertical-align:middle;}
.cfg-table tr:last-child td{border-bottom:0;}
.cfg-key{font-family:monospace;font-size:12px;color:#94a3b8;padding:8px 10px;background:rgba(10,18,36,.6);border:1px solid rgba(255,255,255,.06);border-radius:6px;width:100%;box-sizing:border-box;}
.cfg-val{font-family:monospace;font-size:12px;color:#e2e8f0;padding:8px 10px;background:rgba(10,18,36,.8);border:1px solid rgba(255,255,255,.09);border-radius:6px;width:100%;box-sizing:border-box;outline:none;transition:border .2s;}
.cfg-val:focus{border-color:rgba(0,210,255,.5);}
.del-row{background:none;border:none;color:#475569;cursor:pointer;font-size:16px;padding:4px 8px;border-radius:4px;transition:color .2s;}
.del-row:hover{color:#ef4444;}

/* buttons */
.btn{padding:9px 20px;border:0;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;letter-spacing:.05em;transition:opacity .2s;}
.btn:hover{opacity:.82;} .btn:disabled{opacity:.35;cursor:not-allowed;}
.btn-primary{background:linear-gradient(135deg,#00d2ff,#0891b2);color:#000;}
.btn-add{background:rgba(0,210,255,.1);border:1px solid rgba(0,210,255,.3);color:#00d2ff;font-size:11px;padding:6px 14px;}
.btn-danger{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;}
.btn-warn{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24;}

/* messages */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-top:14px;line-height:1.6;}
.alert-ok {background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.3);color:#4ade80;}
.alert-err{background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.3);color:#f87171;}

/* logs */
#log-frame{width:100%;height:460px;border:1px solid rgba(255,255,255,.07);border-radius:10px;background:#000;}
.log-hint{color:#475569;font-size:11px;margin-bottom:10px;}

/* spinner */
.spin{display:inline-block;width:14px;height:14px;border:2px solid rgba(0,210,255,.2);border-top-color:#00d2ff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px;}
@keyframes spin{to{transform:rotate(360deg)}}
</style>

<div class="app-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex:1;min-width:0;">
<?php include __DIR__ . '/includes/topbar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">Manage Bot</h1>
        <div class="page-breadcrumb">Dashboard &rarr; <a href="deploy.php" style="color:#00d2ff;text-decoration:none">Deploy Bot</a> &rarr; <?= htmlspecialchars($appName) ?></div>
    </div>

    <div class="mw">

        <!-- App info bar -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;background:rgba(0,210,255,.04);border:1px solid rgba(0,210,255,.12);border-radius:14px;padding:14px 20px;margin-bottom:22px;">
            <div>
                <div style="font-family:monospace;font-size:15px;font-weight:700;color:#e2e8f0"><?= htmlspecialchars($appName) ?></div>
                <div style="font-size:11px;color:#475569;margin-top:3px"><?= htmlspecialchars(ucfirst($deployment['bot_type'] ?? '')) ?> &nbsp;&middot;&nbsp; Deployed <?= date('d M Y', strtotime($deployment['deployed_at'])) ?></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="https://<?= htmlspecialchars($appName) ?>.herokuapp.com" target="_blank" class="btn btn-add" style="text-decoration:none">Open App</a>
                <a href="deploy.php" class="btn btn-add" style="text-decoration:none">Back</a>
            </div>
        </div>

        <div class="card">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('config',this)">Config Vars</button>
                <button class="tab-btn"        onclick="switchTab('restart',this)">Restart</button>
                <button class="tab-btn"        onclick="switchTab('logs',this)">Logs</button>
            </div>

            <!-- ── CONFIG TAB ── -->
            <div id="tab-config" class="tab-panel active">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
                    <span style="color:#94a3b8;font-size:13px">Edit environment variables and apply to your deployment.</span>
                    <button class="btn btn-add" onclick="addRow()">+ Add Variable</button>
                </div>
                <div id="cfg-loading" style="text-align:center;padding:30px;color:#475569"><span class="spin"></span> Loading config vars...</div>
                <div id="cfg-wrap" style="display:none">
                    <div style="overflow-x:auto">
                    <table class="cfg-table" id="cfg-table">
                        <thead><tr><th style="width:35%">KEY</th><th>VALUE</th><th style="width:32px"></th></tr></thead>
                        <tbody id="cfg-body"></tbody>
                    </table>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
                        <button class="btn btn-primary" id="btn-save" onclick="saveConfig()">Save and Apply</button>
                    </div>
                    <div id="cfg-msg"></div>
                </div>
            </div>

            <!-- ── RESTART TAB ── -->
            <div id="tab-restart" class="tab-panel">
                <div style="max-width:480px">
                    <p style="color:#94a3b8;font-size:13px;line-height:1.7;margin:0 0 20px">
                        Restarting stops all running processes for <strong style="color:#e2e8f0"><?= htmlspecialchars($appName) ?></strong> and starts them fresh. The bot will reconnect after a few seconds.
                    </p>
                    <button class="btn btn-warn" id="btn-restart" onclick="restartBot()" style="width:100%;padding:14px;font-size:14px">
                        Restart Bot
                    </button>
                    <div id="restart-msg"></div>
                </div>
            </div>

            <!-- ── LOGS TAB ── -->
            <div id="tab-logs" class="tab-panel">
                <p class="log-hint">Real-time log stream &mdash; tail of the last 200 lines. The session link expires after approximately 10 minutes. Click Refresh to obtain a new one.</p>
                <div style="display:flex;gap:10px;margin-bottom:14px;align-items:center;flex-wrap:wrap">
                    <button class="btn btn-primary" onclick="loadLogs()">Load / Refresh Logs</button>
                    <span id="log-status" style="font-size:12px;color:#475569"></span>
                </div>
                <div id="log-container" style="display:none">
                    <iframe id="log-frame" src="" title="Bot Logs"></iframe>
                </div>
            </div>
        </div>

    </div>
</main>
</div>
</div>

<script>
const APP  = "<?= htmlspecialchars($appName, ENT_QUOTES) ?>";
const BASE = window.location.pathname;

// ── Shared fetch wrapper ───────────────────────────────────────────────────────
// Handles non-JSON responses (e.g. session-expired HTML redirect) gracefully.
async function apiFetch(url, options) {
    const r = await fetch(url, options);
    const text = await r.text();
    try {
        return JSON.parse(text);
    } catch (_) {
        // Got HTML instead of JSON — most likely a session expiry redirect
        if (r.redirected || r.status === 200) {
            return { success: false, error: 'Session expired. Please refresh the page and log in again.' };
        }
        return { success: false, error: 'Unexpected server response (HTTP ' + r.status + ').' };
    }
}

// ── Tabs ──────────────────────────────────────────────────────────────────────
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
    if (name === 'config' && !document.getElementById('cfg-body').children.length) loadConfig();
}

// ── Config ────────────────────────────────────────────────────────────────────
async function loadConfig() {
    document.getElementById('cfg-loading').style.display = 'block';
    document.getElementById('cfg-wrap').style.display    = 'none';
    document.getElementById('cfg-msg').style.display     = 'none';
    try {
        const d = await apiFetch(`${BASE}?app=${encodeURIComponent(APP)}&action=get_config`);
        document.getElementById('cfg-loading').style.display = 'none';
        if (!d.success) {
            cfgMsg('err', d.error || 'Failed to load config vars.');
            return;
        }
        const body = document.getElementById('cfg-body');
        body.innerHTML = '';
        const vars = d.configVars || {};
        Object.entries(vars).forEach(([k, v]) => addRow(k, v));
        document.getElementById('cfg-wrap').style.display = 'block';
    } catch (e) {
        document.getElementById('cfg-loading').style.display = 'none';
        cfgMsg('err', 'Could not reach the server: ' + e.message);
    }
}

function addRow(key, val) {
    key = key || ''; val = val || '';
    const body = document.getElementById('cfg-body');
    const tr   = document.createElement('tr');
    tr.innerHTML =
        '<td><input class="cfg-key" type="text" value="' + esc(key) + '" placeholder="VARIABLE_NAME"></td>' +
        '<td><input class="cfg-val" type="text" value="' + esc(val) + '" placeholder="value..."></td>' +
        '<td><button class="del-row" title="Remove" onclick="this.closest(\'tr\').remove()">&times;</button></td>';
    body.appendChild(tr);
    if (!key) tr.querySelector('.cfg-key').focus();
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

async function saveConfig() {
    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    const vars = {};
    document.querySelectorAll('#cfg-body tr').forEach(function(tr) {
        const inputs = tr.querySelectorAll('input');
        const k = inputs[0].value.trim();
        const v = inputs[1].value;
        if (k) vars[k] = v;
    });
    try {
        const d = await apiFetch(
            `${BASE}?app=${encodeURIComponent(APP)}&action=save_config`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ configVars: vars })
            }
        );
        if (d.success) {
            cfgMsg('ok', d.message || 'Config vars updated. Your bot is restarting.');
        } else {
            cfgMsg('err', d.error || 'Failed to save config.');
        }
    } catch (e) {
        cfgMsg('err', 'Could not reach the server: ' + e.message);
    }
    btn.disabled = false;
    btn.textContent = 'Save and Apply';
}

function cfgMsg(type, text) {
    const el = document.getElementById('cfg-msg');
    el.className = 'alert alert-' + (type === 'ok' ? 'ok' : 'err');
    el.textContent = text;
    el.style.display = 'block';
    setTimeout(function() { el.style.display = 'none'; }, 7000);
}

// ── Restart ───────────────────────────────────────────────────────────────────
async function restartBot() {
    if (!confirm('Restart all processes for "' + APP + '"?\n\nThe bot will reconnect in approximately 10-20 seconds.')) return;
    const btn = document.getElementById('btn-restart');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Restarting &mdash; please wait...';
    const fd = new FormData();
    fd.set('action', 'restart');
    fd.set('app', APP);
    try {
        const d = await apiFetch(BASE + '?app=' + encodeURIComponent(APP), { method: 'POST', body: fd });
        const el = document.getElementById('restart-msg');
        el.className = 'alert alert-' + (d.success ? 'ok' : 'err');
        el.textContent = d.success ? (d.message || 'Bot restarted successfully.') : (d.error || 'Restart failed.');
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 7000);
    } catch (e) {
        const el = document.getElementById('restart-msg');
        el.className = 'alert alert-err';
        el.textContent = 'Could not reach the server: ' + e.message;
        el.style.display = 'block';
    }
    btn.disabled = false;
    btn.innerHTML = 'Restart Bot';
}

// ── Logs ──────────────────────────────────────────────────────────────────────
async function loadLogs() {
    const status = document.getElementById('log-status');
    status.textContent = 'Fetching log URL...';
    try {
        const d = await apiFetch(`${BASE}?app=${encodeURIComponent(APP)}&action=get_logs`);
        if (!d.success || !d.logsUrl) {
            status.textContent = 'Error: ' + (d.error || 'Could not get logs URL.');
            return;
        }
        document.getElementById('log-container').style.display = 'block';
        document.getElementById('log-frame').src = d.logsUrl;
        status.textContent = 'Log stream loaded. Session expires in approximately 10 minutes.';
    } catch (e) {
        status.textContent = 'Could not reach the server: ' + e.message;
    }
}

// Auto-load config on page open
window.addEventListener('DOMContentLoaded', loadConfig);
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
