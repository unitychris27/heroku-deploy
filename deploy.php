<?php
// Catch any PHP warnings/notices so they don't corrupt JSON responses
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
requireLogin();

// ╔═══════════════════════════════════════════════════════════════════════════╗
// ║  API_BASE_URL → your published Replit app domain                        ║
// ║  API_SECRET   → must match the API_SECRET_KEY secret in Replit Secrets  ║
$API_BASE_URL = "https://panel.xcasper.site/api";
$API_SECRET   = "Digitex2025";
// ╚═══════════════════════════════════════════════════════════════════════════╝

$DEPLOY_COST = 60;
$pageTitle   = 'Deploy Bot';
$activePage  = 'deploy-bot';
$userId      = $_SESSION['user_id'];
$balance     = currentBalance();
$db          = getDB();

// ── Bot definitions (mirrors the server — only used for the UI here) ──────────
$BOTS = [
  'cypherx' => [
    'name'   => 'CypherX',
    'fields' => [
      ['key'=>'sessionId',  'label'=>'Session ID',   'type'=>'textarea', 'required'=>true,  'placeholder'=>'Paste WhatsApp session string...'],
    ],
  ],
  'bwm' => [
    'name'   => 'BWM-XMD',
    'fields' => [
      ['key'=>'session',     'label'=>'Session ID',   'type'=>'textarea', 'required'=>true,  'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'ownerNumber', 'label'=>'Owner Number', 'type'=>'text',     'required'=>true,  'placeholder'=>'e.g. 254700000000 (with country code)'],
    ],
  ],
  'cypherxultra' => [
    'name'   => 'CypherX-Ultra',
    'fields' => [
      ['key'=>'masterPassword', 'label'=>'Master Password', 'type'=>'password', 'required'=>true,  'placeholder'=>'Secure password for the web dashboard'],
      ['key'=>'githubUsername', 'label'=>'GitHub Username', 'type'=>'text',     'required'=>false, 'placeholder'=>'Your GitHub username (fork owner)'],
    ],
  ],
  'kingmd' => [
    'name'   => 'King MD',
    'fields' => [
      ['key'=>'session', 'label'=>'Session ID',   'type'=>'textarea', 'required'=>true, 'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'dev',     'label'=>'Owner Number', 'type'=>'text',     'required'=>true, 'placeholder'=>'e.g. 254700000000'],
      ['key'=>'code',    'label'=>'Country Code', 'type'=>'text',     'required'=>true, 'placeholder'=>'e.g. 254 (Kenya), 234 (Nigeria)'],
    ],
  ],
  'anitav4' => [
    'name'   => 'Queen Anitah',
    'fields' => [
      ['key'=>'sessionId',       'label'=>'Session ID',           'type'=>'textarea', 'required'=>true,  'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'ownerNumber',     'label'=>'Owner Number',         'type'=>'text',     'required'=>true,  'placeholder'=>'e.g. 254700000000'],
      ['key'=>'prefix',          'label'=>'Command Prefix',       'type'=>'text',     'required'=>false, 'placeholder'=>'. or / or ! or #'],
      ['key'=>'public',          'label'=>'Bot Mode',             'type'=>'select',   'required'=>true,  'options'=>[['value'=>'public','label'=>'Public'],['value'=>'private','label'=>'Private']]],
      ['key'=>'autoViewStatus',  'label'=>'Auto View Status',     'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'antidelete',      'label'=>'Anti-Delete',          'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'autoStatusReact', 'label'=>'Auto React to Status', 'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'chatbot',         'label'=>'Enable Chat Bot',      'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
    ],
  ],
  'atassa' => [
    'name'   => 'Atassa MD',
    'fields' => [
      ['key'=>'sessionId',      'label'=>'Session ID',       'type'=>'textarea', 'required'=>true, 'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'mode',           'label'=>'Bot Mode',         'type'=>'select',   'required'=>true, 'options'=>[['value'=>'public','label'=>'Public'],['value'=>'private','label'=>'Private']]],
      ['key'=>'autoLikeStatus', 'label'=>'Auto Like Status', 'type'=>'select',   'required'=>true, 'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'autoReadStatus', 'label'=>'Auto Read Status', 'type'=>'select',   'required'=>true, 'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
    ],
  ],
];

// ── Ensure DB table exists ────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS deployments (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    user_id               INT          NOT NULL,
    build_id              VARCHAR(120) DEFAULT NULL,
    app_name              VARCHAR(100) DEFAULT NULL,
    bot_type              VARCHAR(50)  DEFAULT NULL,
    bot_name              VARCHAR(100) DEFAULT NULL,
    status                VARCHAR(60)  DEFAULT 'building',
    app_url               VARCHAR(255) DEFAULT NULL,
    logs_url              VARCHAR(255) DEFAULT NULL,
    error_message         TEXT         DEFAULT NULL,
    dsc_charged           INT          DEFAULT 0,
    deployed_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    scheduled_deletion_at TIMESTAMP    DEFAULT NULL,
    updated_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_build (build_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure columns added after the original table was created exist
foreach ([
    "ALTER TABLE deployments ADD COLUMN app_name              VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE deployments ADD COLUMN bot_type              VARCHAR(50)  DEFAULT NULL",
    "ALTER TABLE deployments ADD COLUMN bot_name              VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE deployments ADD COLUMN build_id              VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE deployments ADD COLUMN app_url               VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE deployments ADD COLUMN logs_url              VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE deployments ADD COLUMN error_message         TEXT         DEFAULT NULL",
    "ALTER TABLE deployments ADD COLUMN scheduled_deletion_at TIMESTAMP    DEFAULT NULL",
] as $sql) {
    try { $db->exec($sql); } catch (PDOException $e) { /* column already exists — ignore */ }
}

// ── API helper (calls our Node.js deployment server) ─────────────────────────
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
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)   return ['ok'=>false, 'code'=>0,     'data'=>null, 'error'=>"Connection failed: $err"];
    if (!$raw)  return ['ok'=>false, 'code'=>$code, 'data'=>null, 'error'=>"Empty response (HTTP $code)"];
    $data = json_decode($raw, true);
    if ($data === null) return ['ok'=>false, 'code'=>$code, 'data'=>null, 'error'=>"Invalid JSON from server (HTTP $code)"];
    return ['ok'=>($code >= 200 && $code < 300), 'code'=>$code, 'data'=>$data, 'error'=>$data['error'] ?? null];
}

function sendJson(array $data): void {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── AJAX: connectivity test ───────────────────────────────────────────────────
// Visit deploy.php?action=test to diagnose connection issues
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    global $API_BASE_URL, $API_SECRET;
    $r = apiRequest('GET', '/external/bots', null, $API_BASE_URL, $API_SECRET);
    sendJson([
        'url_used'    => rtrim($API_BASE_URL, '/') . '/external/bots',
        'http_code'   => $r['code'],
        'ok'          => $r['ok'],
        'data'        => $r['data'],
        'error'       => $r['error'],
        'secret_set'  => ($API_SECRET !== 'PASTE_YOUR_API_SECRET_KEY_HERE' && strlen($API_SECRET) > 10),
    ]);
}

// ── Helper: check Heroku directly and update DB ───────────────────────────────
function herokuDirectCheck(string $appName, string $jobId, int $userId, \PDO $db, string $API_BASE_URL, string $API_SECRET): array {
    $chk = apiRequest('GET', "/external/check/$appName", null, $API_BASE_URL, $API_SECRET);
    if (!$chk['ok'] || empty($chk['data'])) {
        return ['status'=>'deploying']; // Can't reach Heroku either — keep polling
    }
    $chkStatus = $chk['data']['status'] ?? 'deploying';
    $chkUrl    = $chk['data']['appUrl'] ?? null;

    if ($chkStatus === 'completed' && $chkUrl) {
        $db->prepare("UPDATE deployments SET status='completed', app_url=?, updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$chkUrl, $jobId, $userId]);
        return ['status'=>'completed', 'appUrl'=>$chkUrl];
    } elseif ($chkStatus === 'failed') {
        $db->prepare("UPDATE deployments SET status='failed', error_message='Build failed on Heroku.', updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$jobId, $userId]);
        return ['status'=>'failed', 'error'=>'Build failed on Heroku.'];
    }
    return ['status'=>'deploying']; // Still pending on Heroku
}

// ── AJAX: poll job status ────────────────────────────────────────────────────
// Status flow: queued → creating_app → setting_buildpack → setting_config
//              → deploying → scaling → completed | failed
// Fallback: if the job is gone from memory (server restarted), checks Heroku
//           directly so deployments that completed still get resolved.
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    set_time_limit(30);

    $jobId   = trim($_GET['job_id']   ?? '');
    $appName = trim($_GET['app_name'] ?? '');

    if (!$jobId || !$appName) sendJson(['status'=>'error','error'=>'Missing job_id or app_name']);

    $r = apiRequest('GET', "/external/status/$jobId", null, $API_BASE_URL, $API_SECRET);

    // Job not found in memory (server restarted mid-deploy) — check Heroku directly
    if (!$r['ok'] || ($r['code'] === 404)) {
        sendJson(herokuDirectCheck($appName, $jobId, $userId, $db, $API_BASE_URL, $API_SECRET));
    }

    $d      = $r['data'];
    $status = $d['status'] ?? 'unknown';
    $appUrl = $d['appUrl'] ?? null;

    // Map our status to DB labels
    $dbStatus = match(true) {
        $status === 'completed' => 'completed',
        $status === 'failed'    => 'failed',
        default                 => 'building',
    };

    // Update DB
    if ($status === 'completed' && $appUrl) {
        $db->prepare("UPDATE deployments SET status='completed', app_url=?, updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$appUrl, $jobId, $userId]);
        sendJson(['status'=>'completed', 'appUrl'=>$appUrl]);
    } elseif ($status === 'failed') {
        $errMsg = $d['error'] ?? 'Deployment failed on server.';
        $db->prepare("UPDATE deployments SET status='failed', error_message=?, updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$errMsg, $jobId, $userId]);
        sendJson(['status'=>'failed', 'error'=>$errMsg]);
    } else {
        $db->prepare("UPDATE deployments SET status=?, updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$dbStatus, $jobId, $userId]);
        sendJson(['status'=>$status]);
    }
}

// ── AJAX: sync stuck deployment by checking Heroku directly ──────────────────
// Called from the "Re-check" button in the history table for building rows
if (isset($_GET['action']) && $_GET['action'] === 'sync') {
    set_time_limit(30);
    $appName = trim($_GET['app_name'] ?? '');
    $jobId   = trim($_GET['job_id']   ?? '');
    if (!$appName) sendJson(['status'=>'error','error'=>'Missing app_name']);
    sendJson(herokuDirectCheck($appName, $jobId ?: $appName, $userId, $db, $API_BASE_URL, $API_SECRET));
}

// ── AJAX: initiate deployment ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deploy') {
    set_time_limit(60);

    // Catch ALL PHP errors/exceptions so we always return JSON, never crash silently
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        sendJson(['success'=>false,'error'=>"PHP Error [$errno]: $errstr in $errfile:$errline"]);
    });
    set_exception_handler(function($e) {
        sendJson(['success'=>false,'error'=>'PHP Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()]);
    });

    $botType = trim($_POST['botType'] ?? '');
    $appName = strtolower(trim($_POST['appName'] ?? ''));

    if (!isset($BOTS[$botType]))
        sendJson(['success'=>false,'error'=>'Invalid bot type selected.']);
    if ($balance < $DEPLOY_COST)
        sendJson(['success'=>false,'error'=>"Insufficient DSC balance. You need {$DEPLOY_COST} DSC to deploy."]);
    if (!preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $appName) || strlen($appName) < 3 || strlen($appName) > 30)
        sendJson(['success'=>false,'error'=>'App name must be 3–30 chars, lowercase letters, numbers and dashes only.']);

    $bot     = $BOTS[$botType];
    $botVars = [];

    // Collect + validate bot-specific fields
    foreach ($bot['fields'] as $f) {
        $val = trim($_POST[$f['key']] ?? '');
        if ($f['required'] && $val === '')
            sendJson(['success'=>false,'error'=>"Field \"{$f['label']}\" is required."]);
        if ($val !== '') $botVars[$f['key']] = $val;
    }

    // Call our deployment API
    $r = apiRequest('POST', '/external/deploy', [
        'botType' => $botType,
        'appName' => $appName,
        'botVars' => $botVars,
    ], $API_BASE_URL, $API_SECRET);

    if (!$r['ok']) {
        $errMsg = $r['data']['error'] ?? $r['error'] ?? null;
        if (!$errMsg) $errMsg = 'API error (HTTP ' . $r['code'] . '). Check deploy.php?action=test for connectivity.';
        sendJson(['success'=>false,'error'=>$errMsg]);
    }

    $jobId = $r['data']['jobId'] ?? null;
    if (!$jobId) {
        // Return full raw response to help diagnose unexpected API responses
        $raw = json_encode($r['data']);
        sendJson(['success'=>false,'error'=>'No job ID in API response. Raw: ' . $raw . ' — Visit deploy.php?action=test to check connectivity.']);
    }

    // Deduct DSC
    $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$DEPLOY_COST, $userId]);

    // Save to DB (build_id stores our jobId)
    $deletion = date('Y-m-d H:i:s', strtotime('+30 days'));
    $db->prepare("INSERT INTO deployments
                    (user_id, build_id, app_name, bot_type, bot_name, status, dsc_charged, deployed_at, scheduled_deletion_at)
                  VALUES (?,?,?,?,?,'building',?,NOW(),?)")
       ->execute([$userId, $jobId, $appName, $botType, $bot['name'], $DEPLOY_COST, $deletion]);

    sendJson([
        'success'    => true,
        'jobId'      => $jobId,
        'appName'    => $appName,
        'newBalance' => currentBalance(),
    ]);
}

// Catch-all: if this is a POST and we never sent JSON, something went wrong
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '(none)';
    sendJson(['success'=>false,'error'=>"POST reached page end without a handler. action=$action method={$_SERVER['REQUEST_METHOD']} post_keys=" . implode(',', array_keys($_POST))]);
}

// ── Load recent deployment history ────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM deployments WHERE user_id=? ORDER BY deployed_at DESC LIMIT 15");
$stmt->execute([$userId]);
$deployments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include_once __DIR__ . '/includes/header.php';
?>

<!---------- STYLES ---------->
<style>
/* ─── Loading overlay ─────────────────────────────────── */
#deploy-loader{
    display:none;position:fixed;inset:0;
    background:rgba(8,12,24,.97);z-index:10000;
    flex-direction:column;justify-content:center;align-items:center;padding:28px;
}
.d-ring{position:relative;width:88px;height:88px;margin-bottom:26px;}
.d-ring svg{width:100%;height:100%;animation:d-spin 2s linear infinite;}
.d-track{fill:none;stroke:rgba(0,210,255,.1);stroke-width:5;}
.d-arc  {fill:none;stroke:#00d2ff;stroke-width:5;stroke-linecap:round;
          stroke-dasharray:175;stroke-dashoffset:175;
          animation:d-arc 2s ease-in-out infinite;}
@keyframes d-spin{to{transform:rotate(360deg);}}
@keyframes d-arc{0%{stroke-dashoffset:175}50%{stroke-dashoffset:28}100%{stroke-dashoffset:175}}

#l-title{color:#fff;font-size:1.1rem;font-weight:700;text-align:center;margin:0 0 6px;}
#l-sub  {color:#475569;font-size:12px;text-align:center;max-width:280px;}
.l-bar-wrap{width:210px;height:3px;background:rgba(0,210,255,.1);border-radius:2px;margin:18px 0 0;}
.l-bar      {height:100%;width:0%;background:#00d2ff;border-radius:2px;transition:width .5s ease;}
#l-step {margin-top:12px;font-size:10px;color:#334155;font-family:monospace;text-transform:uppercase;letter-spacing:.08em;}
#l-step span{color:#00d2ff;font-weight:700;}

/* ─── Page wrapper ──────────────────────────────────────── */
.dw{max-width:800px;margin:0 auto;}
.bal-bar{
    display:flex;justify-content:space-between;align-items:center;
    background:rgba(6,182,212,.06);border:1px solid rgba(6,182,212,.18);
    border-radius:14px;padding:12px 18px;margin-bottom:18px;font-size:13px;
}
.card{background:rgba(30,41,59,.55);border:1px solid rgba(255,255,255,.07);border-radius:22px;padding:28px;margin-bottom:22px;}

/* ─── Bot tabs ───────────────────────────────────────────── */
.btabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px;}
.btab{
    padding:7px 16px;border-radius:20px;border:1px solid rgba(255,255,255,.1);
    background:transparent;color:#64748b;cursor:pointer;font-size:12px;font-weight:600;
    transition:all .2s;letter-spacing:.03em;
}
.btab:hover{border-color:rgba(0,210,255,.4);color:#00d2ff;}
.btab.active{background:rgba(0,210,255,.1);border-color:#00d2ff;color:#00d2ff;}

/* ─── Fields ─────────────────────────────────────────────── */
.fg{margin-bottom:15px;}
.fl{display:block;font-size:10px;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px;font-weight:700;}
.fl .req{color:#ef4444;}
.fi,.fs,.ft{
    width:100%;padding:11px 14px;background:rgba(10,18,36,.8);
    border:1px solid rgba(255,255,255,.09);border-radius:10px;
    color:#e2e8f0;font-size:13px;outline:none;transition:border .2s;box-sizing:border-box;
}
.fi:focus,.fs:focus,.ft:focus{border-color:rgba(0,210,255,.5);}
.ft{resize:vertical;min-height:72px;font-family:monospace;font-size:11px;}
.fs option{background:#1e293b;}

/* ─── Deploy button ──────────────────────────────────────── */
.btn-go{
    width:100%;padding:14px;border:0;border-radius:12px;font-weight:700;
    font-size:14px;cursor:pointer;letter-spacing:.06em;margin-top:6px;
    background:linear-gradient(135deg,#00d2ff,#0891b2);color:#000;transition:opacity .2s;
}
.btn-go:hover{opacity:.86;} .btn-go:disabled{opacity:.35;cursor:not-allowed;}

/* ─── Status message ─────────────────────────────────────── */
.msg{padding:13px 16px;border-radius:10px;font-size:13px;margin-top:18px;text-align:center;line-height:1.6;}
.msg.ok  {background:rgba(34,197,94,.07); border:1px solid rgba(34,197,94,.3); color:#4ade80;}
.msg.err {background:rgba(239,68,68,.07); border:1px solid rgba(239,68,68,.3); color:#f87171;}

/* ─── History table ──────────────────────────────────────── */
.ht{width:100%;border-collapse:collapse;font-size:12px;}
.ht th{text-align:left;padding:8px 12px;color:#334155;text-transform:uppercase;font-size:10px;letter-spacing:.07em;border-bottom:1px solid rgba(255,255,255,.05);}
.ht td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.03);color:#94a3b8;vertical-align:middle;}
.ht tr:last-child td{border-bottom:0;}
.pill{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:.04em;}
.p-ok{background:rgba(34,197,94,.1); color:#4ade80;}
.p-bl{background:rgba(0,210,255,.1);  color:#00d2ff;}
.p-er{background:rgba(239,68,68,.1);  color:#f87171;}
.p-xx{background:rgba(148,163,184,.1);color:#94a3b8;}
.lk{color:#00d2ff;text-decoration:none;font-size:11px;}
.lk:hover{text-decoration:underline;}

@media(max-width:600px){
    .card{padding:16px;}
    .btab{font-size:10px;padding:6px 10px;}
    .ht th:nth-child(4),.ht td:nth-child(4){display:none;}
}
</style>

<!---------- LOADING OVERLAY ---------->
<div id="deploy-loader">
    <div class="d-ring">
        <svg viewBox="0 0 88 88">
            <circle class="d-track" cx="44" cy="44" r="38"/>
            <circle class="d-arc"   cx="44" cy="44" r="38"/>
        </svg>
    </div>
    <div id="l-title">INITIALISING DEPLOYMENT...</div>
    <div id="l-sub">Please wait. Do not close this page.</div>
    <div class="l-bar-wrap"><div class="l-bar" id="l-bar"></div></div>
    <div id="l-step">Status: <span>starting</span></div>
</div>

<!---------- PAGE ---------->
<div class="app-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex:1;min-width:0;">
<?php include __DIR__ . '/includes/topbar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">Deploy Bot ⚡</h1>
        <div class="page-breadcrumb">Dashboard → Deploy Bot</div>
    </div>

    <div class="dw">

        <!--- Balance bar --->
        <div class="bal-bar">
            <span style="color:#94a3b8">Available Balance</span>
            <div style="text-align:right">
                <span id="live-balance" style="font-weight:700;font-size:15px;color:<?= $balance>=$DEPLOY_COST?'#22c55e':'#ef4444' ?>">
                    <?= number_format($balance) ?> DSC
                </span>
                <div style="font-size:11px;color:#475569;margin-top:2px">Cost: <?= $DEPLOY_COST ?> DSC per deploy</div>
            </div>
        </div>

        <!--- Deploy card --->
        <div class="card">
            <h2 style="color:#00d2ff;margin:0 0 20px;font-size:1.15rem;text-align:center;letter-spacing:.05em">
                SELECT &amp; DEPLOY
            </h2>

            <!--- Bot selector tabs --->
            <div class="btabs" id="btabs">
                <?php foreach($BOTS as $k=>$b): ?>
                <button type="button" class="btab<?= $k==='cypherx'?' active':'' ?>" data-bot="<?= $k ?>" onclick="selectBot('<?= $k ?>')">
                    <?= htmlspecialchars($b['name']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!--- Form --->
            <form id="dform">
                <input type="hidden" id="botType" name="botType" value="cypherx">
                <input type="hidden" name="action" value="deploy">

                <div class="fg">
                    <label class="fl" style="display:flex;align-items:center;justify-content:space-between">
                        <span>App Name (unique, 3-30 characters) <span class="req">*</span></span>
                        <span id="regen-btn" onclick="regenName()" style="cursor:pointer;font-size:11px;color:#a78bfa;user-select:none" title="Generate a new name">&#x21bb; regenerate</span>
                    </label>
                    <input type="text" name="appName" id="appName" class="fi"
                        placeholder="e.g. mybot-alpha-01   (lowercase, letters, numbers, dashes)"
                        minlength="3" maxlength="30" required autocomplete="off">
                    <span style="font-size:11px;color:#475569;margin-top:4px;display:block">Auto-generated — feel free to edit or leave as is.</span>
                </div>

                <div id="bot-fields"></div>

                <button type="submit" class="btn-go" id="btn-go"
                    <?= $balance<$DEPLOY_COST?'disabled title="Insufficient DSC balance"':'' ?>>
                    DEPLOY (<?= $DEPLOY_COST ?> DSC)
                </button>
            </form>

            <div id="dmsg"></div>
        </div>

        <!--- Deployments history --->
        <div class="card">
            <h3 style="color:#e2e8f0;margin:0 0 16px;font-size:1rem;display:flex;align-items:center;gap:8px">
                Your Deployments
            </h3>
            <?php if(empty($deployments)): ?>
            <p style="color:#334155;font-size:13px;text-align:center;padding:24px 0">No deployments yet.</p>
            <?php else: ?>
            <div style="overflow-x:auto">
            <table class="ht">
                <thead><tr>
                    <th>App Name</th><th>Bot</th><th>Status</th>
                    <th>DSC</th><th>Date</th><th>Links</th>
                </tr></thead>
                <tbody>
                <?php foreach($deployments as $d):
                    // Support both old schema (name/type) and new schema (app_name/bot_name)
                    $displayName = $d['app_name'] ?: ($d['name'] ?? '—');
                    $displayBot  = $d['bot_name'] ?: ($d['bot_type'] ?: ($d['type'] ?? '—'));
                    $hasAppName  = !empty($d['app_name']);
                    $sc = match(true) {
                        in_array($d['status'],['completed','active']) => 'p-ok',
                        $d['status']==='failed'    => 'p-er',
                        in_array($d['status'],['building','pending','in_progress','building']) => 'p-bl',
                        default => 'p-xx',
                    };
                ?>
                <tr>
                    <td style="color:#e2e8f0;font-family:monospace;font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($displayName) ?>"><?= htmlspecialchars($displayName) ?></td>
                    <td style="color:#94a3b8"><?= htmlspecialchars($displayBot) ?></td>
                    <td><span class="pill <?= $sc ?>"><?= strtoupper($d['status']) ?></span></td>
                    <td style="color:#f59e0b">−<?= $d['dsc_charged'] ?></td>
                    <td style="white-space:nowrap"><?= date('d M, H:i', strtotime($d['deployed_at'])) ?></td>
                    <td style="white-space:nowrap">
                        <?php if($hasAppName): ?>
                        <a href="manage.php?app=<?= urlencode($d['app_name']) ?>" class="lk" style="color:#a78bfa;font-weight:700">Manage</a>&nbsp;
                        <?php endif; ?>
                        <?php if(!empty($d['app_url'])): ?>
                        <a href="<?= htmlspecialchars($d['app_url']) ?>" target="_blank" class="lk">Open App</a>&nbsp;
                        <?php endif; ?>
                        <?php if($d['status']==='building' && $hasAppName): ?>
                        <button onclick="recheckDeployment(this,'<?= htmlspecialchars($d['app_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($d['build_id']??'',ENT_QUOTES) ?>')"
                            style="background:#0ea5e9;color:#fff;border:none;border-radius:5px;padding:3px 10px;cursor:pointer;font-size:12px">
                            Re-check
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>
</div>
</div>

<!---------- JAVASCRIPT ---------->
<script>
const BOTS = <?= json_encode(array_map(function($b){
    return ['name'=>$b['name'],'fields'=>$b['fields']];
}, $BOTS), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

// Maps our Node.js API status → loader display
const STEPS = {
    starting:          {pct:5,  title:'INITIALISING...',              sub:'Connecting to deployment server...'},
    queued:            {pct:8,  title:'QUEUED...',                    sub:'Waiting for a deployment slot...'},
    creating_app:      {pct:20, title:'CREATING APP...',              sub:'Setting up your application...'},
    setting_buildpack: {pct:35, title:'CONFIGURING STACK...',         sub:'Setting buildpack and container stack...'},
    setting_config:    {pct:48, title:'SETTING CONFIG VARS...',       sub:'Applying your bot settings...'},
    deploying:         {pct:65, title:'BUILDING BOT...',              sub:'Deploying code. This can take 2-4 minutes...'},
    scaling:           {pct:88, title:'SCALING DYNOS...',             sub:'Starting your bot dyno...'},
    completed:         {pct:100,title:'DEPLOYMENT COMPLETE',          sub:'Your bot is now live.'},
    failed:            {pct:100,title:'DEPLOYMENT FAILED',            sub:'Something went wrong. Check the deployment log.'},
    error:             {pct:100,title:'CONNECTION ERROR',             sub:'Could not reach the deployment server. Try again.'},
};

let pollTimer = null;
let pollErrorCount = 0;

// ── Render dynamic fields ──────────────────────────────
function renderFields(botKey) {
    const bot = BOTS[botKey]; if(!bot) return;
    let h = '';
    bot.fields.forEach(f => {
        const r = f.required
            ? '<span class="req"> *</span>'
            : '<span style="color:#334155;font-size:10px"> (optional)</span>';
        h += `<div class="fg"><label class="fl">${f.label}${r}</label>`;
        if(f.type==='select'){
            h += `<select name="${f.key}" class="fs"${f.required?' required':''}><option value="">— select —</option>`;
            f.options.forEach(o=>{ h+=`<option value="${o.value}">${o.label}</option>`; });
            h += `</select>`;
        } else if(f.type==='textarea'){
            h += `<textarea name="${f.key}" class="ft" placeholder="${f.placeholder||''}" rows="3"${f.required?' required':''}></textarea>`;
        } else {
            const t = f.type==='password'?'password':'text';
            h += `<input type="${t}" name="${f.key}" class="fi" placeholder="${f.placeholder||''}"${f.required?' required':''}>`;
        }
        h += '</div>';
    });
    document.getElementById('bot-fields').innerHTML = h;
}

function selectBot(k){
    document.getElementById('botType').value = k;
    document.querySelectorAll('.btab').forEach(t=>t.classList.toggle('active', t.dataset.bot===k));
    renderFields(k);
    if(!appNameManuallyEdited) fillGeneratedName(k);
}

// ── App name auto-generator ─────────────────────────────
const _adjectives = ['swift','cloud','echo','nova','bolt','prime','ace','zen','flux','wave','sage','core','peak','spark','dusk'];
const _nouns      = ['bot','hub','net','run','lab','bay','ops','fox','kai','pro','one','max','air','sky','bit'];
const _prefixes   = { cypherx:'cypher', bwm:'bwm', cypherxultra:'ultra', kingmd:'king', anitav4:'anita', atassa:'atassa' };

let appNameManuallyEdited = false;

function generateAppName(botKey){
    const prefix = _prefixes[botKey] || 'bot';
    const adj    = _adjectives[Math.floor(Math.random()*_adjectives.length)];
    const noun   = _nouns[Math.floor(Math.random()*_nouns.length)];
    const num    = String(Math.floor(Math.random()*90)+10);
    const name   = `${prefix}-${adj}-${noun}-${num}`;
    return name.substring(0,30);
}

function fillGeneratedName(botKey){
    const inp = document.getElementById('appName');
    inp.value = generateAppName(botKey || document.getElementById('botType').value);
}

function regenName(){
    appNameManuallyEdited = false;
    fillGeneratedName();
}

document.getElementById('appName').addEventListener('input', ()=>{ appNameManuallyEdited = true; });

selectBot('cypherx');

// ── Loader helpers ─────────────────────────────────────
function showLoader(step){
    const s = STEPS[step] || STEPS.starting;
    document.getElementById('deploy-loader').style.display = 'flex';
    document.getElementById('l-title').textContent = s.title;
    document.getElementById('l-sub').textContent   = s.sub;
    document.getElementById('l-bar').style.width   = s.pct + '%';
    document.getElementById('l-step').innerHTML    = `Status: <span>${step}</span>`;
}
function hideLoader(){ document.getElementById('deploy-loader').style.display='none'; if(pollTimer) clearInterval(pollTimer); }

function msg(type, html){
    const el = document.getElementById('dmsg');
    el.className = 'msg '+type; el.innerHTML = html;
    el.scrollIntoView({behavior:'smooth',block:'nearest'});
}

// ── Poll job status ────────────────────────────────────
function startPolling(jobId, appName){
    showLoader('queued');
    pollErrorCount = 0;
    pollTimer = setInterval(async ()=>{
        try{
            const r = await fetch(`?action=poll&job_id=${encodeURIComponent(jobId)}&app_name=${encodeURIComponent(appName)}`);
            const d = await r.json();

            if(d.status==='completed'){
                clearInterval(pollTimer); hideLoader();
                msg('ok',
                    `Bot is live. &nbsp;
                     <a href="${d.appUrl}" target="_blank" style="color:#00d2ff">Open App</a>
                     <br><small style="color:#475569;font-size:11px">App URL: ${d.appUrl}</small>`);
                setTimeout(()=>location.reload(), 3500);
                return;
            }
            if(d.status==='failed'){
                clearInterval(pollTimer); hideLoader();
                msg('err', `Deployment failed: ${d.error||'Unknown error.'}`);
                return;
            }
            if(d.status==='error'){
                // Transient error — keep polling, fallback to direct Heroku check handles it
                pollErrorCount++;
                if(pollErrorCount >= 8){
                    clearInterval(pollTimer); hideLoader();
                    msg('err', `Could not track deployment status. Your bot may still be deploying &mdash; check your <a href="https://dashboard.heroku.com" target="_blank" style="color:#00d2ff">Heroku dashboard</a>, then use the <strong>Re-check</strong> button in the table below to sync the status here.`);
                    return;
                }
                showLoader('deploying');
                return;
            }
            pollErrorCount = 0;
            showLoader(d.status);
        } catch(e){ /* network blip — keep polling */ }
    }, 4000);
}

// ── Re-check a stuck "building" row in history table ───────────────────
async function recheckDeployment(btn, appName, jobId){
    btn.disabled = true; btn.textContent = 'Checking...';
    try{
        const url = `?action=sync&app_name=${encodeURIComponent(appName)}&job_id=${encodeURIComponent(jobId)}`;
        const r = await fetch(url);
        const d = await r.json();
        const row = btn.closest('tr');
        const pill = row ? row.querySelector('.pill') : null;
        if(d.status==='completed'){
            if(pill){ pill.className='pill p-ok'; pill.textContent='COMPLETED'; }
            btn.textContent='Done!';
            setTimeout(()=>location.reload(), 1500);
        } else if(d.status==='failed'){
            if(pill){ pill.className='pill p-er'; pill.textContent='FAILED'; }
            btn.textContent='Failed'; btn.disabled=false;
        } else {
            btn.disabled=false; btn.textContent='Re-check';
            alert('Still building on Heroku — try again in a minute.');
        }
    } catch(e){
        btn.disabled=false; btn.textContent='Re-check';
        alert('Network error — try again.');
    }
}

// ── Form submit ────────────────────────────────────────
document.getElementById('dform').addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('btn-go');
    btn.disabled = true; btn.textContent = 'Connecting...';
    showLoader('starting');

    const fd = new FormData(this);
    fd.set('action','deploy');
    fd.set('botType', document.getElementById('botType').value);
    try{
        const r = await fetch(window.location.href, {method:'POST', body:fd});
        const d = await r.json();

        if(d.success){
            if(d.newBalance !== undefined)
                document.getElementById('live-balance').textContent = Number(d.newBalance).toLocaleString()+' DSC';
            startPolling(d.jobId, d.appName);
        } else {
            hideLoader();
            msg('err', '❌ ' + (d.error||'Unknown error.'));
            btn.disabled = false;
            btn.textContent = `🚀 DEPLOY (<?= $DEPLOY_COST ?> DSC)`;
        }
    } catch(ex){
        hideLoader();
        msg('err','❌ Network error. Please try again.');
        btn.disabled = false;
        btn.textContent = `🚀 DEPLOY (<?= $DEPLOY_COST ?> DSC)`;
    }
});

// Escape key dismisses stuck loader
document.addEventListener('keydown', e=>{ if(e.key==='Escape') hideLoader(); });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
