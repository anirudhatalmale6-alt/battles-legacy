<?php
/** Every message the site sends leaves through here.
 *
 *  The host runs exim and accepts what PHP hands it, and the domain is
 *  DKIM-signed, so mail does go out. What it cannot promise is that it goes
 *  *in* — a family address at Gmail may still file it under spam. So nothing
 *  in this file ever tells anyone a message "has been sent"; it reports only
 *  what it actually knows, which is whether the mail server took it.
 *
 *  Everything that sends also has a second way through — a link William can
 *  pass on himself — so no one is ever stuck waiting on a message that never
 *  came. */
require_once __DIR__ . '/helpers.php';

/** The address mail leaves from. It has to be at the site's own domain or the
 *  DKIM signature won't match it and delivery gets worse, not better.
 *
 *  Deliberately NOT no-reply@. That prefix is one of the oldest bulk-mail
 *  markers there is, and it also tells the reader not to answer — which costs
 *  us the one signal that actually teaches Gmail this sender is wanted, since
 *  a reply is the strongest "not spam" vote a person can cast. Replies go to
 *  William anyway via Reply-To, so nothing lands in an unread mailbox. */
function mailer_from() {
    $host = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host === '' || strpos($host, '.') === false) {
        $cfg = (string)config('mail_from');
        return $cfg !== '' ? $cfg : 'family@thebattlesfamily.com';
    }
    return 'family@' . $host;
}

function mailer_valid($addr) {
    $addr = trim((string)$addr);
    return $addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL) ? $addr : '';
}

/** Hand one plain-text message to the mail server.
 *
 *  Returns true only when the server accepted it for delivery. That is a much
 *  weaker statement than "it arrived", and every caller words it that way.
 *
 *  $opts['reply_to']  — usually William, so a reply reaches a person
 *  $opts['reply_name']
 *  $opts['to_name']   — the recipient's own name, see below
 */
function mailer_send($to, $subject, $body, $opts = []) {
    if (!function_exists('mail')) return false;
    $to = mailer_valid($to);
    if ($to === '') return false;

    /* A To: header carrying a bare address and no name is a small mark against
       the message — the host's own scanner flagged exactly that (TO_DN_NONE)
       on the first invitation. We always know who we are writing to, because
       William typed her name in beside the address, so there is no reason to
       throw it away. */
    $toName = trim(str_replace(['"', "\r", "\n"], '', (string)($opts['to_name'] ?? '')));
    $toHdr  = $toName !== '' ? mailer_phrase($toName) . ' <' . $to . '>' : $to;

    $from = mailer_from();
    $site = (string)config('site_name') ?: 'The Battles Legacy';

    /* A subject line with an accent in it has to be encoded or it arrives as
       mojibake; plain ASCII is left alone so it stays readable in the logs. */
    $subject = str_replace(["\r", "\n"], ' ', (string)$subject);
    $subjHdr = preg_match('/[\x80-\xFF]/', $subject)
        ? '=?UTF-8?B?' . base64_encode($subject) . '?='
        : $subject;

    $replyTo   = mailer_valid($opts['reply_to'] ?? '');
    $replyName = trim(str_replace(['"', "\r", "\n"], '', (string)($opts['reply_name'] ?? '')));

    $h   = [];
    $h[] = 'From: ' . mailer_phrase($site) . ' <' . $from . '>';
    $h[] = $replyTo !== ''
         ? 'Reply-To: ' . ($replyName !== '' ? mailer_phrase($replyName) . ' ' : '') . '<' . $replyTo . '>'
         : 'Reply-To: ' . mailer_phrase($site) . ' <' . $from . '>';
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'Content-Type: text/plain; charset=UTF-8';
    /* The messages contain em-dashes and curly apostrophes. Declaring UTF-8 and
       shipping the raw bytes as 8bit works right up until one hop in the chain
       is old enough not to announce 8BITMIME, and then the eighth bit is
       stripped and an aunt on an ancient mailbox reads "history online â€"
       the tree". Quoted-printable is plain ASCII on the wire, so there is no
       eighth bit left to lose. */
    $h[] = 'Content-Transfer-Encoding: quoted-printable';
    /* There was an `Auto-Submitted: auto-generated` here with a comment saying
       it marked the message as human-triggered. It does the opposite: RFC 3834
       uses it to label machine-generated mail so that other machines don't
       auto-reply to it, and filters read it as one more bulk marker. This mail
       IS triggered by a person pressing a button, so the honest thing is to
       claim nothing at all. Same reasoning for the home-made X-Mailer that sat
       below it — a mailer name no filter has ever seen before is a small cost
       and buys nothing.

       A Message-ID at the sending domain, on the other hand, is worth setting.
       Left alone, exim stamps one at the physical server (michigan.shnw.net),
       which does not match the From domain — a mismatch there is a documented
       spam signal, and it is free to get right. */
    $fromHost = substr($from, strpos($from, '@') + 1);
    $h[] = 'Message-ID: <' . mailer_msgid_token() . '@' . $fromHost . '>';

    $body = str_replace(["\r\n", "\r"], "\n", (string)$body);
    $body = str_replace("\n", "\r\n", $body);
    $body = quoted_printable_encode($body);

    try { return @mail($toHdr, $subjHdr, $body, implode("\r\n", $h), '-f' . $from) ? true : false; }
    catch (\Throwable $e) { return false; }
}

/** The unique half of a Message-ID. Only has to be unlikely to repeat. */
function mailer_msgid_token() {
    if (function_exists('random_bytes')) {
        try { return bin2hex(random_bytes(12)); } catch (\Throwable $e) { /* fall through */ }
    }
    return bin2hex(pack('N', time())) . uniqid('', true);
}

/** Quote a display name for a header if it needs it. */
function mailer_phrase($name) {
    $name = trim(str_replace(['"', "\r", "\n"], '', (string)$name));
    if ($name === '') return '';
    if (preg_match('/[\x80-\xFF]/', $name)) return '=?UTF-8?B?' . base64_encode($name) . '?=';
    return preg_match('/[^A-Za-z0-9 \.\-]/', $name) ? '"' . $name . '"' : $name;
}

/** A mailto: address that opens William's own email app with the whole message
 *  already written.
 *
 *  This is the path that always works. Mail sent by a website is judged on the
 *  website's reputation; the same words sent from his own Gmail arrive from
 *  somebody the family already has in their contacts. */
function mailto_link($to, $subject, $body) {
    $to = trim((string)$to);
    /* The address goes in as it stands. Encoding it turns the @ into %40, which
       the newer apps decode but some older phone mail apps paste literally into
       the To: box. A "+" in a local part is safe here too — this is the path of
       the mailto, not a query string, so it is not read as a space. */
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) $to = rawurlencode($to);
    /* rawurlencode, not urlencode, for the rest: a space has to be %20 here.
       urlencode turns it into "+", which does get pasted in literally. */
    return 'mailto:' . $to
         . '?subject=' . rawurlencode((string)$subject)
         . '&body=' . rawurlencode(str_replace(["\r\n", "\r"], "\n", (string)$body));
}
