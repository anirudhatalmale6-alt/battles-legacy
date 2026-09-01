<?php
/* TEMPORARY diagnostic - deleted immediately after use. */
if (($_GET['k'] ?? '') !== '5c2ae91b70df') { http_response_code(404); exit('Not Found'); }
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/notes.php';
require_once __DIR__ . '/../src/invites.php';
echo "BEGIN\n";
echo "now=" . date('Y-m-d H:i:s T') . " (unix " . time() . ")\n";
echo "gap=" . note_gap_minutes() . "min cap=" . note_per_day() . "/day\n\n";

foreach (note_all(10) as $n) {
    $p = note_progress((int)$n['id']);
    echo "NOTE #" . $n['id'] . " '" . $n['subject'] . "'\n";
    echo "   created=" . $n['created_at'] . " queued_at=" . var_export($n['queued_at'], true) . "\n";
    echo "   queued=" . $p['queued'] . " sent=" . $p['sent'] . " waiting=" . $p['waiting'] . " refused=" . $p['failed'] . "\n";
}

echo "\nEVERY ROW:\n";
foreach (all("SELECT * FROM note_sends ORDER BY id") as $r) {
    echo "  #" . str_pad($r['id'],3) . " " . str_pad($r['name'], 22)
       . " queued=" . $r['queued_at']
       . " sent=" . str_pad(var_export($r['sent_at'], true), 21)
       . " ok=" . (int)$r['ok'] . "\n";
}

echo "\npending=" . note_pending_count()
   . " sent_last_24h=" . note_sent_last_day()
   . " last_send_ts=" . note_last_send_ts()
   . (note_last_send_ts() ? ' (' . date('H:i:s', note_last_send_ts()) . ', ' . (time() - note_last_send_ts()) . 's ago)' : '') . "\n";
list($ok, $why, $wait) = note_ready();
echo "note_ready = " . var_export($ok, true) . " why='" . $why . "' wait=" . $wait . "s\n";

echo "\nrecipients now=" . count(note_recipients()) . " optedout=" . note_opted_out_count() . "\n";
echo "invite queue=" . invite_queued_count() . " invites sent 24h=" . drip_sent_last_day() . "\n";
echo "mail() available=" . (function_exists('mail') ? 'yes' : 'NO') . "\n";
echo "END\n";
