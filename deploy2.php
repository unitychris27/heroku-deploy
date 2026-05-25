<?php
// Catch any PHP warnings/notices so they don't corrupt JSON responses
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
requireLogin();

// ╔══════════════════════════════════════════════╗
// ║         ONLY EDIT THESE TWO LINES            ║
$HEROKU_API_KEY = "YOUR_HEROKU_API_KEY_HERE";
$HEROKU_TEAM    = "your-heroku-team-slug";
// ╚══════════════════════════════════════════════╝

$DEPLOY_COST = 60;
$pageTitle   = 'Deploy Bot';
$activePage  = 'deploy-bot';
$userId      = $_SESSION['user_id'];
$balance     = currentBalance();
$db          = getDB();

// ── Bot definitions ──────────────────────────────────────────────────────────
$BOTS = [
  'cypherx' => [
    'name'           => 'CypherX',
    'tarball'        => 'https://github.com/Dark-Xploit/CypherX/archive/refs/heads/main.tar.gz',
    'containerStack' => true,
    'fields' => [
      ['key'=>'sessionId',  'envVar'=>'SESSION_ID', 'label'=>'Session ID',   'type'=>'textarea', 'required'=>true,  'placeholder'=>'Paste WhatsApp session string...'],
    ],
  ],
  'bwm' => [
    'name'           => 'BWM-XMD',
    'tarball'        => 'https://github.com/Bwmxmd254/BWM-XMD-GO/archive/refs/heads/main.tar.gz',
    'containerStack' => false,
    'fields' => [
      ['key'=>'session',     'envVar'=>'SESSION',      'label'=>'Session ID',   'type'=>'textarea', 'required'=>true,  'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'ownerNumber', 'envVar'=>'OWNER_NUMBER', 'label'=>'Owner Number', 'type'=>'text',     'required'=>true,  'placeholder'=>'e.g. 254700000000'],
    ],
  ],
  'cypherxultra' => [
    'name'           => 'CypherX-Ultra',
    'tarball'        => 'https://github.com/Dark-Xploit/CypherX-Ultra/archive/refs/heads/main.tar.gz',
    'containerStack' => false,
    'fields' => [
      ['key'=>'masterPassword', 'envVar'=>'MASTER_PASSWORD', 'label'=>'Master Password',  'type'=>'password', 'required'=>true,  'placeholder'=>'Secure password for the web dashboard'],
      ['key'=>'githubUsername', 'envVar'=>'GITHUB_USERNAME', 'label'=>'GitHub Username',  'type'=>'text',     'required'=>false, 'placeholder'=>'Your GitHub username (fork owner)'],
    ],
  ],
  'kingmd' => [
    'name'           => 'King MD',
    'tarball'        => 'https://github.com/sesco001/KING-MD/archive/refs/heads/main.tar.gz',
    'containerStack' => true,
    'fields' => [
      ['key'=>'session', 'envVar'=>'SESSION', 'label'=>'Session ID',   'type'=>'textarea', 'required'=>true, 'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'dev',     'envVar'=>'DEV',     'label'=>'Owner Number', 'type'=>'text',     'required'=>true, 'placeholder'=>'e.g. 254700000000'],
      ['key'=>'code',    'envVar'=>'CODE',    'label'=>'Country Code', 'type'=>'text',     'required'=>true, 'placeholder'=>'e.g. 254 (Kenya), 234 (Nigeria)'],
    ],
  ],
  'anitav4' => [
    'name'           => 'Queen Anitah',
    'tarball'        => 'https://github.com/Blurnk/Anita-V4/archive/refs/heads/main.tar.gz',
    'containerStack' => false,
    'fields' => [
      ['key'=>'sessionId',       'envVar'=>'SESSION_ID',       'label'=>'Session ID',           'type'=>'textarea', 'required'=>true,  'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'ownerNumber',     'envVar'=>'OWNER_NUMBER',     'label'=>'Owner Number',         'type'=>'text',     'required'=>true,  'placeholder'=>'e.g. 254700000000'],
      ['key'=>'prefix',          'envVar'=>'PREFIX',           'label'=>'Command Prefix',       'type'=>'text',     'required'=>false, 'placeholder'=>'. or / or ! or #'],
      ['key'=>'public',          'envVar'=>'PUBLIC',           'label'=>'Bot Mode',             'type'=>'select',   'required'=>true,  'options'=>[['value'=>'public','label'=>'Public'],['value'=>'private','label'=>'Private']]],
      ['key'=>'autoViewStatus',  'envVar'=>'AUTO_VIEW_STATUS', 'label'=>'Auto View Status',     'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'antidelete',      'envVar'=>'ANTIDELETE',       'label'=>'Anti-Delete',          'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'autoStatusReact', 'envVar'=>'AUTO_STATUS_REACT','label'=>'Auto React to Status', 'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'chatbot',         'envVar'=>'CHATBOT',          'label'=>'Enable Chat Bot',      'type'=>'select',   'required'=>true,  'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
    ],
  ],
  'atassa' => [
    'name'           => 'Atassa MD',
    'tarball'        => 'https://github.com/mauricegift/atassa/archive/refs/heads/main.tar.gz',
    'containerStack' => false,
    'fields' => [
      ['key'=>'sessionId',      'envVar'=>'SESSION_ID',       'label'=>'Session ID',      'type'=>'textarea', 'required'=>true, 'placeholder'=>'Paste WhatsApp session string...'],
      ['key'=>'mode',           'envVar'=>'MODE',             'label'=>'Bot Mode',        'type'=>'select',   'required'=>true, 'options'=>[['value'=>'public','label'=>'Public'],['value'=>'private','label'=>'Private']]],
      ['key'=>'autoLikeStatus', 'envVar'=>'AUTO_LIKE_STATUS', 'label'=>'Auto Like Status','type'=>'select',   'required'=>true, 'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
      ['key'=>'autoReadStatus', 'envVar'=>'AUTO_READ_STATUS', 'label'=>'Auto Read Status','type'=>'select',   'required'=>true, 'options'=>[['value'=>'true','label'=>'Yes'],['value'=>'false','label'=>'No']]],
    ],
  ],
];

// ── Ensure DB table exists ────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS deployments (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    user_id               INT          NOT NULL,
    build_id              VARCHAR(120) DEFAULT NULL,
    app_name              VARCHAR(100) NOT NULL,
    bot_type              VARCHAR(50)  NOT NULL,
    bot_name              VARCHAR(100) NOT NULL,
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

// ── Heroku API helpers ────────────────────────────────────────────────────────
function herokuRequest(string $method, string $path, ?array $body, string $apiKey): array {
    $ch = curl_init("https://api.heroku.com" . $path);
    $headers = [
        "Authorization: Bearer $apiKey",
        "Accept: application/vnd.heroku+json; version=3",
        "Content-Type: application/json",
        "User-Agent: HerokuBotDeploy/1.0",
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        // Fix SSL issues common on shared cPanel servers
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
    if ($err) return ['ok'=>false, 'code'=>0, 'data'=>null, 'error'=>"Connection failed: $err — Check your server's outbound internet access."];
    if (!$raw) return ['ok'=>false, 'code'=>$code, 'data'=>null, 'error'=>"Empty response from Heroku (HTTP $code)"];
    $data = json_decode($raw, true);
    if ($data === null) return ['ok'=>false, 'code'=>$code, 'data'=>null, 'error'=>"Invalid JSON from Heroku (HTTP $code)"];
    return ['ok'=>($code >= 200 && $code < 300), 'code'=>$code, 'data'=>$data, 'error'=>$data['message'] ?? null];
}

// Clean any buffered output and send JSON — prevents PHP warnings from corrupting response
function sendJson(array $data): void {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── AJAX: poll build status ───────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    set_time_limit(30);

    $appName = trim($_GET['app_name'] ?? '');
    $buildId = trim($_GET['build_id']  ?? '');

    if (!$appName || !$buildId) sendJson(['status'=>'error','error'=>'Missing params']);

    $r = herokuRequest('GET', "/apps/$appName/builds/$buildId", null, $HEROKU_API_KEY);
    if (!$r['ok']) sendJson(['status'=>'error','error'=>$r['error'] ?? 'Heroku API error']);

    $buildStatus = $r['data']['status'] ?? 'pending';
    $outputUrl   = $r['data']['output_stream_url'] ?? null;

    if ($buildStatus === 'succeeded') {
        herokuRequest('PATCH', "/apps/$appName/formation",
            ['updates'=>[['type'=>'web','quantity'=>1,'size'=>'eco']]],
            $HEROKU_API_KEY);

        $appUrl = "https://$appName.herokuapp.com";
        $db->prepare("UPDATE deployments SET status='completed', app_url=?, logs_url=?, updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$appUrl, $outputUrl, $buildId, $userId]);

        sendJson(['status'=>'completed','appUrl'=>$appUrl,'logsUrl'=>$outputUrl]);

    } elseif ($buildStatus === 'failed') {
        $db->prepare("UPDATE deployments SET status='failed', error_message='Build failed on Heroku.', updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$buildId, $userId]);
        sendJson(['status'=>'failed','error'=>'Build failed on Heroku. Check build logs for details.','logsUrl'=>$outputUrl]);

    } else {
        $db->prepare("UPDATE deployments SET status=?, updated_at=NOW() WHERE build_id=? AND user_id=?")
           ->execute([$buildStatus, $buildId, $userId]);
        sendJson(['status'=>$buildStatus,'logsUrl'=>$outputUrl]);
    }
}

// ── AJAX: initiate deployment ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deploy') {
    set_time_limit(120);

    $botType = trim($_POST['botType'] ?? '');
    $appName = strtolower(trim($_POST['appName'] ?? ''));

    if (!isset($BOTS[$botType]))
        sendJson(['success'=>false,'error'=>'Invalid bot type selected.']);
    if ($balance < $DEPLOY_COST)
        sendJson(['success'=>false,'error'=>"Insufficient DSC balance. You need {$DEPLOY_COST} DSC to deploy."]);
    if (!preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $appName) || strlen($appName) < 3 || strlen($appName) > 30)
        sendJson(['success'=>false,'error'=>'App name must be 3–30 chars, lowercase letters, numbers and dashes only (no leading/trailing dash).']);

    $bot    = $BOTS[$botType];
    $config = ['BOT_TYPE'=>strtoupper($botType), 'NODE_ENV'=>'production'];

    // Collect + validate bot fields
    foreach ($bot['fields'] as $f) {
        $val = trim($_POST[$f['key']] ?? '');
        if ($f['required'] && $val === '')
            sendJson(['success'=>false,'error'=>"Field \"{$f['label']}\" is required."]);
        if ($val !== '') $config[$f['envVar']] = $val;
    }

    // 1. Create app under team
    $r = herokuRequest('POST', '/teams/apps', ['name'=>$appName,'team'=>$HEROKU_TEAM], $HEROKU_API_KEY);
    if (!$r['ok']) {
        $errMsg = $r['data']['message'] ?? $r['error'] ?? 'Could not create Heroku app.';
        sendJson(['success'=>false,'error'=>"Heroku: $errMsg"]);
    }

    // 2. Set stack (Docker bots) OR buildpack (Node.js bots)
    if ($bot['containerStack']) {
        herokuRequest('PATCH', "/apps/$appName", ['build_stack'=>'container'], $HEROKU_API_KEY);
    } else {
        herokuRequest('PUT', "/apps/$appName/buildpack-installations",
            ['updates'=>[['buildpack'=>'heroku/nodejs']]], $HEROKU_API_KEY);
    }

    // 3. Set config vars
    $r = herokuRequest('PATCH', "/apps/$appName/config-vars", $config, $HEROKU_API_KEY);
    if (!$r['ok'])
        sendJson(['success'=>false,'error'=>'Failed to set config vars: ' . ($r['error'] ?? 'unknown error')]);

    // 4. Trigger build from tarball
    $r = herokuRequest('POST', "/apps/$appName/builds",
        ['source_blob'=>['url'=>$bot['tarball'],'version'=>'HEAD']], $HEROKU_API_KEY);
    if (!$r['ok'])
        sendJson(['success'=>false,'error'=>'Failed to trigger build: ' . ($r['error'] ?? 'unknown error')]);

    $buildId   = $r['data']['id']                   ?? null;
    $outputUrl = $r['data']['output_stream_url']     ?? null;

    if (!$buildId)
        sendJson(['success'=>false,'error'=>'Heroku did not return a build ID. Please try again.']);

    // 5. Deduct DSC
    $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$DEPLOY_COST, $userId]);

    // 6. Save to DB
    $deletion = date('Y-m-d H:i:s', strtotime('+30 days'));
    $db->prepare("INSERT INTO deployments (user_id,build_id,app_name,bot_type,bot_name,status,logs_url,dsc_charged,deployed_at,scheduled_deletion_at)
                  VALUES (?,?,?,?,?,'building',?,?,NOW(),?)")
       ->execute([$userId, $buildId, $appName, $botType, $bot['name'], $outputUrl, $DEPLOY_COST, $deletion]);

    sendJson([
        'success'    => true,
        'buildId'    => $buildId,
        'appName'    => $appName,
        'logsUrl'    => $outputUrl,
        'newBalance' => currentBalance(),
    ]);
}

// ── Load recent history ───────────────────────────────────────────────────────
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
                <button class="btab<?= $k==='cypherx'?' active':'' ?>" data-bot="<?= $k ?>" onclick="selectBot('<?= $k ?>')">
                    <?= htmlspecialchars($b['name']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!--- Form --->
            <form id="dform">
                <input type="hidden" id="botType" name="botType" value="cypherx">
                <input type="hidden" name="action" value="deploy">

                <div class="fg">
                    <label class="fl">App Name (unique on Heroku) <span class="req">*</span></label>
                    <input type="text" name="appName" id="appName" class="fi"
                        placeholder="e.g. mybot-alpha-01   (lowercase, letters, numbers, dashes)"
                        minlength="3" maxlength="30" required>
                </div>

                <div id="bot-fields"></div>

                <button type="submit" class="btn-go" id="btn-go"
                    <?= $balance<$DEPLOY_COST?'disabled title="Insufficient DSC balance"':'' ?>>
                    🚀 DEPLOY (<?= $DEPLOY_COST ?> DSC)
                </button>
            </form>

            <div id="dmsg"></div>
        </div>

        <!--- Deployments history --->
        <div class="card">
            <h3 style="color:#e2e8f0;margin:0 0 16px;font-size:1rem;display:flex;align-items:center;gap:8px">
                📋 Your Deployments
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
                    $sc = match(true) {
                        $d['status']==='completed' => 'p-ok',
                        $d['status']==='failed'    => 'p-er',
                        in_array($d['status'],['building','pending','in_progress']) => 'p-bl',
                        default => 'p-xx',
                    };
                ?>
                <tr>
                    <td style="color:#e2e8f0;font-family:monospace;font-weight:600"><?= htmlspecialchars($d['app_name']) ?></td>
                    <td style="color:#94a3b8"><?= htmlspecialchars($d['bot_name']) ?></td>
                    <td><span class="pill <?= $sc ?>"><?= strtoupper($d['status']) ?></span></td>
                    <td style="color:#f59e0b">−<?= $d['dsc_charged'] ?></td>
                    <td style="white-space:nowrap"><?= date('d M, H:i', strtotime($d['deployed_at'])) ?></td>
                    <td>
                        <?php if($d['app_url']): ?>
                        <a href="<?= htmlspecialchars($d['app_url']) ?>" target="_blank" class="lk">App</a>&nbsp;
                        <?php endif; ?>
                        <?php if($d['logs_url']): ?>
                        <a href="<?= htmlspecialchars($d['logs_url']) ?>" target="_blank" class="lk" style="color:#a78bfa">Logs</a>&nbsp;
                        <?php endif; ?>
                        <?php if($d['app_url']): ?>
                        <a href="https://dashboard.heroku.com/apps/<?= htmlspecialchars($d['app_name']) ?>" target="_blank" class="lk" style="color:#64748b">Heroku</a>
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

const STEPS = {
    starting:     {pct:5,  title:'INITIALISING...',              sub:'Connecting to Heroku...'},
    building:     {pct:35, title:'BUILDING BOT...',              sub:'Heroku is compiling your bot. This can take 2–4 minutes...'},
    pending:      {pct:20, title:'BUILD QUEUED...',              sub:'Waiting for a Heroku build slot...'},
    in_progress:  {pct:60, title:'BUILD IN PROGRESS...',         sub:'Installing dependencies & configuring...'},
    completed:    {pct:100,title:'DEPLOYMENT COMPLETE!',         sub:'Your bot is now live 🎉'},
    failed:       {pct:100,title:'DEPLOYMENT FAILED',            sub:'Something went wrong. Check the logs.'},
    error:        {pct:100,title:'CONNECTION ERROR',             sub:'Could not reach Heroku. Try again.'},
};

let pollTimer = null;

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
}
selectBot('cypherx');

// ── Loader ─────────────────────────────────────────────
function showLoader(step){
    const s = STEPS[step] || STEPS.starting;
    document.getElementById('deploy-loader').style.display = 'flex';
    document.getElementById('l-title').textContent = s.title;
    document.getElementById('l-sub').textContent   = s.sub;
    document.getElementById('l-bar').style.width   = s.pct + '%';
    document.getElementById('l-step').innerHTML    = `Status: <span>${step}</span>`;
}
function hideLoader(){ document.getElementById('deploy-loader').style.display='none'; if(pollTimer) clearInterval(pollTimer); }

// ── Message ────────────────────────────────────────────
function msg(type, html){
    const el = document.getElementById('dmsg');
    el.className = 'msg '+type; el.innerHTML = html;
    el.scrollIntoView({behavior:'smooth',block:'nearest'});
}

// ── Poll build status ──────────────────────────────────
function startPolling(appName, buildId, logsUrl){
    showLoader('building');
    pollTimer = setInterval(async ()=>{
        try{
            const r = await fetch(`?action=poll&app_name=${encodeURIComponent(appName)}&build_id=${encodeURIComponent(buildId)}`);
            const d = await r.json();
            showLoader(d.status);

            if(d.status==='completed'){
                clearInterval(pollTimer); hideLoader();
                msg('ok',
                    `✅ Bot is live! &nbsp;
                     <a href="${d.appUrl}" target="_blank" style="color:#00d2ff">Open App</a>
                     ${d.logsUrl?`&nbsp; <a href="${d.logsUrl}" target="_blank" style="color:#a78bfa">Build Logs</a>`:''}
                     <br><small style="color:#475569;font-size:11px">App URL: ${d.appUrl}</small>`);
                setTimeout(()=>location.reload(), 3500);

            } else if(d.status==='failed' || d.status==='error'){
                clearInterval(pollTimer); hideLoader();
                msg('err',
                    `❌ ${d.error||'Build failed.'} `+
                    (d.logsUrl?`<a href="${d.logsUrl}" target="_blank" style="color:#a78bfa">View Build Logs</a>`:''));
            }
        } catch(e){ /* network blip – keep polling */ }
    }, 4000);
}

// ── Submit ─────────────────────────────────────────────
document.getElementById('dform').addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('btn-go');
    btn.disabled = true; btn.textContent = 'Connecting...';

    showLoader('starting');

    const fd = new FormData(this); fd.set('action','deploy');
    try{
        const r = await fetch(window.location.href, {method:'POST', body:fd});
        const d = await r.json();

        if(d.success){
            if(d.newBalance !== undefined)
                document.getElementById('live-balance').textContent = Number(d.newBalance).toLocaleString()+' DSC';
            startPolling(d.appName, d.buildId, d.logsUrl);
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

// Safety escape key to dismiss stuck loader
document.addEventListener('keydown', e=>{ if(e.key==='Escape') hideLoader(); });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
