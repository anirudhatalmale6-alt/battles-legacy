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
 *  DKIM signature won't match it and delivery gets worse, not better. */
function mailer_from() {
    $host = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host === '' || strpos($host, '.') === false) {
        $cfg = (string)config('mail_from');
        return $cfg !== '' ? $cfg : 'no-reply@thebattlesfamily.com';
    }
    return 'no-reply@' . $host;
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
 */
function mailer_send($to, $subject, $body, $opts = []) {
    if (!function_exists('mail')) return false;
    $to = mailer_valid($to);
    if ($to === '') return false;

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
    $h[] = 'Content-Transfer-Encoding: 8bit';
    /* Tells Gmail and the rest this is a one-off triggered by a person, not a
       mailing list — it is one of the few things that helps without DNS. */
    $h[] = 'Auto-Submitted: auto-generated';
    $h[] = 'X-Mailer: The Battles Legacy';

    $body = str_replace(["\r\n", "\r"], "\n", (string)$body);
    $body = str_replace("\n", "\r\n", $body);

    try { return @mail($to, $subjHdr, $body, implode("\r\n", $h), '-f' . $from) ? true : false; }
    catch (\Throwable $e) { return false; }
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
