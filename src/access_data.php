<?php
/** "Can I join?" — someone a family member has shared the site with asks to
 *  come in, and William approves once he has recognised the name.
 *
 *  Approving does not create the account. It creates the same invitation link
 *  the Members page already makes, so the person still chooses their own
 *  password and nothing here ever holds one. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function ar_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS access_requests (
          id $AI, name VARCHAR(160) NOT NULL, email VARCHAR(190) DEFAULT '',
          phone VARCHAR(40) DEFAULT '', relation VARCHAR(300) DEFAULT '',
          note VARCHAR(1000) DEFAULT '', referred_by VARCHAR(160) DEFAULT '',
          status VARCHAR(20) NOT NULL DEFAULT 'new', invite_token VARCHAR(64) DEFAULT '',
          decided_by INT NULL, decided_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )$ENG");
        db()->exec("CREATE INDEX idx_ar_status ON access_requests(status)");
    } catch (\Throwable $e) { /* the index exists on every run after the first */ }

    /* Added later, so CREATE TABLE IF NOT EXISTS above will not put them on a
       table that already exists. Two names now arrive by two different roads
       and William judges them differently: a stranger who found the site and
       asked, or a signed-in relative putting a name forward. */
    db_add_column('access_requests', 'source',       "VARCHAR(20) NOT NULL DEFAULT 'self'");
    db_add_column('access_requests', 'referred_uid', "INT NULL");
}

/** $notify is on by default and both callers leave it alone. Telling the admins
 *  lives in here rather than in the two forms so that a third way in — added
 *  next year by someone who has never read this file — cannot quietly go back
 *  to landing in silence. Returns the new row's id. */
function ar_add($f, $notify = true) {
    ar_migrate();
    /* The two columns above may be missing if the ALTER could not run (a locked
       table, a host that forbids it). Naming them in a fixed INSERT would then
       turn a working "ask to join" form into a fatal error, so the column list
       is built from what the table actually has. */
    $cols = ['name'  => mb_substr(trim($f['name'] ?? ''), 0, 160),
             'email' => mb_substr(strtolower(trim($f['email'] ?? '')), 0, 190),
             'phone' => mb_substr(trim($f['phone'] ?? ''), 0, 40),
             'relation' => mb_substr(trim($f['relation'] ?? ''), 0, 300),
             'note'     => mb_substr(trim($f['note'] ?? ''), 0, 1000),
             'referred_by' => mb_substr(trim($f['referred_by'] ?? ''), 0, 160)];
    if (db_has_column('access_requests', 'source'))
        $cols['source'] = ($f['source'] ?? 'self') === 'member' ? 'member' : 'self';
    if (db_has_column('access_requests', 'referred_uid'))
        $cols['referred_uid'] = !empty($f['referred_uid']) ? (int)$f['referred_uid'] : null;
    $names = array_keys($cols);
    q("INSERT INTO access_requests (" . implode(',', $names) . ") VALUES ("
      . implode(',', array_fill(0, count($names), '?')) . ")", array_values($cols));
    $id = (int)insert_id();
    /* After the INSERT, always. The row is saved and the badge is counting
       before anything is allowed to reach the mail server, so a mail failure
       can never cost us the request itself. */
    if ($notify) { try { ar_notify_admins(ar_get($id) ?: $cols); } catch (\Throwable $e) {} }
    return $id;
}

/** Did a signed-in relative put this name forward, rather than the person
 *  asking for themselves? Reads a column that may not exist yet. */
function ar_from_member($r) {
    return isset($r['source']) && $r['source'] === 'member';
}

/** Tell the admins a name is waiting.
 *
 *  Until now a request landed here in total silence. There is a number badge
 *  beside Members in the menu, but that only helps somebody already on the
 *  site — so a cousin could put a name forward on Tuesday and nobody would
 *  know until William happened to sign in. The whole promise of the Invite
 *  Family form is "it comes to you", and it did not.
 *
 *  Deliberately never blocks the form. Whatever happens in here, the request
 *  is already saved and the badge is already counting; the email is a
 *  convenience on top, so every failure is swallowed and the person who filled
 *  the form in still gets their thank-you page.
 *
 *  Returns how many admins were written to, for the diagnostics only. */
function ar_notify_admins($r) {
    if (!is_array($r) || trim((string)($r['name'] ?? '')) === '') return 0;
    $sent = 0;
    try {
        require_once __DIR__ . '/mailer.php';
        if (!function_exists('mailer_send')) return 0;

        /* A public form plus an email trigger is how an inbox gets buried. If
           names are arriving faster than any real family ever would, the rows
           are still recorded and the badge still counts them — we just stop
           putting each one in his pocket. Five in an hour is far above the
           real rate and far below a nuisance. */
        $burst = one("SELECT COUNT(*) c FROM access_requests
                      WHERE created_at >= ?", [date('Y-m-d H:i:s', time() - 3600)]);
        if ($burst && (int)$burst['c'] > 5) return 0;

        $admins = all("SELECT name,email FROM users
                       WHERE role='admin' AND status='active' AND email<>''");
        if (!$admins) return 0;

        $who  = ar_from_member($r)
            ? trim((string)$r['referred_by']) . ' put this name forward, signed in as a family member.'
            : 'They found the site and asked to join through the public form. Nobody has vouched for them.';
        $site = (string)config('site_name') ?: 'The Battles Legacy';
        $url  = function_exists('base_url') ? rtrim(base_url(), '/') . '/admin.php' : 'admin.php';

        $lines = [
            'Somebody is waiting to be let in to ' . $site . '.',
            '',
            'Name:        ' . $r['name'],
            'Email:       ' . $r['email'],
        ];
        if (trim((string)($r['phone'] ?? '')) !== '')    $lines[] = 'Mobile:      ' . $r['phone'];
        if (trim((string)($r['relation'] ?? '')) !== '') $lines[] = 'Related to:  ' . $r['relation'];
        $lines[] = '';
        $lines[] = $who;
        if (trim((string)($r['note'] ?? '')) !== '') { $lines[] = ''; $lines[] = 'Note: ' . $r['note']; }
        $lines[] = '';
        $lines[] = 'Nothing has been sent to them. Nobody gets in until you approve it here:';
        $lines[] = $url;
        $body = implode("\n", $lines);

        foreach ($admins as $a) {
            if (mailer_send($a['email'], 'Someone is waiting to join ' . $site . ': ' . $r['name'],
                            $body, ['to_name' => $a['name']])) $sent++;
        }
    } catch (\Throwable $e) { return $sent; }
    return $sent;
}

function ar_list($status = 'new') {
    ar_migrate();
    try {
        return $status === 'all'
            ? all("SELECT * FROM access_requests ORDER BY id DESC")
            : all("SELECT * FROM access_requests WHERE status=? ORDER BY id DESC", [$status]);
    } catch (\Throwable $e) { return []; }
}

function ar_count($status = 'new') {
    ar_migrate();
    try { $r = one("SELECT COUNT(*) c FROM access_requests WHERE status=?", [$status]); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}

function ar_get($id) { ar_migrate(); return one("SELECT * FROM access_requests WHERE id=?", [(int)$id]); }

/** Already a member, or already asked? Keeps William from reading the same
 *  request three times because someone pressed the button three times. */
function ar_already($email) {
    ar_migrate();
    $email = strtolower(trim((string)$email));
    if ($email === '') return '';
    try {
        if (one("SELECT id FROM users WHERE email=?", [$email])) return 'member';
        if (one("SELECT id FROM access_requests WHERE email=? AND status='new'", [$email])) return 'pending';
    } catch (\Throwable $e) {}
    return '';
}

/** Approve: make an invitation for them and remember which one it was.
 *
 *  The invitation is made by invite_create() rather than a second INSERT of its
 *  own, so an invitation born here is identical to one typed on the Members
 *  page — same columns, same expiry, and it shows the same send buttons.
 *
 *  Returns ['url','token','emailed'] — emailed is whether the mail server took
 *  it, which is not a promise it arrived, so the caller says so carefully. */
function ar_approve($id, $role, $adminId) {
    require_once __DIR__ . '/invites.php';
    $r = ar_get($id);
    if (!$r || $r['status'] !== 'new') return null;
    list($token, $url) = invite_create($r['name'], $r['email'], $role, $adminId);
    q("UPDATE access_requests SET status='approved', invite_token=?, decided_by=?, decided_at=? WHERE id=?",
      [$token, $adminId, date('Y-m-d H:i:s'), (int)$id]);

    /* They asked to join minutes ago and are waiting on an answer, so try the
       email straight away — but the link is on the page regardless. */
    $emailed = false;
    if ($inv = one("SELECT * FROM invites WHERE token=?", [$token])) {
        $host = function_exists('current_user') ? current_user() : null;
        $emailed = invite_mail($inv, $host);
    }
    return ['url' => $url, 'token' => $token, 'emailed' => $emailed];
}

function ar_decline($id, $adminId) {
    ar_migrate();
    q("UPDATE access_requests SET status='declined', decided_by=?, decided_at=? WHERE id=? AND status='new'",
      [$adminId, date('Y-m-d H:i:s'), (int)$id]);
}

function ar_delete($id) { ar_migrate(); q("DELETE FROM access_requests WHERE id=?", [(int)$id]); }

/** Anyone in the tree whose name is close to what they typed — so William can
 *  see "yes, there is a Dianne Battles" without leaving the page. */
function ar_tree_matches($name, $limit = 5) {
    $name = trim((string)$name);
    if ($name === '') return [];
    $words = array_values(array_filter(preg_split('/\s+/', $name), function ($w) { return mb_strlen($w) > 2; }));
    if (!$words) return [];
    $out = []; $seen = [];
    foreach ($words as $w) {
        try { $rows = all("SELECT pid,name,birth_date,living FROM persons WHERE name LIKE ? ORDER BY name", ['%' . $w . '%']); }
        catch (\Throwable $e) { $rows = []; }
        foreach ($rows as $r) {
            if (isset($seen[$r['pid']])) continue;
            $seen[$r['pid']] = true; $out[] = $r;
            if (count($out) >= $limit) return $out;
        }
    }
    return $out;
}
