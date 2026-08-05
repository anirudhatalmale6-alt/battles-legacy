<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/faith_data.php';
faith_migrate();

$m = faith_minister($_GET['id'] ?? 0);
if (!$m || ($m['status'] !== 'published' && !role_at_least('admin'))) {
    http_response_code(404);
    page_head('Not found', ['body_class' => 'home faith']);
    echo '<div class="faith-body" style="padding:40px 22px"><div class="fpanel"><h2 style="margin-top:0">That minister isn\'t here</h2><p><a href="faith.php#ministry">&larr; Back to the Faith page</a></p></div></div>';
    legacy_footer(); page_foot(); exit;
}

$isAdmin = role_at_least('admin');
page_head($m['name'], ['body_class' => 'home faith']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="faith_manage.php?tab=ministers">&#9998; Edit ministers</a>
  </div>
<?php endif; ?>

<div class="faith-body min-wrap">
  <p style="margin:18px 0 6px"><a class="btn" href="faith.php#ministry">&larr; Back to Ministry Family</a></p>

  <div class="min-hero">
    <div class="min-photo"<?= $m['photo'] ? ' style="background-image:url(\''.e($m['photo']).'\')"' : ' data-empty="1"' ?>>
      <?php if (!$m['photo']): ?><span class="min-mono"><?= faith_mono($m['name']) ?></span><?php endif; ?>
    </div>
    <div class="min-head">
      <?php if ($m['era'] === 'past'): ?><span class="min-badge">In Loving Memory</span><?php endif; ?>
      <h1><?= e($m['name']) ?></h1>
      <?php if ($m['role']): ?><div class="min-role"><?= e($m['role']) ?></div><?php endif; ?>
      <div class="min-meta">
        <?php if ($m['church']): ?><span>&#10013; <?= e($m['church']) ?></span><?php endif; ?>
        <?php if ($m['years']): ?><span><?= e($m['years']) ?></span><?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (trim($m['bio'] ?? '')): ?>
    <div class="fpanel min-bio">
      <h2>Their Story</h2>
      <?php foreach (preg_split('/\n\s*\n/', trim($m['bio'])) as $para): $para = trim($para); if ($para === '') continue; ?>
        <p><?= nl2br(e($para)) ?></p>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="fpanel min-bio"><p class="fp-empty"><?= $isAdmin ? 'No story added yet — add one from the minister editor.' : 'A tribute to this minister is being prepared.' ?></p></div>
  <?php endif; ?>
</div>

<?php legacy_footer(); page_foot();
