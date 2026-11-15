<?php
/** Private media - for signed-in family only.
 *
 *  Every other video on the site shows ancestors or public figures, and lives
 *  in public/assets/video/ where anyone with the address can fetch it. The
 *  reunion video is different: it is full of LIVING relatives, and the whole
 *  site runs on one rule - living relatives are visible to signed-in family and
 *  nobody else. A page behind a login is not enough if the file itself sits at
 *  a guessable public address, so the files live in legacy/private_media/,
 *  outside the web root, and come out only through here.
 *
 *  Byte ranges are honoured. iPhones will not play a video that cannot be
 *  fetched in pieces - Safari asks for bytes=0-1 first and gives up on a server
 *  that answers 200 with the whole file - and seeking needs them everywhere.
 *
 *  Only names on the list below can be asked for. The request never builds a
 *  path from what it was given. */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$FILES = [
    'together-again.mp4' => ['video/mp4',  'Together-Again.mp4'],
    'together-again.jpg' => ['image/jpeg', 'Together-Again.jpg'],
];
/* The reunion album: every file actually in private_media/reunion/ is allowed,
   and nothing else. The request is matched against that directory listing - it
   is compared, never joined onto a path - so "reunion/../config.php" is simply
   not one of the names and gets a 404 like any other stranger. */
$ALBUMS = ['reunion'];
foreach ($ALBUMS as $album) {
    $dir = dirname(__DIR__) . '/private_media/' . $album;
    if (!is_dir($dir)) continue;
    foreach (scandir($dir) as $name) {
        if (preg_match('/^[a-z0-9_-]+\.jpg$/', $name)) {
            $FILES[$album . '/' . $name] = ['image/jpeg', $name];
        }
    }
}

$f = (string)($_GET['f'] ?? '');
if (!isset($FILES[$f])) { http_response_code(404); exit; }
$path = dirname(__DIR__) . '/private_media/' . $f;
if (!is_file($path)) { http_response_code(404); exit; }

list($type, $saveAs) = $FILES[$f];
$size = filesize($path);

/* A three-minute video takes a while to send. Holding the session open for all
   of it would freeze every other page this person has open until it finished. */
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $type);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
if (isset($_GET['dl'])) header('Content-Disposition: attachment; filename="' . $saveAs . '"');

$start = 0;
$end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)) {
    if ($m[1] === '' && $m[2] !== '') {            // "the last N bytes"
        $start = max(0, $size - (int)$m[2]);
    } else {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
    }
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . ($end - $start + 1));

$fp = fopen($path, 'rb');
fseek($fp, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fp)) {
    $chunk = fread($fp, (int)min(131072, $left));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $left -= strlen($chunk);
    flush();
}
fclose($fp);
