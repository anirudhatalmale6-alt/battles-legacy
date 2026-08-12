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

    // Ministry family — a few featured ministers (past & present), each with a photo + profile.
    db()->exec("CREATE TABLE IF NOT EXISTS faith_ministers (
      id $AI, name VARCHAR(160) NOT NULL, role VARCHAR(160) DEFAULT '', era VARCHAR(20) NOT NULL DEFAULT 'present',
      church VARCHAR(200) DEFAULT '', years VARCHAR(80) DEFAULT '', bio TEXT, photo VARCHAR(255) DEFAULT '',
      sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");

    // Prayer warriors — family who sign up to pray over the requests.
    db()->exec("CREATE TABLE IF NOT EXISTS faith_warriors (
      id $AI, name VARCHAR(160) NOT NULL, contact VARCHAR(190) DEFAULT '', note VARCHAR(600) DEFAULT '',
      user_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");

    // Featured videos — sermons, testimonies, songs. Same idea as Enterprise.
    db()->exec("CREATE TABLE IF NOT EXISTS faith_videos (
      id $AI, title VARCHAR(200) NOT NULL, description VARCHAR(500) DEFAULT '', url VARCHAR(255) DEFAULT '',
      duration VARCHAR(20) DEFAULT '', featured INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'published', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    try { db()->exec("CREATE INDEX idx_fv_status ON faith_videos(status)"); } catch (\Throwable $e) {}
}

/* ---------------- Featured videos ---------------- */
function faith_videos($all = false) {
    try {
        $w = $all ? '' : "WHERE status='published'";
        return all("SELECT * FROM faith_videos $w ORDER BY featured DESC, sort, id DESC");
    } catch (\Throwable $e) { return []; }
}
function faith_video($id) { return one("SELECT * FROM faith_videos WHERE id=?", [(int)$id]); }
function faith_video_next_sort() {
    $r = one("SELECT MAX(sort) m FROM faith_videos");
    return ($r && $r['m'] !== null) ? ((int)$r['m'] + 1) : 0;
}
function faith_delete_video($id) { q("DELETE FROM faith_videos WHERE id=?", [(int)$id]); }
/** Is any video currently the big one? */
function faith_one_featured() {
    try { $r = one("SELECT id FROM faith_videos WHERE featured=1 AND status='published'"); return $r ? (int)$r['id'] : 0; }
    catch (\Throwable $e) { return 0; }
}
/** Only one video wears the "featured" crown at a time. */
function faith_set_featured($id) {
    q("UPDATE faith_videos SET featured=0");
    q("UPDATE faith_videos SET featured=1 WHERE id=?", [(int)$id]);
}

/** Pull the YouTube/Vimeo id out of any of the shapes people paste. */
function faith_yt_id($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m)) return $m[1];
    return '';
}
/** A real thumbnail when we can work one out, so the list isn't a row of grey boxes. */
function faith_video_thumb($v) {
    $id = faith_yt_id($v['url'] ?? '');
    return $id ? 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg' : '';
}
/** Normalise a pasted link so "youtube.com/watch?v=x" still opens. */
function faith_video_url($v) {
    $u = trim((string)($v['url'] ?? ''));
    if ($u === '') return '';
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    return $u;
}

/* ---------------- Ministry family ---------------- */
function faith_ministers($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM faith_ministers $w ORDER BY sort, id");
}
function faith_minister($id) { return one("SELECT * FROM faith_ministers WHERE id=?", [(int)$id]); }
function faith_minister_next_sort() {
    $r = one("SELECT MAX(sort) m FROM faith_ministers");
    return ($r && $r['m'] !== null) ? ((int)$r['m'] + 1) : 0;
}
function faith_delete_minister($id) { q("DELETE FROM faith_ministers WHERE id=?", [(int)$id]); }

/* ---------------- Prayer warriors ---------------- */
function faith_add_warrior($name, $contact, $note, $user_id) {
    q("INSERT INTO faith_warriors (name,contact,note,user_id) VALUES (?,?,?,?)",
      [$name, $contact, mb_substr($note, 0, 600), $user_id]);
}
function faith_warriors()      { return all("SELECT * FROM faith_warriors ORDER BY created_at DESC, id DESC"); }
function faith_warrior_count() { $r = one("SELECT COUNT(*) c FROM faith_warriors"); return $r ? (int)$r['c'] : 0; }
function faith_delete_warrior($id) { q("DELETE FROM faith_warriors WHERE id=?", [(int)$id]); }

/** Save a minister photo (JPG/PNG/GIF/WEBP <=12MB) -> assets/faith/ministers/. Returns [relPath, error]. */
function faith_store_photo($field = 'photo', $existing = '') {
    $rel = 'assets/faith/ministers';
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return [$existing, ''];
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return [$existing, 'The photo could not be uploaded — please try again.'];
    $tmp  = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!$info || !isset($allowed[$info['mime']])) return [$existing, 'That file is not a photo (JPG, PNG, GIF or WEBP only).'];
    if ($_FILES[$field]['size'] > 12 * 1024 * 1024) return [$existing, 'That image is larger than 12 MB — please pick a smaller one.'];
    $ext   = $allowed[$info['mime']];
    $fname = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $absDir = dirname(__DIR__) . '/public/' . $rel;
    @mkdir($absDir, 0775, true);
    if (!move_uploaded_file($tmp, $absDir . '/' . $fname)) return [$existing, 'Sorry — the photo could not be saved.'];
    return [$rel . '/' . $fname, ''];
}
function faith_mono($name) {
    $clean = trim(preg_replace('/\s+/', ' ', strip_tags($name)));
    $parts = array_values(array_filter(explode(' ', $clean)));
    if (!$parts) return '&#10013;';
    $ini = strtoupper(substr($parts[0],0,1) . (count($parts)>1 ? substr(end($parts),0,1) : ''));
    return $ini !== '' ? e($ini) : '&#10013;';
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
