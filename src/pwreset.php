<?php
/** Forgotten passwords.
 *
 *  Two ways in, because a family website cannot depend on one of them:
 *    1. The member asks for a link themselves on forgot.php and it is emailed.
 *    2. William presses "Reset link" beside their name on the Members page and
 *       sends it however he already talks to them.
 *  Both make the same one-time link, so if the email never lands nobody is stuck. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

define('PWRESET_HOURS', 24);

function pwreset_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
          id $AI, user_id INT NOT NULL, token VARCHAR(64) NOT NULL,
          source VARCHAR(12) NOT NULL DEFAULT 'self', emailed INT NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          expires_at DATETIME NULL, used_at DATETIME NULL
        )$ENG");
        db()->exec("CREATE UNIQUE INDEX idx_pwr_token ON password_resets(token)");
    } catch (\Throwable $e) { /* index already there on every run after the first */ }
}

/** A fresh one-time link for a user. Returns [token, url]. */
function pwreset_create($user_id, $source = 'self') {
    pwreset_migrate();
    $token = bin2hex(random_bytes(20));
    q("INSERT INTO password_resets (user_id,token,source,expires_at) VALUES (?,?,?,?)",
      [(int)$user_id, $token, $source, date('Y-m-d H:i:s', time() + PWRESET_HOURS * 3600)]);
    return [$token, base_url() . '/reset.php?token=' . $token];
}

/** The live, unused, unexpired row for a token — or null. */
function pwreset_find($token) {
    pwreset_migrate();
    $token = trim((string)$token);
    if ($token === '' || !preg_match('/^[a-f0-9]{20,64}$/i', $token)) return null;
    $r = one("SELECT * FROM password_resets WHERE token=? AND used_at IS NULL", [$token]);
    if (!$r) return null;
    if ($r['expires_at'] && strtotime($r['expires_at']) < time()) return null;
    return $r;
}

/** Set the new password and burn every outstanding link for that person. */
function pwreset_complete($row, $newPassword) {
    q("UPDATE users SET pass_hash=? WHERE id=?", [password_hash($newPassword, PASSWORD_DEFAULT), (int)$row['user_id']]);
    q("UPDATE password_resets SET used_at=CURRENT_TIMESTAMP WHERE user_id=? AND used_at IS NULL", [(int)$row['user_id']]);
}

/** Requests still waiting, newest first — shown to William on the Members page
 *  so a family member is never stuck waiting on an email that didn't arrive. */
function pwreset_open() {
    pwreset_migrate();
    try {
        return all("SELECT r.*, u.name, u.email FROM password_resets r
                    JOIN users u ON u.id = r.user_id
                    WHERE r.used_at IS NULL AND (r.expires_at IS NULL OR r.expires_at > ?)
                    ORDER BY r.id DESC", [date('Y-m-d H:i:s')]);
    } catch (\Throwable $e) { return []; }
}

/** Too many attempts in the last hour? Keeps the form from being used to spam. */
function pwreset_throttled($user_id) {
    pwreset_migrate();
    try {
        $r = one("SELECT COUNT(*) c FROM password_resets WHERE user_id=? AND source='self' AND created_at > ?",
                 [(int)$user_id, date('Y-m-d H:i:s', time() - 3600)]);
        return $r && (int)$r['c'] >= 3;
    } catch (\Throwable $e) { return false; }
}

/** Best-effort email. Returns true only when the mail server accepted it —
 *  which is not the same as it arriving, so nothing user-facing promises that. */
function pwreset_mail($user, $url) {
    require_once __DIR__ . '/mailer.php';
    $name = trim((string)$user['name']) !== '' ? explode(' ', trim($user['name']))[0] : 'there';

    /* Replies go to William rather than into a no-reply void — an aunt who
       can't get in will answer the email rather than find the site again. */
    $admin = null;
    try { $admin = one("SELECT name,email FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1"); }
    catch (\Throwable $e) {}

    $subject = 'Reset your password — The Battles Legacy';
    $body = "Hello $name,\n\n"
          . "Someone asked to reset the password for your account on The Battles Legacy family website.\n\n"
          . "Open this link to choose a new password:\n$url\n\n"
          . "The link works once and expires in " . PWRESET_HOURS . " hours.\n\n"
          . "If you didn't ask for this, you can ignore this message — your password has not changed.\n\n"
          . "— The Battles Legacy\n";

    return mailer_send($user['email'] ?? '', $subject, $body, [
        'to_name'    => $user['name'] ?? '',
        'reply_to'   => $admin['email'] ?? '',
        'reply_name' => $admin['name'] ?? '',
    ]);
}
