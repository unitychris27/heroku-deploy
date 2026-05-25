<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/deploy.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password']   ?? '');
    if ($identifier && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, username, password, is_admin FROM users WHERE username=? OR email=? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            header('Location: ' . BASE_URL . '/deploy.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Digitex Deploy</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);font-family:'Segoe UI',sans-serif}
.card{background:rgba(15,23,42,.85);border:1px solid rgba(139,92,246,.25);border-radius:20px;
  padding:44px 40px;width:100%;max-width:420px;backdrop-filter:blur(12px)}
.logo{text-align:center;margin-bottom:28px}
.logo h1{font-size:22px;font-weight:800;color:#fff;letter-spacing:.5px}
.logo span{color:#8b5cf6}
label{display:block;font-size:13px;color:#94a3b8;margin-bottom:6px}
input{width:100%;padding:12px 16px;background:rgba(30,41,59,.7);border:1px solid rgba(148,163,184,.2);
  border-radius:10px;color:#e2e8f0;font-size:15px;outline:none;transition:border-color .2s}
input:focus{border-color:#8b5cf6}
.field{margin-bottom:18px}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#7c3aed,#a855f7);border:none;
  border-radius:10px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;letter-spacing:.3px;
  transition:opacity .2s}
.btn:hover{opacity:.9}
.error{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);border-radius:10px;
  color:#fca5a5;padding:10px 14px;font-size:13px;margin-bottom:18px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>Digitex <span>Deploy</span></h1>
    <p style="color:#64748b;font-size:13px;margin-top:6px">Heroku Bot Deployment Panel</p>
  </div>
  <?php if($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <div class="field">
      <label>Username or Email</label>
      <input type="text" name="identifier" autocomplete="username" autofocus
             value="<?= htmlspecialchars($_POST['identifier']??'') ?>" placeholder="admin">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" autocomplete="current-password" placeholder="••••••••">
    </div>
    <button class="btn" type="submit">Sign In</button>
  </form>
</div>
</body>
</html>
