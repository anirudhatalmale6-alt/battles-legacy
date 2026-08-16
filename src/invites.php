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

define('INVITE_DAYS', 30);

function invite_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    /* When it was emailed, and whether the mail server took it. Null means
       nobody has tried, which is different from "tried and it failed". */
    db_add_column('invites', 'emailed_at', 'DATETIME NULL');
    db_add_column('invites', 'email_ok', 'INT NOT NULL DEFAULT 0');
    db_add_column('invites', 'sent_count', 'INT NOT NULL DEFAULT 0');
}

/** A fresh invitation. Returns [token, url]. */
function invite_create($name, $email, $role, $by) {
    invite_migrate();
    $role  = in_array($role, ['member', 'moderator', 'admin'], true) ? $role : 'member';
    $token = bin2hex(random_bytes(20));
    q("INSERT INTO invites (token,name,email,role,invited_by,expires_at) VALUES (?,?,?,?,?,?)",
      [$token, mb_substr(trim((string)$name), 0, 120), mb_substr(strtolower(trim((string)$email)), 0, 190),
       $role, (int)$by ?: null, date('Y-m-d H:i:s', time() + INVITE_DAYS * 86400)]);
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
          . "The link works once and expires in " . INVITE_DAYS . " days. If it has run out "
          . "just tell me and I'll send another.\n\n"
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
