<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/faith_data.php';
require_role('admin');
faith_migrate();

$tab = in_array($_GET['tab'] ?? '', ['prayers','ministers','warriors','videos'], true) ? $_GET['tab'] : 'prayers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    /* -------- prayer requests -------- */
    if (in_array($act, ['prayed','unprayed','archive','restore','delete_prayer'], true) && $id) {
        if      ($act === 'prayed')       { faith_mark_prayed($id, 1);   flash('Marked as prayed over.'); }
        elseif  ($act === 'unprayed')     { faith_mark_prayed($id, 0);   flash('Marked as still open.'); }
        elseif  ($act === 'archive')      { faith_archive_prayer($id);   flash('Moved to the archive.'); }
        elseif  ($act === 'restore')      { faith_restore_prayer($id);   flash('Restored to active requests.'); }
        elseif  ($act === 'delete_prayer'){ faith_delete_prayer($id);    flash('Prayer request deleted.'); }
        header('Location: faith_manage.php?tab=prayers' . (($_POST['view'] ?? '') === 'archive' ? '&view=archive' : '')); exit;
    }

    /* -------- ministry family -------- */
    if ($act === 'min_save') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { flash('A minister needs a name.'); }
        else {
            $cur = $id ? faith_minister($id) : null;
            list($photo, $perr) = faith_store_photo('photo', $cur['photo'] ?? '');
            if (!empty($_POST['remove_photo'])) $photo = '';
            if ($perr) flash($perr);
            $era    = ($_POST['era'] ?? 'present') === 'past' ? 'past' : 'present';
            $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
            $f = [$name, trim($_POST['role']??''), $era, trim($_POST['church']??''), trim($_POST['years']??''),
                  trim($_POST['bio']??''), $photo, (int)($_POST['sort']??0), $status];
            if ($id) {
                q("UPDATE faith_ministers SET name=?,role=?,era=?,church=?,years=?,bio=?,photo=?,sort=?,status=? WHERE id=?",
                  array_merge($f, [$id]));
                flash('Minister updated.');
            } else {
                q("INSERT INTO faith_ministers (name,role,era,church,years,bio,photo,sort,status)
                   VALUES (?,?,?,?,?,?,?,?,?)",
                  [$name, trim($_POST['role']??''), $era, trim($_POST['church']??''), trim($_POST['years']??''),
                   trim($_POST['bio']??''), $photo, faith_minister_next_sort(), $status]);
                flash('Minister added — open the Faith page to see them.');
            }
        }
        header('Location: faith_manage.php?tab=ministers'); exit;
    }
    if ($act === 'min_delete' && $id) {
        $cur = faith_minister($id);
        if ($cur && !empty($cur['photo']) && strpos($cur['photo'], 'ministers/') !== false) {
            $abs = __DIR__ . '/' . $cur['photo']; if (is_file($abs)) @unlink($abs);
        }
        faith_delete_minister($id); flash('Minister removed.');
        header('Location: faith_manage.php?tab=ministers'); exit;
    }

    /* -------- featured videos -------- */
    if ($act === 'vid_save') {
        $title = trim($_POST['title'] ?? '');
        $url   = trim($_POST['url'] ?? '');
        if ($title === '')     { flash('A video needs a title.'); }
        elseif ($url === '')   { flash('Please paste the video link so people can watch it.'); }
        else {
            $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
            $f = [$title, trim($_POST['description'] ?? ''), $url, trim($_POST['duration'] ?? ''),
                  (int)($_POST['sort'] ?? 0), $status];
            if ($id) {
                q("UPDATE faith_videos SET title=?,description=?,url=?,duration=?,sort=?,status=? WHERE id=?", array_merge($f, [$id]));
                flash('Video updated.');
            } else {
                q("INSERT INTO faith_videos (title,description,url,duration,sort,status) VALUES (?,?,?,?,?,?)",
                  [$title, trim($_POST['description'] ?? ''), $url, trim($_POST['duration'] ?? ''), faith_video_next_sort(), $status]);
                $id = (int) insert_id();
                flash('Video added — open the Faith page to see it.');
            }
            // the first video added becomes the big one automatically
            if (!empty($_POST['make_featured']) || !faith_one_featured()) faith_set_featured($id);
        }
        header('Location: faith_manage.php?tab=videos'); exit;
    }
    if ($act === 'vid_feature' && $id) { faith_set_featured($id); flash('That video is now the big one at the top.'); header('Location: faith_manage.php?tab=videos'); exit; }
    if ($act === 'vid_delete'  && $id) { faith_delete_video($id);  flash('Video removed.');                          header('Location: faith_manage.php?tab=videos'); exit; }

    /* -------- prayer warriors -------- */
    if ($act === 'war_delete' && $id) { faith_delete_warrior($id); flash('Removed from the prayer warrior list.'); header('Location: faith_manage.php?tab=warriors'); exit; }

    header('Location: faith_manage.php?tab=' . $tab); exit;
}

$view     = ($_GET['view'] ?? '') === 'archive' ? 'archive' : 'active';
$prayers  = faith_prayers($view === 'archive');
$activeN  = faith_prayer_count();
$MINS     = faith_ministers(true);
$WARRIORS = faith_warriors();
$warN     = count($WARRIORS);
$VIDS     = faith_videos(true);

function fm_era_opts($sel) {
    $o = '';
    foreach (['present'=>'Present (serving today)','past'=>'Past (in memory)'] as $v=>$lbl) $o .= '<option value="'.$v.'"'.($sel===$v?' selected':'').'>'.$lbl.'</option>';
    return $o;
}
function fm_status_opts($sel) {
    $o = '';
    foreach (['published'=>'Visible on the page','hidden'=>'Hidden'] as $v=>$lbl) $o .= '<option value="'.$v.'"'.($sel===$v?' selected':'').'>'.$lbl.'</option>';
    return $o;
}

page_head('Prayers & Ministry', ['body_class' => 'em']);
?>
<h1>Prayers &amp; Ministry</h1>
<p class="lede">Manage the Faith page here: read and pray over prayer requests, add the ministers honored on the page (each with a photo and profile), and see who has signed up as a prayer warrior.</p>
<p style="margin:10px 0 4px"><a class="btn" href="faith.php">&larr; Back to the Faith page</a></p>

<div class="em-tabs">
  <a href="?tab=prayers" class="<?= $tab==='prayers'?'on':'' ?>">Prayer requests<?= $activeN ? ' <span class="em-penddot">'.$activeN.'</span>' : ' (0)' ?></a>
  <a href="?tab=ministers" class="<?= $tab==='ministers'?'on':'' ?>">Ministry Family (<?= count($MINS) ?>)</a>
  <a href="?tab=warriors" class="<?= $tab==='warriors'?'on':'' ?>">Prayer Warriors (<?= $warN ?>)</a>
  <a href="?tab=videos" class="<?= $tab==='videos'?'on':'' ?>">Featured Videos (<?= count($VIDS) ?>)</a>
</div>

<?php if ($tab === 'prayers'): ?>
  <div class="em-tabs" style="border:none;margin-top:6px">
    <a href="?tab=prayers&view=active" class="<?= $view==='active'?'on':'' ?>">Active</a>
    <a href="?tab=prayers&view=archive" class="<?= $view==='archive'?'on':'' ?>">Archive</a>
  </div>
  <?php if (!$prayers): ?>
    <div class="panel"><p class="lede" style="margin:0"><?= $view==='archive' ? 'The archive is empty.' : 'No prayer requests are waiting right now. When a family member submits one from the Faith page, it will appear here.' ?></p></div>
  <?php else: foreach ($prayers as $p): ?>
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
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this prayer request permanently?')"><?= csrf_field() ?><input type="hidden" name="view" value="<?= $view ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger" name="action" value="delete_prayer">Delete</button></form>
      </div>
    </div>
  <?php endforeach; endif; ?>

<?php elseif ($tab === 'ministers'): ?>
  <div class="panel em-add">
    <h2>Add a minister</h2>
    <p class="muted" style="margin:0 0 8px">Keep this to a few honored ministers, past and present. Each one gets their own profile page when someone clicks their photo.</p>
    <form method="post" enctype="multipart/form-data" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <div class="em-grid">
        <div><label>Name *</label><input type="text" name="name" required placeholder="e.g. Rev. Horatio Battles"></div>
        <div><label>Role / title</label><input type="text" name="role" placeholder="e.g. Pastor, Oliver Chapel"></div>
        <div><label>Past or present</label><select name="era"><?= fm_era_opts('present') ?></select></div>
        <div><label>Church / ministry</label><input type="text" name="church" placeholder="e.g. Oliver Chapel A.M.E."></div>
        <div><label>Years (optional)</label><input type="text" name="years" placeholder="e.g. 1890–1921"></div>
      </div>
      <label>Their story / profile</label>
      <textarea name="bio" placeholder="A few sentences about their ministry, calling, and legacy."></textarea>
      <label>Photo (JPG/PNG, up to 12 MB)</label>
      <input type="file" name="photo" accept="image/*">
      <button class="btn gold" name="action" value="min_save" style="margin-top:12px">Add minister</button>
    </form>
  </div>

  <?php foreach ($MINS as $m): ?>
    <div class="panel em-row">
      <form method="post" enctype="multipart/form-data" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <div class="em-rowhead">
          <h3><?= e($m['name']) ?><?= $m['era']==='past'?' <span class="em-tag">In Memory</span>':'' ?><?= $m['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="min_delete" onclick="return confirm('Remove this minister?')">Delete</button>
        </div>
        <div class="em-media">
          <div class="em-thumb"<?= $m['photo'] ? ' style="background-image:url(\''.e($m['photo']).'\')"' : '' ?>><?= $m['photo']?'':'No photo' ?></div>
          <div class="em-mediactl">
            <label>Replace photo</label><input type="file" name="photo" accept="image/*">
            <?php if ($m['photo']): ?><label class="em-check"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?>
          </div>
        </div>
        <div class="em-grid">
          <div><label>Name *</label><input type="text" name="name" required value="<?= e($m['name']) ?>"></div>
          <div><label>Role / title</label><input type="text" name="role" value="<?= e($m['role']) ?>"></div>
          <div><label>Past or present</label><select name="era"><?= fm_era_opts($m['era']) ?></select></div>
          <div><label>Church / ministry</label><input type="text" name="church" value="<?= e($m['church']) ?>"></div>
          <div><label>Years</label><input type="text" name="years" value="<?= e($m['years']) ?>"></div>
          <div><label>Order</label><input type="number" name="sort" value="<?= (int)$m['sort'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= fm_status_opts($m['status']) ?></select></div>
        </div>
        <label>Their story / profile</label>
        <textarea name="bio"><?= e($m['bio']) ?></textarea>
        <button class="btn gold" name="action" value="min_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'videos'): ?>
  <div class="panel em-add">
    <h2>Add a video</h2>
    <p class="muted" style="margin:0 0 8px">Sermons, testimonies, songs &mdash; anything you want the family to watch. Paste the link from YouTube (or wherever it lives) and it appears on the left of the Faith page, under Become a Prayer Warrior. YouTube links get their picture automatically.</p>
    <form method="post" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <div class="em-grid">
        <div><label>Title *</label><input type="text" name="title" required placeholder="e.g. Sunday Message — Standing on the Promise"></div>
        <div><label>Video link *</label><input type="text" name="url" required placeholder="Paste the YouTube link here"></div>
        <div><label>Length (optional)</label><input type="text" name="duration" placeholder="e.g. 32:15"></div>
        <div><label>Visibility</label><select name="status"><?= fm_status_opts('published') ?></select></div>
      </div>
      <label>One line about it (optional)</label>
      <input type="text" name="description" placeholder="e.g. Rev. Battles preaching at the 2025 family reunion service">
      <label class="em-check"><input type="checkbox" name="make_featured" value="1"> Make this the big one at the top</label>
      <button class="btn gold" name="action" value="vid_save" style="margin-top:12px">Add video</button>
    </form>
  </div>

  <?php if (!$VIDS): ?>
    <div class="panel"><p class="lede" style="margin:0">No videos yet. Add the first one above and it will show on the Faith page straight away.</p></div>
  <?php endif; ?>

  <?php foreach ($VIDS as $v): ?>
    <div class="panel em-row">
      <form method="post" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
        <div class="em-rowhead">
          <h3><?= e($v['title']) ?><?= $v['featured'] ? ' <span class="em-tag feat">Big one at the top</span>' : '' ?><?= $v['status']==='hidden' ? ' <span class="em-tag hid">Hidden</span>' : '' ?></h3>
          <button class="btn danger" name="action" value="vid_delete" onclick="return confirm('Remove this video?')">Delete</button>
        </div>
        <div class="em-media">
          <div class="em-thumb"<?= faith_video_thumb($v) ? ' style="background-image:url(\''.e(faith_video_thumb($v)).'\')"' : '' ?>><?= faith_video_thumb($v) ? '' : 'No picture' ?></div>
          <div class="em-mediactl">
            <p class="muted" style="margin:0 0 6px;font-size:13px">The picture comes from YouTube automatically. Other links show a play symbol instead.</p>
            <?php if (!$v['featured']): ?>
              <button class="btn2 solid" name="action" value="vid_feature">Make this the big one</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="em-grid">
          <div><label>Title *</label><input type="text" name="title" required value="<?= e($v['title']) ?>"></div>
          <div><label>Video link *</label><input type="text" name="url" required value="<?= e($v['url']) ?>"></div>
          <div><label>Length</label><input type="text" name="duration" value="<?= e($v['duration']) ?>"></div>
          <div><label>Order</label><input type="number" name="sort" value="<?= (int)$v['sort'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= fm_status_opts($v['status']) ?></select></div>
        </div>
        <label>One line about it</label>
        <input type="text" name="description" value="<?= e($v['description']) ?>">
        <button class="btn gold" name="action" value="vid_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php else: /* warriors */ ?>
  <?php if (!$WARRIORS): ?>
    <div class="panel"><p class="lede" style="margin:0">No prayer warriors have signed up yet. When a family member signs up from the Faith page, they&rsquo;ll appear here.</p></div>
  <?php else: foreach ($WARRIORS as $w): ?>
    <div class="panel em-row">
      <div class="em-rowhead">
        <h3><?= e($w['name']) ?></h3>
        <span class="em-by"><?= e(faith_ago($w['created_at'])) ?></span>
      </div>
      <?php if ($w['contact']): ?><p class="fpr-contact" style="color:#4a3620">&#9993; <?= e($w['contact']) ?></p><?php endif; ?>
      <?php if (trim($w['note'] ?? '')): ?><p class="fpr-body"><?= nl2br(e($w['note'])) ?></p><?php endif; ?>
      <div class="em-pendbtns">
        <form method="post" style="display:inline" onsubmit="return confirm('Remove this prayer warrior?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn danger" name="action" value="war_delete">Remove</button></form>
      </div>
    </div>
  <?php endforeach; endif; ?>
<?php endif; ?>

<?php page_foot();
