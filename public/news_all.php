<?php
/** All Family News — the full archive, filterable by category, so any number of
 *  births, marriages or deaths stays easy to find. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/news_data.php';

try { news_migrate(); } catch (\Throwable $e) {}

$cat = isset($_GET['cat']) && array_key_exists($_GET['cat'], news_cats()) ? $_GET['cat'] : '';
try { $POSTS = news_posts(false, $cat); } catch (\Throwable $e) { $POSTS = []; }

$isAdmin = role_at_least('admin');
$counts = [];
foreach (news_cats() as $k => $c) { $counts[$k] = news_count($k); }
$total = news_count();

$heading = $cat ? news_cat($cat)[0] : 'All Family News';
page_head($cat ? $heading . ' — Family News' : 'All Family News', ['body_class' => 'home fnews']);
?>
<section class="fn-abar">
  <div class="fn-abarin">
    <div>
      <a class="fn-back" href="news.php"><?= news_icon('back') ?> Family News</a>
      <h1><?= e($heading) ?></h1>
      <p><?= $cat
            ? e($counts[$cat]) . ' ' . e(strtolower($heading)) . ' announcement' . ($counts[$cat] == 1 ? '' : 's')
            : 'Every announcement the family has shared &mdash; newest first.' ?></p>
    </div>
    <?php if ($isAdmin): ?><a class="btn2 solid" href="news_manage.php">&#9998; Manage announcements</a><?php endif; ?>
  </div>
</section>

<div class="fn-wrap">
  <div class="fn-chips">
    <a class="fn-chip<?= $cat === '' ? ' on' : '' ?>" href="news_all.php">All <i><?= (int)$total ?></i></a>
    <?php foreach (news_cats() as $k => $c): if (!$counts[$k]) continue; ?>
      <a class="fn-chip<?= $cat === $k ? ' on' : '' ?>" href="news_all.php?cat=<?= e($k) ?>"><?= e($c[0]) ?> <i><?= (int)$counts[$k] ?></i></a>
    <?php endforeach; ?>
  </div>

  <?php if ($POSTS): ?>
    <div class="fn-cards wide"><?php foreach ($POSTS as $p) echo news_card($p); ?></div>
  <?php else: ?>
    <p class="fn-empty"><?= $cat
        ? 'Nothing in this category yet.'
        : ($isAdmin ? 'No announcements yet — add your first one from Manage Family News.' : 'Family news will be shared here soon.') ?></p>
  <?php endif; ?>
</div>

<?php legacy_footer(); page_foot();
