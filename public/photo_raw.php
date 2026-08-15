<?php
/** Shows one picture out of the old photo folder.
 *
 *  That folder stopped being served when the site moved to the bare domain,
 *  which is what we want — but the "who is this?" screen still has to display
 *  them. So they come through here instead: signed in as an editor, one file
 *  at a time, by name only, and only if it really is an image. */
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$SRC = rtrim(config('photo_src_dir') ?: (dirname(dirname(__DIR__)) . '/photos'), '/');
if (!is_dir($SRC)) {
    $guess = dirname(dirname(dirname(__DIR__))) . '/photos';
    if (is_dir($guess)) $SRC = $guess;
}

$f = basename((string)($_GET['f'] ?? ''));          // strips any path, so it cannot climb out
if ($f === '' || $f[0] === '.') { http_response_code(404); exit; }
$abs = $SRC . '/' . $f;

$real = @realpath($abs); $rootReal = @realpath($SRC);
if (!$real || !$rootReal || strpos($real, $rootReal . DIRECTORY_SEPARATOR) !== 0) { http_response_code(404); exit; }
if (!is_file($real)) { http_response_code(404); exit; }

$info = @getimagesize($real);
$ok = ['image/jpeg' => 'image/jpeg', 'image/png' => 'image/png',
       'image/gif' => 'image/gif', 'image/webp' => 'image/webp'];
if (!$info || !isset($ok[$info['mime']])) { http_response_code(404); exit; }

header('Content-Type: ' . $ok[$info['mime']]);
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, max-age=600');
header('X-Content-Type-Options: nosniff');
readfile($real);
