<?php
/** The four cards at the foot of the Enterprise page.
 *
 *  They have been sitting there since the page was built as plain <button>
 *  elements with nothing behind them — Hire Family First, Business Resources,
 *  Mentor Connect, Support & Fund. They looked live and did nothing. William
 *  asked for them switched on, and said what he wants to do with the third
 *  one: mentor family who are thinking about starting a business.
 *
 *  Three tables:
 *    enterprise_mentors   — family offering their time, and what about
 *    enterprise_resources — links worth having, with the free ones marked free
 *    enterprise_asks      — somebody asking for a mentor, or offering to help
 *
 *  A mentor's own email and phone are NEVER printed on a page. The default is
 *  that a message goes through the site to William, who passes it on. The
 *  family's living relatives are hidden from the public everywhere else on this
 *  site; a page that lists their names and mobile numbers would undo that in
 *  one go. Mentor Connect is therefore behind sign-in as well.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/site_meta.php';

function ment_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS enterprise_mentors (
          id $AI, uid INT NULL, name VARCHAR(160) NOT NULL, role_line VARCHAR(200) DEFAULT '',
          topics TEXT, about TEXT, location VARCHAR(160) DEFAULT '',
          contact VARCHAR(20) NOT NULL DEFAULT 'site', email VARCHAR(190) DEFAULT '',
          phone VARCHAR(60) DEFAULT '', photo VARCHAR(255) DEFAULT '',
          status VARCHAR(20) NOT NULL DEFAULT 'published', sort INT NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )$ENG");
        db()->exec("CREATE TABLE IF NOT EXISTS enterprise_resources (
          id $AI, title VARCHAR(200) NOT NULL, blurb VARCHAR(600) DEFAULT '',
          url VARCHAR(255) DEFAULT '', category VARCHAR(60) NOT NULL DEFAULT 'Starting out',
          icon VARCHAR(30) NOT NULL DEFAULT 'doc', cost VARCHAR(40) DEFAULT '',
          caution VARCHAR(400) DEFAULT '',
          status VARCHAR(20) NOT NULL DEFAULT 'published', sort INT NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )$ENG");
        db()->exec("CREATE TABLE IF NOT EXISTS enterprise_asks (
          id $AI, kind VARCHAR(20) NOT NULL DEFAULT 'mentor', mentor_id INT NULL,
          name VARCHAR(160) NOT NULL, email VARCHAR(190) DEFAULT '', phone VARCHAR(60) DEFAULT '',
          topic VARCHAR(200) DEFAULT '', message TEXT, offers VARCHAR(300) DEFAULT '',
          uid INT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new',
          handled_by INT NULL, handled_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )$ENG");
    } catch (\Throwable $e) { /* a page must never die because a table exists */ }
    foreach ([
      "CREATE INDEX idx_entmen_status ON enterprise_mentors(status)",
      "CREATE INDEX idx_entres_status ON enterprise_resources(status)",
      "CREATE INDEX idx_entask_status ON enterprise_asks(status)",
    ] as $s) { try { db()->exec($s); } catch (\Throwable $e) {} }
    ment_seed();
}

/** Seeding runs ONCE, ever, and remembers that it has.
 *
 *  The Enterprise page already learned this the hard way: its sample videos
 *  were seeded "whenever the table is empty", so every time William deleted
 *  them they came straight back and he had to ask twice. A flag in site_meta
 *  costs one row and means delete is delete. */
function ment_seed() {
    if (sm('ent_seeded_resources', '') === '') {
        sm_set('ent_seeded_resources', date('Y-m-d H:i:s'));
        $rows = [
          ['Where to start — the SBA Business Guide',
           'The plain-English walkthrough: choosing a structure, registering, licences, taxes, hiring. Written by the government agency whose whole job is small business.',
           'https://www.sba.gov/business-guide', 'Starting out', 'bulb', 'Free', ''],
          ['Write your business plan',
           'A template and two worked examples. Most funding conversations start with this document, so it is worth doing before you need it.',
           'https://www.sba.gov/business-guide/plan-your-business/write-your-business-plan', 'Starting out', 'doc', 'Free', ''],
          ['Register your business',
           'What you actually have to file, and with whom, before you can trade under a name.',
           'https://www.sba.gov/business-guide/launch-your-business/register-your-business', 'Starting out', 'case', 'Free', ''],
          ['Register a business in Texas',
           'The Secretary of State filings page — most of the family businesses on this site are in Texas, so this is the one that will apply.',
           'https://www.sos.state.tx.us/corp/index.shtml', 'Starting out', 'home', '', ''],
          ['Get your EIN direct from the IRS',
           'Your business tax number. It takes about ten minutes online and the IRS issues it immediately.',
           'https://www.irs.gov/businesses/small-businesses-self-employed/apply-for-an-employer-identification-number-ein-online',
           'Money and tax', 'bank', 'Free',
           'The IRS never charges for an EIN. Any site asking you to pay for one is a middleman, not the IRS.'],
          ['Free mentoring from SCORE',
           'Thousands of retired and working business owners who will sit with you for nothing. Run alongside the SBA. Worth using as well as the family.',
           'https://www.score.org/', 'People who will help', 'mentor', 'Free', ''],
          ['Free local help near you',
           'Small Business Development Centers, Women\'s Business Centers and Veterans\' Business Outreach Centers — search by your postcode.',
           'https://www.sba.gov/local-assistance', 'People who will help', 'users', 'Free', ''],
          ['SBA loan programmes',
           'What the government-backed loans are, what they can be used for, and who lends them.',
           'https://www.sba.gov/funding-programs/loans', 'Money and tax', 'seed', '', ''],
          ['Federal grants (grants.gov)',
           'The only official list of federal grants. Most federal grant money goes to organisations rather than individuals, so read the eligibility before you spend an evening on an application.',
           'https://www.grants.gov/', 'Money and tax', 'chart', 'Free',
           'A real grant never asks you for a fee, a gift card, or your online banking password. If somebody messages you about a grant you did not apply for, it is a scam.'],
          ['Trademark basics',
           'How to check whether a name is already taken, and what registering one does and does not protect.',
           'https://www.uspto.gov/trademarks/basics', 'Protecting what you build', 'shield', 'Free', ''],
          ['Scams that target small businesses',
           'The Federal Trade Commission\'s own list — fake invoices, fake directory listings, fake "your listing has expired" calls. Reading it once will save somebody in this family money.',
           'https://www.ftc.gov/business-guidance/small-businesses', 'Protecting what you build', 'shield', 'Free', ''],
        ];
        $i = 0;
        foreach ($rows as $r) {
            try {
                q("INSERT INTO enterprise_resources (title,blurb,url,category,icon,cost,caution,sort,status)
                   VALUES (?,?,?,?,?,?,?,?,'published')",
                  [$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $i++]);
            } catch (\Throwable $e) {}
        }
    }

    /* William told me what he wants to mentor on, so the page does not open
       empty with a form he then has to fill in about himself. The wording is
       mine and he is expected to change it — the manage screen says so. */
    if (sm('ent_seeded_mentor_wh', '') === '') {
        sm_set('ent_seeded_mentor_wh', date('Y-m-d H:i:s'));
        try {
            $u = one("SELECT id,name FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1");
            if ($u) {
                q("INSERT INTO enterprise_mentors (uid,name,role_line,topics,about,location,contact,status,sort)
                   VALUES (?,?,?,?,?,?, 'site', 'published', 0)",
                  [(int)$u['id'], $u['name'], 'GMW Transportation — Dallas, TX',
                   "Starting your own business\nThe first steps, and the order to do them in\nRunning something of your own alongside a job",
                   'Happy to talk with anybody in the family who is thinking about starting something of their own.',
                   'Dallas, TX']);
            }
        } catch (\Throwable $e) {}
    }
}

/* ---- mentors ---------------------------------------------------------- */

function ment_list($all = false) {
    ment_migrate();
    try {
        $w = $all ? '' : "WHERE status='published'";
        return all("SELECT * FROM enterprise_mentors $w ORDER BY sort, id");
    } catch (\Throwable $e) { return []; }
}
function ment_get($id) {
    ment_migrate();
    try { return one("SELECT * FROM enterprise_mentors WHERE id=?", [(int)$id]); }
    catch (\Throwable $e) { return null; }
}
function ment_pending_count() {
    ment_migrate();
    try { $r = one("SELECT COUNT(*) c FROM enterprise_mentors WHERE status='pending'"); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}
/** One topic per line, blanks dropped. Same shape as ent_tips(). */
function ment_topics($blob) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$blob) as $l) {
        $l = trim($l, " \t\r\n-•");
        if ($l !== '') $out[] = $l;
    }
    return $out;
}
/** Initials for the round badge when there is no photograph. */
function ment_initials($name) {
    $p = preg_split('/\s+/', trim((string)$name));
    $p = array_values(array_filter($p));
    if (!$p) return '?';
    $s = strtoupper(mb_substr($p[0], 0, 1));
    if (count($p) > 1) $s .= strtoupper(mb_substr(end($p), 0, 1));
    return $s;
}
/** The name to put on the "Ask ___" button.
 *  strtok() on the first space alone gives "Ask Dr." for "Dr. Rosa Battles" and
 *  "Ask J." for "J. B. Battles", so a first word that is a title or an initial
 *  is skipped, and if nothing sensible is left the whole name is used. */
function ment_first_name($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $parts = array_values(array_filter($parts));
    if (!$parts) return (string)$name;
    $titles = ['mr','mrs','ms','miss','dr','rev','pastor','sis','bro','elder','deacon'];
    foreach ($parts as $w) {
        $bare = mb_strtolower(rtrim($w, '.'));
        if (in_array($bare, $titles, true)) continue;   // a title, not a name
        if (mb_strlen($bare) < 2) continue;             // an initial
        return rtrim($w, ',');
    }
    return (string)$name;
}

function ment_contact_opts() {
    return [
      'site'  => 'Through the site — William passes the message on (recommended)',
      'email' => 'Show my email address on my card',
      'phone' => 'Show my phone number on my card',
      'both'  => 'Show both my email and my phone',
    ];
}
function ment_shows_email($m) { $c = $m['contact'] ?? 'site'; return ($c === 'email' || $c === 'both') && trim((string)$m['email']) !== ''; }
function ment_shows_phone($m) { $c = $m['contact'] ?? 'site'; return ($c === 'phone' || $c === 'both') && trim((string)$m['phone']) !== ''; }

/* ---- resources -------------------------------------------------------- */

function res_list($all = false) {
    ment_migrate();
    try {
        $w = $all ? '' : "WHERE status='published'";
        return all("SELECT * FROM enterprise_resources $w ORDER BY sort, id");
    } catch (\Throwable $e) { return []; }
}
function res_get($id) {
    ment_migrate();
    try { return one("SELECT * FROM enterprise_resources WHERE id=?", [(int)$id]); }
    catch (\Throwable $e) { return null; }
}
/** Published resources grouped by category, in the order the categories first
 *  appear, so William controls the running order with the sort field alone. */
function res_grouped() {
    $out = [];
    foreach (res_list() as $r) {
        $c = trim((string)$r['category']);
        if ($c === '') $c = 'Other';
        if (!isset($out[$c])) $out[$c] = [];
        $out[$c][] = $r;
    }
    return $out;
}
function res_categories() {
    return ['Starting out', 'Money and tax', 'People who will help', 'Protecting what you build', 'Growing', 'Other'];
}

/* ---- asks (a request for a mentor, or an offer of help) --------------- */

function ask_kinds() {
    return ['mentor' => 'Asking for a mentor', 'involved' => 'Offering to help'];
}

/** Save one and tell the admins.
 *
 *  Same shape as ar_add() on the Members side, and for the same reason: the
 *  row is written first and the email is a convenience on top, so a mail
 *  failure can never lose somebody's message. Returns the new row's id. */
function ask_add($f, $notify = true) {
    ment_migrate();
    $kind = ($f['kind'] ?? 'mentor') === 'involved' ? 'involved' : 'mentor';
    q("INSERT INTO enterprise_asks (kind,mentor_id,name,email,phone,topic,message,offers,uid)
       VALUES (?,?,?,?,?,?,?,?,?)",
      [$kind,
       !empty($f['mentor_id']) ? (int)$f['mentor_id'] : null,
       mb_substr(trim($f['name'] ?? ''), 0, 160),
       mb_substr(strtolower(trim($f['email'] ?? '')), 0, 190),
       mb_substr(trim($f['phone'] ?? ''), 0, 60),
       mb_substr(trim($f['topic'] ?? ''), 0, 200),
       mb_substr(trim($f['message'] ?? ''), 0, 4000),
       mb_substr(trim($f['offers'] ?? ''), 0, 300),
       !empty($f['uid']) ? (int)$f['uid'] : null]);
    $id = (int)insert_id();
    if ($notify) { try { ask_notify_admins(ask_get($id)); } catch (\Throwable $e) {} }
    return $id;
}

function ask_get($id) {
    ment_migrate();
    try { return one("SELECT * FROM enterprise_asks WHERE id=?", [(int)$id]); }
    catch (\Throwable $e) { return null; }
}
function ask_list($status = 'new') {
    ment_migrate();
    try {
        return $status === 'all'
            ? all("SELECT * FROM enterprise_asks ORDER BY id DESC")
            : all("SELECT * FROM enterprise_asks WHERE status=? ORDER BY id DESC", [$status]);
    } catch (\Throwable $e) { return []; }
}
function ask_count($status = 'new') {
    ment_migrate();
    try { $r = one("SELECT COUNT(*) c FROM enterprise_asks WHERE status=?", [$status]); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}
function ask_done($id, $adminId) {
    ment_migrate();
    try { q("UPDATE enterprise_asks SET status='done', handled_by=?, handled_at=? WHERE id=?",
             [(int)$adminId, date('Y-m-d H:i:s'), (int)$id]); } catch (\Throwable $e) {}
}
function ask_reopen($id) {
    ment_migrate();
    try { q("UPDATE enterprise_asks SET status='new', handled_by=NULL, handled_at=NULL WHERE id=?", [(int)$id]); }
    catch (\Throwable $e) {}
}
function ask_delete($id) {
    ment_migrate();
    try { q("DELETE FROM enterprise_asks WHERE id=?", [(int)$id]); } catch (\Throwable $e) {}
}

/** A message asking for a mentor is somebody working up the nerve to ask for
 *  help. It cannot sit unread in a database until William next signs in, so
 *  this behaves exactly like the Members-page notifier: never blocks the form,
 *  swallows every failure, and stops itself if the volume stops looking human.
 *  Returns how many admins were written to (diagnostics only). */
function ask_notify_admins($r) {
    if (!is_array($r) || trim((string)($r['name'] ?? '')) === '') return 0;
    $sent = 0;
    try {
        require_once __DIR__ . '/mailer.php';
        if (!function_exists('mailer_send')) return 0;

        $burst = one("SELECT COUNT(*) c FROM enterprise_asks WHERE created_at >= ?",
                     [date('Y-m-d H:i:s', time() - 3600)]);
        if ($burst && (int)$burst['c'] > 8) return 0;

        $admins = all("SELECT name,email FROM users WHERE role='admin' AND status='active' AND email<>''");
        if (!$admins) return 0;

        $site  = (string)config('site_name') ?: 'The Battles Legacy';
        $isAsk = ($r['kind'] ?? 'mentor') !== 'involved';
        $named = !empty($r['mentor_id']) ? ment_get($r['mentor_id']) : null;

        $lines = [];
        if ($isAsk) {
            $lines[] = $named
                ? trim((string)$r['name']) . ' has asked to be put in touch with ' . $named['name'] . '.'
                : trim((string)$r['name']) . ' is looking for a mentor.';
        } else {
            $lines[] = trim((string)$r['name']) . ' would like to help other family businesses.';
        }
        $lines[] = '';
        $lines[] = 'Name:    ' . $r['name'];
        if (trim((string)$r['email']) !== '') $lines[] = 'Email:   ' . $r['email'];
        if (trim((string)$r['phone']) !== '') $lines[] = 'Mobile:  ' . $r['phone'];
        if (trim((string)$r['topic']) !== '') $lines[] = 'About:   ' . $r['topic'];
        if (trim((string)$r['offers']) !== '') $lines[] = 'Offering: ' . $r['offers'];
        if (trim((string)$r['message']) !== '') { $lines[] = ''; $lines[] = $r['message']; }
        $lines[] = '';
        $lines[] = 'Nothing has been sent to them and nothing was published on the site. Their';
        $lines[] = 'address is above so you can simply reply, or pass it on from here:';
        $lines[] = (function_exists('base_url') ? rtrim(base_url(), '/') : '') . '/mentors_manage.php?tab=inbox';
        $body = implode("\n", $lines);

        $subj = $isAsk
            ? ($named ? 'Someone would like to talk to ' . $named['name'] . ': ' . $r['name']
                      : 'Someone is looking for a mentor: ' . $r['name'])
            : 'Someone wants to help a family business: ' . $r['name'];

        foreach ($admins as $a) {
            if (mailer_send($a['email'], $subj, $body, ['to_name' => $a['name']])) $sent++;
        }
    } catch (\Throwable $e) { return $sent; }
    return $sent;
}
