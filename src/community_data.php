<?php
/** Community submissions — family members post Questions, Recipes, and Updates
 *  (and Answers to questions). Everything a member submits waits for admin
 *  approval before it shows. Admin submissions auto-publish. */
require_once __DIR__ . '/db.php';

function community_migrate() {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS community_posts (
      id $AI, kind VARCHAR(16) NOT NULL, parent_id INT NOT NULL DEFAULT 0,
      title VARCHAR(200) DEFAULT '', body TEXT, author VARCHAR(160) DEFAULT '',
      meta VARCHAR(200) DEFAULT '', photo VARCHAR(255) DEFAULT '', likes INT NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'pending', user_id INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    try { db()->exec("CREATE INDEX idx_comm_ks ON community_posts(kind,status)"); } catch (Exception $e) {}
    try { db()->exec("CREATE INDEX idx_comm_parent ON community_posts(parent_id)"); } catch (Exception $e) {}
}

function comm_kinds() {
    /* healthtip is its own kind so a tip shared from the Health page comes back
       to the Health page, instead of disappearing into the general updates. */
    return ['question'=>'Question','recipe'=>'Recipe','update'=>'Update','healthtip'=>'Health Tip','answer'=>'Answer'];
}
function comm_kind_ok($k) { return array_key_exists($k, comm_kinds()); }

/** insert a submission. returns id. admins auto-publish; members go pending. */
function comm_add($kind, $f, $user, $parent = 0) {
    $isAdmin = $user && ($user['role'] ?? '') === 'admin';
    $status  = $isAdmin ? 'published' : 'pending';
    q("INSERT INTO community_posts (kind,parent_id,title,body,author,meta,photo,status,user_id) VALUES (?,?,?,?,?,?,?,?,?)",
      [$kind, (int)$parent, mb_substr(trim($f['title'] ?? ''),0,200), trim($f['body'] ?? ''),
       trim($f['author'] ?? ($user['name'] ?? 'Family member')), trim($f['meta'] ?? ''), trim($f['photo'] ?? ''),
       $status, $user['id'] ?? null]);
    return (int) insert_id();
}

function comm_list($kind, $status = 'published', $limit = null) {
    $sql = "SELECT * FROM community_posts WHERE kind=? AND parent_id=0 AND status=? ORDER BY created_at DESC, id DESC";
    if ($limit) $sql .= " LIMIT " . (int)$limit;
    return all($sql, [$kind, $status]);
}
function comm_one($id) { return one("SELECT * FROM community_posts WHERE id=?", [(int)$id]); }
function comm_answers($qid, $status = 'published') {
    return all("SELECT * FROM community_posts WHERE kind='answer' AND parent_id=? AND status=? ORDER BY created_at, id", [(int)$qid, $status]);
}
function comm_answer_count($qid) {
    $r = one("SELECT COUNT(*) c FROM community_posts WHERE kind='answer' AND parent_id=? AND status='published'", [(int)$qid]);
    return $r ? (int)$r['c'] : 0;
}

/* admin moderation */
function comm_pending() { return all("SELECT * FROM community_posts WHERE status='pending' ORDER BY created_at DESC, id DESC"); }
function comm_pending_count() { $r = one("SELECT COUNT(*) c FROM community_posts WHERE status='pending'"); return $r ? (int)$r['c'] : 0; }
function comm_approve($id) { q("UPDATE community_posts SET status='published' WHERE id=?", [(int)$id]); }
function comm_decline($id) { q("UPDATE community_posts SET status='declined' WHERE id=?", [(int)$id]); }
function comm_delete($id) {
    $p = comm_one($id);
    if ($p && !empty($p['photo']) && strpos($p['photo'], 'uploads/') !== false) { $abs = dirname(__DIR__) . '/public/' . $p['photo']; if (is_file($abs)) @unlink($abs); }
    q("DELETE FROM community_posts WHERE id=? OR parent_id=?", [(int)$id, (int)$id]); // remove answers too
}

/** session-guarded like (one per browser session) */
function comm_like($id) {
    if (!isset($_SESSION['comm_liked'])) $_SESSION['comm_liked'] = [];
    if (isset($_SESSION['comm_liked'][$id])) return false;
    $_SESSION['comm_liked'][$id] = 1;
    q("UPDATE community_posts SET likes = likes + 1 WHERE id=?", [(int)$id]);
    return true;
}
function comm_liked($id) { return isset($_SESSION['comm_liked'][$id]); }

function comm_ago($ts) {
    $t = strtotime((string)$ts); if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d/60) . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    if ($d < 2592000) return floor($d/86400) . ' day' . (floor($d/86400)==1?'':'s') . ' ago';
    return date('M j, Y', $t);
}

function comm_store_photo($field = 'photo') {
    $rel = 'assets/news/uploads';
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return ['', ''];
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return ['', 'The photo could not be uploaded — please try again.'];
    $tmp  = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!$info || !isset($allowed[$info['mime']])) return ['', 'That file is not a photo (JPG, PNG, GIF or WEBP only).'];
    if ($_FILES[$field]['size'] > 12 * 1024 * 1024) return ['', 'That image is larger than 12 MB — please pick a smaller one.'];
    $ext   = $allowed[$info['mime']];
    $fname = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $absDir = dirname(__DIR__) . '/public/' . $rel;
    @mkdir($absDir, 0775, true);
    if (!move_uploaded_file($tmp, $absDir . '/' . $fname)) return ['', 'Sorry — the photo could not be saved.'];
    return [$rel . '/' . $fname, ''];
}
function comm_mono($name) {
    $p = preg_split('/\s+/', trim(strip_tags($name)));
    if (!$p || $p[0] === '') return '&#10086;';
    return e(strtoupper(substr($p[0],0,1) . (isset($p[1]) ? substr($p[1],0,1) : '')));
}
