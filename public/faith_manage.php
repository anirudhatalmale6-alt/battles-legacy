<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/faith_data.php';
require_role('admin');
faith_migrate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($id) {
        if      ($act === 'prayed')   { faith_mark_prayed($id, 1);   flash('Marked as prayed over.'); }
        elseif  ($act === 'unprayed') { faith_mark_prayed($id, 0);   flash('Marked as still open.'); }
        elseif  ($act === 'archive')  { faith_archive_prayer($id);   flash('Moved to the archive.'); }
        elseif  ($act === 'restore')  { faith_restore_prayer($id);   flash('Restored to active requests.'); }
        elseif  ($act === 'delete')   { faith_delete_prayer($id);    flash('Prayer request deleted.'); }
    }
    header('Location: faith_manage.php' . (($_POST['view'] ?? '') === 'archive' ? '?view=archive' : '')); exit;
}

$view    = ($_GET['view'] ?? '') === 'archive' ? 'archive' : 'active';
$prayers = faith_prayers($view === 'archive');
$activeN = faith_prayer_count();

page_head('Prayer Requests', ['body_class' => 'em']);
?>
<h1>Prayer Requests</h1>
<p class="lede">Prayer requests submitted by the family from the Faith page. Mark each one as prayed over when you&rsquo;ve
   lifted it up, then archive it. Private requests are marked so you can keep them between you and the Lord.</p>
<p style="margin:10px 0 4px"><a class="btn" href="faith.php">&larr; Back to the Faith page</a></p>

<div class="em-tabs">
  <a href="?view=active" class="<?= $view==='active'?'on':'' ?>">Active<?= $activeN ? ' <span class="em-penddot">'.$activeN.'</span>' : ' (0)' ?></a>
  <a href="?view=archive" class="<?= $view==='archive'?'on':'' ?>">Archive</a>
</div>

<?php if (!$prayers): ?>
  <div class="panel"><p class="lede" style="margin:0"><?= $view==='archive' ? 'The archive is empty.' : 'No prayer requests are waiting right now. When a family member submits one from the Faith page, it will appear here.' ?></p></div>
<?php else: ?>
  <?php foreach ($prayers as $p): ?>
    <div class="panel em-row fpr<?= $p['prayed'] ? ' done' : '' ?>">
      <div class="em-rowhead">
        <h3><?= $p['subject'] ? e($p['subject']) : 'Prayer request' ?>
          <?php if ($p['is_private']): ?><span class="em-tag hid">Private</span><?php endif; ?>
          <?php if ($p['prayed']): ?><span class="em-tag feat">Prayed over</span><?php endif; ?>
        </h3>
        <span class="em-by"><?= $p['name'] ? 'From ' . e($p['name']) : 'From a family member' ?> &middot; <?= e(faith_ago($p['created_at'])) ?></span>
      </div>
      <p class="fpr-body"><?= nl2br(e($p['body'])) ?></p>
      <?php if ($p['may_contact']): ?><p class="fpr-contact">&#9993; This person is open to being contacted by family.</p><?php endif; ?>
      <div class="em-pendbtns">
        <?php if ($view === 'active'): ?>
          <?php if (!$p['prayed']): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn2 solid" name="action" value="prayed">&#128591; Mark prayed over</button></form>
          <?php else: ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn" name="action" value="unprayed">Mark still open</button></form>
          <?php endif; ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn" name="action" value="archive">Archive</button></form>
        <?php else: ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="view" value="archive"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn" name="action" value="restore">Restore</button></form>
        <?php endif; ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this prayer request permanently?')"><?= csrf_field() ?><input type="hidden" name="view" value="<?= $view ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger" name="action" value="delete">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php page_foot();
