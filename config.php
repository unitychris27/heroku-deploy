<?php
// ──────────────────────────────────────────────────────────────────────────────
//  Digitex Deploy Panel — Central Configuration
// ──────────────────────────────────────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'tipmrnhl_panel');
define('DB_USER', 'tipmrnhl_panel');
define('DB_PASS', 'PanelPass2025!');
define('DB_CHARSET', 'utf8mb4');
define('SESSION_NAME', 'digitex_panel');
define('BASE_URL', 'https://panel.xcasper.site');

// ── Session setup ─────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// ── PDO singleton ─────────────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// ── Auth helpers ──────────────────────────────────────────────────────────────
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function currentBalance(): int {
    $db   = getDB();
    $stmt = $db->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    return (int)($stmt->fetchColumn() ?? 0);
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, email, balance, is_admin FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function isAdmin(): bool {
    return !empty($_SESSION['is_admin']);
}
