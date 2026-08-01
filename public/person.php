<?php
require __DIR__ . '/../src/bootstrap.php';

$pid = $_GET['pid'] ?? '';
$p = one("SELECT * FROM persons WHERE pid=?", [$pid]);
if (!$p) { http_response_code(404); page_head('Not found'); echo '<div class="panel">That person isn\'t in the tree.</div>'; page_foot(); exit; }

// Privacy: living relatives' profiles are for signed-in family only.
if ($p['living'] && !logged_in()) {
    flash('Sign in as family to view living relatives.');
    header('Location: login.php'); exit;
}

// Moderators/admins can choose which photo is this person's main (tree + profile) photo.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && role_at_least('moderator')) {
    csrf_check();
    if (($_POST['action'] ?? '') === 'set_primary') {
        $phid = (int)($_POST['photo_id'] ?? 0);
        if (one("SELECT id FROM photos WHERE id=? AND pid=? AND status='approved'", [$phid, $pid])) {
            q("UPDATE photos SET is_primary=0 WHERE pid=?", [$pid]);
            q("UPDATE photos SET is_primary=1 WHERE id=?", [$phid]);
            flash('Main photo updated — it now shows in the tree and here.');
        }
    } elseif (($_POST['action'] ?? '') === 'delete_photo') {
        $phid = (int)($_POST['photo_id'] ?? 0);
        $ph = one("SELECT * FROM photos WHERE id=? AND pid=?", [$phid, $pid]);
        if ($ph) {
            $abs = __DIR__ . '/' . $ph['path'];
            if (is_file($abs)) @unlink($abs);
            q("DELETE FROM photos WHERE id=?", [$phid]);
            // if we removed the main photo, promote the next one so the tree still has a face
            if (!empty($ph['is_primary'])) {
                $next = one("SELECT id FROM photos WHERE pid=? AND status='approved' ORDER BY id LIMIT 1", [$pid]);
                if ($next) q("UPDATE photos SET is_primary=1 WHERE id=?", [$next['id']]);
            }
            flash('Photo deleted.');
        }
    }
    header('Location: person.php?pid=' . urlencode($pid)); exit;
}

$name = person_display_name($p);
$photos = person_photos($pid);
$occ = json_decode($p['occupation'] ?: '[]', true);
$edu = json_decode($p['education'] ?: '[]', true);
$notes = json_decode($p['notes'] ?: '[]', true);

// relatives
function rel_people($jsonIds) { $ids = json_decode($jsonIds ?: '[]', true); return $ids; }
$parents = []; $spouses = []; $children = [];
foreach (json_decode($p['famc'] ?: '[]', true) as $fid) {
    $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
    if ($f) { foreach (['husb','wife'] as $k) if ($f[$k]) { $rp = one("SELECT * FROM persons WHERE pid=?", [$f[$k]]); if ($rp) $parents[] = $rp; } }
}
foreach (json_decode($p['fams'] ?: '[]', true) as $fid) {
    $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
    if (!$f) continue;
    $sp = $f['husb'] === $pid ? $f['wife'] : $f['husb'];
    if ($sp) { $rp = one("SELECT * FROM persons WHERE pid=?", [$sp]); if ($rp) $spouses[] = $rp; }
    foreach (json_decode($f['chil'] ?: '[]', true) as $cid) { $rp = one("SELECT * FROM persons WHERE pid=?", [$cid]); if ($rp) $children[] = $rp; }
}
function chip_link($rp) {
    $nm = person_display_name($rp);
    $y = yr($rp['birth_date']); $y = $y ? " ($y)" : '';
    return '<a class="chip" href="person.php?pid=' . e($rp['pid']) . '">' . e($nm) . e($y) . '</a>';
}

page_head($name);
?>
<a href="tree.php" class="muted">← Back to the tree</a>
<div class="panel" style="margin-top:12px">
  <div class="profile-head">
    <div class="avatar"><?php if ($photos): ?><img src="<?= e($photos[0]['path']) ?>" alt=""><?php else: ?><span><?= e(strtoupper(substr($p['given'],0,1) . substr($p['surname'],0,1))) ?></span><?php endif; ?></div>
    <div>
      <h1><?= e($name) ?></h1>
      <div class="lede" style="margin-top:2px"><?= e(lifespan($p) ?: 'Dates unknown') ?><?php if ($p['living']): ?> · <span style="color:var(--gold2)">Living family</span><?php endif; ?></div>
      <?php if (logged_in()): ?><a class="btn" href="upload.php?pid=<?= e($pid) ?>" style="margin-top:12px">Add a photo of <?= e(explode(' ', $name)[0]) ?></a><?php endif; ?>
    </div>
  </div>

  <div class="facts">
    <?php if ($p['birth_date'] || $p['birth_place']): ?><div class="fact"><div class="k">Born</div><div class="v"><?= e(trim($p['birth_date'] . ' · ' . $p['birth_place'], ' ·')) ?></div></div><?php endif; ?>
    <?php if ($p['death_date'] || $p['death_place']): ?><div class="fact"><div class="k">Died</div><div class="v"><?= e(trim($p['death_date'] . ' · ' . $p['death_place'], ' ·')) ?></div></div><?php endif; ?>
    <?php if ($p['buri_date'] || $p['buri_place']): ?><div class="fact"><div class="k">Buried</div><div class="v"><?= e(trim($p['buri_date'] . ' · ' . $p['buri_place'], ' ·')) ?></div></div><?php endif; ?>
    <?php foreach ($occ as $o): ?><div class="fact"><div class="k">Occupation</div><div class="v"><?= e($o) ?></div></div><?php endforeach; ?>
    <?php foreach ($notes as $n): ?><div class="fact" style="border-left-color:var(--gold)"><div class="k">From the family records</div><div class="v" style="font-size:15px;white-space:pre-line">“<?= e($n) ?>”</div></div><?php endforeach; ?>
  </div>

  <?php if ($parents || $spouses || $children): ?>
    <?php if ($parents): ?><h2 style="font-size:20px;margin-top:20px">Parents</h2><?php foreach ($parents as $rp) echo chip_link($rp); endif; ?>
    <?php if ($spouses): ?><h2 style="font-size:20px;margin-top:16px">Spouse</h2><?php foreach ($spouses as $rp) echo chip_link($rp); endif; ?>
    <?php if ($children): ?><h2 style="font-size:20px;margin-top:16px">Children (<?= count($children) ?>)</h2><?php foreach ($children as $rp) echo chip_link($rp); endif; ?>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:20px">
  <h2>Photographs<?= $photos ? ' (' . count($photos) . ')' : '' ?></h2>
  <?php if ($photos): ?>
    <?php if (role_at_least('moderator')): ?><p class="muted" style="margin-bottom:8px">The photo marked <b style="color:var(--gold2)">&#9733; Main</b> is what shows in the family tree. Hover a photo to <b>Set as main</b>, or click <b>&times;</b> to delete a duplicate.</p><?php endif; ?>
    <div class="gallery">
      <?php foreach ($photos as $i => $ph): $isMain = ($i === 0); ?>
        <div class="gphoto<?= $isMain ? ' is-main' : '' ?>">
          <a href="#" onclick="lb('<?= e($ph['path']) ?>');return false"><img src="<?= e($ph['path']) ?>" alt="<?= e($ph['caption']) ?>"></a>
          <?php if ($isMain && count($photos) > 1): ?><span class="gmain">&#9733; Main</span><?php endif; ?>
          <?php if (role_at_least('moderator')): ?>
            <form method="post" class="gdel" onsubmit="return confirm('Delete this photo permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_photo"><input type="hidden" name="photo_id" value="<?= (int)$ph['id'] ?>"><button type="submit" title="Delete photo">&times;</button></form>
            <?php if (!$isMain): ?>
              <form method="post" class="gsetmain"><?= csrf_field() ?><input type="hidden" name="action" value="set_primary"><input type="hidden" name="photo_id" value="<?= (int)$ph['id'] ?>"><button type="submit">Set as main</button></form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted">No photographs pinned yet.<?php if (logged_in()): ?> Be the first — <a href="upload.php?pid=<?= e($pid) ?>" style="color:var(--gold2)">add one</a>.<?php endif; ?></p>
  <?php endif; ?>
</div>

<div id="lightbox" onclick="closeLb()"><span class="x">×</span><img id="lightbox-img" onclick="event.stopPropagation()" src="" alt=""></div>
<script>
function lb(src){document.getElementById('lightbox-img').src=src;document.getElementById('lightbox').classList.add('show');}
function closeLb(){document.getElementById('lightbox').classList.remove('show');document.getElementById('lightbox-img').src='';}
window.addEventListener('keydown',e=>{if(e.key==='Escape')closeLb();});
</script>
<?php page_foot();
