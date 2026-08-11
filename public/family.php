<?php
/** Close Family — one person's immediate family on a single screen:
 *  grandparents and parents above, siblings and spouse beside, children and
 *  grandchildren below. A readable alternative to the full 772-person tree. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/tree_edit.php';
te_migrate();

$pid = $_GET['pid'] ?? config('root_person');
$p   = one("SELECT * FROM persons WHERE pid=?", [$pid]);
if (!$p) { $p = one("SELECT * FROM persons WHERE pid=?", [config('root_person')]); }
if (!$p) { http_response_code(404); page_head('Not found'); echo '<div class="panel">That person isn\'t in the tree.</div>'; page_foot(); exit; }

// living relatives stay private to signed-in family
if ($p['living'] && !logged_in()) { flash('Sign in as family to view living relatives.'); header('Location: login.php'); exit; }

$member = logged_in();
function fam_json($row, $field) { return json_decode(($row[$field] ?? '') ?: '[]', true) ?: []; }
function fam_get($pid) { return $pid ? one("SELECT * FROM persons WHERE pid=?", [$pid]) : null; }

/** parents of a person (as rows) */
function fam_parents($row) {
    $out = [];
    foreach (fam_json($row, 'famc') as $fid) {
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f) continue;
        foreach (['husb','wife'] as $k) if (!empty($f[$k])) { $r = fam_get($f[$k]); if ($r) $out[$r['pid']] = $r; }
    }
    return array_values($out);
}
/** spouses + children, and the sibling list */
function fam_spouses($row) {
    $out = [];
    foreach (fam_json($row, 'fams') as $fid) {
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f) continue;
        $sp = ($f['husb'] === $row['pid']) ? $f['wife'] : $f['husb'];
        if ($sp) {
            $r = fam_get($sp);
            if ($r) { $r['_rel'] = $f['rel_status'] ?? ''; $r['_relend'] = $f['rel_end'] ?? ''; $out[$r['pid']] = $r; }
        }
    }
    return array_values($out);
}
function fam_children($row) {
    $out = [];
    foreach (fam_json($row, 'fams') as $fid) {
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f) continue;
        foreach (fam_json($f, 'chil') as $cid) { $r = fam_get($cid); if ($r) $out[$r['pid']] = $r; }
    }
    return array_values($out);
}
function fam_siblings($row) {
    $out = [];
    foreach (fam_json($row, 'famc') as $fid) {
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f) continue;
        foreach (fam_json($f, 'chil') as $cid) {
            if ($cid === $row['pid']) continue;
            $r = fam_get($cid); if ($r) $out[$r['pid']] = $r;
        }
    }
    return array_values($out);
}
/** oldest first; unknown years last */
function fam_by_age($a, $b) {
    $ya = (int)(yr($a['birth_date']) ?: 99999); $yb = (int)(yr($b['birth_date']) ?: 99999);
    if ($ya !== $yb) return $ya - $yb;
    return strcmp($a['name'], $b['name']);
}

$parents     = fam_parents($p);
$grandparents = [];
foreach ($parents as $par) foreach (fam_parents($par) as $gp) $grandparents[$gp['pid']] = $gp;
$grandparents = array_values($grandparents);
$siblings = fam_siblings($p);
$spouses  = fam_spouses($p);
$children = fam_children($p);
$grandchildren = [];
foreach ($children as $c) foreach (fam_children($c) as $gc) $grandchildren[$gc['pid']] = $gc;
$grandchildren = array_values($grandchildren);

usort($siblings, 'fam_by_age'); usort($children, 'fam_by_age');
usort($grandparents, 'fam_by_age'); usort($grandchildren, 'fam_by_age');

/** one person tile */
function fam_tile($r, $cls = '') {
    $name = person_display_name($r);
    $phr  = primary_photo($r['pid']); $ph = $phr['path'] ?? '';
    $ls   = lifespan($r) ?: '';
    $ini  = strtoupper(substr($r['given'],0,1) . substr($r['surname'],0,1));
    if (trim($ini) === '') $ini = strtoupper(substr($r['name'],0,1));
    if (!empty($r['_rel']) && te_rel_ended($r['_rel'])) $cls .= ' ex';
    $out  = '<a class="ft ' . $cls . '" href="family.php?pid=' . e($r['pid']) . '" title="See ' . e($name) . '\'s close family">';
    $out .= $ph ? '<span class="ft-face" style="background-image:url(\'' . e($ph) . '\')"></span>'
                : '<span class="ft-face ft-mono">' . e($ini) . '</span>';
    $out .= '<span class="ft-name">' . e($name) . '</span>';
    if ($ls) $out .= '<span class="ft-yrs">' . e($ls) . '</span>';
    if (!empty($r['_rel']) && te_rel_ended($r['_rel'])) {
        $out .= '<span class="ft-ex">' . e(te_rel_long($r['_rel']))
              . (!empty($r['_relend']) ? ' &middot; ' . e($r['_relend']) : '') . '</span>';
    }
    $out .= '</a>';
    return $out;
}
function fam_row($people, $cls = '') {
    if (!$people) return '';
    $o = '<div class="ft-row">';
    foreach ($people as $r) $o .= fam_tile($r, $cls);
    return $o . '</div>';
}

$title = person_display_name($p);
page_head('Close Family — ' . $title, ['body_class' => 'home closefam']);
?>
<section class="cf-bar">
  <div class="cf-barin">
    <div>
      <h1>Close Family</h1>
      <p>Grandparents and parents above, brothers and sisters beside, children and grandchildren below. Click anyone to centre on them.</p>
    </div>
    <div class="cf-actions">
      <input type="text" id="cfq" placeholder="Search a name&hellip;" autocomplete="off">
      <div id="cfres" class="cf-res"></div>
      <a class="btn2 solid" href="tree.php">Full family tree</a>
    </div>
  </div>
</section>

<div class="cf-wrap">
  <?php if ($grandparents): ?>
    <div class="cf-gen"><span class="cf-lbl">Grandparents</span><?= fam_row($grandparents, 'sm') ?></div>
    <div class="cf-link"></div>
  <?php endif; ?>

  <?php if ($parents): ?>
    <div class="cf-gen"><span class="cf-lbl">Parents</span><?= fam_row($parents) ?></div>
    <div class="cf-link"></div>
  <?php endif; ?>

  <div class="cf-gen cf-focus">
    <span class="cf-lbl">
      <?= $siblings ? 'This person, with brothers and sisters' : 'This person' ?>
      <?= $spouses ? ' &middot; and spouse' : '' ?>
    </span>
    <div class="ft-row">
      <?php
        $left = array_filter($siblings, function ($s) use ($p) { return fam_by_age($s, $p) < 0; });
        $right = array_filter($siblings, function ($s) use ($p) { return fam_by_age($s, $p) >= 0; });
        foreach ($left as $s) echo fam_tile($s, 'sib');
      ?>
      <span class="ft me">
        <?php $phr = primary_photo($p['pid']); $ph = $phr['path'] ?? ''; $ini = strtoupper(substr($p['given'],0,1).substr($p['surname'],0,1)); ?>
        <?php if ($ph): ?><span class="ft-face" style="background-image:url('<?= e($ph) ?>')"></span>
        <?php else: ?><span class="ft-face ft-mono"><?= e($ini ?: strtoupper(substr($p['name'],0,1))) ?></span><?php endif; ?>
        <span class="ft-name"><?= e($title) ?></span>
        <?php if (lifespan($p)): ?><span class="ft-yrs"><?= e(lifespan($p)) ?></span><?php endif; ?>
        <a class="ft-profile" href="person.php?pid=<?= e($p['pid']) ?>">Full profile &amp; photos &rsaquo;</a>
      </span>
      <?php foreach ($right as $s) echo fam_tile($s, 'sib'); ?>
      <?php foreach ($spouses as $s) echo fam_tile($s, 'spouse'); ?>
    </div>
  </div>

  <?php if ($children): ?>
    <div class="cf-link"></div>
    <div class="cf-gen"><span class="cf-lbl">Children (<?= count($children) ?>)</span><?= fam_row($children) ?></div>
  <?php endif; ?>

  <?php if ($grandchildren): ?>
    <div class="cf-link"></div>
    <div class="cf-gen"><span class="cf-lbl">Grandchildren (<?= count($grandchildren) ?>)</span><?= fam_row($grandchildren, 'sm') ?></div>
  <?php endif; ?>

  <?php if (!$parents && !$children && !$siblings && !$spouses): ?>
    <p class="cf-none">We don&rsquo;t have any close family recorded for <?= e($title) ?> yet.
       <a href="person.php?pid=<?= e($p['pid']) ?>">Open their profile</a> to add relatives.</p>
  <?php endif; ?>
</div>

<script>
(function(){
  var box=document.getElementById('cfq'), res=document.getElementById('cfres');
  if(!box) return;
  var people=[];
  fetch('data.php').then(function(r){return r.text();}).then(function(t){
    try{ (0,eval)(t); }catch(e){ return; }
    if(!window.GED||!window.GED.indi) return;
    for (var id in window.GED.indi){
      var p=window.GED.indi[id];
      people.push({id:id, name:p.name||'', y:(p.birth&&p.birth.date||'').match(/\d{4}/)?(p.birth.date.match(/\d{4}/)[0]):''});
    }
  });
  function hide(){ res.style.display='none'; res.innerHTML=''; }
  box.addEventListener('input', function(){
    var q=box.value.toLowerCase().trim();
    if(q.length<2 || !people.length){ hide(); return; }
    var hits=people.filter(function(p){return p.name.toLowerCase().indexOf(q)>-1;})
                   .sort(function(a,b){var ya=+(a.y||99999),yb=+(b.y||99999);return ya!==yb?ya-yb:a.name.localeCompare(b.name);})
                   .slice(0,10);
    if(!hits.length){ hide(); return; }
    res.innerHTML = hits.map(function(p){
      return '<a href="family.php?pid='+encodeURIComponent(p.id)+'">'+p.name.replace(/</g,'&lt;')+(p.y?' <em>'+p.y+'</em>':'')+'</a>';
    }).join('');
    res.style.display='block';
  });
  document.addEventListener('click', function(e){ if(!res.contains(e.target) && e.target!==box) hide(); });
})();
</script>

<?php legacy_footer(); page_foot();
