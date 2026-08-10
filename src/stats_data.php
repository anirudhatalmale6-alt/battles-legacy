<?php
/** Private, built-in visitor statistics.
 *  Nothing leaves the server and no third party is involved. Visitor addresses
 *  are stored only as a one-way hash so we can count unique visitors without
 *  keeping anyone's IP address. */
require_once __DIR__ . '/db.php';

function stats_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    try {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS page_views (
      id $AI, page VARCHAR(120) NOT NULL, title VARCHAR(160) DEFAULT '',
      visitor VARCHAR(40) NOT NULL DEFAULT '', member VARCHAR(120) DEFAULT '',
      day VARCHAR(10) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    foreach (["CREATE INDEX idx_pv_day ON page_views(day)",
              "CREATE INDEX idx_pv_page ON page_views(page)"] as $s) {
        try { db()->exec($s); } catch (\Throwable $e) {}
    }
    } catch (\Throwable $e) { /* statistics must never break the site */ }
}

/** pages we never count (admin screens, the tracker's own page, assets) */
function stats_ignored($page) {
    $skip = ['stats.php','admin.php','moderate.php','tree_review.php','logout.php',
             'enterprise_manage.php','faith_manage.php','news_manage.php','health_manage.php','data.php'];
    return in_array($page, $skip, true);
}

/** record one page view; called from page_head(). Never breaks the page. */
function stats_record($title = '') {
    try {
        if (!function_exists('db')) return;
        $page = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
        if ($page === '' ) $page = 'index.php';
        if (stats_ignored($page)) return;
        if (!preg_match('/\.php$/', $page)) return;
        stats_migrate();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // ignore obvious bots so the numbers reflect real family visits
        if ($ua && preg_match('/bot|crawl|spider|slurp|bingpreview|headless/i', $ua)) return;
        $visitor = substr(hash('sha256', $ip . '|' . $ua . '|battles-salt'), 0, 32);
        $u = function_exists('current_user') ? current_user() : null;
        q("INSERT INTO page_views (page,title,visitor,member,day) VALUES (?,?,?,?,?)",
          [$page, substr((string)$title, 0, 160), $visitor, $u['name'] ?? '', date('Y-m-d')]);
    } catch (\Throwable $e) { /* statistics must never interrupt a page */ }
}

/* ---------- reporting helpers ---------- */
function stats_since($days) { return date('Y-m-d', strtotime("-" . (int)$days . " days")); }

function stats_totals($days = 30) {
    $since = stats_since($days);
    $v = one("SELECT COUNT(*) c FROM page_views WHERE day >= ?", [$since]);
    $u = one("SELECT COUNT(DISTINCT visitor) c FROM page_views WHERE day >= ?", [$since]);
    $m = one("SELECT COUNT(DISTINCT member) c FROM page_views WHERE day >= ? AND member <> ''", [$since]);
    return ['views' => $v ? (int)$v['c'] : 0, 'visitors' => $u ? (int)$u['c'] : 0, 'members' => $m ? (int)$m['c'] : 0];
}
function stats_top_pages($days = 30, $limit = 12) {
    return all("SELECT page, MAX(title) title, COUNT(*) views, COUNT(DISTINCT visitor) visitors
                FROM page_views WHERE day >= ? GROUP BY page ORDER BY views DESC LIMIT " . (int)$limit, [stats_since($days)]);
}
function stats_by_day($days = 14) {
    $rows = all("SELECT day, COUNT(*) views, COUNT(DISTINCT visitor) visitors
                 FROM page_views WHERE day >= ? GROUP BY day ORDER BY day", [stats_since($days)]);
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $out[$d] = ['views'=>0,'visitors'=>0]; }
    foreach ($rows as $r) if (isset($out[$r['day']])) $out[$r['day']] = ['views'=>(int)$r['views'],'visitors'=>(int)$r['visitors']];
    return $out;
}
function stats_members($days = 30, $limit = 12) {
    return all("SELECT member, COUNT(*) views, MAX(created_at) last_seen
                FROM page_views WHERE day >= ? AND member <> '' GROUP BY member ORDER BY views DESC LIMIT " . (int)$limit, [stats_since($days)]);
}
function stats_recent($limit = 15) {
    return all("SELECT page, title, member, created_at FROM page_views ORDER BY id DESC LIMIT " . (int)$limit);
}
function stats_friendly($page) {
    $map = ['index.php'=>'Home','tree.php'=>'Family Tree','history.php'=>'History','faith.php'=>'Faith',
            'enterprise.php'=>'Enterprise','health.php'=>'Health','news.php'=>'Family News',
            'memorial.php'=>'Memorial','aahistory.php'=>'African American History','person.php'=>'A person\'s profile',
            'tribute.php'=>'A memorial tribute','minister.php'=>'A minister profile','ministers.php'=>'Ministry Family',
            'login.php'=>'Login','upload.php'=>'Add a Photo','community_list.php'=>'Questions / Recipes / Updates',
            'community_view.php'=>'A question or recipe','community_submit.php'=>'Submit form',
            'enterprise_submit.php'=>'Submit to Enterprise','section.php'=>'Section page'];
    return $map[$page] ?? $page;
}
function stats_ago($ts) {
    $t = strtotime((string)$ts); if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d/60) . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    if ($d < 2592000) return floor($d/86400) . ' day' . (floor($d/86400)==1?'':'s') . ' ago';
    return date('M j, Y', $t);
}
