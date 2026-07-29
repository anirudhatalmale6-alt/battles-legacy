<?php
/** Sessions, login, roles. */
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('battles_sess');
    session_start();
}

function current_user() {
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? null;
    $u = $id ? one("SELECT * FROM users WHERE id=?", [$id]) : null;
    return $u;
}

function logged_in() { return current_user() !== null; }

function user_role() { $u = current_user(); return $u ? $u['role'] : null; }

/** admin > moderator > member */
function role_at_least($needed) {
    $rank = ['member' => 1, 'moderator' => 2, 'admin' => 3];
    $have = $rank[user_role()] ?? 0;
    return $have >= ($rank[$needed] ?? 99);
}

function require_login() {
    if (!logged_in()) { header('Location: login.php'); exit; }
}
function require_role($needed) {
    require_login();
    if (!role_at_least($needed)) { http_response_code(403); exit('Access denied — you need the ' . htmlspecialchars($needed) . ' role.'); }
}

function attempt_login($email, $password) {
    $u = one("SELECT * FROM users WHERE email=? AND status='active'", [strtolower(trim($email))]);
    if ($u && $u['pass_hash'] && password_verify($password, $u['pass_hash'])) {
        $_SESSION['uid'] = $u['id'];
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        q("UPDATE users SET last_login=CURRENT_TIMESTAMP WHERE id=?", [$u['id']]);
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = [];
    session_destroy();
}

/** CSRF */
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? 'x')) { http_response_code(400); exit('Bad request (CSRF).'); }
}
