<?php
/** One nudge of the note queue, for the page he is watching.
 *
 *  The queue moves on ordinary page loads, which is fine for a busy site and
 *  useless on a private family one at eleven at night: he pressed send, one
 *  message went, and nothing loaded a page again for ten minutes. So while the
 *  compose page is open it asks here every half minute, and the queue really
 *  does finish on its own in front of him.
 *
 *  It only nudges. Every rule about how fast a copy may leave lives in
 *  note_ready(); calling this in a loop cannot make anything go faster. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/notes.php';
require_role('admin');

note_tick();

list($ok, $why, $wait) = note_ready();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'pending' => note_pending_count(),
    'ready'   => (bool)$ok,
    'wait'    => (int)$wait,
    'why'     => (string)$why,
    'sent24'  => note_sent_last_day(),
]);
