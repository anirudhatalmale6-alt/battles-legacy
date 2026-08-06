<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/tree_edit.php';
te_migrate();

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
    } elseif (in_array(($_POST['action'] ?? ''), ['add_child','add_spouse'], true)) {
        $given = trim($_POST['c_given'] ?? '');
        if ($given === '' && trim($_POST['c_surname'] ?? '') === '') {
            flash('Please enter at least a first or last name for the new person.');
        } else {
            $nf = [
              'given'      => $given,
              'surname'    => trim($_POST['c_surname'] ?? ''),
              'sex'        => in_array(strtoupper($_POST['c_sex'] ?? ''), ['M','F'], true) ? strtoupper($_POST['c_sex']) : '',
              'birth_date' => trim($_POST['c_birth'] ?? ''),
              'birth_place'=> trim($_POST['c_birthplace'] ?? ''),
              'death_date' => trim($_POST['c_death'] ?? ''),
              'living'     => !empty($_POST['c_living']) ? 1 : 0,
            ];
            if (($_POST['action']) === 'add_child') {
                $new = te_add_child($pid, $nf, trim($_POST['c_fid'] ?? ''));
                flash($new ? 'Child added to the family. Open their profile to add photos or more detail.' : 'Sorry — that could not be added.');
            } else {
                $new = te_add_spouse($pid, $nf);
                flash($new ? 'Spouse added and linked. Open their profile to add photos or more detail.' : 'Sorry — that could not be added.');
            }
        }
        header('Location: person.php?pid=' . urlencode($pid)); exit;
    } elseif (($_POST['action'] ?? '') === 'link_existing') {
        $rel = in_array($_POST['rel'] ?? '', ['spouse','child','parent'], true) ? $_POST['rel'] : '';
        list($ok, $msg) = te_link_existing($pid, trim($_POST['other_pid'] ?? ''), $rel);
        flash($msg);
        header('Location: person.php?pid=' . urlencode($pid)); exit;
    } elseif (($_POST['action'] ?? '') === 'disconnect') {
        $rtype = in_array($_POST['rtype'] ?? '', ['spouse','child','parent'], true) ? $_POST['rtype'] : '';
        list($ok, $msg) = te_disconnect($pid, trim($_POST['other_pid'] ?? ''), $rtype);
        flash($msg);
        header('Location: person.php?pid=' . urlencode($pid)); exit;
    } elseif (($_POST['action'] ?? '') === 'delete_person') {
        list($ok, $msg) = te_delete_person($pid);
        flash($msg);
        header('Location: ' . ($ok ? 'tree.php' : 'person.php?pid=' . urlencode($pid))); exit;
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

// Members (non-admin) can CLAIM their own person, and SUGGEST adds/edits for
// their close relatives. Every suggestion waits for admin approval.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && logged_in() && !role_at_least('moderator')) {
    csrf_check();
    $u   = current_user();
    $me  = te_user_pid($u);
    $act = $_POST['action'] ?? '';
    if ($act === 'claim_me') {
        if ($me === '') { te_set_user_pid($u['id'], $pid); flash('Thanks — your account is now connected to your place in the family tree.'); }
        else { flash('Your account is already connected to a person in the tree. Ask William if it needs changing.'); }
    } elseif ($act === 'suggest_edit') {
        if (te_can_edit($me, $pid)) { te_add_suggestion('edit', $pid, te_clean_fields($_POST), $u); flash('Thank you — your suggested correction has been sent to William for approval.'); }
        else { flash('You can only suggest changes for your close relatives.'); }
    } elseif (in_array($act, ['suggest_child','suggest_spouse','suggest_sibling'], true)) {
        // these act on the member's own family, so only from their own profile
        if ($me !== '' && $me === $pid) {
            $kind = ['suggest_child'=>'add_child','suggest_spouse'=>'add_spouse','suggest_sibling'=>'add_sibling'][$act];
            $fields = te_clean_fields($_POST);
            if (trim($fields['given']) === '' && trim($fields['surname']) === '') flash('Please enter at least a first or last name.');
            else { te_add_suggestion($kind, $me, $fields, $u); flash('Thank you — your suggestion has been sent to William for approval.'); }
        } else { flash('You can add to your own family from your own profile page.'); }
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
/** relative chip + (admin) a small "disconnect" button */
function rel_chip($rp, $type) {
    $out = '<span class="relwrap">' . chip_link($rp);
    if (role_at_least('moderator')) {
        $out .= '<form method="post" class="reldc" onsubmit="return confirm(\'Remove the connection to ' . e(addslashes(person_display_name($rp))) . '? This only unlinks them here — it does not delete them from the tree.\')">'
              . csrf_field()
              . '<input type="hidden" name="action" value="disconnect"><input type="hidden" name="rtype" value="' . e($type) . '"><input type="hidden" name="other_pid" value="' . e($rp['pid']) . '">'
              . '<button type="submit" title="Remove this connection">&times;</button></form>';
    }
    return $out . '</span>';
}

/** vital-fields form grid (c_* names), prefilled from a person row or blank */
function render_vitals($src = []) {
    $g = e($src['given'] ?? ''); $s = e($src['surname'] ?? '');
    $sex = strtoupper($src['sex'] ?? '');
    $bd = e($src['birth_date'] ?? ''); $bp = e($src['birth_place'] ?? '');
    $dd = e($src['death_date'] ?? ''); $dp = e($src['death_place'] ?? '');
    $liv = !empty($src['living']) ? ' checked' : '';
    ?>
    <div class="af-grid">
      <div><label>First name</label><input type="text" name="c_given" value="<?= $g ?>"></div>
      <div><label>Last name</label><input type="text" name="c_surname" value="<?= $s ?>"></div>
      <div><label>Sex</label><select name="c_sex"><option value="">—</option><option value="M"<?= $sex==='M'?' selected':'' ?>>Male</option><option value="F"<?= $sex==='F'?' selected':'' ?>>Female</option></select></div>
      <div><label>Birth year / date</label><input type="text" name="c_birth" value="<?= $bd ?>" placeholder="e.g. 1978"></div>
      <div><label>Birthplace (optional)</label><input type="text" name="c_birthplace" value="<?= $bp ?>" placeholder="e.g. Dallas, TX"></div>
      <div><label>Died (optional)</label><input type="text" name="c_death" value="<?= $dd ?>" placeholder="year / date, if applicable"></div>
      <div><label>Death place (optional)</label><input type="text" name="c_deathplace" value="<?= $dp ?>" placeholder="e.g. Houston, TX"></div>
    </div>
    <label class="af-check"><input type="checkbox" name="c_living" value="1"<?= $liv ?>> Living family member (hidden from public visitors)</label>
    <?php
}

$me = logged_in() ? te_user_pid(current_user()) : '';
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
    <?php if ($parents): ?><h2 style="font-size:20px;margin-top:20px">Parents</h2><?php foreach ($parents as $rp) echo rel_chip($rp, 'parent'); endif; ?>
    <?php if ($spouses): ?><h2 style="font-size:20px;margin-top:16px">Spouse</h2><?php foreach ($spouses as $rp) echo rel_chip($rp, 'spouse'); endif; ?>
    <?php if ($children): ?><h2 style="font-size:20px;margin-top:16px">Children (<?= count($children) ?>)</h2><?php foreach ($children as $rp) echo rel_chip($rp, 'child'); endif; ?>
  <?php endif; ?>
</div>

<?php if (role_at_least('moderator')): $pfams = te_parent_families($pid); ?>
<div class="panel addfam" style="margin-top:20px">
  <h2>Add a family member</h2>
  <p class="muted" style="margin:0 0 12px">Add someone who isn&rsquo;t in the tree yet &mdash; a child of <?= e(explode(' ', $name)[0]) ?>, or their spouse. They&rsquo;ll link in automatically, and you can open the new person afterward to add photos and details.</p>
  <div class="addfam-cols">
    <form method="post" class="addfam-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_child">
      <h3>&#128118; Add a child</h3>
      <div class="af-grid">
        <div><label>First name</label><input type="text" name="c_given" placeholder="e.g. James"></div>
        <div><label>Last name</label><input type="text" name="c_surname" value="<?= e($p['surname']) ?>"></div>
        <div><label>Sex</label><select name="c_sex"><option value="">—</option><option value="M">Male</option><option value="F">Female</option></select></div>
        <div><label>Birth year / date</label><input type="text" name="c_birth" placeholder="e.g. 1978"></div>
        <div><label>Birthplace (optional)</label><input type="text" name="c_birthplace" placeholder="e.g. Dallas, TX"></div>
        <div><label>Died (optional)</label><input type="text" name="c_death" placeholder="year / date, if applicable"></div>
      </div>
      <?php if (count($pfams) > 1): ?>
        <label>Which family?</label>
        <select name="c_fid"><?php foreach ($pfams as $pf): ?><option value="<?= e($pf['fid']) ?>"><?= e($pf['label']) ?></option><?php endforeach; ?></select>
      <?php endif; ?>
      <label class="af-check"><input type="checkbox" name="c_living" value="1"> Living family member (hidden from public visitors)</label>
      <button class="btn gold" type="submit" style="margin-top:10px">Add child</button>
    </form>

    <form method="post" class="addfam-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_spouse">
      <h3>&#128141; Add a spouse</h3>
      <div class="af-grid">
        <div><label>First name</label><input type="text" name="c_given" placeholder="e.g. Mary"></div>
        <div><label>Last name</label><input type="text" name="c_surname" placeholder="maiden or married name"></div>
        <div><label>Sex</label><select name="c_sex"><option value="">—</option><option value="M">Male</option><option value="F">Female</option></select></div>
        <div><label>Birth year / date</label><input type="text" name="c_birth" placeholder="e.g. 1955"></div>
        <div><label>Birthplace (optional)</label><input type="text" name="c_birthplace"></div>
        <div><label>Died (optional)</label><input type="text" name="c_death" placeholder="year / date, if applicable"></div>
      </div>
      <label class="af-check"><input type="checkbox" name="c_living" value="1"> Living family member (hidden from public visitors)</label>
      <button class="btn gold" type="submit" style="margin-top:10px">Add spouse</button>
    </form>
  </div>
</div>

<div class="panel addfam" style="margin-top:20px">
  <h2>Connect someone already in the tree</h2>
  <p class="muted" style="margin:0 0 12px">If both people are already in the tree, connect them here &mdash; for example, mark <?= e(explode(' ', $name)[0]) ?> and their spouse, or link a child to a parent. (To add someone who is <em>not</em> in the tree yet, use the boxes above.)</p>
  <form method="post" class="af-connect">
    <?= csrf_field() ?><input type="hidden" name="action" value="link_existing">
    <div class="af-grid">
      <div>
        <label>The person I pick&hellip;</label>
        <select name="rel">
          <option value="spouse">is <?= e(explode(' ', $name)[0]) ?>&rsquo;s spouse</option>
          <option value="child">is <?= e(explode(' ', $name)[0]) ?>&rsquo;s child</option>
          <option value="parent">is <?= e(explode(' ', $name)[0]) ?>&rsquo;s parent</option>
        </select>
      </div>
      <div>
        <label>Person to connect</label>
        <input type="text" id="connfilter" placeholder="Type a name to filter&hellip;" autocomplete="off">
        <select name="other_pid" id="connsel" required>
          <?php foreach (te_people_options($pid) as $o): ?><option value="<?= e($o['pid']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <button class="btn gold" type="submit" style="margin-top:10px">Connect them</button>
  </form>
</div>
<script>
(function(){
  var f=document.getElementById('connfilter'), s=document.getElementById('connsel');
  if(!f||!s) return;
  var opts=Array.prototype.slice.call(s.options).map(function(o){return {t:o.text.toLowerCase(), o:o};});
  f.addEventListener('input',function(){
    var q=f.value.toLowerCase().trim(); s.innerHTML='';
    opts.forEach(function(x){ if(!q || x.t.indexOf(q)>-1) s.appendChild(x.o); });
  });
})();
</script>

<div class="panel" style="margin-top:20px;border-left:3px solid #7a2e1f">
  <h2 style="font-size:20px;margin-top:0">Remove this person</h2>
  <p class="muted" style="margin:0 0 12px">If <b><?= e($name) ?></b> was added by mistake or is a duplicate, you can remove them from the tree. Their connections and photos are removed too. This can&rsquo;t be undone.</p>
  <form method="post" onsubmit="return confirm('Remove <?= e(addslashes($name)) ?> from the tree permanently? This cannot be undone.')">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_person">
    <button class="btn danger" type="submit">Remove <?= e(explode(' ', $name)[0]) ?> from the tree</button>
  </form>
</div>

<?php elseif (logged_in()): /* ---- family member: claim self + suggest close-relative edits ---- */ ?>
  <?php if ($me === ''): ?>
    <div class="panel addfam" style="margin-top:20px">
      <h2>Is this you?</h2>
      <p class="muted" style="margin:0 0 12px">Connect your account to your own place in the family tree. Once connected, you can suggest additions and corrections for your close relatives &mdash; your parents, brothers and sisters, spouse, and children &mdash; and William approves them.</p>
      <p class="muted" style="margin:0 0 12px">If <b style="color:var(--gold2)"><?= e($name) ?></b> is you, connect it here. If not, open your own profile in the tree and connect it there.</p>
      <form method="post" onsubmit="return confirm('Connect your account to <?= e(addslashes($name)) ?>?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="claim_me">
        <button class="btn gold" type="submit">This is me &mdash; connect my account</button>
      </form>
    </div>
  <?php elseif ($me === $pid): ?>
    <div class="panel addfam" style="margin-top:20px">
      <h2>Your family</h2>
      <p class="muted" style="margin:0 0 12px">Suggest adding to your family or correcting your own details. Everything you suggest is sent to William to approve before it appears on the tree.</p>
      <details class="af-det">
        <summary>&#128118; Suggest adding a child</summary>
        <form method="post" class="addfam-form"><?= csrf_field() ?><input type="hidden" name="action" value="suggest_child"><?php render_vitals(['surname'=>$p['surname']]); ?><button class="btn gold" type="submit" style="margin-top:10px">Send suggestion</button></form>
      </details>
      <details class="af-det">
        <summary>&#128141; Suggest adding a spouse</summary>
        <form method="post" class="addfam-form"><?= csrf_field() ?><input type="hidden" name="action" value="suggest_spouse"><?php render_vitals(); ?><button class="btn gold" type="submit" style="margin-top:10px">Send suggestion</button></form>
      </details>
      <?php if (json_decode($p['famc'] ?: '[]', true)): ?>
      <details class="af-det">
        <summary>&#128106; Suggest adding a brother or sister</summary>
        <form method="post" class="addfam-form"><?= csrf_field() ?><input type="hidden" name="action" value="suggest_sibling"><?php render_vitals(['surname'=>$p['surname']]); ?><button class="btn gold" type="submit" style="margin-top:10px">Send suggestion</button></form>
      </details>
      <?php endif; ?>
      <details class="af-det">
        <summary>&#9998; Suggest a correction to your own details</summary>
        <form method="post" class="addfam-form"><?= csrf_field() ?><input type="hidden" name="action" value="suggest_edit"><?php render_vitals($p); ?><button class="btn gold" type="submit" style="margin-top:10px">Send suggestion</button></form>
      </details>
    </div>
  <?php elseif (te_can_edit($me, $pid)): ?>
    <div class="panel addfam" style="margin-top:20px">
      <h2>Suggest a correction</h2>
      <p class="muted" style="margin:0 0 12px"><?= e(explode(' ', $name)[0]) ?> is one of your close relatives, so you can suggest a correction to their details. It goes to William to approve.</p>
      <form method="post" class="addfam-form"><?= csrf_field() ?><input type="hidden" name="action" value="suggest_edit"><?php render_vitals($p); ?><button class="btn gold" type="submit" style="margin-top:10px">Send suggestion</button></form>
    </div>
  <?php else: ?>
    <div class="panel" style="margin-top:20px">
      <p class="muted" style="margin:0">You can suggest additions and corrections for your close relatives (your parents, brothers and sisters, spouse, and children). <a href="person.php?pid=<?= e($me) ?>" style="color:var(--gold2)">Open your own profile</a> to add to your family.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>

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
