<?php
/** William's inbox for everything sent through Share Your Thoughts. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/feedback_data.php';
require_role('admin');
feedback_migrate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($act === 'tab') {
        fb_meta_set('tab', empty($_POST['on']) ? '0' : '1');
        flash(empty($_POST['on'])
            ? 'The "Your thoughts" tab is hidden now.'
            : 'The "Your thoughts" tab is showing on every page again.');
        header('Location: feedback_manage.php'); exit;
    }
    if ($id && fb_one($id)) {
        if ($act === 'status')      { fb_set_status($id, $_POST['status'] ?? 'new'); flash('Moved.'); }
        elseif ($act === 'share')   { fb_set_shared($id, !empty($_POST['on'])); flash(!empty($_POST['on']) ? 'Shared — the family can see this one now.' : 'Taken off the family page.'); }
        elseif ($act === 'reply')   { fb_set_reply($id, $_POST['reply'] ?? ''); flash('Your note was saved. It shows under the thought if you share it.'); }
        elseif ($act === 'delete')  { fb_delete($id); flash('Deleted.'); }
    }
    header('Location: feedback_manage.php?tab=' . urlencode($_POST['back'] ?? 'new')); exit;
}

$TAB   = in_array($_GET['tab'] ?? '', ['new','reading','done','all'], true) ? $_GET['tab'] : 'new';
$ROWS  = fb_all($TAB === 'all' ? '' : $TAB);
$COUNT = ['new'=>0,'reading'=>0,'done'=>0,'all'=>0];
foreach (fb_all() as $r) { $COUNT['all']++; if (isset($COUNT[$r['status']])) $COUNT[$r['status']]++; }
list($AVG, $RATED) = fb_avg_rating();

page_head('What people are saying', ['body_class' => 'em']);
?>
<h1>What people are saying</h1>
<p class="lede">Everything sent through <a href="feedback.php">Share Your Thoughts</a> lands here. Nobody else can see this page.</p>

<div class="fbm-top">
  <div class="fbm-stat"><b><?= (int)$COUNT['all'] ?></b><span>thoughts in total</span></div>
  <div class="fbm-stat"><b><?= (int)$COUNT['new'] ?></b><span>you haven&rsquo;t read yet</span></div>
  <div class="fbm-stat"><b><?= $RATED ? e(number_format($AVG, 1)) . ' / 5' : '&mdash;' ?></b><span><?= $RATED ? (int)$RATED . ' star ratings' : 'no ratings yet' ?></span></div>
  <form method="post" class="fbm-tabsw">
    <?= csrf_field() ?><input type="hidden" name="action" value="tab">
    <input type="hidden" name="on" value="<?= fb_tab_on() ? '0' : '1' ?>">
    <b>The &ldquo;Your thoughts&rdquo; tab</b>
    <span><?= fb_tab_on() ? 'is showing in the corner of every page.' : 'is hidden right now.' ?></span>
    <button class="btn2<?= fb_tab_on() ? '' : ' solid' ?>"><?= fb_tab_on() ? 'Hide it' : 'Show it' ?></button>
  </form>
</div>

<div class="fbm-tabs">
  <?php foreach (['new'=>'New','reading'=>'Looking into it','done'=>'Handled','all'=>'Everything'] as $k => $label): ?>
    <a class="fbm-tab<?= $TAB === $k ? ' on' : '' ?>" href="feedback_manage.php?tab=<?= e($k) ?>"><?= e($label) ?> <i><?= (int)$COUNT[$k] ?></i></a>
  <?php endforeach; ?>
</div>

<?php if (!$ROWS): ?>
  <div class="panel"><p><?= $TAB === 'new' ? 'Nothing new right now. When someone sends a thought it will appear here.' : 'Nothing in this list.' ?></p></div>
<?php endif; ?>

<?php foreach ($ROWS as $r): $K = fb_kinds()[$r['kind']] ?? fb_kinds()['suggestion']; ?>
  <div class="panel fbm-item<?= $r['status'] === 'new' ? ' isnew' : '' ?>">
    <div class="fbm-head">
      <span class="fb-av"><?= fb_initials($r['name']) ?></span>
      <div class="fbm-who">
        <b><?= e($r['name'] ?: 'Family member') ?></b>
        <?php if (trim((string)$r['contact']) !== ''): ?><span class="fbm-contact"><?= e($r['contact']) ?></span><?php endif; ?>
        <span class="fbm-meta"><?= fb_icon($K[1], 15) ?> <?= e($K[0]) ?> &middot; <?= e(fb_area_label($r['area'])) ?> &middot; <?= e(fb_ago($r['created_at'])) ?></span>
      </div>
      <?= fb_stars($r['rating']) ?>
      <?php if ($r['shared']): ?><span class="fbm-badge">Shared with the family<?= (int)$r['agrees'] ? ' &middot; ' . (int)$r['agrees'] . ' agree' : '' ?></span><?php endif; ?>
    </div>

    <p class="fbm-body"><?= nl2br(e($r['body'])) ?></p>

    <form method="post" class="fbm-reply">
      <?= csrf_field() ?><input type="hidden" name="action" value="reply">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($TAB) ?>">
      <label>Your note back (shows under the thought if you share it)</label>
      <textarea name="reply" rows="2" placeholder="e.g. Good idea — I'll add the Alabama photos this week."><?= e($r['reply']) ?></textarea>
      <button class="btn2">Save note</button>
    </form>

    <div class="fbm-acts">
      <?php foreach (['new'=>'Mark new','reading'=>'Looking into it','done'=>'Handled'] as $s => $label): if ($s === $r['status']) continue; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="<?= e($s) ?>">
          <input type="hidden" name="back" value="<?= e($TAB) ?>"><button class="btn2"><?= e($label) ?></button></form>
      <?php endforeach; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="share">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="on" value="<?= $r['shared'] ? '0' : '1' ?>">
        <input type="hidden" name="back" value="<?= e($TAB) ?>">
        <button class="btn2<?= $r['shared'] ? '' : ' solid' ?>"><?= $r['shared'] ? 'Stop sharing' : 'Share with the family' ?></button></form>
      <form method="post" onsubmit="return confirm('Delete this thought for good?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="back" value="<?= e($TAB) ?>"><button class="btn2 fbm-del">Delete</button></form>
    </div>
  </div>
<?php endforeach; ?>

<?php page_foot();
