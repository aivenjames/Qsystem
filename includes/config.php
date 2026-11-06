<?php
// Update these values if your local MySQL configuration is different.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'qsystem');
define('DB_USER', 'root');
define('DB_PASS', '');
define('PYTHON_API_URL', 'http://127.0.0.1:5055');
define('PYTHON_API_TOKEN', 'change-this-local-token');

if (session_status() === PHP_SESSION_NONE) session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function logged_in(): bool { return isset($_SESSION['user']); }
function require_login(): void { if (!logged_in()) { header('Location: login.php'); exit; } }
function require_roles(array $roles): void { require_login(); if (!in_array($_SESSION['user']['role'], $roles, true)) { http_response_code(403); exit('Access denied.'); } }
function flash(string $message, string $type='success'): void { $_SESSION['flash'] = ['message'=>$message,'type'=>$type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Session expired. Go back and try again.'); } }
function current_user_id(): int { return (int)($_SESSION['user']['id'] ?? 0); }
