<?php
/** Enterprise section — tables, seed data, and read helpers.
 *  ent_migrate() is idempotent: creates the tables if missing and seeds
 *  the current sample entries once so the page looks unchanged. William
 *  then edits/replaces them from the manage screen. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/site_meta.php';

function ent_migrate() {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $tables = [
"enterprise_businesses" => "CREATE TABLE IF NOT EXISTS enterprise_businesses (
  id $AI, name VARCHAR(160) NOT NULL, owner VARCHAR(160) DEFAULT '', category VARCHAR(160) DEFAULT '',
  cat_type VARCHAR(20) NOT NULL DEFAULT 'Business', location VARCHAR(160) DEFAULT '', blurb TEXT,
  link VARCHAR(255) DEFAULT '', phone VARCHAR(60) DEFAULT '', email VARCHAR(190) DEFAULT '', photo VARCHAR(255) DEFAULT '',
  sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)$ENG",
"enterprise_videos" => "CREATE TABLE IF NOT EXISTS enterprise_videos (
  id $AI, title VARCHAR(200) NOT NULL, description VARCHAR(500) DEFAULT '', url VARCHAR(255) DEFAULT '',
  duration VARCHAR(20) DEFAULT '', featured INT NOT NULL DEFAULT 0, sample INT NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)$ENG",
"enterprise_sayings" => "CREATE TABLE IF NOT EXISTS enterprise_sayings (
  id $AI, quote VARCHAR(600) NOT NULL, author VARCHAR(160) DEFAULT '',
  sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)$ENG",
"enterprise_finance" => "CREATE TABLE IF NOT EXISTS enterprise_finance (
  id $AI, icon VARCHAR(30) NOT NULL DEFAULT 'seed', title VARCHAR(160) NOT NULL, tips TEXT, link VARCHAR(255) DEFAULT '',
  sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)$ENG",
/* The four cards at the foot of the page. Their words used to be typed into
   enterprise.php, so "Support & Fund" could only be renamed by me. William
   asked whether the editor could put information into them; now it can. */
"enterprise_actions" => "CREATE TABLE IF NOT EXISTS enterprise_actions (
  id $AI, icon VARCHAR(30) NOT NULL DEFAULT 'star', title VARCHAR(120) NOT NULL,
  blurb VARCHAR(600) DEFAULT '', cta VARCHAR(80) DEFAULT '', href VARCHAR(255) DEFAULT '',
  members INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'published', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)$ENG",
    ];
    foreach ($tables as $sql) db()->exec($sql);
    foreach ([
      "CREATE INDEX idx_entbiz_status ON enterprise_businesses(status)",
      "CREATE INDEX idx_entvid_status ON enterprise_videos(status)",
      "CREATE INDEX idx_entsay_status ON enterprise_sayings(status)",
      "CREATE INDEX idx_entfin_status ON enterprise_finance(status)",
    ] as $s) { try { db()->exec($s); } catch (Exception $e) {} }
    // who submitted an entry (blank for admin-entered items); idempotent add
    foreach (['enterprise_businesses','enterprise_videos','enterprise_sayings','enterprise_finance'] as $t) {
        try { db()->exec("ALTER TABLE $t ADD COLUMN submitted_by VARCHAR(160) DEFAULT ''"); } catch (Exception $e) {}
    }
    // how the card photo is displayed: 'cover' (fill, may crop) or 'contain' (show whole photo)
    try { db()->exec("ALTER TABLE enterprise_businesses ADD COLUMN photo_fit VARCHAR(10) NOT NULL DEFAULT 'cover'"); } catch (Exception $e) {}
    // a picture for a video whose link we can't read (Facebook, Vimeo, a private host)
    db_add_column('enterprise_videos', 'photo', "VARCHAR(255) DEFAULT ''");
    ent_seed();
    return array_keys($tables);
}

/** table name for a pending-item type code (biz/vid/say/fin) */
function ent_pend_table($t) {
    return [
      'biz' => 'enterprise_businesses', 'vid' => 'enterprise_videos',
      'say' => 'enterprise_sayings',    'fin' => 'enterprise_finance',
    ][$t] ?? '';
}

/** all family-submitted entries awaiting review, newest first, tagged with _type */
function ent_pending_all() {
    $out = [];
    foreach (['biz'=>'enterprise_businesses','vid'=>'enterprise_videos','say'=>'enterprise_sayings','fin'=>'enterprise_finance'] as $code => $tbl) {
        foreach (all("SELECT * FROM $tbl WHERE status='pending' ORDER BY created_at DESC, id DESC") as $r) {
            $r['_type'] = $code; $out[] = $r;
        }
    }
    return $out;
}

/** count of entries awaiting review across all sections */
function ent_pending_count() {
    $n = 0;
    foreach (['enterprise_businesses','enterprise_videos','enterprise_sayings','enterprise_finance'] as $t) {
        $r = one("SELECT COUNT(*) c FROM $t WHERE status='pending'");
        $n += $r ? (int)$r['c'] : 0;
    }
    return $n;
}

/** Save a family-submitted photo (used by the member submission form).
 *  Returns [relPath, errorString]. Mirrors the admin uploader's rules. */
function ent_store_photo($field = 'photo') {
    $rel = 'assets/enterprise/uploads';
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

/** Where an action card can point. The four built-in destinations are offered
 *  by name so nobody has to know a filename; anything else is typed in. */
function ent_action_targets() {
    return [
      'businesses.php'       => 'The family business directory',
      'resources.php'        => 'Business Resources',
      'mentors.php'          => 'Mentor Connect',
      'get_involved.php'     => 'Support & Fund form',
      'enterprise_submit.php'=> 'Submit your business',
      'faith.php'            => 'Faith',
      'health.php'           => 'Health',
      'news.php'             => 'Family News',
      ''                     => 'Somewhere else — type the link below',
    ];
}

/** Icons an action card may use.
 *
 *  NOT ent_fin_icons(). That list was written for the financial guidance cards
 *  and has no magnifying glass, document, mentor, globe, phone or envelope in
 *  it — which are four of the icons the action cards already use. Validating a
 *  card against it meant the dropdown showed the wrong icon and, worse, saving
 *  the card silently replaced its icon with the fallback. Every icon ent_icon()
 *  can draw is listed here. */
function ent_action_icons() {
    return ent_fin_icons() + [
      'search' => 'Magnifying glass', 'doc'   => 'Document',   'mentor' => 'Mentor / two people',
      'film'   => 'Film',             'globe' => 'Globe',      'phone'  => 'Telephone',
      'mail'   => 'Envelope',
    ];
}

function ent_actions($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    try { return all("SELECT * FROM enterprise_actions $w ORDER BY sort, id"); }
    catch (\Throwable $e) { return []; }
}
function ent_action_get($id) {
    try { return one("SELECT * FROM enterprise_actions WHERE id=?", [(int)$id]); }
    catch (\Throwable $e) { return null; }
}

/** Seed the sample content once (only when a table is empty). */
function ent_seed() {
    if (!one("SELECT id FROM enterprise_businesses LIMIT 1")) {
        $biz = [
          ['GMW Transportation','Bill Holmes','Airport Transportation','Business','Dallas, TX','Private airport transportation to DFW & Love Field. Dependable, professional, and on time.','','','','assets/enterprise/biz_gmw.jpg'],
          ['Threads & Grace Boutique','Danielle Battles',"Women's Fashion & Accessories",'Business','Fort Worth, TX','Stylish fashion for every season. Empowering women to look and feel their best.','','','','assets/enterprise/biz_threads.jpg'],
          ['Battles Law Group','Tanisha Battles, Esq.','Personal Injury • Estate Planning','Profession','Houston, TX','Dedicated legal representation with compassion, integrity, and results.','','','','assets/enterprise/biz_law.jpg'],
          ['Battles Table Café','James Battles Jr.','Café & Catering','Business','Frisco, TX','Delicious food. Warm atmosphere. Bringing people together one meal at a time.','','','','assets/enterprise/biz_cafe.jpg'],
          ['KSJ Consulting','Katrina Smith-Jackson','Business Strategy • Leadership','Profession','Atlanta, GA','Helping organizations grow through strategy, leadership and operational excellence.','','','','assets/enterprise/biz_ksj.jpg'],
          ['Battles & Sons Construction','Robert Battles','General Contracting','Business','Arlington, TX','Quality construction. Strong foundations. Building for generations to come.','','','','assets/enterprise/biz_sons.jpg'],
        ];
        $i = 0;
        foreach ($biz as $b) {
            q("INSERT INTO enterprise_businesses (name,owner,category,cat_type,location,blurb,link,phone,email,photo,sample,sort)
               VALUES (?,?,?,?,?,?,?,?,?,?,1,?)",
              [$b[0],$b[1],$b[2],$b[3],$b[4],$b[5],$b[6],$b[7],$b[8],$b[9],$i++]);
        }
    }
    /* No sample videos. There used to be five placeholders here with no links —
       William asked for them gone, and because this seeds whenever the table is
       empty, deleting them just brought them straight back. An empty video list
       is the correct empty state; the page hides the panel from visitors. */
    if (!one("SELECT id FROM enterprise_sayings LIMIT 1")) {
        $says = [
          ['Whatever you do, work at it with all your heart, as working for the Lord, not for human masters.','Colossians 3:23'],
          ['If you don\'t like something, change it. If you can\'t change it, change your attitude.','Maya Angelou'],
          ['The time is always right to do what is right.','Dr. Martin Luther King Jr.'],
          ['Success is to be measured not so much by the position that one has reached in life as by the obstacles overcome.','Booker T. Washington'],
          ['Hard work, perseverance, and faith will carry a family further than any inheritance.','A Battles family saying'],
        ];
        $i = 0;
        foreach ($says as $s) {
            q("INSERT INTO enterprise_sayings (quote,author,sample,sort) VALUES (?,?,1,?)", [$s[0],$s[1],$i++]);
        }
    }
    /* Seeded once and remembered, NOT "whenever the table is empty" — the
       sample videos taught this page that the second kind comes back from the
       dead every time William deletes it. If he removes all four cards, they
       stay removed. */
    if (sm('ent_seeded_actions', '') === '') {
        sm_set('ent_seeded_actions', date('Y-m-d H:i:s'));
        $acts = [
          ['search', 'Hire Family First', 'Need a service or professional? Search our family business directory and support one another.', 'Search Directory', 'businesses.php', 0],
          ['doc',    'Business Resources', 'Guides, funding, filings and the free help most people never hear about.', 'Browse Resources', 'resources.php', 0],
          ['mentor', 'Mentor Connect', 'Learn from those who have walked the path. Find a mentor or become one.', 'Find a Mentor', 'mentors.php', 1],
          ['heart',  'Support & Fund', 'Help family businesses thrive through support, partnerships, and investments.', 'Get Involved', 'get_involved.php', 0],
        ];
        $i = 0;
        foreach ($acts as $a) {
            try {
                q("INSERT INTO enterprise_actions (icon,title,blurb,cta,href,members,sort,status)
                   VALUES (?,?,?,?,?,?,?,'published')", [$a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $i++]);
            } catch (\Throwable $e) {}
        }
    }
    if (!one("SELECT id FROM enterprise_finance LIMIT 1")) {
        $fin = [
          ['seed','Build Wealth',"Budget Wisely\nSave Consistently\nInvest Early\nAvoid Debt Traps"],
          ['home','Buy & Own',"Homeownership Tips\nReal Estate Investing\nBuilding Equity\nFamily Property"],
          ['shield','Protect Your Future',"Insurance Essentials\nEmergency Fund\nEstate Planning\nWills & Trusts"],
          ['cap','Invest in Education',"College Savings Plans\nScholarships\nStudent Loan Tips\nSkill Development"],
        ];
        $i = 0;
        foreach ($fin as $c) {
            q("INSERT INTO enterprise_finance (icon,title,tips,sample,sort) VALUES (?,?,?,1,?)", [$c[0],$c[1],$c[2],$i++]);
        }
    }
}

/* ---- read helpers (published only unless $all) ---- */
function ent_businesses($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM enterprise_businesses $w ORDER BY sort, id");
}
function ent_videos($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM enterprise_videos $w ORDER BY featured DESC, sort, id");
}
function ent_sayings($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM enterprise_sayings $w ORDER BY sort, id");
}
function ent_finance($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM enterprise_finance $w ORDER BY sort, id");
}
/** entry types shown on the Enterprise page (value => friendly label) */
function ent_types() {
    return [
      'Business'   => 'Business',
      'Profession' => 'Profession',
      'Book'       => 'Published Book',
      'Article'    => 'Published Article',
    ];
}
function ent_type_ok($t) { return array_key_exists($t, ent_types()); }
/** the call-to-action label for a card, by type */
function ent_cta($type) {
    if ($type === 'Book')    return 'View the Book';
    if ($type === 'Article') return 'Read the Article';
    return 'View Business';
}

/** icon choices for the finance cards (key => friendly label) */
function ent_fin_icons() {
    return ['seed'=>'Plant / Growth','home'=>'House','shield'=>'Shield','cap'=>'Graduation cap','bank'=>'Bank',
            'chart'=>'Chart','star'=>'Star','heart'=>'Heart','bulb'=>'Lightbulb','case'=>'Briefcase','users'=>'People'];
}
/** split a tips blob (one per line) into a clean array */
function ent_tips($blob) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$blob) as $line) {
        $line = trim($line);
        if ($line !== '') $out[] = $line;
    }
    return $out;
}

/* ---- the line-icon set -------------------------------------------------
 *  This used to live inside enterprise.php, which meant it existed only while
 *  that one page was rendering. The four cards at the foot of that page now
 *  lead somewhere, and those pages want the same icons drawn the same way, so
 *  it has moved down here beside the data it decorates. Stroke colour and
 *  width come from the CSS; the paths are all fill:none. */
function ent_icon($k) {
  $p = [
    'bulb'   => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.8.7 1 1.4 1 2.5h6c0-1.1.2-1.8 1-2.5A6 6 0 0 0 12 3z"/>',
    'case'   => '<rect x="3" y="7.5" width="18" height="12.5" rx="2"/><path d="M8.5 7.5V5.5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2M3 12.5h18"/>',
    'chart'  => '<path d="M3 21h18"/><rect x="5" y="12" width="3" height="6"/><rect x="10.5" y="8" width="3" height="10"/><rect x="16" y="4" width="3" height="14"/>',
    'users'  => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
    'star'   => '<path d="M12 3.2l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.4l5.9-.8z"/>',
    'seed'   => '<path d="M12 21v-7.5M12 13.5C12 10.5 9.8 8.3 6.5 8.3c0 3 2.2 5.2 5.5 5.2zM12 11.8c0-3.1 2.2-5.3 5.5-5.3 0 3.1-2.2 5.3-5.5 5.3z"/>',
    'home'   => '<path d="M3 11l9-7 9 7M5 9.7V20h14V9.7M10 20v-6h4v6"/>',
    'shield' => '<path d="M12 3.2l7 2.6v5c0 4.4-3 7.4-7 8.9-4-1.5-7-4.5-7-8.9v-5z"/><path d="M9 12l2 2 4-4"/>',
    'cap'    => '<path d="M12 5L2 9l10 4 10-4-10-4zM6 11v4c0 1.6 2.7 3 6 3s6-1.4 6-3v-4M20 9.5v4.5"/>',
    'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.7-4.7"/>',
    'doc'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h5"/>',
    'mentor' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20v-1a4 4 0 0 1 4-4h3a4 4 0 0 1 4 4v1M16.5 5.5l1.6 1.6L21.5 3.7"/>',
    'heart'  => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'film'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M8 5v14M16 5v14"/>',
    'bank'   => '<path d="M4 10h16M3 10l9-6 9 6M6 10v7M10 10v7M14 10v7M18 10v7M4 20h16"/>',
    'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.7 2.6 15.3 0 18M12 3c-2.6 2.7-2.6 15.3 0 18"/>',
    'phone'  => '<path d="M6 3h3l1.6 5-2 1.4a12 12 0 0 0 5.9 5.9l1.4-2 5 1.6v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2 2 2 0 0 1 6 3z"/>',
    'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
  ];
  $inner = isset($p[$k]) ? $p[$k] : '<circle cx="12" cy="12" r="8"/>';
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
}

/** initials monogram from a business name */
function ent_mono($name) {
    $clean = trim(html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8'));
    $parts = preg_split('/\s+/', $clean);
    $parts = array_values(array_filter($parts, function($w){ return !in_array(strtolower($w), ['&','and','the','of']); }));
    if (!$parts) return '&#10086;';
    $ini = strtoupper(substr($parts[0], 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
    return $ini !== '' ? e($ini) : '&#10086;';
}
