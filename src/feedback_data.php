<?php
/** "Share Your Thoughts" — opinions and suggestions from everyone William invites
 *  to look at the site. Reviewers may not have a login yet, so this works signed
 *  out too; every note lands in William's inbox, and he can choose which ones the
 *  whole family gets to see and agree with. */
require_once __DIR__ . '/db.php';

function feedback_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS feedback (
      id $AI,
      name VARCHAR(120) DEFAULT '', contact VARCHAR(160) DEFAULT '',
      area VARCHAR(40) DEFAULT 'overall', kind VARCHAR(20) DEFAULT 'suggestion',
      rating INT NOT NULL DEFAULT 0, body TEXT,
      status VARCHAR(20) NOT NULL DEFAULT 'new', shared INT NOT NULL DEFAULT 0,
      agrees INT NOT NULL DEFAULT 0, reply TEXT, user_id INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    try { db()->exec("CREATE INDEX idx_fb_status ON feedback(status)"); } catch (\Throwable $e) {}
    try { db()->exec("CREATE INDEX idx_fb_shared ON feedback(shared)"); } catch (\Throwable $e) {}
    /* Saving a note back and actually sending it are two different things, and
       the page used to be unable to tell him which had happened. */
    db_add_column('feedback', 'reply_sent_at', 'DATETIME NULL');
    db_add_column('feedback', 'reply_ok', 'TINYINT NOT NULL DEFAULT 0');
    db()->exec("CREATE TABLE IF NOT EXISTS fb_meta (
      k VARCHAR(40) PRIMARY KEY, v VARCHAR(255) DEFAULT ''
    )$ENG");
}

/* ---- the little on/off switch for the floating tab ---- */
function fb_meta($k, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (all("SELECT k,v FROM fb_meta") as $r) $cache[$r['k']] = $r['v']; }
        catch (\Throwable $e) { $cache = []; }
    }
    return array_key_exists($k, $cache) ? $cache[$k] : $default;
}
function fb_meta_set($k, $v) {
    try {
        if (one("SELECT k FROM fb_meta WHERE k=?", [$k])) q("UPDATE fb_meta SET v=? WHERE k=?", [$v, $k]);
        else q("INSERT INTO fb_meta (k,v) VALUES (?,?)", [$k, $v]);
    } catch (\Throwable $e) { return false; }
    return true;
}
/** Is the little "Your thoughts" tab showing on every page? On by default. */
function fb_tab_on() { return fb_meta('tab', '1') !== '0'; }

/* ---- vocabulary ---- */
function fb_kinds() {
    return [
      'suggestion' => ['An idea or suggestion', 'idea'],
      'problem'    => ["Something isn't working", 'wrench'],
      'praise'     => ['Something I love',        'heart'],
      'question'   => ['A question',              'quest'],
    ];
}
function fb_kind_ok($k) { return array_key_exists($k, fb_kinds()); }
function fb_kind_label($k) { $a = fb_kinds(); return $a[$k][0] ?? $a['suggestion'][0]; }

/** Where on the site — plain English, in the order the menu shows them. */
function fb_areas() {
    return [
      'overall'    => 'The site overall',
      'home'       => 'Home page',
      'history'    => 'History',
      'tree'       => 'Family Tree',
      'faith'      => 'Faith',
      'enterprise' => 'Enterprise',
      'health'     => 'Health',
      'news'       => 'Family News',
      'memorial'   => 'Memorial',
      'aahistory'  => 'African American History',
      'photos'     => 'Photos',
      'other'      => 'Something else',
    ];
}
function fb_area_ok($a) { return array_key_exists($a, fb_areas()); }
function fb_area_label($a) { $x = fb_areas(); return $x[$a] ?? $x['overall']; }

/** Guess which page they came from so the form starts on the right area. */
function fb_area_from_referer() {
    if (empty($_SERVER['HTTP_REFERER'])) return 'overall';   // came in cold — don't guess
    $ref = basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '');
    if (substr($ref, -4) !== '.php') $ref = 'index.php';     // ".../legacy/" is the home page
    $map = [
      'index.php'=>'home', ''=>'home', 'history.php'=>'history', 'tree.php'=>'tree',
      'person.php'=>'tree', 'family.php'=>'tree', 'faith.php'=>'faith', 'ministers.php'=>'faith',
      'minister.php'=>'faith', 'enterprise.php'=>'enterprise', 'health.php'=>'health',
      'news.php'=>'news', 'news_all.php'=>'news', 'news_view.php'=>'news',
      'memorial.php'=>'memorial', 'tribute.php'=>'memorial', 'aahistory.php'=>'aahistory',
      'upload.php'=>'photos',
    ];
    return $map[$ref] ?? 'overall';
}

/* ---- reading and writing ---- */
function fb_add($f, $user) {
    $rating = (int)($f['rating'] ?? 0);
    q("INSERT INTO feedback (name,contact,area,kind,rating,body,status,user_id) VALUES (?,?,?,?,?,?,'new',?)",
      [mb_substr(trim($f['name'] ?? ''), 0, 120),
       mb_substr(trim($f['contact'] ?? ''), 0, 160),
       fb_area_ok($f['area'] ?? '') ? $f['area'] : 'overall',
       fb_kind_ok($f['kind'] ?? '') ? $f['kind'] : 'suggestion',
       ($rating >= 1 && $rating <= 5) ? $rating : 0,
       mb_substr(trim($f['body'] ?? ''), 0, 4000),
       $user['id'] ?? null]);
    return (int) insert_id();
}

function fb_all($status = '') {
    if ($status !== '') return all("SELECT * FROM feedback WHERE status=? ORDER BY id DESC", [$status]);
    return all("SELECT * FROM feedback ORDER BY id DESC");
}
function fb_one($id) { return one("SELECT * FROM feedback WHERE id=?", [(int)$id]); }
function fb_shared($limit = 0) {
    $sql = "SELECT * FROM feedback WHERE shared=1 ORDER BY agrees DESC, id DESC";
    if ($limit) $sql .= " LIMIT " . (int)$limit;
    return all($sql);
}
function fb_new_count() {
    try { $r = one("SELECT COUNT(*) c FROM feedback WHERE status='new'"); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}
function fb_total() {
    try { $r = one("SELECT COUNT(*) c FROM feedback"); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}
/** Average star rating, ignoring the notes that left it blank. */
function fb_avg_rating() {
    try {
        $r = one("SELECT AVG(rating) a, COUNT(*) c FROM feedback WHERE rating > 0");
        if (!$r || !(int)$r['c']) return [0, 0];
        return [round((float)$r['a'], 1), (int)$r['c']];
    } catch (\Throwable $e) { return [0, 0]; }
}

function fb_set_status($id, $s) {
    if (!in_array($s, ['new','reading','done'], true)) return;
    q("UPDATE feedback SET status=? WHERE id=?", [$s, (int)$id]);
}

/** Clear the count beside Feedback in the menu in one go. Nothing is deleted
 *  and nothing is answered — they move from New to Looking into it. */
function fb_mark_all_read() {
    feedback_migrate();
    try {
        $n = fb_new_count();
        q("UPDATE feedback SET status='reading' WHERE status='new'");
        return $n;
    } catch (\Throwable $e) { return 0; }
}

/** An address to write back to, or ''.
 *
 *  Two places it can come from and they are not the same: the "best way to
 *  reach you" box is free text and often holds a phone number, while a signed-in
 *  member already has a real address on their account. Prefer what they typed —
 *  somebody who wrote an address on the form is asking to be answered there. */
function fb_reply_email($row) {
    require_once __DIR__ . '/mailer.php';
    $typed = mailer_valid($row['contact'] ?? '');
    if ($typed !== '') return $typed;
    if (!empty($row['user_id'])) {
        try {
            $u = one("SELECT email FROM users WHERE id=?", [(int)$row['user_id']]);
            if ($u) return mailer_valid($u['email']);
        } catch (\Throwable $e) {}
    }
    return '';
}

/** The reply as it would arrive, whether by email or pasted into a text. */
function fb_reply_text($row, $hostName = 'William') {
    $first = trim((string)($row['name'] ?? ''));
    if ($first !== '') { $b = preg_split('/\s+/', $first); $first = $b[0]; }
    $quote = trim((string)($row['body'] ?? ''));
    if (mb_strlen($quote) > 400) $quote = mb_substr($quote, 0, 400) . '…';
    $out  = 'Hello ' . ($first !== '' ? $first : 'there') . ",\n\n";
    $out .= "Thank you for what you sent through the family site. You wrote:\n\n";
    $out .= '"' . $quote . "\"\n\n";
    $out .= trim((string)($row['reply'] ?? '')) . "\n\n";
    $out .= $hostName . "\n";
    return $out;
}

/** Email the saved reply to whoever sent the thought.
 *  Returns [sent(bool), message]. Records the attempt either way, because
 *  "did I already answer this one?" is the question this page has to answer. */
function fb_send_reply($id, $host = null) {
    feedback_migrate();
    require_once __DIR__ . '/mailer.php';
    $row = fb_one($id);
    if (!$row) return [false, 'That thought is no longer there.'];
    if (trim((string)$row['reply']) === '') return [false, 'Write your note back first — there is nothing to send yet.'];
    $to = fb_reply_email($row);
    if ($to === '') return [false, 'There is no email address for ' . ($row['name'] ?: 'this person') . ', so there is nowhere to send it.'];

    $who  = ($host && trim((string)$host['name']) !== '') ? $host['name'] : 'William';
    $site = (string)config('site_name') ?: 'The Battles Legacy';
    $sent = mailer_send($to, 'Re: what you sent through ' . $site, fb_reply_text($row, $who), [
        'to_name'    => (string)$row['name'],
        'reply_to'   => $host ? (string)$host['email'] : '',
        'reply_name' => $who,
    ]);
    try {
        q("UPDATE feedback SET reply_sent_at=?, reply_ok=? WHERE id=?",
          [date('Y-m-d H:i:s'), $sent ? 1 : 0, (int)$id]);
    } catch (\Throwable $e) {}
    /* "Handed to the mail server" is all we know - see the note at the top of
       mailer.php. It is not the same as "she read it". */
    return [$sent, $sent
        ? 'Handed to the mail server for ' . $to . '. That is not proof it arrived — if it matters, text it as well.'
        : 'The mail server would not take it.'];
}
function fb_set_shared($id, $on) { q("UPDATE feedback SET shared=? WHERE id=?", [$on ? 1 : 0, (int)$id]); }
function fb_set_reply($id, $t)   { q("UPDATE feedback SET reply=? WHERE id=?", [mb_substr(trim($t), 0, 2000), (int)$id]); }
function fb_delete($id)          { q("DELETE FROM feedback WHERE id=?", [(int)$id]); }

/** "I agree too" — one per browser session, same as the news likes. */
function fb_agree($id) {
    if (!isset($_SESSION['fb_agreed'])) $_SESSION['fb_agreed'] = [];
    if (isset($_SESSION['fb_agreed'][$id])) return false;
    $_SESSION['fb_agreed'][$id] = 1;
    q("UPDATE feedback SET agrees = agrees + 1 WHERE id=?", [(int)$id]);
    return true;
}
function fb_agreed($id) { return isset($_SESSION['fb_agreed'][$id]); }

/** Light spam brake: nobody needs to send six notes in five minutes. */
function fb_too_fast() {
    $t = $_SESSION['fb_times'] ?? [];
    $t = array_values(array_filter($t, function ($x) { return $x > time() - 300; }));
    $_SESSION['fb_times'] = $t;
    return count($t) >= 5;
}
function fb_mark_sent() { $_SESSION['fb_times'][] = time(); }

function fb_ago($ts) {
    $t = strtotime((string)$ts); if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d/60) . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    if ($d < 2592000) return floor($d/86400) . ' day' . (floor($d/86400) == 1 ? '' : 's') . ' ago';
    return date('M j, Y', $t);
}

function fb_stars($n, $cls = '') {
    $n = (int)$n; if ($n < 1) return '';
    $out = '<span class="fb-stars ' . $cls . '">';
    for ($i = 1; $i <= 5; $i++) $out .= '<span' . ($i <= $n ? ' class="on"' : '') . '>&#9733;</span>';
    return $out . '</span>';
}

function fb_initials($name) {
    $p = preg_split('/\s+/', trim(strip_tags((string)$name)));
    if (!$p || $p[0] === '') return '&#10086;';
    return e(strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : '')));
}

function fb_icon($n, $s = 20) {
    $p = [
      'idea'  => '<circle cx="12" cy="10" r="5"/><line x1="10" y1="18" x2="14" y2="18"/><line x1="11" y1="21" x2="13" y2="21"/>',
      'wrench'=> '<path d="M20 6a5 5 0 0 1-6.5 6.4L6 20l-2-2 7.6-7.5A5 5 0 0 1 18 4l-3 3 2 2 3-3z"/>',
      'heart' => '<path d="M12 20s-7-4.6-7-9a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 4.4-7 9-7 9z"/>',
      'quest' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.3 2.4c-.6.2-.8.7-.8 1.3v.3"/><line x1="12" y1="17" x2="12" y2="17.01"/>',
      'chat'  => '<path d="M21 12a8 8 0 0 1-8 8H7l-4 3 1-5a8 8 0 1 1 17-6z"/>',
      'back'  => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
    ];
    return '<svg class="fbi" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . ($p[$n] ?? $p['chat']) . '</svg>';
}
