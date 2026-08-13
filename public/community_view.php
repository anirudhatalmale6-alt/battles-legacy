<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/community_data.php';
community_migrate();

$item = comm_one($_GET['id'] ?? 0);
if (!$item || !in_array($item['kind'], ['question','recipe','update','healthtip'], true) || ($item['status'] !== 'published' && !role_at_least('admin'))) {
    http_response_code(404);
    page_head('Not found', ['body_class' => 'home fnews']);
    echo '<div class="fn-wrap" style="padding:30px 20px"><div class="fn-col"><p class="fn-cmt" style="margin:0">That isn&rsquo;t here. <a href="news.php">Back to Family News</a>.</p></div></div>';
    legacy_footer(); page_foot(); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'like') {
    csrf_check(); comm_like((int)$item['id']);
    header('Location: community_view.php?id=' . (int)$item['id']); exit;
}
/* An admin's own posts publish straight away, so they never reach the pending
   queue and there was no way to take one down again. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove' && role_at_least('admin')) {
    csrf_check();
    $kind = $item['kind'];
    comm_delete((int)$item['id']);
    flash('Removed.');
    header('Location: ' . ($kind === 'healthtip' ? 'health.php#familytips' : 'community_list.php?kind=' . $kind)); exit;
}

$isQ   = $item['kind'] === 'question';
$isTip = $item['kind'] === 'healthtip';
$listKind = $item['kind'];
$BACKS = [
  'question'  => ['community_list.php?kind=question', 'Questions'],
  'recipe'    => ['community_list.php?kind=recipe',   'Recipes'],
  'update'    => ['community_list.php?kind=update',   'Family Updates'],
  'healthtip' => ['health.php#familytips',            'Health'],
];
list($backUrl, $backLabel) = $BACKS[$item['kind']];
$heading = $isQ ? 'Family Question' : (trim($item['title']) !== '' ? $item['title'] : ($isTip ? 'Family Health Tip' : 'Family Update'));
page_head($heading, ['body_class' => 'home fnews']);
?>
<div class="fn-wrap" style="padding-top:22px;max-width:820px">
  <p style="margin:0 0 12px"><a class="btn" href="<?= e($backUrl) ?>">&larr; Back to <?= e($backLabel) ?></a></p>

  <div class="fn-col cv-main">
    <?php if ($isQ): ?>
      <p class="cv-q">&ldquo;<?= e($item['body']) ?>&rdquo;</p>
      <div class="fn-by">Asked by <?= e($item['author']) ?> &middot; <?= e(comm_ago($item['created_at'])) ?></div>
    <?php else: ?>
      <?php if ($item['photo']): ?><img class="cv-pic" src="<?= e($item['photo']) ?>" alt="<?= e($item['title'] ?: 'Shared picture') ?>"><?php endif; ?>
      <?php if ($isTip): ?><span class="cv-kind">Health Tip</span><?php endif; ?>
      <h1 style="color:#5c1a1f;margin:6px 0 2px"><?= e($heading) ?></h1>
      <div class="fn-by">Shared by <?= e($item['author']) ?> &middot; <?= e(comm_ago($item['created_at'])) ?></div>
      <?php if ($item['body']): ?><div class="cv-recipe"><?= nl2br(e($item['body'])) ?></div><?php endif; ?>
      <form method="post" class="cl-like" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="like">
        <button type="submit"<?= comm_liked($item['id']) ? ' disabled' : '' ?>>&#9825; <?= (int)$item['likes'] ?> &middot; <?= comm_liked($item['id']) ? 'Liked' : 'Like this' ?></button></form>
    <?php endif; ?>
    <?php if (role_at_least('admin')): ?>
      <form method="post" class="cv-remove" onsubmit="return confirm('Remove this permanently?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="remove">
        <button type="submit">Remove this</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($isQ): $answers = comm_answers($item['id']); ?>
    <h2 style="color:#5c1a1f;margin:22px 0 10px"><?= count($answers) ?> Answer<?= count($answers)==1?'':'s' ?></h2>
    <?php if ($answers): foreach ($answers as $a): ?>
      <div class="fn-col cv-answer">
        <p><?= nl2br(e($a['body'])) ?></p>
        <div class="fn-by">&mdash; <?= e($a['author']) ?> &middot; <?= e(comm_ago($a['created_at'])) ?></div>
      </div>
    <?php endforeach; else: ?>
      <p class="fn-cmt">No answers yet.<?= logged_in() ? ' Be the first to help.' : '' ?></p>
    <?php endif; ?>

    <div class="fn-col" style="margin-top:14px">
      <?php if (logged_in()): ?>
        <h3 style="margin-top:0;color:#5c1a1f;font-family:'Cormorant Garamond',serif">Add your answer</h3>
        <p class="fn-cmt" style="margin:0 0 10px"><?= role_at_least('admin') ? 'As an editor, your answer posts right away.' : 'Your answer goes to William for approval before it appears.' ?></p>
        <form method="post" action="community_submit.php" class="em-form">
          <?= csrf_field() ?><input type="hidden" name="kind" value="answer"><input type="hidden" name="parent" value="<?= (int)$item['id'] ?>">
          <textarea name="body" required placeholder="Share what you know&hellip;"></textarea>
          <button class="btn gold" type="submit" style="margin-top:10px">Post answer</button>
        </form>
      <?php else: ?>
        <p class="fn-cmt" style="margin:0"><a href="login.php">Sign in</a> to answer this question.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php legacy_footer(); page_foot();
