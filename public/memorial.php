<?php
require __DIR__ . '/../src/bootstrap.php';

/* Memorial — honors everyone in the tree who has passed (living=0, so it is
   public-safe; living relatives are never listed here). Drawn straight from
   the family records, with a photo where we have one. */
$people = all("SELECT pid,name,given,surname,birth_date,death_date,death_place,buri_place
               FROM persons WHERE living=0 ORDER BY surname, given, name");

/* primary approved photo per person, in one query (no N+1) */
$phmap = [];
foreach (all("SELECT pid, path FROM photos WHERE status='approved' ORDER BY id") as $ph) {
    if (!isset($phmap[$ph['pid']])) $phmap[$ph['pid']] = $ph['path'];
}
$count = count($people);

page_head('Memorial', ['body_class' => 'home mem']);
?>
<section class="mem-hero">
  <div class="mem-flame">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2c1.6 3.2.6 4.9-.8 6.6-1.3 1.6-2.7 3.1-2.7 5.4a3.5 3.5 0 0 0 7 0c0-1.4-.6-2.5-1.2-3.4 1.9 1 3.2 2.9 3.2 5.1a6.5 6.5 0 1 1-13 0C6.7 8.9 12 8 12 2z"/></svg>
  </div>
  <h1>In Loving Memory</h1>
  <div class="mem-script">Forever in Our Hearts</div>
  <p>We honor the lives, love, and legacy of our family members who have gone before us.
     Their light continues to shine in us.</p>
  <?php if ($count): ?><div class="mem-count"><?= (int)$count ?> remembered</div><?php endif; ?>
</section>
<div class="mem-verse">
  <span class="mv-dove">&#128330;</span>
  <span class="mv-text">&ldquo;Precious in the sight of the Lord is the death of his saints.&rdquo; <em>&mdash; Psalm 116:15</em></span>
</div>

<div class="mem-wrap">
  <?php if ($count > 12): ?>
    <div class="mem-search">
      <input type="text" id="memq" placeholder="Search a name…" oninput="memFilter()" autocomplete="off">
      <span class="mem-hits" id="memhits"></span>
    </div>
  <?php endif; ?>

  <?php if ($count): ?>
  <div class="mem-grid" id="memgrid">
    <?php foreach ($people as $p):
      $yrs  = lifespan($p) ?: 'Dates unknown';
      $rest = $p['buri_place'] ?: $p['death_place'] ?: '';
      $img  = $phmap[$p['pid']] ?? '';
      $ini  = strtoupper(substr($p['given'], 0, 1) . substr($p['surname'], 0, 1));
      if ($ini === '') $ini = strtoupper(substr($p['name'], 0, 1));
    ?>
      <a class="mem-card" href="tribute.php?pid=<?= e($p['pid']) ?>" data-name="<?= e(strtolower($p['name'])) ?>">
        <div class="mem-photo">
          <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy">
          <?php else: ?><span class="mem-mono"><?= e($ini) ?></span><?php endif; ?>
        </div>
        <div class="mem-name"><?= e($p['name']) ?></div>
        <div class="mem-years"><?= e($yrs) ?></div>
        <?php if ($rest): ?><div class="mem-rest">&#10013; <?= e($rest) ?></div><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="mem-none" id="memnone" style="display:none">No names match that search.</p>
  <?php else: ?>
    <p class="mem-empty">Names will be gathered here soon.</p>
  <?php endif; ?>
</div>

<script>
function memFilter(){
  var q = document.getElementById('memq').value.toLowerCase().trim();
  var cards = document.querySelectorAll('#memgrid .mem-card'), shown = 0;
  cards.forEach(function(c){
    var hit = !q || c.getAttribute('data-name').indexOf(q) > -1;
    c.style.display = hit ? '' : 'none'; if (hit) shown++;
  });
  document.getElementById('memhits').textContent = q ? (shown + ' found') : '';
  document.getElementById('memnone').style.display = (q && shown === 0) ? 'block' : 'none';
}
</script>

<?php legacy_footer(); page_foot();
