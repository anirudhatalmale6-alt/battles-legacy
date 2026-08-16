<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/memorial_data.php';
require_once __DIR__ . '/../src/music.php';
mem_migrate();
music_handle_post('memorial', 'memorial.php');

$isAdmin = role_at_least('moderator');

/* admin: hide a name from the memorial, or restore it */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'hide_mem' && !empty($_POST['pid'])) { mem_set_hidden($_POST['pid'], 1); flash('Removed from the Memorial.'); header('Location: memorial.php'); exit; }
    if ($act === 'show_mem' && !empty($_POST['pid'])) { mem_set_hidden($_POST['pid'], 0); flash('Restored to the Memorial.'); header('Location: memorial.php?hidden=1'); exit; }
    header('Location: memorial.php'); exit;
}

/* Memorial — honors everyone in the tree who has passed (living=0, so it is
   public-safe; living relatives are never listed here). Admins can hide names. */
$showHidden = $isAdmin && (($_GET['hidden'] ?? '') === '1');
$people = all("SELECT p.pid,p.name,p.given,p.surname,p.birth_date,p.death_date,p.death_place,p.buri_place
               FROM persons p LEFT JOIN memorial_meta m ON m.pid=p.pid
               WHERE p.living=0 AND COALESCE(m.hidden,0)=" . ($showHidden ? 1 : 0) . "
               ORDER BY p.surname, p.given, p.name");

/* main approved photo per person (respects the chosen 'main' photo, and counts
   group photographs they appear in), no N+1 */
require_once __DIR__ . '/../src/photo_people.php';
$phmap = [];
$phrows = [];
if (pp_migrate()) {
    try {
        $phrows = all("SELECT t.pid, ph.path FROM photo_people t JOIN photos ph ON ph.id=t.photo_id
                       WHERE ph.status='approved' ORDER BY t.is_primary DESC, ph.id");
    } catch (\Throwable $e) { $phrows = []; }
}
if (!$phrows) $phrows = all("SELECT pid, path FROM photos WHERE status='approved' ORDER BY is_primary DESC, id");
foreach ($phrows as $ph) {
    if (!isset($phmap[$ph['pid']])) $phmap[$ph['pid']] = $ph['path'];
}
$count = count($people);
$hiddenCount = $isAdmin ? (int)((one("SELECT COUNT(*) c FROM memorial_meta WHERE hidden=1") ?: ['c'=>0])['c']) : 0;

page_head('Memorial', ['body_class' => 'home mem']);
?>
<section class="mem-hero2">
  <img class="mem-hero-img" src="assets/memorial/hero.jpg" alt="In Loving Memory — Forever in Our Hearts. We honor our family members who have gone before us.">
  <div class="mem-hero-inner">
    <div class="mem-flame"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2c1.6 3.2.6 4.9-.8 6.6-1.3 1.6-2.7 3.1-2.7 5.4a3.5 3.5 0 0 0 7 0c0-1.4-.6-2.5-1.2-3.4 1.9 1 3.2 2.9 3.2 5.1a6.5 6.5 0 1 1-13 0C6.7 8.9 12 8 12 2z"/></svg></div>
    <h1>In Loving Memory</h1>
    <div class="mem-script">Forever in Our Hearts</div>
    <p>We honor the lives, love, and legacy of our family members who have gone before us. Their light continues to shine in us.</p>
  </div>
</section>

<?php music_player('memorial', ['class' => 'mus-band mus-mem', 'lead' => 'A Song of Remembrance']); ?>
<?php music_admin_box('memorial', 'Memorial music'); ?>

<section class="mem-bar">
  <div class="mb-left">
    <span class="mb-candle"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2c1.6 3.2.6 4.9-.8 6.6-1.3 1.6-2.7 3.1-2.7 5.4a3.5 3.5 0 0 0 7 0c0-1.4-.6-2.5-1.2-3.4 1.9 1 3.2 2.9 3.2 5.1a6.5 6.5 0 1 1-13 0C6.7 8.9 12 8 12 2z"/></svg></span>
    <div class="mb-legacy"><b>Celebrating Their Legacy</b><span>Explore the lives and memories of our cherished family members.</span></div>
  </div>
  <div class="mb-search">
    <input type="text" id="memq" placeholder="Search by name&hellip;" oninput="memFilter()" autocomplete="off">
    <button type="button" class="mb-all" onclick="memClear()">All Memorials</button>
  </div>
  <div class="mb-verse"><span class="mv-dove">&#128330;</span><span>&ldquo;Precious in the sight of the Lord is the death of his saints.&rdquo; <em>&mdash; Psalm 116:15</em></span></div>
</section>

<div class="mem-wrap">
  <?php if ($showHidden): ?>
    <div class="mem-adminnote">Hidden from the Memorial &mdash; these names are not shown publicly. <a href="memorial.php">&larr; Back to the Memorial</a></div>
  <?php elseif ($isAdmin && $hiddenCount): ?>
    <div class="mem-adminnote"><a href="memorial.php?hidden=1">Manage hidden names (<?= $hiddenCount ?>)</a></div>
  <?php endif; ?>
  <?php if ($count): ?><div class="mem-count-line"><b><?= (int)$count ?></b> <?= $showHidden ? 'hidden' : 'loved ones remembered' ?> <span class="mem-hits" id="memhits"></span><?php if ($isAdmin && !$showHidden): ?> <span class="mem-tip">&middot; hover a card and click Hide to remove a name</span><?php endif; ?></div><?php endif; ?>

  <?php if ($count): ?>
  <div class="mem-grid" id="memgrid">
    <?php foreach ($people as $p):
      $yrs  = lifespan($p) ?: 'Dates unknown';
      $rest = $p['buri_place'] ?: $p['death_place'] ?: '';
      $img  = $phmap[$p['pid']] ?? '';
      $ini  = strtoupper(substr($p['given'], 0, 1) . substr($p['surname'], 0, 1));
      if ($ini === '') $ini = strtoupper(substr($p['name'], 0, 1));
    ?>
      <div class="mem-cell" data-name="<?= e(strtolower($p['name'])) ?>">
        <a class="mem-card" href="tribute.php?pid=<?= e($p['pid']) ?>">
          <div class="mem-photo">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy">
            <?php else: ?><span class="mem-mono"><?= e($ini) ?></span><?php endif; ?>
          </div>
          <div class="mem-name"><?= e($p['name']) ?></div>
          <div class="mem-years"><?= e($yrs) ?></div>
          <?php if ($rest): ?><div class="mem-rest">&#10013; <?= e($rest) ?></div><?php endif; ?>
        </a>
        <?php if ($isAdmin): ?>
          <form method="post" class="mem-hidebtn" onsubmit="return confirm('<?= $showHidden ? 'Restore this name to the Memorial?' : 'Remove this name from the Memorial? (You can restore it later.)' ?>')">
            <?= csrf_field() ?><input type="hidden" name="action" value="<?= $showHidden ? 'show_mem' : 'hide_mem' ?>"><input type="hidden" name="pid" value="<?= e($p['pid']) ?>">
            <button type="submit"><?= $showHidden ? 'Restore' : '&times; Hide' ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="mem-none" id="memnone" style="display:none">No names match that search.</p>
  <?php else: ?>
    <p class="mem-empty">Names will be gathered here soon.</p>
  <?php endif; ?>
</div>

<script>
function memFilter(){
  var box = document.getElementById('memq'); if(!box) return;
  var q = box.value.toLowerCase().trim();
  var cards = document.querySelectorAll('#memgrid .mem-cell'), shown = 0;
  cards.forEach(function(c){
    var hit = !q || c.getAttribute('data-name').indexOf(q) > -1;
    c.style.display = hit ? '' : 'none'; if (hit) shown++;
  });
  var hits = document.getElementById('memhits'); if(hits) hits.textContent = q ? ('· ' + shown + ' found') : '';
  var none = document.getElementById('memnone'); if(none) none.style.display = (q && shown === 0) ? 'block' : 'none';
}
function memClear(){ var b=document.getElementById('memq'); if(b){ b.value=''; memFilter(); b.focus(); } }
</script>

<?php music_script(); ?>

<?php legacy_footer(); page_foot();
