<?php
/** What we know about each photograph file - its fingerprint and pixel size -
 *  read from disk at most once, until the file changes.
 *
 *  Photos > Manage needs the fingerprint of every site photo and of every file
 *  in the old TribalPages library (1,345 files, 67 MB), and the pixel size of
 *  every site photo. It used to read all of them on every visit. On this host's
 *  throttled disk a cold run takes minutes - past PHP's 90-second limit - so the
 *  page never arrived, and because the session was held locked meanwhile, every
 *  other page William opened froze too (reported 17 Nov 2026).
 *
 *  Kept here rather than in the page so that anything else that fills or reads
 *  the index builds exactly the same keys. The cache lives in legacy/cache/,
 *  outside the web root. */

function pm_src_dir($pub) {
    /* the old library: beside the app in public_html, unless config says otherwise */
    $src = rtrim(config('photo_src_dir') ?: (dirname(dirname($pub)) . '/photos'), '/');
    if (!is_dir($src)) {
        $guess = dirname(dirname(dirname($pub))) . '/photos';
        if (is_dir($guess)) $src = $guess;
    }
    return $src;
}

function pm_cache_file($pub) {
    $dir = dirname($pub) . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    return $dir . '/photo_index.json';
}

function pm_index_load($pub) {
    $GLOBALS['PM_IDX'] = [];
    $GLOBALS['PM_DIRTY'] = false;
    $GLOBALS['PM_PENDING'] = 0;
    $f = pm_cache_file($pub);
    if (is_file($f)) $GLOBALS['PM_IDX'] = json_decode((string)@file_get_contents($f), true) ?: [];
}

function pm_index_save($pub) {
    if (!empty($GLOBALS['PM_DIRTY'])) {
        @file_put_contents(pm_cache_file($pub), json_encode($GLOBALS['PM_IDX']), LOCK_EX);
        $GLOBALS['PM_DIRTY'] = false;
    }
}

/** Fingerprint ('md5') or pixel size ('dim') of a file, read at most once.
 *  Returns null when it is not known yet and the time budget has run out. */
function pm_info($abs, $want, $deadline) {
    $e = $GLOBALS['PM_IDX'][$abs] ?? null;
    $have = $e && (($want === 'md5' && array_key_exists('md5', $e)) || ($want === 'dim' && array_key_exists('w', $e)));
    /* Out of time: what we already know is good enough for this visit. Even a
       stat() per file is slow on a cold, throttled disk - with ~2,100 files it
       alone can outrun the budget - so past the deadline nothing touches the
       disk at all. A photograph changed in place is caught on the next visit
       that has time to spare. */
    if (microtime(true) > $deadline) {
        if ($have) return $e;
        $GLOBALS['PM_PENDING']++;
        return null;
    }
    $st = @stat($abs);
    if (!$st) return null;
    if (!$e || $e['s'] !== $st['size'] || $e['m'] !== $st['mtime']) {
        $e = ['s' => $st['size'], 'm' => $st['mtime']];
    }
    $missing = ($want === 'md5' && !array_key_exists('md5', $e)) || ($want === 'dim' && !array_key_exists('w', $e));
    if ($missing) {
        if (microtime(true) > $deadline) { $GLOBALS['PM_PENDING']++; return null; }
        if ($want === 'md5') {
            $e['md5'] = (string)@md5_file($abs);
        } else {
            $sz = @getimagesize($abs);
            $e['w'] = $sz ? (int)$sz[0] : 0;
            $e['hh'] = $sz ? (int)$sz[1] : 0;
        }
        $GLOBALS['PM_IDX'][$abs] = $e;
        $GLOBALS['PM_DIRTY'] = true;
    }
    return $e;
}
