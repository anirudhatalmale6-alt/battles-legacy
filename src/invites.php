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
    /* Waiting in the drip queue. Forty-nine invitations left in forty-seven
       seconds and two were ever opened; the ten sent one at a time got six
       accounts back. Nothing about the wording changed between those two - only
       the rate. A queued invitation goes out on a clock instead of in a burst. */
    db_add_column('invites', 'queued_at', 'DATETIME NULL');
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

/** Correct the address on an invitation that is still waiting.
 *
 *  Two addresses in the first sixty were mistyped — "hatmail" for hotmail, and
 *  a ymail where a gmail was meant. Until now the only way to put that right
 *  was to cancel the invitation and make a new one, and the page never said so;
 *  typing the corrected address into the invite form just answered "they are
 *  already holding an invitation" and left the wrong one sitting in the list.
 *
 *  ISSUING A NEW LINK. If the wrong address had already been emailed, the
 *  invitation link — which is a key to a private site full of living
 *  relatives' details — is now sitting in a stranger's mailbox. ymail.com is a
 *  real Yahoo domain; somebody owns that address, and it is not family. So when
 *  the address changes after it has been sent, the token is replaced: the old
 *  link stops working and the new one goes to the right person. Correcting a
 *  typo before anything was sent leaves the link alone, since nobody ever had
 *  it.
 *
 *  Returns ['ok'=>bool, 'msg'=>string] plus, on success, what happened. */
function invite_update($id, $email, $name = null) {
    invite_migrate();
    $id  = (int)$id;
    $inv = one("SELECT * FROM invites WHERE id=?", [$id]);
    if (!$inv)                   return ['ok' => false, 'msg' => 'That invitation is no longer there.'];
    if (!empty($inv['used_at'])) return ['ok' => false, 'msg' => 'That person has already signed up, so this is their account now, not an invitation.'];

    $email = strtolower(trim((string)$email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
        return ['ok' => false, 'msg' => 'That doesn\'t look like an email address: ' . $email];

    $old     = strtolower(trim((string)$inv['email']));
    $changed = ($email !== $old);

    if ($changed) {
        try {
            if (one("SELECT id FROM users WHERE LOWER(email)=?", [$email]))
                return ['ok' => false, 'msg' => $email . ' already belongs to somebody with an account.'];
            if (one("SELECT id FROM invites WHERE LOWER(email)=? AND used_at IS NULL AND id<>?", [$email, $id]))
                return ['ok' => false, 'msg' => $email . ' is already on another invitation in the list below.'];
        } catch (\Throwable $e) {}
    }

    $sets = ['email=?'];
    $args = [mb_substr($email, 0, 190)];
    if ($name !== null && trim((string)$name) !== '') {
        $sets[] = 'name=?';
        $args[] = mb_substr(trim((string)$name), 0, 120);
    }

    $reissued = false;
    if ($changed) {
        if (!empty($inv['emailed_at'])) {
            $sets[] = 'token=?';
            $args[] = bin2hex(random_bytes(20));
            $reissued = true;
        }
        /* Emailed / opened were true of the old address, not this one. Leaving
           them would have the page report "handed to the mail server" about a
           mailbox nothing has ever been written to. */
        $sets[] = 'emailed_at=NULL';
        $sets[] = 'email_ok=0';
        $sets[] = 'sent_count=0';
        $sets[] = 'opened_at=NULL';
        /* A fresh 30 days: the clock should run from the invitation they can
           actually receive. */
        $sets[] = 'expires_at=?';
        $args[] = date('Y-m-d H:i:s', time() + INVITE_DAYS * 86400);
    }
    $args[] = $id;
    try { q("UPDATE invites SET " . implode(',', $sets) . " WHERE id=?", $args); }
    catch (\Throwable $e) { return ['ok' => false, 'msg' => 'Could not save that — please try again.']; }

    return ['ok'       => true,
            'changed'  => $changed,
            'reissued' => $reissued,
            'old'      => $old,
            'email'    => $email,
            'inv'      => one("SELECT * FROM invites WHERE id=?", [$id])];
}

/** -------------------------------------------------------------------------
 *  Does this address look like it will reach anybody?
 *
 *  Sixty invitations went out and five accounts came back, and the reason is
 *  not one thing. Some of it is spam folders. But some of it is simply that
 *  the address is wrong, and a wrong address is invisible: the site says
 *  "handed to the mail server" and is telling the truth, while the message
 *  bounces somewhere William never sees.
 *
 *  Two checks, because they catch different mistakes:
 *
 *  1. Has the domain a mail server at all? "hatmail.com" is registered and
 *     parked with no MX and no A record — nothing sent there can arrive.
 *  2. Is the domain one letter away from a much commoner one? "ymail.com" is
 *     a real Yahoo domain that delivers perfectly, and is also exactly what
 *     you get if you meant gmail and your finger slipped. No check can know
 *     which; it can point at it and let him say.
 *  ---------------------------------------------------------------------- */

/** Domains a family address is realistically at. Order matters only in that
 *  the first few are the ones a typo is usually aiming for. */
function invite_big_domains() {
    return ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com'];
}

function invite_known_domains() {
    return array_merge(invite_big_domains(), [
        'live.com', 'msn.com', 'me.com', 'mac.com', 'ymail.com', 'rocketmail.com',
        'comcast.net', 'att.net', 'sbcglobal.net', 'verizon.net', 'bellsouth.net',
        'charter.net', 'cox.net', 'earthlink.net', 'juno.com', 'netzero.com',
        'suddenlink.net', 'protonmail.com', 'proton.me', 'mail.com', 'gmx.com',
    ]);
}

/** Cached, because the Members page asks about sixty addresses across about
 *  fifteen distinct domains and a DNS lookup each time would be visible. */
function invite_domain_has_mail($domain) {
    static $seen = [];
    $domain = strtolower(trim((string)$domain));
    if ($domain === '') return false;
    if (array_key_exists($domain, $seen)) return $seen[$domain];
    $ok = true;                       // if we cannot look it up, say nothing
    try {
        if (function_exists('checkdnsrr')) {
            /* A domain with no MX still takes mail at its A record — that is
               the implicit-MX rule, and a stale work address usually looks
               like this. Only "neither" is a certain failure. */
            $ok = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        }
    } catch (\Throwable $e) { $ok = true; }
    $seen[$domain] = $ok;
    return $ok;
}

/** ['level' => 'ok'|'watch'|'bad', 'note' => plain English] */
function invite_address_check($email) {
    $email = strtolower(trim((string)$email));
    if ($email === '') return ['level' => 'ok', 'note' => ''];
    $at = strrpos($email, '@');
    if ($at === false) return ['level' => 'bad', 'note' => 'That is not an email address.'];
    $domain = substr($email, $at + 1);

    if (!invite_domain_has_mail($domain))
        return ['level' => 'bad',
                'note'  => 'There is no mail server at ' . $domain . ' — nothing sent to this address can arrive.'];

    $known = invite_known_domains();
    $big   = invite_big_domains();
    $isKnown = in_array($domain, $known, true);

    /* A known domain is only worth questioning against a commoner one, and
       only at a single letter's distance — otherwise every legitimate Yahoo
       address in the list grows a warning. */
    $against = $isKnown ? $big : $known;
    $limit   = $isKnown ? 1 : 2;
    foreach ($against as $r) {
        if ($r === $domain) continue;
        if (levenshtein($domain, $r) <= $limit) {
            return ['level' => 'watch',
                    'note'  => $isKnown
                        ? $domain . ' is a real address, but it is one letter from ' . $r . ' — worth checking which was meant.'
                        : $domain . ' looks like it might be ' . $r . '.'];
        }
    }
    return ['level' => 'ok', 'note' => ''];
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

/** The waiting invitation held by a person in the tree, if there is one.
 *
 *  Wanted so that "they already have an invitation" can say which address it
 *  is addressed to. That is the whole question when the address is the thing
 *  that is wrong. */
function invite_pending_for_pid($pid) {
    invite_migrate();
    $pid = trim((string)$pid);
    if ($pid === '') return null;
    try { $r = one("SELECT * FROM invites WHERE pid=? AND used_at IS NULL ORDER BY id DESC", [$pid]); }
    catch (\Throwable $e) { return null; }
    return $r ?: null;
}

/** An unused invitation addressed to this person under a DIFFERENT email.
 *
 *  The point of matching on name rather than address: a relative putting a
 *  cousin forward types the address that cousin actually uses today. If an
 *  invitation is already sitting unopened at the address the family had years
 *  ago, matching by email would find nothing and William would end up with two
 *  invitations for one person and no idea they were the same. Matched on the
 *  whole name, exactly, so "Battles" alone can never collide two people.
 *
 *  $notEmail is excluded because an invitation to the SAME address is a
 *  different situation with a different answer. */
function invite_pending_by_name($name, $notEmail = '') {
    invite_migrate();
    $name = trim((string)$name);
    if ($name === '') return null;
    try {
        $r = one("SELECT * FROM invites WHERE used_at IS NULL AND LOWER(name)=? AND email<>?
                  ORDER BY id DESC", [mb_strtolower($name), strtolower(trim((string)$notEmail))]);
    } catch (\Throwable $e) { return null; }
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

/** The same invitation cut down to something that reads as a text message.
 *
 *  The email wording above is right for an inbox and wrong for a phone: a text
 *  that arrives as six paragraphs gets scrolled past. This keeps only what a
 *  person actually needs — who it is from, what it is, the link, and the one
 *  instruction ("open the link first") that fourteen people needed and did not
 *  have. Kept short deliberately: a longer one splits into several messages and
 *  some phones then deliver them out of order, which breaks the link. */
function invite_share_text($inv, $url, $host = null) {
    $first = invite_first_name($inv['name'] ?? '');
    $who   = trim((string)($host['name'] ?? '')) ?: 'William';
    $site  = (string)config('site_name') ?: 'The Battles Legacy';

    return 'Hi ' . ($first !== '' ? $first : 'there') . ", it's " . $who . ". "
         . 'I have been putting our family history online — ' . $site . '. '
         . "The tree, old photographs, the church and business history.\n\n"
         . "It's private, so it's invitation only. This link is yours:\n"
         . $url . "\n\n"
         . 'Open that link the first time and you choose your own password. '
         . "Until you have, there is no password of yours to type.";
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
        /* Replies go to the family mailbox on the domain, not to a Gmail
           address, so the From and the Reply-To agree. It forwards to
           William either way. */
        'reply_to'   => mailer_address(),
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

/* ------------------------------------------------------------------ *
 *  The drip queue
 *
 *  Measured from the invites table on 1 September, not remembered:
 *
 *    17 Aug  49 sent between 22:04:59 and 22:19:03  ->  2 accounts   (4%)
 *    every other day, 1-3 at a time                 ->  7 of 9       (78%)
 *
 *  Same words, same sending domain, same family. And 36 of the 64
 *  addresses are Yahoo-operated (yahoo, att, sbcglobal, aol all run on
 *  Yahoo's mail), so ONE reputation decision covers over half the list.
 *
 *  Correlation, not proof - the 49 were also the least-connected part of
 *  the list. But it is the only thing in the data that moves with the
 *  outcome, and slowing down costs nothing to try.
 *
 *  Nothing here rewrites the invitation. It only changes the rate.
 * ------------------------------------------------------------------ */

/** at most this many go out in any rolling 24 hours */
function drip_per_day() { return 6; }
/** and never two closer together than this many minutes */
function drip_gap_minutes() { return 9; }

/** invitations sitting in the queue, oldest first */
function invite_queued($limit = 0) {
    invite_migrate();
    $sql = "SELECT * FROM invites WHERE queued_at IS NOT NULL AND used_at IS NULL
            AND email <> '' ORDER BY queued_at, id";
    if ($limit > 0) $sql .= ' LIMIT ' . (int)$limit;
    try { return all($sql); } catch (\Throwable $e) { return []; }
}

function invite_queued_count() { return count(invite_queued()); }

/** put invitations into the queue. $ids empty = every unopened one that has an address. */
function invite_queue_add($ids = null) {
    invite_migrate();
    $now = date('Y-m-d H:i:s');
    $n = 0;
    if (is_array($ids) && $ids) {
        foreach ($ids as $id) {
            q("UPDATE invites SET queued_at=? WHERE id=? AND queued_at IS NULL AND used_at IS NULL AND email <> ''",
              [$now, (int)$id]);
            $n++;
        }
        return $n;
    }
    /* everyone who has never opened theirs - the 47 */
    $rows = all("SELECT id FROM invites WHERE used_at IS NULL AND opened_at IS NULL
                 AND email <> '' AND queued_at IS NULL");
    foreach ($rows as $r) { q("UPDATE invites SET queued_at=? WHERE id=?", [$now, (int)$r['id']]); $n++; }
    return $n;
}

function invite_queue_clear() {
    invite_migrate();
    try { q("UPDATE invites SET queued_at=NULL WHERE queued_at IS NOT NULL AND used_at IS NULL"); } catch (\Throwable $e) {}
}

/** how many have actually gone out in the last 24 hours, by any route */
function drip_sent_last_day() {
    invite_migrate();
    try {
        $r = one("SELECT COUNT(*) c FROM invites WHERE emailed_at IS NOT NULL AND emailed_at >= ?",
                 [date('Y-m-d H:i:s', time() - 86400)]);
        return $r ? (int)$r['c'] : 0;
    } catch (\Throwable $e) { return 0; }
}

/** the most recent send of any kind, as a unix time, or 0 */
function drip_last_send_ts() {
    invite_migrate();
    try {
        $r = one("SELECT MAX(emailed_at) m FROM invites WHERE emailed_at IS NOT NULL");
        return ($r && $r['m']) ? (int)strtotime($r['m']) : 0;
    } catch (\Throwable $e) { return 0; }
}

/**
 * May one go out right now? Returns [bool ok, string why-not, int seconds-to-wait].
 * Deliberately counts EVERY send, not just dripped ones: a manual "send again"
 * click still spends the domain's goodwill, so it still resets the clock.
 */
function drip_ready() {
    if (!invite_queued(1)) return [false, 'The queue is empty.', 0];
    $today = drip_sent_last_day();
    if ($today >= drip_per_day())
        return [false, $today . ' have already gone out in the last 24 hours, which is the daily limit.', 0];
    $gap  = drip_gap_minutes() * 60;
    $last = drip_last_send_ts();
    $wait = $last ? ($last + $gap) - time() : 0;
    if ($wait > 0) return [false, 'Too soon after the last one.', $wait];
    return [true, '', 0];
}

/**
 * Send at most ONE queued invitation. Returns [sent(bool), message].
 * One per call on purpose - a loop here would be the burst all over again.
 */
function invite_drip_release($host = null) {
    list($ok, $why, $wait) = drip_ready();
    if (!$ok) return [false, $why];
    $rows = invite_queued(1);
    if (!$rows) return [false, 'The queue is empty.'];
    $inv = $rows[0];
    $sent = invite_mail($inv, $host ?: invite_host());
    /* Out of the queue either way. A refused address will not start working on
       the next pass, and leaving it in would block everybody behind it. */
    try { q("UPDATE invites SET queued_at=NULL WHERE id=?", [(int)$inv['id']]); } catch (\Throwable $e) {}
    $who = trim((string)$inv['name']) ?: $inv['email'];
    return [$sent, $sent ? 'Sent to ' . $who . '.' : 'The mail server would not take the one for ' . $who . '.'];
}

/**
 * Called on ordinary page loads so the queue keeps moving without a cron job.
 * Does nothing at all unless the gap and the daily cap both allow it, and
 * never sends more than one. Cheap: one COUNT and one MAX before it gives up.
 */
function invite_drip_tick() {
    static $ran = false;
    if ($ran) return; $ran = true;
    try {
        list($ok,,) = drip_ready();
        if ($ok) invite_drip_release(invite_host());
    } catch (\Throwable $e) { /* never break a page over this */ }
}
