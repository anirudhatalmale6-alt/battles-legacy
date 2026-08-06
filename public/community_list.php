<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/community_data.php';
community_migrate();

$kind = $_GET['kind'] ?? 'update';
if (!in_array($kind, ['question','recipe','update'], true)) $kind = 'update';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'like') {
    csrf_check(); comm_like((int)($_POST['id'] ?? 0));
    header('Location: community_list.php?kind=' . $kind); exit;
}

$items = comm_list($kind);
$META = [
  'question' => ['Family Questions', 'Ask the family — about our history, a photo, a recipe, or a relative.', 'Ask a Question'],
  'recipe'   => ['Family Recipes',   'The dishes that bring us together, passed down for generations.',      'Share a Recipe'],
  'update'   => ['Family Updates',    'What&rsquo;s happening across our family.',                            'Post an Update'],
];
list($ttl, $intro, $cta) = $META[$kind];
page_head($ttl, ['body_class' => 'home fnews']);
?>
<div class="fn-wrap" style="padding-top:22px">
  <p style="margin:0 0 6px"><a class="btn" href="news.php">&larr; Back to Family News</a></p>
  <div class="mlist-head"><h1><?= e($ttl) ?></h1><div class="mlist-orn">&#10086; &nbsp;&bull;&nbsp; &#10086;</div><p><?= $intro ?></p></div>
  <p style="text-align:center;margin:0 0 18px">
    <?php if (logged_in()): ?><a class="btn2 solid" href="community_submit.php?kind=<?= e($kind) ?>"><?= e($cta) ?></a>
    <?php else: ?><a class="btn2 solid" href="login.php">Sign in to <?= e($cta) ?></a><?php endif; ?>
  </p>

  <?php if (!$items): ?>
    <div class="fn-col" style="text-align:center"><p class="fn-cmt" style="margin:0">Nothing here yet. <?= logged_in() ? 'Be the first to add one!' : 'Sign in to be the first to add one.' ?></p></div>
  <?php else: ?>
    <div class="cl-grid">
      <?php foreach ($items as $it): ?>
        <div class="fn-col cl-item">
          <?php if ($kind === 'recipe' && $it['photo']): ?><div class="cl-photo" style="background-image:url('<?= e($it['photo']) ?>')"></div><?php endif; ?>
          <?php if ($kind === 'update' && $it['photo']): ?><div class="cl-photo" style="background-image:url('<?= e($it['photo']) ?>')"></div><?php endif; ?>
          <?php if ($kind === 'recipe'): ?>
            <h3 class="cl-title"><?= e($it['title']) ?></h3>
            <div class="fn-by">Shared by <?= e($it['author']) ?> &middot; <?= e(comm_ago($it['created_at'])) ?></div>
            <?php if ($it['body']): ?><p class="cl-body"><?= nl2br(e(mb_strimwidth($it['body'],0,220,'…'))) ?></p><?php endif; ?>
            <a class="btn2 solid" href="community_view.php?id=<?= (int)$it['id'] ?>">View recipe</a>
          <?php elseif ($kind === 'question'): ?>
            <p class="cl-q">&ldquo;<?= e($it['body']) ?>&rdquo;</p>
            <div class="fn-by">Asked by <?= e($it['author']) ?> &middot; <?= e(comm_ago($it['created_at'])) ?></div>
            <a class="btn2 solid" href="community_view.php?id=<?= (int)$it['id'] ?>"><?= comm_answer_count($it['id']) ?> Answer<?= comm_answer_count($it['id'])==1?'':'s' ?> &middot; View &amp; Answer</a>
          <?php else: /* update */ ?>
            <p class="cl-body"><?= nl2br(e($it['body'])) ?></p>
            <div class="fn-by"><?= e($it['author']) ?> &middot; <?= e(comm_ago($it['created_at'])) ?></div>
          <?php endif; ?>
          <?php if ($kind !== 'question'): ?>
            <form method="post" class="cl-like"><?= csrf_field() ?><input type="hidden" name="action" value="like"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button type="submit"<?= comm_liked($it['id']) ? ' disabled' : '' ?>>&#9825; <?= (int)$it['likes'] ?></button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php legacy_footer(); page_foot();
