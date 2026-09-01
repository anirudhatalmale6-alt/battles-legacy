<?php
/** The monthly family note.
 *
 *  The problem this exists for: people opened the site once and never came
 *  back. Nothing on it tells them when something new has appeared, so there is
 *  never a reason to return. A short note once a month, built out of what has
 *  actually changed, is that reason.
 *
 *  Three rules run through the whole file.
 *
 *  1. It never invents. Every line of the draft comes from a row in the
 *     database. A month with no new photographs simply has no photographs
 *     paragraph — it does not get a cheerful sentence about photographs.
 *  2. William writes the final words. The draft is a starting point in an
 *     editable box, not something that goes out behind his back. Nothing is
 *     ever sent without him pressing send on a note he has read.
 *  3. It goes out on a clock, like the invitations. Ten messages in ten
 *     seconds from a domain that sends almost nothing is exactly the shape
 *     that got the August invitations filed as spam.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/calendar_data.php';

function note_migrate() {
    static $done = false;
    if ($done) return;
    $done = true;
    $ENG = db_driver() === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $AI  = db_driver() === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS family_notes (
            id $AI,
            subject VARCHAR(200) NOT NULL DEFAULT '',
            body TEXT,
            created_at DATETIME NULL,
            created_by INT NULL,
            created_by_name VARCHAR(160) NOT NULL DEFAULT '',
            queued_at DATETIME NULL
        )$ENG");
        db()->exec("CREATE TABLE IF NOT EXISTS note_sends (
            id $AI,
            note_id INT NOT NULL,
            user_id INT NULL,
            name VARCHAR(160) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            token VARCHAR(64) NOT NULL DEFAULT '',
            queued_at DATETIME NULL,
            sent_at DATETIME NULL,
            ok TINYINT NOT NULL DEFAULT 0
        )$ENG");
    } catch (\Throwable $e) { /* the pages below all cope with the tables missing */ }
    /* Someone who does not want the note must be able to stop it without
       having to ask a person for permission. */
    db_add_column('users', 'no_email', 'TINYINT NOT NULL DEFAULT 0');
}

function note_token() {
    if (function_exists('random_bytes')) {
        try { return bin2hex(random_bytes(20)); } catch (\Throwable $e) {}
    }
    return bin2hex(pack('N', time())) . md5(uniqid('', true));
}

/* ------------------------------------------------------------------ *
 *  Notes
 * ------------------------------------------------------------------ */

function note_all($limit = 24) {
    note_migrate();
    try { return all("SELECT * FROM family_notes ORDER BY id DESC LIMIT " . (int)$limit); }
    catch (\Throwable $e) { return []; }
}

function note_get($id) {
    note_migrate();
    try { return one("SELECT * FROM family_notes WHERE id=?", [(int)$id]); }
    catch (\Throwable $e) { return null; }
}

/** The newest note that has actually been sent to somebody. */
function note_last_sent() {
    note_migrate();
    try {
        return one("SELECT n.* FROM family_notes n
                    JOIN note_sends s ON s.note_id = n.id AND s.sent_at IS NOT NULL
                    ORDER BY n.id DESC LIMIT 1");
    } catch (\Throwable $e) { return null; }
}

function note_save($subject, $body, $user = null, $id = 0) {
    note_migrate();
    $subject = mb_substr(trim((string)$subject), 0, 200);
    $body    = mb_substr((string)$body, 0, 20000);
    $uid     = $user ? (int)$user['id'] : null;
    $uname   = $user ? (string)$user['name'] : '';
    try {
        if ($id > 0 && note_get($id)) {
            q("UPDATE family_notes SET subject=?, body=? WHERE id=?", [$subject, $body, (int)$id]);
            return (int)$id;
        }
        q("INSERT INTO family_notes (subject, body, created_at, created_by, created_by_name)
           VALUES (?,?,?,?,?)", [$subject, $body, date('Y-m-d H:i:s'), $uid, $uname]);
        return (int)db()->lastInsertId();
    } catch (\Throwable $e) { return 0; }
}

/** Who the note would go to: members with an account, an address, and no opt-out. */
function note_recipients() {
    note_migrate();
    try {
        $rows = all("SELECT id,name,email,no_email FROM users
                     WHERE status='active' AND email <> '' ORDER BY name, id");
    } catch (\Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        if ((int)$r['no_email'] === 1) continue;
        if (mailer_valid($r['email']) === '') continue;
        $out[] = $r;
    }
    return $out;
}

function note_opted_out_count() {
    note_migrate();
    try {
        $r = one("SELECT COUNT(*) c FROM users WHERE status='active' AND no_email=1");
        return $r ? (int)$r['c'] : 0;
    } catch (\Throwable $e) { return 0; }
}

/* ------------------------------------------------------------------ *
 *  Building the draft out of what actually changed
 * ------------------------------------------------------------------ */

/** Occasions inside a real forward window, not cal_upcoming()'s wrap-around.
 *
 *  cal_upcoming() fills its list by running on into next year when the rest of
 *  this one is quiet, which is right for a sidebar and wrong here: a note that
 *  says "coming up" about a birthday in seven months is a note nobody believes
 *  a second time. */
function note_upcoming($days = 31, $limit = 10) {
    $today = mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
    $end   = $today + $days * 86400;
    $out   = [];
    try { $list = cal_visible(true); } catch (\Throwable $e) { return []; }
    foreach ($list as $o) {
        if (!$o['m'] || !$o['d']) continue;
        /* Try this year and next, so a window that crosses 31 December works. */
        foreach ([(int)date('Y'), (int)date('Y') + 1] as $yr) {
            $ts = mktime(0, 0, 0, (int)$o['m'], (int)$o['d'], $yr);
            if ($ts >= $today && $ts <= $end) {
                $o['ts'] = $ts;
                $out[] = $o;
                break;
            }
        }
    }
    usort($out, function ($a, $b) { return $a['ts'] - $b['ts']; });
    return array_slice($out, 0, $limit);
}

function note_since_default() {
    $last = note_last_sent();
    if ($last && !empty($last['created_at'])) return $last['created_at'];
    return date('Y-m-d H:i:s', time() - 31 * 86400);
}

/** Everything new since $since, as plain counts and names. Never guesses. */
function note_whats_new($since) {
    note_migrate();
    $out = ['photos' => [], 'photo_count' => 0, 'stories' => [], 'news' => [],
            'posts' => [], 'members' => [], 'since' => $since];

    /* Approved only, and deliberately no fallback to "any status". An upload
       sits at 'pending' until a moderator lets it through; announcing one would
       send the family to a page where it is not there yet. A photograph nobody
       is named on is still new but gives nothing to say, so the count and the
       names are counted separately rather than one standing in for the other. */
    try {
        $r = one("SELECT COUNT(*) c FROM photos WHERE created_at >= ? AND status='approved'", [$since]);
        $out['photo_count'] = $r ? (int)$r['c'] : 0;
    } catch (\Throwable $e) {}
    try {
        $rows = all("SELECT DISTINCT p.name FROM photos ph JOIN persons p ON p.pid = ph.pid
                     WHERE ph.created_at >= ? AND ph.status='approved' AND p.name <> ''
                     ORDER BY p.name LIMIT 12", [$since]);
        foreach ($rows as $r) $out['photos'][] = $r['name'];
    } catch (\Throwable $e) {}

    try {
        $rows = all("SELECT s.pid, p.name FROM person_stories s LEFT JOIN persons p ON p.pid = s.pid
                     WHERE s.updated_at >= ? ORDER BY s.updated_at DESC LIMIT 8", [$since]);
        foreach ($rows as $r) if (trim((string)$r['name']) !== '') $out['stories'][] = $r;
    } catch (\Throwable $e) {}

    try {
        $out['news'] = all("SELECT id,title FROM news_posts WHERE status='published' AND created_at >= ?
                            ORDER BY id DESC LIMIT 6", [$since]);
    } catch (\Throwable $e) {}

    /* 'answer' is left out on purpose: it hangs off a question and reads as
       nothing on its own. Statuses here are published/pending, not approved. */
    try {
        $out['posts'] = all("SELECT id,kind,title,author FROM community_posts
                             WHERE status='published' AND parent_id=0 AND created_at >= ?
                             AND kind IN ('question','recipe','update','healthtip')
                             ORDER BY id DESC LIMIT 6", [$since]);
    } catch (\Throwable $e) {}

    try {
        $out['members'] = all("SELECT name FROM users WHERE status='active' AND created_at >= ?
                               ORDER BY id LIMIT 12", [$since]);
    } catch (\Throwable $e) {}

    return $out;
}

/** One ancestor to read about, rotated so it is not the same person every month.
 *  Prefers somebody whose story has been written, because there is then
 *  something to read when the link is followed. */
function note_featured() {
    note_migrate();
    $seen = 0;
    try { $r = one("SELECT COUNT(*) c FROM family_notes"); $seen = $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) {}
    $pool = [];
    try {
        $pool = all("SELECT p.pid, p.name FROM person_stories s JOIN persons p ON p.pid = s.pid
                     WHERE p.name <> '' AND s.story <> '' ORDER BY p.pid");
    } catch (\Throwable $e) {}
    if (!$pool) {
        try {
            $pool = all("SELECT p.pid, p.name FROM persons p JOIN photos ph ON ph.pid = p.pid
                         WHERE p.name <> '' AND p.living = 0 AND p.birth_date <> ''
                         GROUP BY p.pid, p.name ORDER BY p.pid LIMIT 60");
        } catch (\Throwable $e) {}
    }
    if (!$pool) return null;
    return $pool[$seen % count($pool)];
}

/** The draft. Sections with nothing in them are left out entirely. */
function note_draft($since = null) {
    note_migrate();
    if ($since === null) $since = note_since_default();
    $site = (string)config('site_name') ?: 'The Battles Legacy';
    $base = rtrim(base_url(), '/');
    $new  = note_whats_new($since);
    $soon = note_upcoming(31, 10);
    $feat = note_featured();

    /* "since the last note" is a lie in the very first one, and the first one
       is the one that carries 262 imported photographs and eleven people who
       joined - the most striking note he will ever send. */
    $ago = note_last_sent() ? 'since the last note' : 'in the last few weeks';

    $paras = [];

    if ($soon) {
        $lines = [];
        foreach ($soon as $o) {
            $when = date('j F', $o['ts']);
            if ($o['kind'] === 'birthday')          $lines[] = $when . ' — ' . $o['title'] . "'s birthday";
            elseif ($o['kind'] === 'born')          $lines[] = $when . ' — ' . $o['title'] . ' was born' . ($o['y'] ? ' in ' . $o['y'] : '');
            elseif ($o['kind'] === 'anniversary')   $lines[] = $when . ' — ' . $o['title'] . ($o['y'] ? ', married ' . $o['y'] : ', wedding anniversary');
            elseif ($o['kind'] === 'remembrance')   $lines[] = $when . ' — remembering ' . $o['title'];
            else                                    $lines[] = $when . ' — ' . $o['title'];
        }
        $paras[] = "COMING UP IN THE NEXT FEW WEEKS\n\n" . implode("\n", $lines)
                 . "\n\nThe whole year is on the calendar: " . $base . "/calendar.php";
    }

    if ($new['photo_count'] > 0) {
        $p = $new['photo_count'] . ' new photograph' . ($new['photo_count'] === 1 ? '' : 's')
           . ' ' . ($new['photo_count'] === 1 ? 'has' : 'have') . ' gone up ' . $ago;
        if ($new['photos']) {
            $names = array_slice($new['photos'], 0, 6);
            $p .= ', including new ones of ' . note_join($names)
                . (count($new['photos']) > count($names) ? ' and others' : '');
        }
        $paras[] = "NEW PHOTOGRAPHS\n\n" . $p . ".\n\n" . $base . '/family.php';
    }

    if ($new['stories']) {
        $names = [];
        foreach ($new['stories'] as $s) $names[] = $s['name'];
        $paras[] = "SOMEBODY'S STORY HAS BEEN WRITTEN DOWN\n\n"
                 . note_join(array_slice($names, 0, 5)) . '. If you knew them, or you were told '
                 . "something about them, add what you remember — it goes on their page with your name on it.";
    }

    if ($new['news']) {
        $t = [];
        foreach ($new['news'] as $n) $t[] = $n['title'];
        $paras[] = "FAMILY NEWS\n\n" . implode("\n", $t) . "\n\n" . $base . '/news.php';
    }

    if ($new['posts']) {
        /* A post can be saved with no title at all - one on the live site is -
           and joining an empty string to an author produced a line reading
           " - William Holmes" and nothing else. Name it by what it is instead. */
        $KIND = ['question' => 'A question', 'recipe' => 'A recipe',
                 'update' => 'An update', 'healthtip' => 'A health tip'];
        $t = [];
        foreach ($new['posts'] as $n) {
            $title = trim((string)$n['title']);
            if ($title === '') $title = isset($KIND[$n['kind']]) ? $KIND[$n['kind']] : 'A post';
            $who = trim((string)$n['author']);
            /* Its own address, not the section index: community_list.php with
               no kind quietly falls back to Updates, which is the wrong page
               for a recipe and a dead end for the person who followed it. */
            $t[] = $title . ($who !== '' ? ' — ' . $who : '')
                 . "\n" . $base . '/community_view.php?id=' . (int)$n['id'];
        }
        $paras[] = "FROM THE FAMILY\n\n" . implode("\n\n", $t);
    }

    if ($new['members']) {
        $names = [];
        foreach ($new['members'] as $m) if (trim((string)$m['name']) !== '') $names[] = $m['name'];
        if ($names) {
            $paras[] = "NEW ON THE SITE\n\n" . note_join($names) . ' joined us ' . $ago . '.';
        }
    }

    if ($feat) {
        $paras[] = "SOMEONE TO LOOK UP THIS MONTH\n\n" . $feat['name'] . ' — '
                 . $base . '/person.php?pid=' . urlencode($feat['pid']);
    }

    if (!$paras) {
        /* An honest empty draft. Saying "nothing has changed" out loud is more
           use to him than a page of filler he then has to delete. */
        $paras[] = "Nothing new has been added to the site since the last note, and there is nothing "
                 . "on the calendar in the next few weeks. Write something here yourself before you "
                 . "send it — a photograph you have found, a question you want answering, or news "
                 . "of somebody. This box is yours; everything above the line is only a starting point.";
    }

    $month   = date('F');
    $subject = $site . ' — what\'s new in ' . $month;
    $body    = implode("\n\n\n", $paras);

    return ['subject' => $subject, 'body' => $body, 'since' => $since,
            'sections' => count($paras)];
}

function note_join($names) {
    $names = array_values(array_filter(array_map('trim', $names), 'strlen'));
    $n = count($names);
    if ($n === 0) return '';
    if ($n === 1) return $names[0];
    $last = array_pop($names);
    return implode(', ', $names) . ' and ' . $last;
}

/* ------------------------------------------------------------------ *
 *  What one person actually receives
 * ------------------------------------------------------------------ */

/** The greeting and the footer are added here rather than sitting in the
 *  editable box, so a note can never go out addressed to nobody, and the way
 *  to stop receiving it can never be edited away by accident. */
function note_render($note, $row) {
    $first = trim((string)($row['name'] ?? ''));
    if ($first !== '') { $bits = preg_split('/\s+/', $first); $first = $bits[0]; }
    $base = rtrim(base_url(), '/');
    $host = null;
    try { $host = one("SELECT name FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1"); }
    catch (\Throwable $e) {}
    $who = ($host && trim((string)$host['name']) !== '') ? $host['name'] : 'William';

    $out  = 'Hello ' . ($first !== '' ? $first : 'there') . ",\n\n";
    $out .= rtrim((string)$note['body']) . "\n\n\n";
    $out .= "The site is here whenever you want it: " . $base . "/login.php\n\n";
    $out .= $who . "\n\n";
    $out .= "---\n";
    $out .= "You are getting this because you have an account on the family site. "
          . "If you would rather not, this stops it and nothing else changes:\n"
          . $base . '/note_off.php?t=' . (string)$row['token'] . "\n";
    return $out;
}

/* ------------------------------------------------------------------ *
 *  Sending, on the same kind of clock as the invitations
 * ------------------------------------------------------------------ */

/** members are a warmer list than cold invitations, but not by so much that
 *  ten at once is safe from a domain this quiet */
function note_per_day() { return 25; }
function note_gap_minutes() { return 4; }

function note_queue($noteId) {
    note_migrate();
    $note = note_get($noteId);
    if (!$note) return 0;
    $now = date('Y-m-d H:i:s');
    $n = 0;
    foreach (note_recipients() as $r) {
        try {
            /* Never twice for the same note, however many times the button is
               pressed. */
            if (one("SELECT id FROM note_sends WHERE note_id=? AND user_id=?", [(int)$noteId, (int)$r['id']])) continue;
            q("INSERT INTO note_sends (note_id, user_id, name, email, token, queued_at)
               VALUES (?,?,?,?,?,?)",
              [(int)$noteId, (int)$r['id'], (string)$r['name'], (string)$r['email'], note_token(), $now]);
            $n++;
        } catch (\Throwable $e) {}
    }
    if ($n) { try { q("UPDATE family_notes SET queued_at=? WHERE id=? AND queued_at IS NULL", [$now, (int)$noteId]); } catch (\Throwable $e) {} }
    return $n;
}

function note_pending($limit = 0) {
    note_migrate();
    $sql = "SELECT * FROM note_sends WHERE sent_at IS NULL ORDER BY queued_at, id";
    if ($limit > 0) $sql .= ' LIMIT ' . (int)$limit;
    try { return all($sql); } catch (\Throwable $e) { return []; }
}

function note_pending_count() { return count(note_pending()); }

function note_queue_clear($noteId = 0) {
    note_migrate();
    try {
        if ($noteId > 0) q("DELETE FROM note_sends WHERE sent_at IS NULL AND note_id=?", [(int)$noteId]);
        else             q("DELETE FROM note_sends WHERE sent_at IS NULL");
    } catch (\Throwable $e) {}
}

/** How a note has got on: queued, sent, refused. */
function note_progress($noteId) {
    note_migrate();
    $out = ['queued' => 0, 'sent' => 0, 'failed' => 0, 'waiting' => 0];
    try {
        foreach (all("SELECT sent_at, ok FROM note_sends WHERE note_id=?", [(int)$noteId]) as $r) {
            $out['queued']++;
            if ($r['sent_at'] === null) $out['waiting']++;
            elseif ((int)$r['ok'] === 1) $out['sent']++;
            else $out['failed']++;
        }
    } catch (\Throwable $e) {}
    return $out;
}

function note_sent_last_day() {
    note_migrate();
    try {
        $r = one("SELECT COUNT(*) c FROM note_sends WHERE sent_at IS NOT NULL AND sent_at >= ?",
                 [date('Y-m-d H:i:s', time() - 86400)]);
        return $r ? (int)$r['c'] : 0;
    } catch (\Throwable $e) { return 0; }
}

function note_last_send_ts() {
    note_migrate();
    try {
        $r = one("SELECT MAX(sent_at) m FROM note_sends WHERE sent_at IS NOT NULL");
        return ($r && $r['m']) ? (int)strtotime($r['m']) : 0;
    } catch (\Throwable $e) { return 0; }
}

/** [ok, why-not, seconds-to-wait] */
function note_ready() {
    if (!note_pending(1)) return [false, 'Nothing is waiting to go out.', 0];
    $today = note_sent_last_day();
    if ($today >= note_per_day())
        return [false, $today . ' have gone out in the last 24 hours, which is the daily limit.', 0];
    $last = note_last_send_ts();
    $wait = $last ? ($last + note_gap_minutes() * 60) - time() : 0;
    if ($wait > 0) return [false, 'Too soon after the last one.', $wait];
    return [true, '', 0];
}

/** Send at most ONE. Same reasoning as the invitation drip: a loop here is the
 *  burst all over again. */
function note_release() {
    list($ok, $why, $wait) = note_ready();
    if (!$ok) return [false, $why];
    $rows = note_pending(1);
    if (!$rows) return [false, 'Nothing is waiting to go out.'];
    $row  = $rows[0];
    $note = note_get($row['note_id']);
    if (!$note) {
        try { q("DELETE FROM note_sends WHERE id=?", [(int)$row['id']]); } catch (\Throwable $e) {}
        return [false, 'That note is no longer there.'];
    }
    $host = null;
    try { $host = one("SELECT name,email FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1"); }
    catch (\Throwable $e) {}

    $sent = mailer_send($row['email'], $note['subject'], note_render($note, $row), [
        'to_name'    => (string)$row['name'],
        'reply_to'   => $host ? (string)$host['email'] : '',
        'reply_name' => $host ? (string)$host['name'] : '',
    ]);
    /* Out of the queue either way — an address the server refuses will not
       start working on the next pass, and leaving it would block the rest. */
    try {
        q("UPDATE note_sends SET sent_at=?, ok=? WHERE id=?",
          [date('Y-m-d H:i:s'), $sent ? 1 : 0, (int)$row['id']]);
    } catch (\Throwable $e) {}
    $who = trim((string)$row['name']) !== '' ? $row['name'] : $row['email'];
    return [$sent, $sent ? 'Sent to ' . $who . '.' : 'The mail server would not take the one for ' . $who . '.'];
}

/** One copy to William himself, straight away and outside the queue.
 *
 *  Worth having its own path: he should be able to read the real thing in his
 *  own inbox — wrapped lines, working links, whatever his phone does to it —
 *  before ten relatives do, and a single message to one address is not a rate
 *  question. It is not recorded as a send, so it does not mark the note as
 *  having gone out. */
function note_send_test($noteId, $user) {
    note_migrate();
    $note = note_get($noteId);
    $to   = $user ? mailer_valid($user['email']) : '';
    if (!$note) return [false, 'That note is no longer there.'];
    if ($to === '') return [false, 'There is no email address on your own account to send it to.'];
    $row = ['name' => (string)$user['name'], 'email' => $to, 'token' => 'preview'];
    $sent = mailer_send($to, '[Preview] ' . $note['subject'], note_render($note, $row),
                        ['to_name' => (string)$user['name']]);
    return [$sent, $sent
        ? 'Handed to the mail server for ' . $to . '. Give it a minute, and check your spam folder too.'
        : 'The mail server would not take it.'];
}

/** Keeps the queue moving on ordinary page loads, so there is no cron job to
 *  set up and nothing to remember. Gives up after two cheap queries when the
 *  clock says no. */
function note_tick() {
    static $ran = false;
    if ($ran) return;
    $ran = true;
    try {
        list($ok, , ) = note_ready();
        if ($ok) note_release();
    } catch (\Throwable $e) { /* never break a page over this */ }
}

/** Stop sending to whoever holds this token. Returns their name, or ''. */
function note_opt_out($token) {
    note_migrate();
    $token = trim((string)$token);
    if ($token === '') return '';
    try {
        $row = one("SELECT * FROM note_sends WHERE token=?", [$token]);
        if (!$row) return '';
        if ($row['user_id']) {
            q("UPDATE users SET no_email=1 WHERE id=?", [(int)$row['user_id']]);
            /* Anything still queued for them goes now, not next month. */
            q("DELETE FROM note_sends WHERE sent_at IS NULL AND user_id=?", [(int)$row['user_id']]);
        }
        return trim((string)$row['name']) !== '' ? $row['name'] : $row['email'];
    } catch (\Throwable $e) { return ''; }
}
