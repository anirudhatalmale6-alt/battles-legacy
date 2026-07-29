<?php
/**
 * Auto-pin a folder of photos to people by reading the filename.
 * Usage: php bin/import_photos.php /path/to/photo/library [--dry]
 *
 * "Elizabeth Battles 3.jpg" -> person "Elizabeth Battles"
 * "Elbert Domino  Sr 1.jpg" -> "Elbert Domino Sr" (falls back to "Elbert Domino")
 * "Andrea K Battles 1.jpg"  -> first name + surname match -> "Andrea Kefane Battles"
 * Numeric TribalPages files (battlesfamily_1234[1].jpg) can't be matched -> listed for review.
 */
require __DIR__ . '/../src/db.php';

$srcDir = $argv[1] ?? dirname(__DIR__, 1);          // default: the project folder (holds the sample jpgs)
$dry    = in_array('--dry', $argv, true);
if (!is_dir($srcDir)) { fwrite(STDERR, "Source dir not found: $srcDir\n"); exit(1); }

$photosRoot = __DIR__ . '/../public/' . config('photos_dir');
if (!$dry) @mkdir($photosRoot, 0775, true);

// ---- helpers ----
$SUFFIX = ['sr','jr','ii','iii','iv','v'];
function norm($s) {
    $s = strtolower($s);
    $s = str_replace(['`',"'",'"','.'], '', $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}
function clean_filename($base) {
    $b = preg_replace('/\[\d+\]/', '', $base);       // [1]
    $b = preg_replace('/\([^)]*\)/', '', $b);        // (1) or (Gus) nickname
    $b = preg_replace('/[_]+/', ' ', $b);            // underscores -> space
    $b = preg_replace('/\s*\d+\s*$/', '', $b);       // trailing photo number
    return trim(preg_replace('/\s+/', ' ', $b));
}
function first_last($name, $SUFFIX) {
    $t = explode(' ', norm($name));
    $t = array_values(array_filter($t, fn($w) => !in_array($w, $SUFFIX, true)));
    if (count($t) < 2) return null;
    return $t[0] . ' ' . end($t);
}

// Manual aliases for names the client spelled differently (extend as needed)
$OVERRIDES = [
    'anothany wynn'   => '@I252@',  // client spelled "Anothany" -> Anthony 'Tony' Damon Wynn
    'agustus battles' => '@I35@',   // "Agustus (Gus)" -> Augustus `Gus` Battles
];

// ---- build person lookup ----
$people = all("SELECT pid,name,given,surname FROM persons");
$byFull = []; $byFL = []; $byGiven = []; $ambiguous = [];
foreach ($people as $p) {
    $full = norm($p['name']);
    if ($full !== '') {
        if (isset($byFull[$full]) && $byFull[$full] !== $p['pid']) $ambiguous[$full] = true;
        else $byFull[$full] = $p['pid'];
    }
    $fl = first_last($p['name'], $SUFFIX);
    if ($fl && !isset($byFL[$fl])) $byFL[$fl] = $p['pid'];
    // Full given name (e.g. "Annie Pearl") for surname-less filenames — only when unambiguous.
    $g = norm($p['given']);
    if ($g !== '' && strpos($g, ' ') !== false) {
        if (isset($byGiven[$g]) && $byGiven[$g] !== $p['pid']) $byGiven[$g] = null; // ambiguous -> disable
        elseif (!array_key_exists($g, $byGiven)) $byGiven[$g] = $p['pid'];
    }
}

function match_person($cleanName, $SUFFIX, $OVERRIDES, $byFull, $byFL, $byGiven) {
    $n = norm($cleanName);
    if (isset($OVERRIDES[$n])) return [$OVERRIDES[$n], 'override'];
    if (isset($byFull[$n]))    return [$byFull[$n], 'exact'];
    // drop a trailing suffix and retry exact (e.g. "elbert domino sr" -> "elbert domino")
    $t = explode(' ', $n);
    if (count($t) > 2 && in_array(end($t), $SUFFIX, true)) {
        array_pop($t); $n2 = implode(' ', $t);
        if (isset($byFull[$n2])) return [$byFull[$n2], 'exact-nosuffix'];
    }
    $fl = first_last($cleanName, $SUFFIX);
    if ($fl && isset($byFL[$fl])) return [$byFL[$fl], 'first-last'];
    if (!empty($byGiven[$n]))  return [$byGiven[$n], 'given-name'];  // surname-less filename
    return [null, 'unmatched'];
}

// ---- scan files ----
$exts = ['jpg','jpeg','png','gif'];
$files = [];
foreach (scandir($srcDir) as $f) {
    if ($f[0] === '.') continue;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) continue;
    $files[] = $f;
}
sort($files);

$stats = ['matched'=>0,'copied'=>0,'skipped'=>0,'unmatched'=>0];
$unmatched = [];
$exists = db()->prepare("SELECT id FROM photos WHERE pid=? AND filename=?");
$insert = db()->prepare("INSERT INTO photos (pid,filename,path,caption,status,source) VALUES (?,?,?,?, 'approved','import')");

foreach ($files as $f) {
    $base = pathinfo($f, PATHINFO_FILENAME);
    $cleanName = clean_filename($base);
    [$pid, $how] = match_person($cleanName, $SUFFIX, $OVERRIDES, $byFull, $byFL, $byGiven);
    if (!$pid) { $stats['unmatched']++; $unmatched[] = $f; continue; }
    $stats['matched']++;

    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $f);
    $relDir = config('photos_dir') . '/' . trim($pid, '@');
    $rel = $relDir . '/' . $safe;

    $exists->execute([$pid, $f]);
    if ($exists->fetch()) { $stats['skipped']++; continue; }

    if (!$dry) {
        @mkdir(__DIR__ . '/../public/' . $relDir, 0775, true);
        if (@copy($srcDir . '/' . $f, __DIR__ . '/../public/' . $rel)) $stats['copied']++;
        $insert->execute([$pid, $f, $rel, $cleanName]);
    }
    echo sprintf("  %-40s -> %-8s %s\n", $f, $pid, $how);
}

echo "\n";
printf("Matched %d  ·  copied %d  ·  already-present %d  ·  UNMATCHED %d%s\n",
    $stats['matched'], $stats['copied'], $stats['skipped'], $stats['unmatched'], $dry ? '  (dry run)' : '');
if ($unmatched) {
    echo "\nNeeds a name from the family (couldn't match automatically):\n";
    foreach ($unmatched as $u) echo "  - $u\n";
}
