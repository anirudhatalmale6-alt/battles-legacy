<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/memorial_data.php';
mem_migrate();

$pid = $_GET['pid'] ?? '';
$p = one("SELECT * FROM persons WHERE pid=?", [$pid]);
if (!$p) { http_response_code(404); page_head('Not found'); echo '<div class="panel">That person isn\'t in the family records.</div>'; page_foot(); exit; }

// Memorial pages are for those who have passed. Living relatives stay private.
if ($p['living']) {
    if (!logged_in()) { flash('Sign in as family to view living relatives.'); header('Location: login.php'); exit; }
    header('Location: person.php?pid=' . urlencode($pid)); exit;
}

/* ---- POST actions (candle / condolence / admin edit / delete) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'candle') {
        if (empty($_SESSION['lit'][$pid])) { mem_light_candle($pid); $_SESSION['lit'][$pid] = true; flash('Your candle is lit. Thank you for remembering.'); }
    } elseif ($act === 'condolence') {
        require_login();
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            $u = current_user();
            mem_add_condolence($pid, $u['name'], mb_substr($body, 0, 1500), $u['id']);
            flash('Your message has been shared. Thank you.');
        }
    } elseif ($act === 'save_meta') {
        require_role('admin');
        mem_save_meta($pid, [
          'tribute'=>trim($_POST['tribute']??''), 'known_for'=>trim($_POST['known_for']??''),
          'faith'=>trim($_POST['faith']??''), 'legacy'=>trim($_POST['legacy']??''),
          'scripture'=>trim($_POST['scripture']??''), 'scripture_ref'=>trim($_POST['scripture_ref']??''),
        ]);
        flash('Tribute details saved.');
    } elseif ($act === 'del_condolence') {
        require_role('admin');
        mem_delete_condolence((int)($_POST['cid'] ?? 0));
        flash('Message removed.');
    }
    header('Location: tribute.php?pid=' . urlencode($pid) . (empty($_POST['tab']) ? '' : '#' . $_POST['tab'])); exit;
}

/* ---- gather data ---- */
$name   = $p['name'];
$photos = person_photos($pid);
$occ    = json_decode($p['occupation'] ?: '[]', true) ?: [];
$notes  = json_decode($p['notes'] ?: '[]', true) ?: [];
$edu    = json_decode($p['education'] ?: '[]', true) ?: [];
$meta   = mem_meta($pid);
$conds  = mem_condolences($pid);

$parents = []; $spouses = []; $children = []; $marr = '';
foreach (json_decode($p['famc'] ?: '[]', true) as $fid) {
    $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
    if ($f) foreach (['husb','wife'] as $k) if ($f[$k]) { $rp = one("SELECT * FROM persons WHERE pid=?", [$f[$k]]); if ($rp) $parents[] = $rp; }
}
foreach (json_decode($p['fams'] ?: '[]', true) as $fid) {
    $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
    if (!$f) continue;
    if (!$marr && $f['marr_date']) $marr = $f['marr_date'];
    $sp = $f['husb'] === $pid ? $f['wife'] : $f['husb'];
    if ($sp) { $rp = one("SELECT * FROM persons WHERE pid=?", [$sp]); if ($rp) $spouses[] = $rp; }
    foreach (json_decode($f['chil'] ?: '[]', true) as $cid) { $rp = one("SELECT * FROM persons WHERE pid=?", [$cid]); if ($rp) $children[] = $rp; }
}

function trib_chip($rp) {
    $y = yr($rp['birth_date']); $y = $y ? " ($y)" : '';
    $href = ($rp['living'] && !logged_in()) ? 'javascript:void(0)' : ('tribute.php?pid=' . e($rp['pid']));
    return '<a class="chip" href="' . $href . '">' . e(person_display_name($rp)) . e($y) . '</a>';
}

$born  = trim(($p['birth_date'] ?: '') . ($p['birth_place'] ? ($p['birth_date'] ? ' · ' : '') . $p['birth_place'] : ''));
$rest  = $p['buri_place'] ?: $p['death_place'] ?: '';
$spouseName = $spouses ? person_display_name($spouses[0]) : '';
$isAdmin = role_at_least('admin');
$ini = strtoupper(substr($p['given'],0,1) . substr($p['surname'],0,1));

page_head('In Memory of ' . $name, ['body_class' => 'home mem tribute']);
?>
<div class="trib-crumb"><a href="memorial.php">&larr; All Memorials</a></div>

<!-- HERO -->
<section class="trib-hero">
  <div class="trib-portrait">
    <?php if ($photos): ?><img src="<?= e($photos[0]['path']) ?>" alt="<?= e($name) ?>">
    <?php else: ?><span class="trib-mono"><?= e($ini) ?></span><?php endif; ?>
  </div>
  <div class="trib-headwrap">
    <h1><?= e($name) ?></h1>
    <div class="trib-years"><?= e(lifespan($p) ?: 'Dates unknown') ?></div>
    <div class="trib-orn">&#10086; &nbsp;&bull;&nbsp; &#10086;</div>
    <?php if ($meta['tribute']): ?>
      <p class="trib-quote">&ldquo;<?= e($meta['tribute']) ?>&rdquo;</p>
    <?php elseif ($isAdmin): ?>
      <p class="trib-quote muted-add">Add a tribute line in “Edit tribute details” below.</p>
    <?php endif; ?>
  </div>
</section>

<!-- FACT ROW -->
<section class="trib-facts">
  <?php if ($born): ?><div class="trib-fact"><span class="tf-ic">&#128197;</span><b>Born</b><span><?= e($born) ?></span></div><?php endif; ?>
  <?php if ($spouseName || $marr): ?><div class="trib-fact"><span class="tf-ic">&#128141;</span><b>Married</b><span><?= e(trim($spouseName . ($marr ? ' · ' . $marr : ''), ' ·')) ?></span></div><?php endif; ?>
  <?php if ($occ): ?><div class="trib-fact"><span class="tf-ic">&#9874;</span><b>Occupation</b><span><?= e(implode(', ', $occ)) ?></span></div><?php endif; ?>
  <?php if ($rest): ?><div class="trib-fact"><span class="tf-ic">&#9962;</span><b>Resting Place</b><span><?= e($rest) ?></span></div><?php endif; ?>
</section>

<div class="trib-main">
  <div class="trib-left">
    <!-- TABS -->
    <div class="trib-tabs" id="tribtabs">
      <button class="tt on" data-tab="story">Life Story</button>
      <button class="tt" data-tab="photos">Photos<?= $photos ? ' (' . count($photos) . ')' : '' ?></button>
      <button class="tt" data-tab="family">Family</button>
    </div>

    <div class="tt-panel on" id="tab-story">
      <h3>About <?= e(explode(' ', $name)[0]) ?></h3>
      <?php if ($notes): ?>
        <?php foreach ($notes as $n): ?><p class="trib-bio"><?= nl2br(e($n)) ?></p><?php endforeach; ?>
      <?php else: ?>
        <p class="trib-bio muted"><?= e(explode(' ', $name)[0]) ?> is remembered and honored here.
          <?php if ($isAdmin): ?>You can add their life story to the family records from the tree.<?php endif; ?></p>
      <?php endif; ?>
      <?php if ($edu): ?><p class="trib-bio"><b>Education:</b> <?= e(implode('; ', $edu)) ?></p><?php endif; ?>
    </div>

    <div class="tt-panel" id="tab-photos">
      <?php if ($photos): ?>
        <div class="trib-gallery">
          <?php foreach ($photos as $ph): ?>
            <a href="#" onclick="lb('<?= e($ph['path']) ?>');return false"><img src="<?= e($ph['path']) ?>" alt="<?= e($ph['caption']) ?>" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
      <?php else: ?><p class="muted">No photographs yet.<?php if (logged_in()): ?> <a href="upload.php?pid=<?= e($pid) ?>" style="color:var(--gold2)">Add one</a>.<?php endif; ?></p><?php endif; ?>
    </div>

    <div class="tt-panel" id="tab-family">
      <?php if ($parents): ?><div class="trib-relrow"><h4>Parents</h4><div><?php foreach ($parents as $rp) echo trib_chip($rp); ?></div></div><?php endif; ?>
      <?php if ($spouses): ?><div class="trib-relrow"><h4>Spouse</h4><div><?php foreach ($spouses as $rp) echo trib_chip($rp); ?></div></div><?php endif; ?>
      <?php if ($children): ?><div class="trib-relrow"><h4>Children (<?= count($children) ?>)</h4><div><?php foreach ($children as $rp) echo trib_chip($rp); ?></div></div><?php endif; ?>
      <?php if (!$parents && !$spouses && !$children): ?><p class="muted">No family connections recorded yet.</p><?php endif; ?>
      <p style="margin-top:14px"><a class="chip" href="person.php?pid=<?= e($pid) ?>">View full profile &amp; tree &rsaquo;</a></p>
    </div>
  </div>

  <!-- CONDOLENCES -->
  <div class="trib-right">
    <div class="trib-card">
      <h3 class="tc-title">&#128172; Memories &amp; Condolences</h3>
      <p class="tc-sub">Share a memory, prayer, or message for <?= e(explode(' ', $name)[0]) ?>.</p>
      <?php if (logged_in()): ?>
        <form method="post" class="cond-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="condolence"><input type="hidden" name="tab" value="condolences">
          <textarea name="body" required placeholder="Share your memory or message…" maxlength="1500"></textarea>
          <button class="btn gold">Post Message</button>
        </form>
      <?php else: ?>
        <p class="cond-login"><a href="login.php">Sign in as family</a> to leave a message.</p>
      <?php endif; ?>

      <div class="cond-feed">
        <?php if (!$conds): ?>
          <p class="muted" style="text-align:center;padding:16px 0">Be the first to share a memory.</p>
        <?php else: foreach ($conds as $c): ?>
          <div class="cond">
            <div class="cond-av"><?= e(strtoupper(substr($c['author'] ?: '?', 0, 1))) ?></div>
            <div class="cond-body">
              <div class="cond-head"><b><?= e($c['author'] ?: 'Family') ?></b><span><?= e(mem_ago($c['created_at'])) ?></span>
                <?php if ($isAdmin): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Remove this message?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="del_condolence"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="tab" value="condolences">
                    <button class="cond-del" title="Remove">&times;</button>
                  </form>
                <?php endif; ?>
              </div>
              <p><?= nl2br(e($c['body'])) ?></p>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- QUICK FACTS + CANDLE -->
<section class="trib-bottom">
  <div class="trib-card qf">
    <h3 class="tc-title">Quick Facts</h3>
    <?php
      $qf = [];
      if ($meta['faith'])     $qf[] = ['Faith', $meta['faith']];
      if ($meta['legacy'])    $qf[] = ['Legacy', $meta['legacy']];
      if ($meta['known_for']) $qf[] = ['Known For', $meta['known_for']];
      if ($meta['scripture']) $qf[] = ['Favorite Scripture', '“' . $meta['scripture'] . '”' . ($meta['scripture_ref'] ? ' — ' . $meta['scripture_ref'] : '')];
    ?>
    <?php if ($qf): ?>
      <?php foreach ($qf as $row): ?><div class="qf-row"><div class="qf-k"><?= e($row[0]) ?></div><div class="qf-v"><?= e($row[1]) ?></div></div><?php endforeach; ?>
    <?php else: ?>
      <p class="muted"><?php if ($isAdmin): ?>Add faith, legacy, what they were known for, and a favorite scripture below.<?php else: ?>Details will be added here.<?php endif; ?></p>
    <?php endif; ?>
  </div>

  <div class="trib-card candle">
    <div class="candle-flame<?= !empty($_SESSION['lit'][$pid]) ? ' lit' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M12 2c1.6 3.2.6 4.9-.8 6.6-1.3 1.6-2.7 3.1-2.7 5.4a3.5 3.5 0 0 0 7 0c0-1.4-.6-2.5-1.2-3.4 1.9 1 3.2 2.9 3.2 5.1a6.5 6.5 0 1 1-13 0C6.7 8.9 12 8 12 2z"/></svg>
    </div>
    <div class="candle-count"><?= (int)$meta['candles'] ?> candle<?= (int)$meta['candles'] === 1 ? '' : 's' ?> lit</div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="candle">
      <button class="btn gold" <?= !empty($_SESSION['lit'][$pid]) ? 'disabled' : '' ?>><?= !empty($_SESSION['lit'][$pid]) ? 'You lit a candle' : 'Light a Candle' ?></button>
    </form>
    <div class="trib-share">Share this memorial:
      <!-- base_url is the site's own address; the /legacy/ folder is no longer part of it -->
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(base_url() . '/tribute.php?pid=' . $pid) ?>" target="_blank" rel="noopener" title="Facebook">f</a>
      <button type="button" class="share-copy" onclick="tribCopy(this)" title="Copy link">&#128279;</button>
    </div>
  </div>
</section>

<?php if ($isAdmin): ?>
<section class="trib-adminedit">
  <details>
    <summary>&#9998; Edit tribute details (only you can see this)</summary>
    <form method="post" class="tae-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_meta">
      <label>Tribute line (the quote under the name)</label>
      <input type="text" name="tribute" value="<?= e($meta['tribute']) ?>" maxlength="600" placeholder="A faithful man who loved his family…">
      <div class="tae-grid">
        <div><label>Faith</label><input type="text" name="faith" value="<?= e($meta['faith']) ?>" placeholder="Christian"></div>
        <div><label>Known For</label><input type="text" name="known_for" value="<?= e($meta['known_for']) ?>" placeholder="Kindness, leadership"></div>
        <div><label>Legacy</label><input type="text" name="legacy" value="<?= e($meta['legacy']) ?>" placeholder="Strong faith, family, hard work"></div>
      </div>
      <label>Favorite Scripture</label>
      <input type="text" name="scripture" value="<?= e($meta['scripture']) ?>" placeholder="Trust in the Lord with all your heart">
      <label>Scripture reference</label>
      <input type="text" name="scripture_ref" value="<?= e($meta['scripture_ref']) ?>" placeholder="Proverbs 3:5">
      <button class="btn gold" style="margin-top:12px">Save tribute details</button>
    </form>
  </details>
</section>
<?php endif; ?>

<div id="lightbox" onclick="closeLb()"><span class="x">&times;</span><img id="lightbox-img" onclick="event.stopPropagation()" src="" alt=""></div>
<script>
(function(){
  var tabs=document.querySelectorAll('#tribtabs .tt');
  tabs.forEach(function(b){ b.addEventListener('click',function(){
    tabs.forEach(function(x){x.classList.remove('on');}); b.classList.add('on');
    document.querySelectorAll('.tt-panel').forEach(function(p){p.classList.remove('on');});
    document.getElementById('tab-'+b.getAttribute('data-tab')).classList.add('on');
  });});
})();
function lb(src){document.getElementById('lightbox-img').src=src;document.getElementById('lightbox').classList.add('show');}
function closeLb(){document.getElementById('lightbox').classList.remove('show');document.getElementById('lightbox-img').src='';}
window.addEventListener('keydown',function(e){if(e.key==='Escape')closeLb();});
function tribCopy(btn){var u=window.location.href;navigator.clipboard&&navigator.clipboard.writeText(u);btn.textContent='Copied';setTimeout(function(){btn.innerHTML='&#128279;';},1500);}
</script>
<?php legacy_footer(); page_foot();
