<?php
/** Faith section — prayer requests (family submits, William reviews).
 *  Idempotent migration; other Faith content (salvation prayer, ministry
 *  family, scripture library) is authored in faith.php for now. */
require_once __DIR__ . '/db.php';

function faith_migrate() {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS faith_prayers (
      id $AI, name VARCHAR(160) DEFAULT '', subject VARCHAR(200) DEFAULT '',
      body VARCHAR(2000) NOT NULL, is_private INT NOT NULL DEFAULT 0, may_contact INT NOT NULL DEFAULT 0,
      prayed INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'new',
      user_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    try { db()->exec("CREATE INDEX idx_fp_status ON faith_prayers(status)"); } catch (Exception $e) {}
}

/** store a submitted prayer request */
function faith_add_prayer($name, $subject, $body, $is_private, $may_contact, $user_id) {
    q("INSERT INTO faith_prayers (name,subject,body,is_private,may_contact,user_id) VALUES (?,?,?,?,?,?)",
      [$name, $subject, $body, $is_private ? 1 : 0, $may_contact ? 1 : 0, $user_id]);
}

/** prayers for the admin review list (active, or archived) */
function faith_prayers($archived = false) {
    $st = $archived ? "status='archived'" : "status='new'";
    return all("SELECT * FROM faith_prayers WHERE $st ORDER BY prayed ASC, created_at DESC, id DESC");
}
function faith_prayer_count() {
    $r = one("SELECT COUNT(*) c FROM faith_prayers WHERE status='new'");
    return $r ? (int)$r['c'] : 0;
}
function faith_mark_prayed($id, $val = 1) { q("UPDATE faith_prayers SET prayed=? WHERE id=?", [$val ? 1 : 0, (int)$id]); }
function faith_archive_prayer($id)        { q("UPDATE faith_prayers SET status='archived' WHERE id=?", [(int)$id]); }
function faith_restore_prayer($id)        { q("UPDATE faith_prayers SET status='new' WHERE id=?", [(int)$id]); }
function faith_delete_prayer($id)         { q("DELETE FROM faith_prayers WHERE id=?", [(int)$id]); }

/** "time ago" helper (shared style with the memorial feed) */
function faith_ago($ts) {
    $t = strtotime($ts); if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d/60) . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    if ($d < 2592000) return floor($d/86400) . ' day' . (floor($d/86400)==1?'':'s') . ' ago';
    return date('M j, Y', $t);
}
