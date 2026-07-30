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
  // The family patriarchs — the portraits provided by the family. These are fixed (not auto-selected).
  $pool = [
    ['n' => 'Richmond Battles',    'y' => '1832 – 1909', 'p' => 'assets/patriarchs/richmond.jpg', 'pid' => '@I294@'],
    ['n' => 'John N. Battles',     'y' => '1870 – 1940', 'p' => 'assets/patriarchs/johnn.jpg',    'pid' => ''],
    ['n' => 'William Holmes',      'y' => '1921 – 1988', 'p' => 'assets/patriarchs/william.jpg',  'pid' => ''],
    ['n' => 'Lafane Battles Sr.',  'y' => '1896 – 1978', 'p' => 'assets/patriarchs/lafane.jpg',   'pid' => '@I450@'],
  ];
  $slots = count($pool);
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

<?php endif;
page_foot();
