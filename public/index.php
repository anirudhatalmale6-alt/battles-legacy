<?php
require __DIR__ . '/../src/bootstrap.php';
$u = current_user();
$np = one("SELECT COUNT(*) c FROM persons")['c'] ?? 0;
$nph = one("SELECT COUNT(*) c FROM photos WHERE status='approved'")['c'] ?? 0;

page_head('Home');
?>
<?php if ($u): ?>
  <h1>Welcome home, <?= e(explode(' ', $u['name'])[0]) ?>.</h1>
  <p class="lede">This is the private hub for the Battles family — our tree, our photographs, and the stories
     that hold it all together. Everything here is visible only to family.</p>

  <div class="grid cols3" style="margin-top:28px">
    <a class="tile" href="tree.php"><h3>Explore the Family Tree</h3>
       <p><?= (int)$np ?> relatives across the generations. As a signed-in member you can see living family too —
          zoom, pan, and open anyone to read their story.</p></a>
    <a class="tile" href="upload.php"><h3>Add a Photo or Memory</h3>
       <p>Share a photograph and pin it to the right person. A moderator gives it a quick look, then it appears on their profile.</p></a>
    <?php if (role_at_least('moderator')): ?>
    <a class="tile" href="moderate.php"><h3>Review Queue</h3>
       <p>Approve or decline the photos family members have submitted. Nothing goes public until you say so.</p></a>
    <?php endif; ?>
    <?php if (role_at_least('admin')): ?>
    <a class="tile" href="admin.php"><h3>Invite Family</h3>
       <p>Send invitations and set who is an Admin, Moderator, or Member.</p></a>
    <?php endif; ?>
  </div>

  <div class="panel" style="margin-top:28px">
    <h2>Our archive so far</h2>
    <p class="lede"><b style="color:var(--gold2)"><?= (int)$np ?></b> people in the tree ·
       <b style="color:var(--gold2)"><?= (int)$nph ?></b> photographs pinned and growing.</p>
  </div>
<?php else: ?>
  <?php
  // Featured ancestors for the hero — deceased (public) relatives who have a photograph, eldest first.
  // Best-documented deceased ancestors (most portraits => most likely a real portrait, not a lone headstone).
  $anc = all("SELECT p.pid,p.name,p.given,p.surname,p.birth_date,p.death_date, MIN(ph.path) photo, COUNT(ph.id) c
              FROM persons p JOIN photos ph ON ph.pid=p.pid
              WHERE p.living=0 AND ph.status='approved'
                AND ph.filename NOT LIKE '%headstone%' AND ph.filename NOT LIKE '%tomb%'
                AND ph.filename NOT LIKE '%grave%' AND ph.filename NOT LIKE '%cemetery%'
              GROUP BY p.pid,p.name,p.given,p.surname,p.birth_date,p.death_date
              HAVING c >= 2
              ORDER BY c DESC");
  $anc = array_slice($anc, 0, 16);                 // keep the best-documented
  foreach ($anc as $i => $a) { preg_match('/(\d{4})/', $a['birth_date'], $m); $anc[$i]['yr'] = isset($m[1]) ? (int)$m[1] : 9999; }
  usort($anc, function($x,$y){ return $x['yr'] <=> $y['yr']; });   // display eldest first
  $pool = [];
  foreach ($anc as $a) {
      $yrs = trim(yr($a['birth_date']) . ' – ' . yr($a['death_date']));
      $pool[] = ['n' => trim($a['given'] . ' ' . $a['surname']), 'y' => trim($yrs, ' –'), 'p' => $a['photo']];
  }
  $slots = min(4, count($pool));
  ?>
  <section class="hero">
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="hero-inner">
      <div class="anc-group left">
        <?php for ($s = 0; $s < $slots && $s < 2; $s++): $a = $pool[$s]; ?>
          <figure class="anc" data-slot="<?= $s ?>">
            <img src="<?= e($a['p']) ?>" alt="<?= e($a['n']) ?>">
            <figcaption><?= e($a['n']) ?><span class="yr"><?= e($a['y']) ?></span></figcaption>
          </figure>
        <?php endfor; ?>
      </div>
      <div class="hero-center">
        <h1 class="hero-title">One Family.<br>Many Stories.<br><span class="script">One Legacy.</span></h1>
        <p class="hero-lede">Welcome to our family's digital home — a place where generations come together to
           preserve our history, honor our ancestors, and build a stronger future for those who follow us.</p>
        <a class="btn gold hero-cta" href="tree.php">Explore Our Family Tree</a>
        <div class="hero-actions"><a class="btn-ghost" href="login.php">Family Login</a></div>
      </div>
      <div class="anc-group right">
        <?php for ($s = 2; $s < $slots && $s < 4; $s++): $a = $pool[$s]; ?>
          <figure class="anc" data-slot="<?= $s ?>">
            <img src="<?= e($a['p']) ?>" alt="<?= e($a['n']) ?>">
            <figcaption><?= e($a['n']) ?><span class="yr"><?= e($a['y']) ?></span></figcaption>
          </figure>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <div class="panel" style="text-align:center;max-width:720px;margin:30px auto">
    <p class="lede" style="margin:0 auto">This is the private home of the Battles family — our tree, our photographs,
       and the memories that connect us. Sign in to see everyone, including our living relatives.</p>
    <p class="muted" style="margin-top:16px">Living relatives' names and photos stay hidden from the public view — family sees everything once signed in.</p>
  </div>

  <script>
  (function(){
    var pool = <?= json_encode($pool, JSON_UNESCAPED_UNICODE) ?>;
    var figs = Array.prototype.slice.call(document.querySelectorAll('.anc'));
    if (pool.length <= figs.length) return;
    var idx = figs.length;
    function rotate(){
      var fig = figs[Math.floor(Math.random()*figs.length)];
      var next = pool[idx % pool.length]; idx++;
      var img = fig.querySelector('img'), cap = fig.querySelector('figcaption');
      fig.classList.add('swap');
      setTimeout(function(){
        var pre = new Image();
        pre.onload = function(){
          img.src = next.p; img.alt = next.n;
          cap.innerHTML = next.n + '<span class="yr">' + next.y + '</span>';
          fig.classList.remove('swap');
        };
        pre.src = next.p;
      }, 1000);
    }
    setInterval(rotate, 4200);
  })();
  </script>
<?php endif;
page_foot();
