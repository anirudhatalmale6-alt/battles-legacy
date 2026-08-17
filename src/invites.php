<?php
/** Invitations.
 *
 *  Until now this only ever made a link and left it on the Members page for
 *  William to find, copy and paste into his own email by hand. The form asked
 *  for an email address and then did nothing with it, which read — reasonably —
 *  as "the website will email them". It never did.
 *
 *  So now there are two ways to get an invitation to somebody, and the page is
 *  honest about which is which:
 *    1. the site emails it — quick, but it is a machine writing to a Gmail
 *       account that has never heard of this domain, so it can land in spam;
 *    2. William's own email app opens with the message already written — one
 *       extra tap, arrives from a name the family recognises, always works.
 *
 *  The link itself is identical either way, and stays on the page regardless,
 *  so nothing depends on a message that may not have arrived. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/people_pick.php';

define('INVITE_DAYS', 30);

function invite_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    /* When it was emailed, and whether the mail server took it. Null means
       nobody has tried, which is different from "tried and it failed". */
    db_add_column('invites', 'emailed_at', 'DATETIME NULL');
    db_add_column('invites', 'email_ok', 'INT NOT NULL DEFAULT 0');
    db_add_column('invites', 'sent_count', 'INT NOT NULL DEFAULT 0');
    /* When the link was first opened. "Emailed" and "opened" answer different
       questions: the first says the mail server took it, the second says a
       human being actually got as far as the sign-up form. Sixty invitations
       had gone out with only four accounts back, and nothing on the site could
       tell which half of that journey was failing. */
    db_add_column('invites', 'opened_at', 'DATETIME NULL');
    pp_migrate();                       // invites.pid — which person in the tree this is
    pp_backfill();                      // and join up the ones made before it existed
}

/** A fresh invitation. Returns [token, url].
 *
 *  $pid ties the invitation to a person already in the tree, so the spelling on
 *  the invitation is the spelling on their page and the account they end up
 *  with knows whose it is. Blank is fine — somebody who married in, or a name
 *  the tree hasn't caught up with yet, still gets invited. */
function invite_create($name, $email, $role, $by, $pid = '') {
    invite_migrate();
    $role  = in_array($role, ['member', 'moderator', 'admin'], true) ? $role : 'member';
    $token = bin2hex(random_bytes(20));
    $pid   = pp_person($pid) ? trim((string)$pid) : '';
    q("INSERT INTO invites (token,name,email,role,invited_by,pid,expires_at) VALUES (?,?,?,?,?,?,?)",
      [$token, mb_substr(trim((string)$name), 0, 120), mb_substr(strtolower(trim((string)$email)), 0, 190),
       $role, (int)$by ?: null, $pid, date('Y-m-d H:i:s', time() + INVITE_DAYS * 86400)]);
    /* The suggestion list carries an "already invited" mark against each name.
       It was read before this insert, so rebuild it or the page that renders in
       a moment will still offer this person as though nobody had asked them. */
    pp_people(true);
    return [$token, invite_url($token)];
}

function invite_url($token) { return base_url() . '/register.php?token=' . $token; }

/** Outstanding invitations, newest first. */
function invite_open() {
    invite_migrate();
    try {
        return all("SELECT i.*, u.name AS by_name FROM invites i
                    LEFT JOIN users u ON u.id = i.invited_by
                    WHERE i.used_at IS NULL ORDER BY i.id DESC");
    } catch (\Throwable $e) { return []; }
}

function invite_by_id($id) {
    invite_migrate();
    return one("SELECT * FROM invites WHERE id=? AND used_at IS NULL", [(int)$id]);
}

function invite_delete($id) {
    invite_migrate();
    try { q("DELETE FROM invites WHERE id=? AND used_at IS NULL", [(int)$id]); } catch (\Throwable $e) {}
}

/** Somebody reached the sign-up form with a working link.
 *
 *  Written with date(), not CURRENT_TIMESTAMP: the app runs on America/Chicago
 *  and the database clock is UTC, and this lands on the Members page directly
 *  beside emailed_at, which uses date(). Mixing them would read as "emailed
 *  4:41pm, opened 9:41pm" for the same instant. */
function invite_mark_opened($id) {
    invite_migrate();
    try { q("UPDATE invites SET opened_at=? WHERE id=? AND opened_at IS NULL",
             [date('Y-m-d H:i:s'), (int)$id]); }
    catch (\Throwable $e) {}
}

/** How far the invitations have actually got, for the Members page. */
function invite_progress() {
    invite_migrate();
    $out = ['total' => 0, 'joined' => 0, 'opened' => 0, 'waiting' => 0, 'unopened' => 0];
    try {
        $r = one("SELECT COUNT(*) total,
                         SUM(CASE WHEN used_at   IS NOT NULL THEN 1 ELSE 0 END) joined,
                         SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) opened
                  FROM invites");
    } catch (\Throwable $e) { return $out; }
    if (!$r) return $out;
    $out['total']  = (int)$r['total'];
    $out['joined'] = (int)$r['joined'];
    $out['opened'] = (int)$r['opened'];
    $out['waiting']  = $out['total'] - $out['joined'];
    /* Waiting AND never opened — the ones where the email itself is the problem,
       not the sign-up form. */
    try {
        $u = one("SELECT COUNT(*) c FROM invites WHERE used_at IS NULL AND opened_at IS NULL");
        $out['unopened'] = $u ? (int)$u['c'] : 0;
    } catch (\Throwable $e) {}
    return $out;
}

/** ---------------------------------------------------------------------------
 *  The dead end at the sign-in page
 *
 *  Sixty invitations went out and four accounts came back. Meanwhile fourteen
 *  people who were not signed in sat on login.php trying passwords they had
 *  never chosen, and four of them went on to "forgotten password" — which
 *  correctly found no account for them and therefore, correctly, sent nothing.
 *
 *  That is a dead end nobody can get out of by trying harder, and from the
 *  outside it reads as the website refusing you. So both pages now recognise an
 *  invitation that has not been taken up and post the person their own link
 *  again. The link still only ever goes to the mailbox it was addressed to, so
 *  none of this hands out a way in — it removes the dead end.
 *  ------------------------------------------------------------------------ */

/** The newest invitation for this address that has not been used yet, expired
 *  or not. Expiry is not a reason to say "no invitation" — the invitation is
 *  real, it is the 30-day window that ran out, and that we can reopen. */
function invite_pending_for($email) {
    invite_migrate();
    $email = strtolower(trim((string)$email));
    if ($email === '') return null;
    try { $r = one("SELECT * FROM invites WHERE email=? AND used_at IS NULL ORDER BY id DESC", [$email]); }
    catch (\Throwable $e) { return null; }
    return $r ?: null;
}

/** Whoever the family recognises as the sender — the site's first admin. Used
 *  when there is no logged-in host, because the person asking is locked out. */
function invite_host() {
    try { return one("SELECT id,name,email FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1"); }
    catch (\Throwable $e) { return null; }
}

/** Post somebody their own invitation link again, on their own say-so.
 *
 *  Returns 'sent' when they should go and look in their inbox — including when
 *  one went a few minutes ago and we deliberately did not send a second, since
 *  "check your inbox" is true either way and a differing answer here would turn
 *  the form into a way to bury someone in email. Returns 'none' when that
 *  address has no invitation waiting, and 'joined' when they already have an
 *  account and simply need the password page instead. */
function invite_resend_self($email, $host = null) {
    $email = strtolower(trim((string)$email));
    if ($email === '') return 'none';
    try { if (one("SELECT id FROM users WHERE email=?", [$email])) return 'joined'; }
    catch (\Throwable $e) {}

    $inv = invite_pending_for($email);
    if (!$inv) return 'none';

    if ($inv['expires_at'] && strtotime($inv['expires_at']) < time()) {
        $fresh = date('Y-m-d H:i:s', time() + INVITE_DAYS * 86400);
        try { q("UPDATE invites SET expires_at=? WHERE id=?", [$fresh, (int)$inv['id']]);
              $inv['expires_at'] = $fresh; } catch (\Throwable $e) {}
    }
    $recent = !empty($inv['emailed_at']) && (time() - strtotime($inv['emailed_at'])) < 600;
    if (!$recent) invite_mail($inv, $host ?: invite_host());
    return 'sent';
}

/** The wording, in one place, so the site's email and William's own email say
 *  exactly the same thing. $host is whoever is doing the inviting. */
function invite_message($inv, $url, $host = null) {
    $first = invite_first_name($inv['name'] ?? '');
    $who   = trim((string)($host['name'] ?? '')) ?: 'William';
    $site  = (string)config('site_name') ?: 'The Battles Legacy';

    $subject = 'You are invited to ' . $site;
    $body = "Hello " . ($first !== '' ? $first : 'there') . ",\n\n"
          . "I've been putting our family history online — the tree going back to "
          . "our great-great-grandparents, old photographs, the church and business "
          . "history, and a page for the ones we've lost.\n\n"
          . "It's private, so it's invitation only. This link is yours; open it and "
          . "you can choose your own password:\n\n"
          . $url . "\n\n"
          /* Fourteen people went to the website, tried to sign in, and could not:
             until the link above has been opened there is no password of theirs
             to type. Saying so here is cheaper than rescuing them one at a time. */
          . "Please use that link the first time. Until you have, there is no "
          . "password of yours to type, so going straight to the website and "
          . "trying to sign in won't let you in.\n\n"
          . "The link expires in " . INVITE_DAYS . " days. If it has run out, or you "
          . "can't find this email later, open " . base_url() . "/login.php and put "
          . "your email address in the \"first time here\" box — a new link comes "
          . "straight back to you.\n\n"
          . "Hope you enjoy seeing it.\n\n"
          . $who . "\n";
    return ['subject' => $subject, 'body' => $body];
}

function invite_first_name($name) {
    $name = trim((string)$name);
    if ($name === '') return '';
    $bits = preg_split('/\s+/', $name);
    return $bits[0];
}

/** Ask the mail server to deliver it. True means the server accepted it —
 *  not that it arrived. Records the attempt either way. */
function invite_mail($inv, $host = null) {
    invite_migrate();
    $to = mailer_valid($inv['email'] ?? '');
    if ($to === '') return false;
    $m  = invite_message($inv, invite_url($inv['token']), $host);
    $ok = mailer_send($to, $m['subject'], $m['body'], [
        'to_name'    => $inv['name'] ?? '',
        'reply_to'   => $host['email'] ?? '',
        'reply_name' => $host['name'] ?? '',
    ]);
    try {
        q("UPDATE invites SET emailed_at=?, email_ok=?, sent_count=sent_count+1 WHERE id=?",
          [date('Y-m-d H:i:s'), $ok ? 1 : 0, (int)$inv['id']]);
    } catch (\Throwable $e) {}
    return $ok;
}

/** One line of a pasted list -> [name, email].
 *
 *  William has 59 people in the Facebook group. Typing them one at a time is
 *  the sort of job that doesn't get finished, so the form takes a whole list
 *  at once and accepts whatever shape it is pasted in:
 *
 *      Dianne Battles, dianne@example.com
 *      Dianne Battles <dianne@example.com>
 *      dianne@example.com
 *      Dianne Battles
 */
function invite_parse_line($line) {
    $line = trim(str_replace(["\xc2\xa0"], ' ', (string)$line));
    if ($line === '') return null;

    $email = '';
    /* Take the address out first, whatever brackets or punctuation sit round
       it, and treat everything left over as the name. */
    if (preg_match('/[<(\[]?\s*([^\s<>(),;\[\]]+@[^\s<>(),;\[\]]+\.[A-Za-z]{2,})\s*[>)\]]?/', $line, $m)) {
        $cand = rtrim($m[1], '.,;');
        if (filter_var($cand, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower($cand);
            $line  = trim(str_replace($m[0], ' ', $line));
        }
    }
    $name = trim($line, " \t,;:-<>()[]\"'");
    $name = preg_replace('/\s{2,}/', ' ', $name);

    if ($email === '' && $name === '') return null;
    return ['name' => mb_substr($name, 0, 120), 'email' => mb_substr($email, 0, 190)];
}

/** Is this person already a member, or already holding an invitation? */
function invite_existing($email) {
    $email = mailer_valid($email);
    if ($email === '') return null;
    invite_migrate();
    try {
        if ($u = one("SELECT id FROM users WHERE LOWER(email)=?", [strtolower($email)])) return 'member';
        if (one("SELECT id FROM invites WHERE LOWER(email)=? AND used_at IS NULL", [strtolower($email)])) return 'invited';
    } catch (\Throwable $e) {}
    return null;
}
