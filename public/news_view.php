<?php
/** One announcement on its own page — the whole photo, the whole story.
 *  Card space is limited; this is where a long tribute can be read properly. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/news_data.php';

try { news_migrate(); } catch (\Throwable $e) {}

$p = news_post($_GET['id'] ?? 0);
$isAdmin = role_at_least('admin');
if (!$p || ($p['status'] !== 'published' && !$isAdmin)) {
    http_response_code(404);
    page_head('Not found');
    echo '<div class="panel"><p>That announcement is no longer here. <a href="news.php">Back to Family News</a></p></div>';
    page_foot(); exit;
}

$cat  = news_cat($p['category']);
$more = news_posts(false, $p['category'], 4);
$more = array_values(array_filter($more, function ($r) use ($p) { return (int)$r['id'] !== (int)$p['id']; }));

page_head($p['title'] . ' — Family News', ['body_class' => 'home fnews']);
?>
<article class="nv-wrap">
  <a class="fn-back" href="news_all.php?cat=<?= e($p['category']) ?>"><?= news_icon('back') ?> All <?= e(strtolower($cat[0])) ?> news</a>

  <header class="nv-head">
    <span class="fn-tag <?= e($cat[2]) ?>"><?= e($cat[0]) ?></span>
    <h1><?= e($p['title']) ?></h1>
    <?php if ($p['date_label']): ?><div class="nv-date"><?= e($p['date_label']) ?></div><?php endif; ?>
    <?php if ($p['status'] !== 'published'): ?><div class="nv-hidden">Hidden &mdash; only you can see this.</div><?php endif; ?>
  </header>

  <?php if ($p['photo']): ?>
    <figure class="nv-photo"><img src="<?= e($p['photo']) ?>" alt="<?= e($p['title']) ?>"></figure>
  <?php endif; ?>

  <?php if (trim($p['body']) !== ''): ?>
    <div class="nv-body"><?= nl2br(e($p['body'])) ?></div>
  <?php endif; ?>

  <div class="nv-meta">
    <span><?= news_icon('heart') ?> <?= (int)$p['likes'] ?></span>
    <span><?= news_icon('chat') ?> <?= (int)$p['comments'] ?></span>
    <?php if ($isAdmin): ?><a class="btn2" href="news_manage.php">&#9998; Edit this announcement</a><?php endif; ?>
  </div>

  <?php if ($more): ?>
    <section class="nv-more">
      <h2>More <?= e(strtolower($cat[0])) ?> news</h2>
      <div class="fn-cards"><?php foreach ($more as $m) echo news_card($m); ?></div>
    </section>
  <?php endif; ?>

  <p style="margin-top:22px"><a class="btn2 solid" href="news.php">&laquo; Back to Family News</a></p>
</article>

<?php legacy_footer(); page_foot();
