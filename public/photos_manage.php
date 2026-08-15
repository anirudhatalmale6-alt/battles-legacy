<?php
/** Two jobs in one place:
 *   1. the photographs in the old folder nobody could put a name to
 *   2. the ones that came across small, so William knows which originals to resend */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/install.php';
require_role('admin');

$SRC = rtrim(config('photo_src_dir') ?: (dirname(dirname(__DIR__)) . '/photos'), '/');
if (!is_dir($SRC)) {
    /* the app lives in .../legacy, the old library sits beside it in public_html */
    $guess = dirname(dirname(dirname(__DIR__))) . '/photos';
    if (is_dir($guess)) $SRC = $guess;
}
$EXT = ['jpg','jpeg','png','gif','webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'assign') {
        $done = 0; $bad = 0;
        foreach (($_POST['pid'] ?? []) as $file => $pid) {
            $pid = trim($pid);
            if ($pid === '') continue;
            $file = basename($file);                                  // never leave the folder
            $abs  = $SRC . '/' . $file;
            if (!is_file($abs) || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $EXT, true)) { $bad++; continue; }
            if (!one("SELECT pid FROM persons WHERE pid=?", [$pid])) { $bad++; continue; }
            $safe   = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file);
            $relDir = config('photos_dir') . '/' . trim($pid, '@');
            $rel    = $relDir . '/' . $safe;
            if (one("SELECT id FROM photos WHERE pid=? AND filename=?", [$pid, $file])) continue;
            @mkdir(__DIR__ . '/' . $relDir, 0775, true);
            if (!@copy($abs, __DIR__ . '/' . $rel)) { $bad++; continue; }
            $cap = trim($_POST['cap'][$file] ?? '') ?: _clean_filename(pathinfo($file, PATHINFO_FILENAME));
            q("INSERT INTO photos (pid,filename,path,caption,status,source) VALUES (?,?,?,?, 'approved','import')",
              [$pid, $file, $rel, mb_substr($cap, 0, 500)]);
            $done++;
        }
        flash($done ? "$done photograph" . ($done === 1 ? '' : 's') . ' placed.' . ($bad ? " $bad could not be." : '')
                    : 'Nothing was selected.');
        header('Location: photos_manage.php'); exit;
    }
}

/* ---- which files in the old folder are still unplaced? ---- */
$unplaced = [];
if (is_dir($SRC)) {
    $known = [];
    foreach (all("SELECT filename FROM photos") as $r) $known[$r['filename']] = true;
    $files = [];
    foreach (scandir($SRC) as $f) {
        if ($f[0] === '.' || !in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $EXT, true)) continue;
        if (preg_match('/^thumb[_ ]/i', $f)) continue;      // the small twin of another file
        $files[] = $f;
    }
    sort($files);
    /* Skip anything that is byte for byte a photo already on the site — that
       folder holds three copies of most pictures and there is no sense asking
       him to name the same face over and over. */
    $haveHash = [];
    foreach (all("SELECT path FROM photos") as $r) {
        $abs = __DIR__ . '/' . $r['path'];
        if (is_file($abs)) $haveHash[md5_file($abs)] = true;
    }
    foreach ($files as $f) {
        if (isset($known[$f])) continue;
        $h = @md5_file($SRC . '/' . $f);
        if ($h === false || isset($haveHash[$h])) continue;
        $haveHash[$h] = true;                                // and only ask once per distinct picture
        $unplaced[] = $f;
    }
}

/* ---- photographs that arrived small ---- */
$small = []; $checked = 0;
foreach (all("SELECT p.pid, p.path, p.caption, x.name FROM photos p LEFT JOIN persons x ON x.pid=p.pid ORDER BY x.name") as $r) {
    $abs = __DIR__ . '/' . $r['path'];
    if (!is_file($abs)) continue;
    $checked++;
    $sz = @getimagesize($abs);
    if (!$sz) continue;
    if (max($sz[0], $sz[1]) < 600) $small[] = $r + ['w' => $sz[0], 'h' => $sz[1]];
}

$people = all("SELECT pid,name,birth_date FROM persons WHERE name<>'' ORDER BY name");
page_head('Photographs');
?>
<h1>Photographs</h1>
<p class="lede">Everything still to do with the family picture library, in one place.</p>

<div class="panel" style="margin-top:20px">
  <h2>Pictures nobody has named yet (<?= count($unplaced) ?>)</h2>
  <?php if (!is_dir($SRC)): ?>
    <p class="muted">The old photo folder isn&rsquo;t where I expected it (<?= e($SRC) ?>).</p>
  <?php elseif (!$unplaced): ?>
    <p class="muted">None left &mdash; every distinct picture in that folder is on somebody&rsquo;s page.</p>
  <?php else: ?>
    <p class="muted">These came out of TribalPages with a number instead of a name, or with a name I couldn&rsquo;t
      match to anyone in the tree. Pick the person and press Save &mdash; the picture goes onto their page.
      Leave any you don&rsquo;t recognise; they&rsquo;ll still be here next time.</p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="assign">
      <div class="pm-grid">
        <?php foreach ($unplaced as $f): $u = 'photo_raw.php?f=' . urlencode($f); ?>
          <figure class="pm-card">
            <a href="<?= e($u) ?>" target="_blank" rel="noopener"><img src="<?= e($u) ?>" alt="<?= e($f) ?>" loading="lazy"></a>
            <figcaption><?= e($f) ?></figcaption>
            <select name="pid[<?= e($f) ?>]">
              <option value="">— who is this? —</option>
              <?php foreach ($people as $p): ?>
                <option value="<?= e($p['pid']) ?>"><?= e($p['name']) ?><?= yr($p['birth_date']) ? ' (' . e(yr($p['birth_date'])) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="cap[<?= e($f) ?>]" placeholder="Caption (optional)" maxlength="200">
          </figure>
        <?php endforeach; ?>
      </div>
      <button class="btn gold" type="submit" style="margin-top:16px">Save the ones I&rsquo;ve named</button>
    </form>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:20px">
  <h2>Photographs that came across small (<?= count($small) ?> of <?= $checked ?>)</h2>
  <p class="muted">These are under 600 pixels on their longest side, so they look soft when opened.
    They came out of TribalPages at the size it displayed them, not the size you scanned them.
    Enlarging one of these won&rsquo;t sharpen it &mdash; the detail was never in the file. The fix is to upload
    the original from wherever you scanned it, on that person&rsquo;s page.</p>
  <?php if (!$small): ?>
    <p class="muted">None &mdash; every picture on the site is a decent size.</p>
  <?php else: ?>
    <div class="pm-small">
      <?php foreach ($small as $s): ?>
        <a class="pm-srow" href="person.php?pid=<?= e(urlencode($s['pid'])) ?>">
          <span class="pm-sthumb"><img src="<?= e($s['path']) ?>" alt="" loading="lazy"></span>
          <span class="pm-sname"><?= e($s['name'] ?: $s['pid']) ?></span>
          <span class="pm-sdim"><?= (int)$s['w'] ?> &times; <?= (int)$s['h'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php page_foot();
