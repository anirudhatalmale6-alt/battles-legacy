<?php
/** Memorial tributes — per-person tribute details, candle counts, and the
 *  family "Memories & Condolences" feed. Idempotent migration. */
require_once __DIR__ . '/db.php';

function mem_migrate() {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS memorial_meta (
      pid VARCHAR(16) PRIMARY KEY, tribute VARCHAR(600) DEFAULT '', known_for VARCHAR(300) DEFAULT '',
      faith VARCHAR(140) DEFAULT '', legacy VARCHAR(240) DEFAULT '', scripture VARCHAR(400) DEFAULT '',
      scripture_ref VARCHAR(140) DEFAULT '', candles INT NOT NULL DEFAULT 0, hidden INT NOT NULL DEFAULT 0,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    db()->exec("CREATE TABLE IF NOT EXISTS memorial_condolences (
      id $AI, pid VARCHAR(16) NOT NULL, user_id INT NULL, author VARCHAR(160) DEFAULT '',
      body VARCHAR(1500) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'visible',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    try { db()->exec("CREATE INDEX idx_cond_pid ON memorial_condolences(pid)"); } catch (Exception $e) {}
}

/** meta row for a person (always returns a full array with defaults) */
function mem_meta($pid) {
    $r = one("SELECT * FROM memorial_meta WHERE pid=?", [$pid]);
    if (!$r) $r = ['pid'=>$pid,'tribute'=>'','known_for'=>'','faith'=>'','legacy'=>'','scripture'=>'','scripture_ref'=>'','candles'=>0];
    return $r;
}

function mem_ensure_meta($pid) {
    if (!one("SELECT pid FROM memorial_meta WHERE pid=?", [$pid])) {
        q("INSERT INTO memorial_meta (pid) VALUES (?)", [$pid]);
    }
}

/** hide (1) or restore (0) a person from the Memorial listing */
function mem_set_hidden($pid, $hidden) {
    mem_ensure_meta($pid);
    q("UPDATE memorial_meta SET hidden=? WHERE pid=?", [$hidden ? 1 : 0, $pid]);
}

function mem_light_candle($pid) {
    mem_ensure_meta($pid);
    q("UPDATE memorial_meta SET candles = candles + 1 WHERE pid=?", [$pid]);
    $r = one("SELECT candles FROM memorial_meta WHERE pid=?", [$pid]);
    return $r ? (int)$r['candles'] : 0;
}

function mem_save_meta($pid, $f) {
    mem_ensure_meta($pid);
    q("UPDATE memorial_meta SET tribute=?, known_for=?, faith=?, legacy=?, scripture=?, scripture_ref=?, updated_at=CURRENT_TIMESTAMP WHERE pid=?",
      [$f['tribute'], $f['known_for'], $f['faith'], $f['legacy'], $f['scripture'], $f['scripture_ref'], $pid]);
}

function mem_condolences($pid) {
    return all("SELECT * FROM memorial_condolences WHERE pid=? AND status='visible' ORDER BY created_at DESC, id DESC", [$pid]);
}

function mem_add_condolence($pid, $author, $body, $user_id) {
    q("INSERT INTO memorial_condolences (pid,user_id,author,body) VALUES (?,?,?,?)",
      [$pid, $user_id, $author, $body]);
}

function mem_delete_condolence($id) {
    q("UPDATE memorial_condolences SET status='hidden' WHERE id=?", [(int)$id]);
}

/** "time ago" for a timestamp string */
function mem_ago($ts) {
    $t = strtotime($ts);
    if (!$t) return '';
    $diff = time() - $t;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hr ago';
    if ($diff < 2592000) return floor($diff/86400) . ' day' . (floor($diff/86400)==1?'':'s') . ' ago';
    return date('M j, Y', $t);
}
