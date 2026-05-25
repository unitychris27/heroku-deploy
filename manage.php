<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
requireLogin();

$API_BASE_URL = "https://panel.xcasper.site/api";
$API_SECRET   = "Digitex2025";

$userId = $_SESSION['user_id'];
session_write_close();

// ── API helper (server-side, for any PHP-gated ops) ───────────────────────────
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
    if ($err)  return ['ok'=>false,'code'=>0,    'data'=>null,'error'=>"Connection failed: $err"];
    if (!$raw) return ['ok'=>false,'code'=>$code,'data'=>null,'error'=>"Empty response (HTTP $code)"];
    $data = json_decode($raw, true);
    if ($data === null) return ['ok'=>false,'code'=>$code,'data'=>null,'error'=>"Invalid JSON (HTTP $code)"];
    return ['ok'=>($code>=200&&$code<300),'code'=>$code,'data'=>$data,'error'=>$data['error']??null];
}

function sendJson(array $data): void {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Route: no ?app= → show the bot LIST page
// ─────────────────────────────────────────────────────────────────────────────
$appName = trim($_GET['app'] ?? $_POST['app'] ?? '');
$isDetail = $appName && preg_match('/^[a-z0-9][a-z0-9\-]{0,28}[a-z0-9]$|^[a-z0-9]{1,2}$/', $appName);

if (!$isDetail) {
    // Try to load user's bots from DB
    $bots    = [];
    $dbError = null;
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT app_name, bot_type, MAX(deployed_at) as deployed_at
             FROM deployments
             WHERE user_id = ?
             GROUP BY app_name
             ORDER BY MAX(deployed_at) DESC"
        );
        $stmt->execute([$userId]);
        $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }

    $pageTitle  = 'My Bots';
    $activePage = 'deploy-bot';
    include_once __DIR__ . '/includes/header.php';
?>
<style>
.mw{max-width:900px;margin:0 auto;}
.bot-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-top:20px;}
.bot-card{background:rgba(30,41,59,.6);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:22px 22px 18px;display:flex;flex-direction:column;gap:12px;transition:border-color .2s;}
.bot-card:hover{border-color:rgba(0,210,255,.3);}
.bot-name{font-family:monospace;font-size:15px;font-weight:700;color:#e2e8f0;word-break:break-all;}
.bot-meta{font-size:11px;color:#475569;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-ok     {background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3); color:#4ade80;}
.badge-err    {background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); color:#f87171;}
.badge-warn   {background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24;}
.badge-pending{background:rgba(0,210,255,.08);border:1px solid rgba(0,210,255,.2); color:#67e8f9;}
.btn{padding:8px 18px;border:0;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;letter-spacing:.05em;transition:opacity .2s;text-decoration:none;display:inline-block;}
.btn:hover{opacity:.82;}
.btn-primary{background:linear-gradient(135deg,#00d2ff,#0891b2);color:#000;}
.btn-add{background:rgba(0,210,255,.1);border:1px solid rgba(0,210,255,.3);color:#00d2ff;}
.empty-state{text-align:center;padding:60px 20px;color:#475569;}
.empty-state h2{color:#94a3b8;font-size:18px;margin-bottom:10px;}
.spin{display:inline-block;width:10px;height:10px;border:2px solid rgba(0,210,255,.2);border-top-color:#00d2ff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;}
@keyframes spin{to{transform:rotate(360deg)}}
</style>

<div class="app-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex:1;min-width:0;">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">My Bots</h1>
        <div class="page-breadcrumb">Dashboard → My Bots</div>
    </div>

    <div class="mw">

        <?php if ($dbError): ?>
        <div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:13px;">
            Could not load bot list from the database. <a href="deploy.php" style="color:#f87171">Go to Deploy page</a> to manage bots from there.
        </div>
        <?php endif; ?>

        <?php if (empty($bots)): ?>
        <div class="empty-state">
            <h2>No bots deployed yet</h2>
            <p style="margin-bottom:22px">Once you deploy a bot it will appear here.</p>
            <a href="deploy.php" class="btn btn-primary">Deploy Your First Bot</a>
        </div>
        <?php else: ?>

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
            <span style="color:#475569;font-size:13px"><?= count($bots) ?> bot<?= count($bots)!==1?'s':'' ?> — click a card to manage</span>
            <a href="deploy.php" class="btn btn-add">+ Deploy New Bot</a>
        </div>

        <div class="bot-grid">
        <?php foreach ($bots as $bot):
            $name = htmlspecialchars($bot['app_name']);
            $type = htmlspecialchars(ucfirst($bot['bot_type'] ?? 'Bot'));
            $date = $bot['deployed_at'] ? date('d M Y', strtotime($bot['deployed_at'])) : '—';
        ?>
            <div class="bot-card">
                <div>
                    <div class="bot-name"><?= $name ?></div>
                    <div class="bot-meta" style="margin-top:4px"><?= $type ?> &nbsp;·&nbsp; <?= $date ?></div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <span id="status-<?= $name ?>" class="badge badge-pending"><span class="spin"></span> checking…</span>
                    <a href="manage.php?app=<?= urlencode($bot['app_name']) ?>" class="btn btn-primary" style="padding:7px 16px;font-size:12px">Manage →</a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>
</div>
</div>

<script>
const API_URL  = "<?= htmlspecialchars(rtrim($API_BASE_URL,'/'), ENT_QUOTES) ?>";
const API_KEY  = "<?= htmlspecialchars($API_SECRET, ENT_QUOTES) ?>";
const BOT_NAMES = <?= json_encode(array_column($bots, 'app_name')) ?>;

async function apiCall(method, path, body) {
    const opts = { method, headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json', 'Accept': 'application/json' } };
    if (body !== undefined) opts.body = JSON.stringify(body);
    const r    = await fetch(API_URL + path, opts);
    const text = await r.text();
    try { return JSON.parse(text); } catch(_) { return { success: false }; }
}

async function loadStatus(name) {
    const el = document.getElementById('status-' + name);
    if (!el) return;
    try {
        const d = await apiCall('GET', `/external/check/${encodeURIComponent(name)}`);
        if (!d.success || !d.exists) {
            el.className = 'badge badge-warn'; el.textContent = 'Not deployed'; return;
        }
        if (d.status === 'completed') {
            el.className = 'badge badge-ok'; el.textContent = '● Running';
        } else if (d.status === 'failed') {
            el.className = 'badge badge-err'; el.textContent = '● Build Failed';
        } else {
            el.className = 'badge badge-pending'; el.textContent = '○ Deploying…';
        }
    } catch(_) {
        el.className = 'badge badge-warn'; el.textContent = 'Unknown';
    }
}

window.addEventListener('DOMContentLoaded', () => {
    BOT_NAMES.forEach(name => loadStatus(name));
});
</script>
<?php
    include_once __DIR__ . '/includes/footer.php';
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// DETAIL PAGE — ?app=<appName> is set and valid
// ─────────────────────────────────────────────────────────────────────────────

// ── AJAX: connectivity test ───────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    $r = apiRequest('GET', '/external/bots', null, $API_BASE_URL, $API_SECRET);
    sendJson([
        'php_ok'     => true,
        'url_used'   => rtrim($API_BASE_URL, '/') . '/external/bots',
        'http_code'  => $r['code'],
        'api_ok'     => $r['ok'],
        'error'      => $r['error'],
        'secret_set' => strlen($API_SECRET) > 5,
    ]);
}

// ── AJAX: get config vars ─────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_config') {
    $r = apiRequest('GET', "/external/config/$appName", null, $API_BASE_URL, $API_SECRET);
    sendJson($r['ok'] ? $r['data'] : ['success'=>false,'error'=>$r['error']]);
}

// ── AJAX: save config vars ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save_config') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $vars = $body['configVars'] ?? null;
    if (!is_array($vars)) sendJson(['success'=>false,'error'=>'Invalid config vars payload']);
    $r = apiRequest('PATCH', "/external/config/$appName", ['configVars'=>$vars], $API_BASE_URL, $API_SECRET);
    sendJson($r['ok'] ? $r['data'] : ['success'=>false,'error'=>$r['error']]);
}

// ── AJAX: restart bot ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? ($_POST['action'] ?? '')) === 'restart') {
    $r = apiRequest('POST', "/external/restart/$appName", [], $API_BASE_URL, $API_SECRET);
    sendJson($r['ok'] ? $r['data'] : ['success'=>false,'error'=>$r['error']]);
}

// ── AJAX: get log text (PHP fetches it so works regardless of API version) ────
if (isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    $r = apiRequest('GET', "/external/logs/$appName", null, $API_BASE_URL, $API_SECRET);
    if (!$r['ok']) { sendJson(['success'=>false,'error'=>$r['error']]); }
    $data = $r['data'];
    // New API returns logText directly; old API returns logsUrl — fetch text via cURL
    if (!empty($data['logText'])) {
        sendJson(['success'=>true,'logText'=>$data['logText']]);
    } elseif (!empty($data['logsUrl'])) {
        $ch2 = curl_init($data['logsUrl']);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,   CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $txt = curl_exec($ch2); curl_close($ch2);
        sendJson(['success'=>true,'logText'=> $txt ?: '']);
    } else {
        sendJson(['success'=>false,'error'=>'Could not retrieve log session.']);
    }
}

// ── Load deployment record ────────────────────────────────────────────────────
// $db initialised AFTER all AJAX handlers so DB failure never kills AJAX requests
$deployment = null;
try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM deployments WHERE app_name=? AND user_id=? ORDER BY deployed_at DESC LIMIT 1");
    $stmt->execute([$appName, $userId]);
    $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$deployment) {
        $stmt2 = $db->prepare("SELECT * FROM deployments WHERE app_name=? ORDER BY deployed_at DESC LIMIT 1");
        $stmt2->execute([$appName]);
        $deployment = $stmt2->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) { /* DB error — page still works without it */ }

if (!$deployment) {
    $deployment = [
        'app_name'    => $appName,
        'bot_type'    => '',
        'deployed_at' => date('Y-m-d H:i:s'),
        'status'      => 'unknown',
    ];
}

$pageTitle  = 'Manage Bot';
$activePage = 'deploy-bot';
include_once __DIR__ . '/includes/header.php';
?>
<style>
/* All classes are mng-prefixed to avoid conflicts with site theme/Bootstrap */
.mng-mw{max-width:860px;margin:0 auto;}
.mng-card{background:rgba(30,41,59,.55);border:1px solid rgba(255,255,255,.07);border-radius:22px;padding:28px;margin-bottom:22px;}
.mng-tabs{display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:26px;flex-wrap:wrap;}
.mng-tab-btn{padding:10px 22px;background:none;border:none;border-bottom:3px solid transparent;color:#475569;cursor:pointer;font-size:13px;font-weight:600;letter-spacing:.04em;transition:color .2s,border-color .2s;margin-bottom:-1px;outline:none;}
.mng-tab-btn:hover{color:#94a3b8;}
.mng-tab-btn.mng-active{color:#00d2ff;border-bottom-color:#00d2ff;}
.mng-tab-btn.mng-danger{color:#f87171;}
.mng-tab-btn.mng-danger.mng-active{border-bottom-color:#ef4444;}
/* panels: visibility controlled via JS inline style — no CSS needed */
.mng-cfg-table{width:100%;border-collapse:collapse;}
.mng-cfg-table th{text-align:left;padding:8px 10px;color:#334155;font-size:10px;text-transform:uppercase;letter-spacing:.07em;border-bottom:1px solid rgba(255,255,255,.05);}
.mng-cfg-table td{padding:6px 4px;border-bottom:1px solid rgba(255,255,255,.03);vertical-align:middle;}
.mng-cfg-table tr:last-child td{border-bottom:0;}
.mng-cfg-key{font-family:monospace;font-size:12px;color:#94a3b8;padding:8px 10px;background:rgba(10,18,36,.6);border:1px solid rgba(255,255,255,.06);border-radius:6px;width:100%;box-sizing:border-box;}
.mng-cfg-val{font-family:monospace;font-size:12px;color:#e2e8f0;padding:8px 10px;background:rgba(10,18,36,.8);border:1px solid rgba(255,255,255,.09);border-radius:6px;width:100%;box-sizing:border-box;outline:none;transition:border .2s;}
.mng-cfg-val:focus{border-color:rgba(0,210,255,.5);}
.mng-del-row{background:none;border:none;color:#475569;cursor:pointer;font-size:16px;padding:4px 8px;border-radius:4px;transition:color .2s;}
.mng-del-row:hover{color:#ef4444;}
.mng-btn{padding:9px 20px;border:0;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;letter-spacing:.05em;transition:opacity .2s;line-height:1.4;}
.mng-btn:hover{opacity:.82;} .mng-btn:disabled{opacity:.35;cursor:not-allowed;}
.mng-btn-blue{background:linear-gradient(135deg,#00d2ff,#0891b2);color:#000;}
.mng-btn-ghost{background:rgba(0,210,255,.1);border:1px solid rgba(0,210,255,.3);color:#00d2ff;font-size:11px;padding:6px 14px;text-decoration:none;display:inline-block;}
.mng-btn-red{background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.5);color:#f87171;}
.mng-btn-amber{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24;}
.mng-msg{padding:12px 16px;border-radius:10px;font-size:13px;margin-top:14px;line-height:1.6;}
.mng-msg-ok {background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.3);color:#4ade80;}
.mng-msg-err{background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.3);color:#f87171;}
#mng-log-pre{width:100%;min-height:400px;max-height:580px;overflow-y:auto;border:1px solid rgba(255,255,255,.12);border-radius:10px;background:#0d1117;color:#c9d1d9;font-family:'Courier New',Courier,monospace;font-size:12px;line-height:1.55;padding:14px 16px;white-space:pre-wrap;word-break:break-all;box-sizing:border-box;}
.mng-del-zone{border:1px solid rgba(239,68,68,.25);border-radius:14px;padding:24px;background:rgba(239,68,68,.04);}
.mng-del-input{font-family:monospace;font-size:13px;color:#e2e8f0;padding:10px 14px;background:rgba(10,18,36,.8);border:1px solid rgba(239,68,68,.3);border-radius:8px;width:100%;max-width:360px;box-sizing:border-box;outline:none;display:block;margin:12px 0;}
.mng-del-input:focus{border-color:rgba(239,68,68,.7);}
.mng-spin{display:inline-block;width:13px;height:13px;border:2px solid rgba(0,210,255,.2);border-top-color:#00d2ff;border-radius:50%;animation:mngspin .7s linear infinite;vertical-align:middle;margin-right:5px;}
@keyframes mngspin{to{transform:rotate(360deg)}}
</style>

<div class="app-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex:1;min-width:0;">
<?php include __DIR__ . '/includes/topbar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">Manage Bot</h1>
        <div class="page-breadcrumb">Dashboard → <a href="manage.php" style="color:#00d2ff;text-decoration:none">My Bots</a> → <?= htmlspecialchars($appName) ?></div>
    </div>

    <div class="mng-mw">

        <!-- App info bar -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;background:rgba(0,210,255,.04);border:1px solid rgba(0,210,255,.12);border-radius:14px;padding:14px 20px;margin-bottom:22px;">
            <div>
                <div style="font-family:monospace;font-size:15px;font-weight:700;color:#e2e8f0"><?= htmlspecialchars($appName) ?></div>
                <div style="font-size:11px;color:#475569;margin-top:3px"><?= htmlspecialchars(ucfirst($deployment['bot_type'] ?? '')) ?> &nbsp;·&nbsp; Deployed <?= date('d M Y', strtotime($deployment['deployed_at'])) ?></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="manage.php" class="mng-btn mng-btn-ghost">← All Bots</a>
            </div>
        </div>

        <div class="mng-card">
            <!-- Tab bar -->
            <div class="mng-tabs">
                <button class="mng-tab-btn mng-active" onclick="mngTab('config',this)">Config Vars</button>
                <button class="mng-tab-btn"            onclick="mngTab('restart',this)">Restart</button>
                <button class="mng-tab-btn"            onclick="mngTab('logs',this)">Logs</button>
                <button class="mng-tab-btn mng-danger" onclick="mngTab('delete',this)">Delete</button>
            </div>

            <!-- CONFIG PANEL -->
            <div id="mng-pane-config" style="display:block">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
                    <span style="color:#94a3b8;font-size:13px">Edit environment variables and apply to your deployment.</span>
                    <button class="mng-btn mng-btn-ghost" onclick="addRow()">+ Add Variable</button>
                </div>
                <div id="cfg-loading" style="text-align:center;padding:30px;color:#475569"><span class="mng-spin"></span> Loading config vars...</div>
                <div id="cfg-wrap" style="display:none">
                    <div style="overflow-x:auto">
                    <table class="mng-cfg-table" id="cfg-table">
                        <thead><tr><th style="width:35%">KEY</th><th>VALUE</th><th style="width:32px"></th></tr></thead>
                        <tbody id="cfg-body"></tbody>
                    </table>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
                        <button class="mng-btn mng-btn-blue" id="btn-save" onclick="saveConfig()">Save and Apply</button>
                    </div>
                    <div id="cfg-msg" class="mng-msg" style="display:none"></div>
                </div>
            </div>

            <!-- RESTART PANEL -->
            <div id="mng-pane-restart" style="display:none">
                <div style="max-width:480px">
                    <p style="color:#94a3b8;font-size:13px;line-height:1.7;margin:0 0 20px">
                        Restarting stops all running processes for <strong style="color:#e2e8f0"><?= htmlspecialchars($appName) ?></strong> and starts them fresh. The bot will reconnect after a few seconds.
                    </p>
                    <button class="mng-btn mng-btn-amber" id="btn-restart" onclick="restartBot()" style="width:100%;padding:14px;font-size:14px">
                        Restart Bot
                    </button>
                    <div id="restart-msg" class="mng-msg" style="display:none"></div>
                </div>
            </div>

            <!-- LOGS PANEL -->
            <div id="mng-pane-logs" style="display:none">
                <p style="color:#475569;font-size:11px;margin:0 0 12px">Last 200 lines of bot output. Click Refresh to reload.</p>
                <div style="display:flex;gap:10px;margin-bottom:14px;align-items:center;flex-wrap:wrap">
                    <button class="mng-btn mng-btn-blue" onclick="loadLogs()">Load / Refresh Logs</button>
                    <span id="log-status" style="font-size:12px;color:#475569"></span>
                </div>
                <div id="log-container" style="display:none">
                    <pre id="mng-log-pre"></pre>
                </div>
            </div>

            <!-- DELETE PANEL -->
            <div id="mng-pane-delete" style="display:none">
                <div class="mng-del-zone" style="max-width:500px">
                    <h3 style="color:#f87171;margin:0 0 12px;font-size:16px;font-weight:700">⚠ Delete This Bot</h3>
                    <p style="color:#94a3b8;font-size:13px;line-height:1.7;margin:0">
                        This will <strong style="color:#f87171">permanently delete</strong> the app
                        <strong style="color:#e2e8f0;font-family:monospace"><?= htmlspecialchars($appName) ?></strong>
                        — all config vars, processes, and history will be gone.<br>
                        <strong style="color:#f87171">This cannot be undone.</strong>
                    </p>
                    <p style="color:#475569;font-size:12px;margin:16px 0 4px">Type the app name to confirm:</p>
                    <input id="del-confirm" class="mng-del-input" type="text" placeholder="<?= htmlspecialchars($appName) ?>" autocomplete="off">
                    <button class="mng-btn mng-btn-red" id="btn-delete" onclick="deleteBot()" style="padding:12px 28px;font-size:13px;margin-top:4px">
                        Delete Permanently
                    </button>
                    <div id="del-msg" class="mng-msg" style="display:none;margin-top:14px"></div>
                </div>
            </div>

        </div><!-- /.mng-card -->
    </div><!-- /.mng-mw -->
</main>
</div>
</div>

<script>
const APP     = "<?= htmlspecialchars($appName, ENT_QUOTES) ?>";
const API_URL = "<?= htmlspecialchars(rtrim($API_BASE_URL,'/'), ENT_QUOTES) ?>";
const API_KEY = "<?= htmlspecialchars($API_SECRET, ENT_QUOTES) ?>";

// ── Direct API helper (browser → Replit API, CORS enabled) ───────────────────
async function apiCall(method, path, body) {
    const opts = {
        method,
        headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json', 'Accept': 'application/json' }
    };
    if (body !== undefined) opts.body = JSON.stringify(body);
    const r    = await fetch(API_URL + path, opts);
    const text = await r.text();
    try { return JSON.parse(text); }
    catch(_) { return { success: false, error: `Unexpected response (HTTP ${r.status}): ${text.slice(0,120)}` }; }
}

// ── Tabs — uses inline style.display so no external CSS can interfere ─────────
const MNG_TABS = ['config','restart','logs','delete'];
function mngTab(name, btn) {
    MNG_TABS.forEach(t => {
        const p = document.getElementById('mng-pane-' + t);
        if (p) p.style.display = (t === name) ? 'block' : 'none';
    });
    document.querySelectorAll('.mng-tab-btn').forEach(b => b.classList.remove('mng-active'));
    btn.classList.add('mng-active');
    if (name === 'config' && !document.getElementById('cfg-body').children.length) loadConfig();
}

// ── Config Vars ───────────────────────────────────────────────────────────────
async function loadConfig() {
    document.getElementById('cfg-loading').style.display = 'block';
    document.getElementById('cfg-wrap').style.display    = 'none';
    try {
        const d = await apiCall('GET', `/external/config/${encodeURIComponent(APP)}`);
        if (!d.success) { cfgMsg('err', '❌ ' + (d.error || 'Could not load config.')); document.getElementById('cfg-loading').style.display = 'none'; return; }
        document.getElementById('cfg-body').innerHTML = '';
        Object.entries(d.configVars || {}).forEach(([k, v]) => addRow(k, v));
        document.getElementById('cfg-loading').style.display = 'none';
        document.getElementById('cfg-wrap').style.display    = 'block';
    } catch(e) {
        cfgMsg('err', '❌ Could not reach the API: ' + e.message);
        document.getElementById('cfg-loading').style.display = 'none';
    }
}

function addRow(key = '', val = '') {
    const body = document.getElementById('cfg-body');
    const tr   = document.createElement('tr');
    tr.innerHTML = `
        <td><input class="mng-cfg-key" type="text" value="${esc(key)}" placeholder="VARIABLE_NAME"></td>
        <td><input class="mng-cfg-val" type="text" value="${esc(val)}" placeholder="value..."></td>
        <td><button class="mng-del-row" title="Remove" onclick="this.closest('tr').remove()">×</button></td>`;
    body.appendChild(tr);
    if (!key) tr.querySelector('.mng-cfg-key').focus();
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function saveConfig() {
    const btn = document.getElementById('btn-save');
    btn.disabled = true; btn.textContent = 'Saving...';
    const vars = {};
    document.querySelectorAll('#cfg-body tr').forEach(tr => {
        const k = tr.querySelectorAll('input')[0].value.trim();
        const v = tr.querySelectorAll('input')[1].value;
        if (k) vars[k] = v;
    });
    try {
        const d = await apiCall('PATCH', `/external/config/${encodeURIComponent(APP)}`, { configVars: vars });
        showMsg('cfg-msg', d.success ? 'ok' : 'err', d.success ? (d.message || 'Config vars updated. Bot is restarting.') : ('❌ ' + (d.error || 'Failed to save.')));
    } catch(e) {
        showMsg('cfg-msg', 'err', '❌ Could not reach the API: ' + e.message);
    }
    btn.disabled = false; btn.textContent = 'Save and Apply';
}

function showMsg(id, type, html, timeout) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'mng-msg ' + (type === 'ok' ? 'mng-msg-ok' : 'mng-msg-err');
    el.innerHTML = html; el.style.display = 'block';
    if (timeout !== false) setTimeout(() => { el.style.display = 'none'; }, timeout || 7000);
}

// ── Restart ───────────────────────────────────────────────────────────────────
async function restartBot() {
    if (!confirm(`Restart all processes for "${APP}"?\n\nThe bot will reconnect in approximately 10-20 seconds.`)) return;
    const btn = document.getElementById('btn-restart');
    btn.disabled = true; btn.innerHTML = '<span class="mng-spin"></span> Restarting — please wait...';
    try {
        const d = await apiCall('POST', `/external/restart/${encodeURIComponent(APP)}`, {});
        showMsg('restart-msg', d.success ? 'ok' : 'err',
            d.success ? (d.message || 'Bot restarted successfully.') : ('❌ ' + (d.error || 'Restart failed.')));
    } catch(e) {
        showMsg('restart-msg', 'err', '❌ Could not reach the API: ' + e.message);
    }
    btn.disabled = false; btn.innerHTML = 'Restart Bot';
}

// ── Logs — calls PHP proxy so works with any API version ─────────────────────
async function loadLogs() {
    const statusEl  = document.getElementById('log-status');
    const container = document.getElementById('log-container');
    const pre       = document.getElementById('mng-log-pre');
    statusEl.textContent = 'Fetching logs…';
    container.style.display = 'none'; pre.textContent = '';
    try {
        const url = `?action=get_logs&app=${encodeURIComponent(APP)}`;
        const r   = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d   = await r.json();
        if (!d.success) {
            statusEl.textContent = 'Error: ' + (d.error || 'Could not fetch logs.');
            return;
        }
        const txt = (d.logText || '').trim();
        pre.textContent = txt || '(no output yet — your bot may not have produced any logs)';
        container.style.display = 'block';
        pre.scrollTop = pre.scrollHeight;
        statusEl.textContent = 'Last 200 lines — click Refresh to update.';
    } catch(e) {
        statusEl.textContent = 'Could not load logs: ' + e.message;
    }
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function deleteBot() {
    const input = document.getElementById('del-confirm').value.trim();
    const msgEl = document.getElementById('del-msg');
    const btn   = document.getElementById('btn-delete');

    if (input !== APP) {
        showMsg('del-msg', 'err', `❌ App name doesn't match. Type exactly: <code style="font-family:monospace">${esc(APP)}</code>`, false);
        return;
    }

    if (!confirm(`FINAL WARNING\n\nPermanently delete "${APP}"?\n\nAll data will be lost. This cannot be undone.`)) return;

    btn.disabled = true; btn.textContent = 'Deleting…';
    document.getElementById('del-msg').style.display = 'none';
    try {
        const d = await apiCall('DELETE', `/external/delete/${encodeURIComponent(APP)}`);
        if (d.success) {
            showMsg('del-msg', 'ok', `✓ ${d.message || 'Bot deleted.'} Redirecting…`, false);
            setTimeout(() => window.location.href = 'manage.php', 2500);
        } else {
            showMsg('del-msg', 'err', '❌ ' + (d.error || 'Delete failed.'), false);
            btn.disabled = false; btn.textContent = 'Delete Permanently';
        }
    } catch(e) {
        showMsg('del-msg', 'err', '❌ Could not reach the API: ' + e.message, false);
        btn.disabled = false; btn.textContent = 'Delete Permanently';
    }
}

window.addEventListener('DOMContentLoaded', loadConfig);
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
