<?php
/** Enterprise section — tables, seed data, and read helpers.
 *  ent_migrate() is idempotent: creates the tables if missing and seeds
 *  the current sample entries once so the page looks unchanged. William
 *  then edits/replaces them from the manage screen. */
require_once __DIR__ . '/db.php';

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
    if (!one("SELECT id FROM enterprise_videos LIMIT 1")) {
        $vids = [
          ['Legacy in Action','Words of Wisdom from Our Elders','','5:42',1],
          ['Building a Business With Faith & Purpose','','','4:18',0],
          ['Next Generation Entrepreneurs','','','3:57',0],
          ['Financial Freedom Starts Now','','','6:21',0],
          ['Our Story. Our Legacy. Our Future.','','','4:09',0],
        ];
        $i = 0;
        foreach ($vids as $v) {
            q("INSERT INTO enterprise_videos (title,description,url,duration,featured,sample,sort) VALUES (?,?,?,?,?,1,?)",
              [$v[0],$v[1],$v[2],$v[3],$v[4],$i++]);
        }
    }
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

/** initials monogram from a business name */
function ent_mono($name) {
    $clean = trim(html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8'));
    $parts = preg_split('/\s+/', $clean);
    $parts = array_values(array_filter($parts, function($w){ return !in_array(strtolower($w), ['&','and','the','of']); }));
    if (!$parts) return '&#10086;';
    $ini = strtoupper(substr($parts[0], 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
    return $ini !== '' ? e($ini) : '&#10086;';
}
