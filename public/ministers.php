<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/faith_data.php';
faith_migrate();

$era = ($_GET['era'] ?? '') === 'past' ? 'past' : '';
$all = faith_ministers();                 // published only
if ($era === 'past') $all = array_values(array_filter($all, function ($m) { return $m['era'] === 'past'; }));

$isAdmin = role_at_least('admin');
$title = $era === 'past' ? 'In Loving Memory' : 'Our Ministry Family';
page_head($title, ['body_class' => 'home faith']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="faith_manage.php?tab=ministers">&#9998; Add or edit ministers</a>
  </div>
<?php endif; ?>

<div class="faith-body min-wrap">
  <p style="margin:18px 0 6px"><a class="btn" href="faith.php#ministry">&larr; Back to the Faith page</a></p>

  <div class="mlist-head">
    <h1><?= e($title) ?></h1>
    <div class="mlist-orn">&#10086; &nbsp;&bull;&nbsp; &#10086;</div>
    <p><?php if ($era === 'past'): ?>Remembering our spiritual leaders who faithfully served God and have gone home to be with the Lord.<?php else: ?>Honoring the men and women &mdash; past and present &mdash; who answered the call to serve God through ministry. Click anyone to read their story.<?php endif; ?></p>
  </div>

  <?php if ($era === 'past'): ?>
    <p style="text-align:center;margin:0 0 14px"><a class="btn2" href="ministers.php">See all ministers &rarr;</a></p>
  <?php endif; ?>

  <?php if ($all): ?>
    <div class="fmins mlist-grid">
      <?php foreach ($all as $m): ?>
        <a class="fmin-card" href="minister.php?id=<?= (int)$m['id'] ?>">
          <span class="fmin-photo"<?= $m['photo'] ? ' style="background-image:url(\''.e($m['photo']).'\')"' : ' data-empty="1"' ?>>
            <?php if (!$m['photo']): ?><span class="fmin-mono"><?= faith_mono($m['name']) ?></span><?php endif; ?>
            <?php if ($m['era'] === 'past'): ?><span class="fmin-era">In Memory</span><?php endif; ?>
          </span>
          <span class="fmin-name"><?= e($m['name']) ?></span>
          <?php if ($m['role']): ?><span class="fmin-role"><?= e($m['role']) ?></span><?php endif; ?>
          <?php if ($m['years']): ?><span class="fmin-role"><?= e($m['years']) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif ($isAdmin): ?>
    <div class="fpanel"><p class="fp-empty" style="margin:0"><?= $era === 'past' ? 'No past ministers added yet.' : 'No ministers added yet.' ?> Use &ldquo;Add or edit ministers&rdquo; above to add them &mdash; each with a photo and a profile.</p></div>
  <?php else: ?>
    <div class="fpanel"><p class="fp-empty" style="margin:0">Our ministry family will be honored here soon.</p></div>
  <?php endif; ?>
</div>

<?php legacy_footer(); page_foot();
