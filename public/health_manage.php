<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/health_data.php';
require_role('admin');
health_migrate();

function hm_next_sort($t) { $r = one("SELECT MAX(sort) m FROM $t"); return ($r && $r['m'] !== null) ? ((int)$r['m'] + 1) : 0; }

$tab = 'tips';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    if (strpos($act, 'ev') === 0) $tab = 'events';
    try {
        if ($act === 'tip_save') {
            $tip = trim($_POST['tip'] ?? '');
            if ($tip === '') { flash('Please write the tip.'); }
            else {
                $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
                if ($id) { q("UPDATE health_tips SET tip=?,source=?,sort=?,status=?,sample=0 WHERE id=?", [$tip, trim($_POST['source']??''), (int)($_POST['sort']??0), $status, $id]); flash('Tip updated.'); }
                else { q("INSERT INTO health_tips (tip,source,sort,status,sample) VALUES (?,?,?,?,0)", [$tip, trim($_POST['source']??''), hm_next_sort('health_tips'), $status]); flash('Tip added.'); }
            }
        } elseif ($act === 'tip_delete' && $id) { q("DELETE FROM health_tips WHERE id=?", [$id]); flash('Tip removed.'); }
        elseif ($act === 'ev_save') {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { flash('An event needs a title.'); }
            else {
                $icon   = array_key_exists($_POST['icon'] ?? '', health_event_icons()) ? $_POST['icon'] : 'walk';
                $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
                $f = [strtoupper(trim($_POST['mon']??'')), trim($_POST['day']??''), $title, trim($_POST['detail']??''), $icon, (int)($_POST['sort']??0), $status];
                if ($id) { q("UPDATE health_events SET mon=?,day=?,title=?,detail=?,icon=?,sort=?,status=?,sample=0 WHERE id=?", array_merge($f,[$id])); flash('Event updated.'); }
                else { q("INSERT INTO health_events (mon,day,title,detail,icon,sort,status,sample) VALUES (?,?,?,?,?,?,?,0)",
                        [strtoupper(trim($_POST['mon']??'')), trim($_POST['day']??''), $title, trim($_POST['detail']??''), $icon, hm_next_sort('health_events'), $status]); flash('Event added.'); }
            }
        } elseif ($act === 'ev_delete' && $id) { q("DELETE FROM health_events WHERE id=?", [$id]); flash('Event removed.'); }
    } catch (Exception $ex) { flash('Sorry — that could not be saved. Please try again.'); }
    header('Location: health_manage.php?tab=' . $tab); exit;
}

$tab  = in_array($_GET['tab'] ?? '', ['tips','events'], true) ? $_GET['tab'] : 'tips';
$TIPS = health_tips(true);
$EVTS = health_events(true);
function hm_icon_opts($sel){ $o=''; foreach (health_event_icons() as $k=>$l) $o .= '<option value="'.e($k).'"'.($sel===$k?' selected':'').'>'.e($l).'</option>'; return $o; }
function hm_status_opts($sel){ $o=''; foreach (['published'=>'Visible on the page','hidden'=>'Hidden'] as $v=>$l) $o .= '<option value="'.$v.'"'.($sel===$v?' selected':'').'>'.$l.'</option>'; return $o; }

page_head('Manage Health', ['body_class' => 'em']);
?>
<h1>Manage Health</h1>
<p class="lede">Add, edit, or remove the daily health tips (they rotate on the page) and the upcoming health events. Entries marked &ldquo;Example&rdquo; are samples &mdash; edit one or add your own and the tag goes away.</p>
<p style="margin:10px 0 4px"><a class="btn gold" href="health.php" target="_blank" rel="noopener">View the Health page &#8599;</a></p>

<div class="em-tabs">
  <a href="?tab=tips" class="<?= $tab==='tips'?'on':'' ?>">Health Tips (<?= count($TIPS) ?>)</a>
  <a href="?tab=events" class="<?= $tab==='events'?'on':'' ?>">Health Events (<?= count($EVTS) ?>)</a>
</div>

<?php if ($tab === 'tips'): ?>
  <div class="panel em-add">
    <h2>Add a health tip</h2>
    <form method="post" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <label>Tip *</label>
      <textarea name="tip" required placeholder="e.g. A short walk after meals helps digestion."></textarea>
      <label>Source (optional)</label>
      <input type="text" name="source" placeholder="e.g. Dr. Smith / CDC">
      <button class="btn gold" name="action" value="tip_save" style="margin-top:12px">Add tip</button>
    </form>
  </div>
  <?php foreach ($TIPS as $t): ?>
    <div class="panel em-row">
      <form method="post" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <div class="em-rowhead">
          <h3><?= e(mb_strimwidth($t['tip'],0,70,'…')) ?><?= $t['sample']?' <span class="em-tag">Example</span>':'' ?><?= $t['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="tip_delete" onclick="return confirm('Remove this tip?')">Delete</button>
        </div>
        <label>Tip *</label>
        <textarea name="tip" required><?= e($t['tip']) ?></textarea>
        <div class="em-grid">
          <div><label>Source</label><input type="text" name="source" value="<?= e($t['source']) ?>"></div>
          <div><label>Order</label><input type="number" name="sort" value="<?= (int)$t['sort'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= hm_status_opts($t['status']) ?></select></div>
        </div>
        <button class="btn gold" name="action" value="tip_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php else: ?>
  <div class="panel em-add">
    <h2>Add a health event</h2>
    <form method="post" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <div class="em-grid">
        <div><label>Title *</label><input type="text" name="title" required placeholder="e.g. Community Walk"></div>
        <div><label>Detail</label><input type="text" name="detail" placeholder="e.g. Family &amp; friends welcome"></div>
        <div><label>Month (short)</label><input type="text" name="mon" maxlength="4" placeholder="e.g. MAY"></div>
        <div><label>Day</label><input type="text" name="day" maxlength="4" placeholder="e.g. 24"></div>
        <div><label>Icon</label><select name="icon"><?= hm_icon_opts('walk') ?></select></div>
      </div>
      <button class="btn gold" name="action" value="ev_save" style="margin-top:12px">Add event</button>
    </form>
  </div>
  <?php foreach ($EVTS as $ev): ?>
    <div class="panel em-row">
      <form method="post" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
        <div class="em-rowhead">
          <h3><?= e(trim($ev['mon'].' '.$ev['day'])) ?> &middot; <?= e($ev['title']) ?><?= $ev['sample']?' <span class="em-tag">Example</span>':'' ?><?= $ev['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="ev_delete" onclick="return confirm('Remove this event?')">Delete</button>
        </div>
        <div class="em-grid">
          <div><label>Title *</label><input type="text" name="title" required value="<?= e($ev['title']) ?>"></div>
          <div><label>Detail</label><input type="text" name="detail" value="<?= e($ev['detail']) ?>"></div>
          <div><label>Month (short)</label><input type="text" name="mon" maxlength="4" value="<?= e($ev['mon']) ?>"></div>
          <div><label>Day</label><input type="text" name="day" maxlength="4" value="<?= e($ev['day']) ?>"></div>
          <div><label>Icon</label><select name="icon"><?= hm_icon_opts($ev['icon']) ?></select></div>
          <div><label>Order</label><input type="number" name="sort" value="<?= (int)$ev['sort'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= hm_status_opts($ev['status']) ?></select></div>
        </div>
        <button class="btn gold" name="action" value="ev_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php page_foot();
