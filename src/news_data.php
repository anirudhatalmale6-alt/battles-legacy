<?php
/** Family News — announcements/news posts + upcoming events.
 *  Idempotent migration; seeds a few sample entries once so the page looks
 *  complete, which William then edits from the manage screen. */
require_once __DIR__ . '/db.php';

function news_migrate() {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS news_posts (
      id $AI, category VARCHAR(20) NOT NULL DEFAULT 'news', date_label VARCHAR(60) DEFAULT '',
      title VARCHAR(200) NOT NULL, body TEXT, photo VARCHAR(255) DEFAULT '',
      likes INT NOT NULL DEFAULT 0, comments INT NOT NULL DEFAULT 0,
      sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    db()->exec("CREATE TABLE IF NOT EXISTS news_events (
      id $AI, mon VARCHAR(4) DEFAULT '', day VARCHAR(4) DEFAULT '', title VARCHAR(200) NOT NULL,
      place VARCHAR(200) DEFAULT '', time_label VARCHAR(100) DEFAULT '',
      sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    news_seed();
}

function news_seed() {
    if (!one("SELECT id FROM news_posts LIMIT 1")) {
        $posts = [
          ['graduation','May 15, 2024','Congratulations to Sydney Battles!','Sydney graduated Summa Cum Laude from Howard University with a degree in Psychology.','',32,8],
          ['birth','May 12, 2024','Welcome Baby Aaliyah Grace!','Proud parents Jasmine & Michael Battles welcomed their beautiful baby girl on May 10, 2024.','',45,12],
          ['marriage','April 27, 2024','Mr. & Mrs. Jordan Battles','Jordan Battles and Brianna Smith were united in marriage on April 26, 2024 in Dallas, TX.','',57,15],
          ['memory','April 20, 2024','Remembering Evelyn Mosley','A beautiful soul who touched so many lives. Forever in our hearts.','',68,21],
        ];
        $i = 0; foreach ($posts as $p) {
            q("INSERT INTO news_posts (category,date_label,title,body,photo,likes,comments,sample,sort) VALUES (?,?,?,?,?,?,?,1,?)",
              [$p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$i++]);
        }
    }
    if (!one("SELECT id FROM news_events LIMIT 1")) {
        $events = [
          ['JUN','21','Family Reunion 2024','Tyler Rose Garden Center, Tyler, Texas','10:00 AM – 4:00 PM'],
          ['JUL','04','Independence Day Family Picnic','Bob Woodruff Park, Plano, TX','11:00 AM – 3:00 PM'],
          ['AUG','10','Youth Leadership Workshop','Online Event','10:00 AM – 12:00 PM'],
        ];
        $i = 0; foreach ($events as $e) {
            q("INSERT INTO news_events (mon,day,title,place,time_label,sample,sort) VALUES (?,?,?,?,?,1,?)",
              [$e[0],$e[1],$e[2],$e[3],$e[4],$i++]);
        }
    }
}

/* ---- read helpers (published unless $all) ---- */
function news_posts($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM news_posts $w ORDER BY sort, id");
}
function news_events($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM news_events $w ORDER BY sort, id");
}

/** category key => [label, icon, css-class] */
function news_cats() {
    return [
      'birth'      => ['Birth',      'baby',  'c-birth'],
      'graduation' => ['Graduation', 'cap',   'c-grad'],
      'marriage'   => ['Marriage',   'rings', 'c-marr'],
      'reunion'    => ['Reunion',    'people','c-reun'],
      'memory'     => ['In Memory',  'lily',  'c-mem'],
      'news'       => ['News',       'news',  'c-news'],
      'prayer'     => ['Prayer',     'hands', 'c-pray'],
    ];
}
function news_cat($key) { $c = news_cats(); return $c[$key] ?? $c['news']; }

function news_mono($title) {
    $clean = trim(preg_replace('/\s+/', ' ', strip_tags($title)));
    $parts = array_values(array_filter(explode(' ', $clean)));
    if (!$parts) return '&#10086;';
    return e(strtoupper(substr($parts[0],0,1)));
}

/** Save an uploaded news photo -> assets/news/uploads/. Returns [relPath, error]. */
function news_store_photo($field = 'photo', $existing = '') {
    $rel = 'assets/news/uploads';
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
