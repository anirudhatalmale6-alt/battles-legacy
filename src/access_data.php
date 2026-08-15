<?php
/** "Can I join?" — someone a family member has shared the site with asks to
 *  come in, and William approves once he has recognised the name.
 *
 *  Approving does not create the account. It creates the same invitation link
 *  the Members page already makes, so the person still chooses their own
 *  password and nothing here ever holds one. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function ar_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS access_requests (
          id $AI, name VARCHAR(160) NOT NULL, email VARCHAR(190) DEFAULT '',
          phone VARCHAR(40) DEFAULT '', relation VARCHAR(300) DEFAULT '',
          note VARCHAR(1000) DEFAULT '', referred_by VARCHAR(160) DEFAULT '',
          status VARCHAR(20) NOT NULL DEFAULT 'new', invite_token VARCHAR(64) DEFAULT '',
          decided_by INT NULL, decided_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )$ENG");
        db()->exec("CREATE INDEX idx_ar_status ON access_requests(status)");
    } catch (\Throwable $e) { /* the index exists on every run after the first */ }
}

function ar_add($f) {
    ar_migrate();
    q("INSERT INTO access_requests (name,email,phone,relation,note,referred_by) VALUES (?,?,?,?,?,?)",
      [mb_substr(trim($f['name'] ?? ''), 0, 160), mb_substr(strtolower(trim($f['email'] ?? '')), 0, 190),
       mb_substr(trim($f['phone'] ?? ''), 0, 40), mb_substr(trim($f['relation'] ?? ''), 0, 300),
       mb_substr(trim($f['note'] ?? ''), 0, 1000), mb_substr(trim($f['referred_by'] ?? ''), 0, 160)]);
}

function ar_list($status = 'new') {
    ar_migrate();
    try {
        return $status === 'all'
            ? all("SELECT * FROM access_requests ORDER BY id DESC")
            : all("SELECT * FROM access_requests WHERE status=? ORDER BY id DESC", [$status]);
    } catch (\Throwable $e) { return []; }
}

function ar_count($status = 'new') {
    ar_migrate();
    try { $r = one("SELECT COUNT(*) c FROM access_requests WHERE status=?", [$status]); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}

function ar_get($id) { ar_migrate(); return one("SELECT * FROM access_requests WHERE id=?", [(int)$id]); }

/** Already a member, or already asked? Keeps William from reading the same
 *  request three times because someone pressed the button three times. */
function ar_already($email) {
    ar_migrate();
    $email = strtolower(trim((string)$email));
    if ($email === '') return '';
    try {
        if (one("SELECT id FROM users WHERE email=?", [$email])) return 'member';
        if (one("SELECT id FROM access_requests WHERE email=? AND status='new'", [$email])) return 'pending';
    } catch (\Throwable $e) {}
    return '';
}

/** Approve: make an invitation for them and remember which one it was. */
function ar_approve($id, $role, $adminId) {
    $r = ar_get($id);
    if (!$r || $r['status'] !== 'new') return null;
    $role  = in_array($role, ['member','moderator','admin'], true) ? $role : 'member';
    $token = bin2hex(random_bytes(20));
    q("INSERT INTO invites (token,name,email,role,invited_by,expires_at) VALUES (?,?,?,?,?,?)",
      [$token, $r['name'], $r['email'], $role, $adminId, date('Y-m-d H:i:s', time() + 30 * 86400)]);
    q("UPDATE access_requests SET status='approved', invite_token=?, decided_by=?, decided_at=? WHERE id=?",
      [$token, $adminId, date('Y-m-d H:i:s'), (int)$id]);
    return base_url() . '/register.php?token=' . $token;
}

function ar_decline($id, $adminId) {
    ar_migrate();
    q("UPDATE access_requests SET status='declined', decided_by=?, decided_at=? WHERE id=? AND status='new'",
      [$adminId, date('Y-m-d H:i:s'), (int)$id]);
}

function ar_delete($id) { ar_migrate(); q("DELETE FROM access_requests WHERE id=?", [(int)$id]); }

/** Anyone in the tree whose name is close to what they typed — so William can
 *  see "yes, there is a Dianne Battles" without leaving the page. */
function ar_tree_matches($name, $limit = 5) {
    $name = trim((string)$name);
    if ($name === '') return [];
    $words = array_values(array_filter(preg_split('/\s+/', $name), function ($w) { return mb_strlen($w) > 2; }));
    if (!$words) return [];
    $out = []; $seen = [];
    foreach ($words as $w) {
        try { $rows = all("SELECT pid,name,birth_date,living FROM persons WHERE name LIKE ? ORDER BY name", ['%' . $w . '%']); }
        catch (\Throwable $e) { $rows = []; }
        foreach ($rows as $r) {
            if (isset($seen[$r['pid']])) continue;
            $seen[$r['pid']] = true; $out[] = $r;
            if (count($out) >= $limit) return $out;
        }
    }
    return $out;
}
