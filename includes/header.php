<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__) . '/config.php';
}
$__user = currentUser();
$__username = $__user['username'] ?? 'User';
$__balance  = $__user['balance']  ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'Digitex Deploy') ?> — Digitex</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0b1121;color:#e2e8f0;min-height:100vh;display:flex;flex-direction:column}
a{text-decoration:none;color:inherit}
/* NAV */
.top-nav{background:rgba(15,23,42,.95);border-bottom:1px solid rgba(139,92,246,.2);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;backdrop-filter:blur(10px)}
.nav-brand{font-size:17px;font-weight:800;color:#fff;letter-spacing:.3px}.nav-brand span{color:#a855f7}
.nav-links{display:flex;gap:6px}
.nav-link{padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;color:#94a3b8;transition:all .2s}
.nav-link:hover{background:rgba(139,92,246,.15);color:#c4b5fd}
.nav-link.active{background:rgba(139,92,246,.2);color:#a855f7}
.nav-right{display:flex;align-items:center;gap:12px;font-size:13px;color:#94a3b8}
.badge{background:linear-gradient(135deg,#7c3aed,#a855f7);border-radius:20px;padding:4px 12px;font-weight:700;font-size:12px;color:#fff}
.logout-btn{padding:6px 14px;border:1px solid rgba(148,163,184,.2);border-radius:8px;font-size:13px;color:#94a3b8;cursor:pointer;background:transparent;transition:all .2s}
.logout-btn:hover{border-color:#ef4444;color:#ef4444}
/* MAIN */
.main-content{flex:1;max-width:1100px;width:100%;margin:0 auto;padding:32px 24px}
@media(max-width:700px){.nav-links{display:none}.main-content{padding:20px 14px}}
</style>
</head>
<body>
<nav class="top-nav">
  <div class="nav-brand">Digitex <span>Deploy</span></div>
  <div class="nav-links">
    <a href="/deploy.php"         class="nav-link <?= ($activePage??'')==='deploy-bot'    ?'active':'' ?>">🚀 Deploy Bot</a>
    <a href="/manage.php"         class="nav-link <?= ($activePage??'')==='manage-bots'   ?'active':'' ?>">⚙️ My Bots</a>
    <?php if(!empty($_SESSION['is_admin'])): ?>
    <a href="/admin-settings.php" class="nav-link <?= ($activePage??'')==='admin-settings'?'active':'' ?>">🔧 Admin</a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <span><span style="color:#64748b">Balance:</span> <span class="badge"><?= number_format($__balance) ?> DSC</span></span>
    <span style="color:#475569"><?= htmlspecialchars($__username) ?></span>
    <a href="/logout.php"><button class="logout-btn">Logout</button></a>
  </div>
</nav>
<div class="main-content">
