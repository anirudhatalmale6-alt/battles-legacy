<?php
/** Two jobs in one place:
 *   1. the photographs in the old folder nobody could put a name to
 *   2. the ones that came across small, so William knows which originals to resend */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/install.php';
require_once __DIR__ . '/../src/photo_people.php';
require_role('admin');
pp_migrate();

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
        $done = 0; $bad = 0; $already = 0; $extraTags = 0; $missing = [];
        /* The cards are keyed by their position on the page, NOT by file name.
           They used to be keyed by file name, and 36 of the 37 photographs still
           waiting are called battlesfamily_6157998[1].jpg — a square bracket inside
           a form field name ends the key early, so PHP received
           "battlesfamily_6157998[1" and the file could never be found. Every one of
           those was unsaveable from the day the page was written. The real name now
           travels in its own hidden field, where no character can break it. */
        foreach (($_POST['pid'] ?? []) as $i => $pid) {
            $pid = trim($pid);
            if ($pid === '') continue;
            $file = basename(trim($_POST['file'][$i] ?? ''));          // never leave the folder
            if ($file === '') { $bad++; continue; }
            $abs  = $SRC . '/' . $file;
            if (!is_file($abs) || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $EXT, true)) { $bad++; $missing[] = $file; continue; }
            if (!one("SELECT pid FROM persons WHERE pid=?", [$pid])) { $bad++; continue; }
            $safe   = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file);
            $relDir = config('photos_dir') . '/' . trim($pid, '@');
            $rel    = $relDir . '/' . $safe;
            /* already on that person - not a failure, but it was silent before */
            if (one("SELECT id FROM photos WHERE pid=? AND filename=?", [$pid, $file])) { $already++; continue; }
            @mkdir(__DIR__ . '/' . $relDir, 0775, true);
            if (!@copy($abs, __DIR__ . '/' . $rel)) { $bad++; $missing[] = $file; continue; }
            $cap = trim($_POST['cap'][$i] ?? '') ?: _clean_filename(pathinfo($file, PATHINFO_FILENAME));
            q("INSERT INTO photos (pid,filename,path,caption,status,source) VALUES (?,?,?,?, 'approved','import')",
              [$pid, $file, $rel, mb_substr($cap, 0, 500)]);
            $newId = (int)insert_id();
            pp_tag($newId, $pid);
            /* Everyone else named in the same card. One file on disk, a row each
               — the group photograph lands on all of their pages. */
            foreach ((array)($_POST['extra'][$i] ?? []) as $ex) {
                $ex = trim($ex);
                if ($ex === '' || $ex === $pid) continue;
                if (!one("SELECT pid FROM persons WHERE pid=?", [$ex])) continue;
                if (pp_tag($newId, $ex)) { pp_reseat_primary($ex); $extraTags++; }
            }
            $done++;
        }
        /* Was: when nothing succeeded the message read "Nothing was selected." and
           threw the failure count away, so 36 photographs failing looked identical
           to him not having picked anybody. Never report a failure as an absence. */
        $bits = [];
        if ($done)    $bits[] = "$done photograph" . ($done === 1 ? '' : 's') . ' placed.';
        if ($already) $bits[] = "$already " . ($already === 1 ? 'was' : 'were') . ' already on that person, so nothing changed for ' . ($already === 1 ? 'it' : 'those') . '.';
        /* no e() here - theme.php escapes the flash on the way out, and escaping
           twice turns an ampersand in a file name into &amp; on screen */
        if ($bad)     $bits[] = "$bad could not be saved" . ($missing ? ' (' . implode(', ', array_slice($missing, 0, 3)) . (count($missing) > 3 ? ', and ' . (count($missing) - 3) . ' more' : '') . ')' : '') . '.';
        if (!$bits)   $bits[] = 'Nothing was selected — pick a name under a photograph first.';
        $msg = implode(' ', $bits);
        if ($extraTags) $msg .= " $extraTags extra " . ($extraTags === 1 ? 'person was' : 'people were')
                              . ' named in group pictures — those now show on their pages too.';
        flash($msg);
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

$people = all("SELECT pid,name,given,surname,sex,birth_date FROM persons WHERE name<>'' ORDER BY name");
$byPid = []; foreach ($people as $pp) $byPid[$pp['pid']] = $pp;
/* Read the file name for anything that looks like a family name and put those
   people at the top of the list. The importer already tried an exact match on
   all of these and failed, which is why they are still here — but "Holmes
   boys.jpg" still knows something, and scrolling two hundred names on a phone
   to find the four Holmeses is the actual work being done on this page. */
$suggest = [];
foreach ($unplaced as $f) $suggest[$f] = pp_suggest(_clean_filename(pathinfo($f, PATHINFO_FILENAME)), $people);
$peopleJson = [];
foreach ($people as $pp) {
    $y = yr($pp['birth_date']);
    $peopleJson[] = ['pid' => $pp['pid'], 'label' => $pp['name'] . ($y ? " ($y)" : '')];
}
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
    <p class="muted">Where the file name gave me something to go on, the likely people are listed first under
      <b>Best guesses</b> &mdash; check the face before you trust it. If more than one person is in the picture,
      press <b>+ someone else in this picture</b> and add them: the photograph is stored once and appears on
      every one of their pages.</p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="assign">
      <div class="pm-grid">
        <?php foreach ($unplaced as $i => $f): $u = 'photo_raw.php?f=' . urlencode($f); $sg = $suggest[$f]; ?>
          <figure class="pm-card">
            <a href="<?= e($u) ?>" target="_blank" rel="noopener"><img src="<?= e($u) ?>" alt="<?= e($f) ?>" loading="lazy"></a>
            <figcaption><?= e($f) ?></figcaption>
            <input type="hidden" name="file[<?= $i ?>]" value="<?= e($f) ?>">
            <select name="pid[<?= $i ?>]">
              <option value="">— who is this? —</option>
              <?php if ($sg): ?>
                <optgroup label="Best guesses from the file name">
                  <?php foreach ($sg as $sp): if (!isset($byPid[$sp])) continue; $p = $byPid[$sp]; ?>
                    <option value="<?= e($p['pid']) ?>"><?= e($p['name']) ?><?= yr($p['birth_date']) ? ' (' . e(yr($p['birth_date'])) . ')' : '' ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="Everyone in the tree">
              <?php endif; ?>
              <?php foreach ($people as $p): ?>
                <option value="<?= e($p['pid']) ?>"><?= e($p['name']) ?><?= yr($p['birth_date']) ? ' (' . e(yr($p['birth_date'])) . ')' : '' ?></option>
              <?php endforeach; ?>
              <?php if ($sg): ?></optgroup><?php endif; ?>
            </select>
            <div class="pm-extra"></div>
            <button type="button" class="pm-more" data-file="<?= $i ?>">+ someone else in this picture</button>
            <input type="text" name="cap[<?= $i ?>]" placeholder="Caption (optional)" maxlength="200">
          </figure>
        <?php endforeach; ?>
      </div>
      <button class="btn gold" type="submit" style="margin-top:16px">Save the ones I&rsquo;ve named</button>
    </form>
    <script>
    /* One copy of the name list for the whole page, rather than another two
       hundred <option> tags every time a group photograph is named. */
    (function(){
      var PEOPLE = <?= json_encode($peopleJson, JSON_UNESCAPED_UNICODE) ?>;
      document.addEventListener('click', function(ev){
        var b = ev.target;
        if (!b || !b.classList || !b.classList.contains('pm-more')) return;
        ev.preventDefault();
        var box = b.parentNode.querySelector('.pm-extra');
        if (!box) return;
        var s = document.createElement('select');
        s.name = 'extra[' + b.getAttribute('data-file') + '][]';
        var first = document.createElement('option');
        first.value = ''; first.textContent = '— and who else? —';
        s.appendChild(first);
        for (var i = 0; i < PEOPLE.length; i++) {
          var o = document.createElement('option');
          o.value = PEOPLE[i].pid; o.textContent = PEOPLE[i].label;
          s.appendChild(o);
        }
        box.appendChild(s);
        s.focus();
      });
    })();
    </script>
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
