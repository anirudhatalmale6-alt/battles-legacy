<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/news_data.php';
require_once __DIR__ . '/../src/community_data.php';
require_role('admin');
news_migrate();
community_migrate();

$tab = 'news';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    if (strpos($act, 'event') === 0) $tab = 'events';
    elseif (strpos($act, 'comm') === 0) $tab = 'submissions';
    try {
        if ($act === 'comm_approve' && $id) { comm_approve($id); flash('Approved — it is live now.'); }
        elseif ($act === 'comm_decline' && $id) { comm_decline($id); flash('Declined.'); }
        elseif ($act === 'comm_delete' && $id) { comm_delete($id); flash('Removed.'); }
        elseif ($act === 'post_save') {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { flash('An announcement needs a title.'); }
            else {
                $cur = $id ? one("SELECT photo, photo_fit FROM news_posts WHERE id=?", [$id]) : null;
                list($photo, $perr, $autofit) = news_store_photo('photo', $cur['photo'] ?? '');
                if (!empty($_POST['remove_photo'])) $photo = '';
                if ($perr) flash($perr);
                // a new upload picks its own best fit; otherwise honour the chooser
                $fit = in_array($_POST['photo_fit'] ?? '', ['cover','whole'], true) ? $_POST['photo_fit'] : ($cur['photo_fit'] ?? 'cover');
                if ($autofit && ($_POST['photo_fit'] ?? '') === ($_POST['photo_fit_prev'] ?? '')) $fit = $autofit;
                $cat    = news_cat_ok($_POST['category'] ?? '');
                $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
                $lbl = trim($_POST['date_label'] ?? '');
                /* The label is his to word however he likes; on_date is the machine
                   copy the ordering runs on. Unreadable label, or none, falls back
                   to today so a new post still lands at the top rather than last. */
                $on  = news_date_from_label($lbl);
                if (!$on) $on = $id ? null : date('Y-m-d');
                $f = [$cat, $lbl, $title, trim($_POST['body']??''), $photo, $fit,
                      (int)($_POST['likes']??0), (int)($_POST['comments']??0), (int)($_POST['sort']??0), $status];
                if ($id) {
                    if ($on === null) { $r = one("SELECT on_date FROM news_posts WHERE id=?", [$id]); $on = $r ? $r['on_date'] : null; }
                    $f[] = $on;
                    q("UPDATE news_posts SET category=?,date_label=?,title=?,body=?,photo=?,photo_fit=?,likes=?,comments=?,sort=?,status=?,on_date=?,sample=0 WHERE id=?", array_merge($f, [$id]));
                    flash('Announcement updated.');
                } else {
                    // new announcements sort to the top on their own — no renumbering needed
                    q("INSERT INTO news_posts (category,date_label,title,body,photo,photo_fit,likes,comments,sort,status,on_date,sample) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)",
                      [$cat, $lbl, $title, trim($_POST['body']??''), $photo, $fit, (int)($_POST['likes']??0), (int)($_POST['comments']??0), 0, $status, $on]);
                    flash('Announcement added — open the Family News page to see it.');
                }
            }
        } elseif ($act === 'post_delete' && $id) {
            $r = one("SELECT photo FROM news_posts WHERE id=?", [$id]);
            if ($r && !empty($r['photo']) && strpos($r['photo'], 'uploads/') !== false) { $abs = __DIR__ . '/' . $r['photo']; if (is_file($abs)) @unlink($abs); }
            q("DELETE FROM news_posts WHERE id=?", [$id]); flash('Announcement removed.');
        } elseif ($act === 'event_save') {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { flash('An event needs a title.'); }
            else {
                $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
                /* One date field now, year and all, and the big MON/DAY on the card
                   is drawn from it. Typing the month and day separately is what left
                   the events with no year to expire on. */
                $on = trim($_POST['on_date'] ?? '');
                $ts = $on ? strtotime($on) : false;
                $on = $ts ? date('Y-m-d', $ts) : null;
                $mon = $ts ? strtoupper(date('M', $ts)) : strtoupper(trim($_POST['mon'] ?? ''));
                $day = $ts ? date('j', $ts) : trim($_POST['day'] ?? '');
                if (!$on) flash('That event has no date, so it will not appear under Upcoming Events until you give it one.');
                $annual = !empty($_POST['annual']) ? 1 : 0;
                $f = [$mon, $day, $on, $annual, $title, trim($_POST['place']??''), trim($_POST['time_label']??''), (int)($_POST['sort']??0), $status];
                if ($id) {
                    q("UPDATE news_events SET mon=?,day=?,on_date=?,annual=?,title=?,place=?,time_label=?,sort=?,status=?,sample=0 WHERE id=?", array_merge($f, [$id]));
                    flash('Event updated.');
                } else {
                    q("INSERT INTO news_events (mon,day,on_date,annual,title,place,time_label,sort,status,sample) VALUES (?,?,?,?,?,?,?,?,?,0)",
                      [$mon, $day, $on, $annual, $title, trim($_POST['place']??''), trim($_POST['time_label']??''), 0, $status]);
                    flash('Event added.');
                }
            }
        } elseif ($act === 'event_delete' && $id) {
            q("DELETE FROM news_events WHERE id=?", [$id]); flash('Event removed.');
        }
    } catch (\Throwable $ex) { flash('Sorry — that could not be saved. Please try again.'); }
    header('Location: news_manage.php?tab=' . $tab); exit;
}

$tab   = in_array($_GET['tab'] ?? '', ['news','events','submissions'], true) ? $_GET['tab'] : 'news';
$POSTS = news_posts(true);
$EVTS  = news_events(true);
$PENDING = comm_pending();
$PN = count($PENDING);

function nm_cat_opts($sel) {
    // plain-English hints so the right category is obvious at a glance
    $hint = ['memory'=>'In Memory (a death / passing)','birth'=>'Birth (a new baby)','marriage'=>'Marriage (a wedding)',
             'graduation'=>'Graduation','reunion'=>'Reunion','news'=>'News (anything else)','prayer'=>'Prayer',
             'anniversary'=>'Anniversary','military'=>'Service / Military'];
    $o=''; foreach (news_cats() as $k=>$c) $o .= '<option value="'.e($k).'"'.($sel===$k?' selected':'').'>'.e($hint[$k] ?? $c[0]).'</option>';
    return $o;
}
function nm_fit_opts($sel) {
    $o=''; foreach (['cover'=>'Fill the card (may crop the edges)','whole'=>'Show the whole photo (nothing cropped)'] as $v=>$l)
        $o .= '<option value="'.$v.'"'.($sel===$v?' selected':'').'>'.$l.'</option>';
    return $o;
}
function nm_status_opts($sel){ $o=''; foreach (['published'=>'Visible on the page','hidden'=>'Hidden'] as $v=>$l) $o .= '<option value="'.$v.'"'.($sel===$v?' selected':'').'>'.$l.'</option>'; return $o; }

page_head('Manage Family News', ['body_class' => 'em']);
?>
<h1>Manage Family News</h1>
<p class="lede">Add, edit, or remove the news announcements and upcoming events shown on the Family News page.
  Announcements run newest first by their date. Events drop off on their own once the day has passed.</p>
<p class="lede nm-warn">Anything marked &ldquo;Example&rdquo; is made-up filler that came with the site &mdash; invented
  names, invented dates. Editing one only removes the little tag; the words stay. Set it to
  <b>Hidden</b>, or delete it, rather than leaving it up for the family to read.</p>
<p style="margin:10px 0 4px"><a class="btn gold" href="news.php" target="_blank" rel="noopener">View the Family News page &#8599;</a></p>

<div class="em-tabs">
  <a href="?tab=news" class="<?= $tab==='news'?'on':'' ?>">Announcements (<?= count($POSTS) ?>)</a>
  <a href="?tab=events" class="<?= $tab==='events'?'on':'' ?>">Upcoming Events (<?= count($EVTS) ?>)</a>
  <a href="?tab=submissions" class="<?= $tab==='submissions'?'on':'' ?>">Family Submissions<?= $PN ? ' <span class="em-penddot">'.$PN.'</span>' : ' (0)' ?></a>
</div>

<?php if ($tab === 'news'): ?>
  <div class="panel em-add">
    <h2>Add an announcement</h2>
    <form method="post" enctype="multipart/form-data" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <div class="em-grid">
        <div><label>Title *</label><input type="text" name="title" required placeholder="e.g. Congratulations to Sydney Battles!"></div>
        <div><label>Category</label><select name="category"><?= nm_cat_opts('news') ?></select></div>
        <div><label>Date (as shown)</label><input type="text" name="date_label" placeholder="e.g. May 15, 2024"></div>
        <div><label>Photo (JPG/PNG, up to 12 MB)</label><input type="file" name="photo" accept="image/*"></div>
      </div>
      <label>Details</label>
      <textarea name="body" class="nm-count" placeholder="A sentence or two about the news. Write as much as you like — long tributes are welcome."></textarea>
      <p class="nm-hint">Write as much as you want. The card on the Family News page shows the first ~190 characters and a
        &ldquo;Read the full story&rdquo; link &mdash; the whole thing appears on the announcement&rsquo;s own page. Tall photos
        (memorial cards, phone portraits) are shown whole automatically, never cropped.</p>
      <button class="btn gold" name="action" value="post_save" style="margin-top:12px">Add announcement</button>
    </form>
  </div>
  <?php foreach ($POSTS as $p): ?>
    <div class="panel em-row">
      <form method="post" enctype="multipart/form-data" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <div class="em-rowhead">
          <h3><?= e($p['title']) ?><?= $p['sample']?' <span class="em-tag">Example</span>':'' ?><?= $p['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="post_delete" onclick="return confirm('Remove this announcement?')">Delete</button>
        </div>
        <div class="em-media">
          <div class="em-thumb"<?= $p['photo'] ? ' style="background-image:url(\''.e($p['photo']).'\');background-size:'.(($p['photo_fit'] ?? 'cover')==='whole'?'contain':'cover').';background-repeat:no-repeat"' : '' ?>><?= $p['photo']?'':'No photo' ?></div>
          <div class="em-mediactl">
            <label>Replace photo</label><input type="file" name="photo" accept="image/*">
            <label style="margin-top:8px">How the photo shows</label>
            <input type="hidden" name="photo_fit_prev" value="<?= e($p['photo_fit'] ?? 'cover') ?>">
            <select name="photo_fit"><?= nm_fit_opts($p['photo_fit'] ?? 'cover') ?></select>
            <?php if ($p['photo']): ?><label class="em-check"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?>
            <?php if ($p['photo']): ?><a class="nm-hint" href="<?= e($p['photo']) ?>" target="_blank" rel="noopener">Open the photo full size &#8599;</a><?php endif; ?>
          </div>
        </div>
        <div class="em-grid">
          <div><label>Title *</label><input type="text" name="title" required value="<?= e($p['title']) ?>"></div>
          <div><label>Category</label><select name="category"><?= nm_cat_opts($p['category']) ?></select></div>
          <div><label>Date (as shown)</label><input type="text" name="date_label" value="<?= e($p['date_label']) ?>"></div>
          <div><label>Pin to top (0 = off)</label><input type="number" name="sort" min="0" value="<?= (int)$p['sort'] ?>"></div>
          <div><label>Likes</label><input type="number" name="likes" value="<?= (int)$p['likes'] ?>"></div>
          <div><label>Comments</label><input type="number" name="comments" value="<?= (int)$p['comments'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= nm_status_opts($p['status']) ?></select></div>
        </div>
        <label>Details</label>
        <textarea name="body" class="nm-count"><?= e($p['body']) ?></textarea>
        <div class="nm-row">
          <button class="btn gold" name="action" value="post_save" style="margin-top:12px">Save changes</button>
          <a class="btn" href="news_view.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener" style="margin-top:12px">See this announcement &#8599;</a>
        </div>
      </form>
    </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'events'): ?>
  <div class="panel em-add">
    <h2>Add an event</h2>
    <form method="post" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <div class="em-grid">
        <div><label>Title *</label><input type="text" name="title" required placeholder="e.g. Family Reunion <?= date('Y') ?>"></div>
        <div><label>Place</label><input type="text" name="place" placeholder="e.g. Tyler Rose Garden Center, Tyler, TX"></div>
        <div><label>Date *</label><input type="date" name="on_date" required></div>
        <div><label>Time</label><input type="text" name="time_label" placeholder="e.g. 10:00 AM – 4:00 PM"></div>
      </div>
      <label class="em-check"><input type="checkbox" name="annual" value="1"> Happens every year (the reunion, a holiday, a yearly deadline)</label>
      <p class="nm-hint">The date needs the year. Once the day has passed the event
        comes off the Family News page on its own &mdash; nothing for you to tidy up.
        Tick &ldquo;every year&rdquo; and instead of disappearing it rolls forward to next year by itself.</p>
      <button class="btn gold" name="action" value="event_save" style="margin-top:12px">Add event</button>
    </form>
  </div>
  <?php foreach ($EVTS as $ev): ?>
    <div class="panel em-row">
      <form method="post" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
        <?php
          $evOn   = !empty($ev['on_date']) ? $ev['on_date'] : '';
          // an annual date in the past is not stale — it has rolled to next year
          $evPast = $evOn && $evOn < date('Y-m-d') && empty($ev['annual']);
          $evNext = !empty($ev['annual']) ? news_event_next($ev) : null;
        ?>
        <div class="em-rowhead">
          <h3><?= e($ev['mon']) ?> <?= e($ev['day']) ?> &middot; <?= e($ev['title']) ?><?= $ev['sample']?' <span class="em-tag">Example</span>':'' ?><?= $ev['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="event_delete" onclick="return confirm('Remove this event?')">Delete</button>
        </div>
        <?php if (!$evOn): ?>
          <p class="nm-warn">This event has no date, so it is not showing under Upcoming Events.
            Give it a date (with the year) and it will appear.</p>
        <?php elseif ($evNext): ?>
          <p class="nm-hint">Happens every year &mdash; next on
            <b><?= e(date('j F Y', strtotime($evNext))) ?></b>.</p>
        <?php elseif ($evPast): ?>
          <p class="nm-warn">This one has been and gone (<?= e(date('j F Y', strtotime($evOn))) ?>), so it has
            come off the Family News page. Change the date to run it again, or delete it.</p>
        <?php endif; ?>
        <div class="em-grid">
          <div><label>Title *</label><input type="text" name="title" required value="<?= e($ev['title']) ?>"></div>
          <div><label>Place</label><input type="text" name="place" value="<?= e($ev['place']) ?>"></div>
          <div><label>Date *</label><input type="date" name="on_date" value="<?= e($evOn) ?>"></div>
          <div><label>Time</label><input type="text" name="time_label" value="<?= e($ev['time_label']) ?>"></div>
          <div><label>Visibility</label><select name="status"><?= nm_status_opts($ev['status']) ?></select></div>
        </div>
        <label class="em-check"><input type="checkbox" name="annual" value="1"<?= !empty($ev['annual']) ? ' checked' : '' ?>> Happens every year</label>
        <button class="btn gold" name="action" value="event_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php else: /* Family Submissions */ ?>
  <div class="panel em-add">
    <h2>Family submissions awaiting your review</h2>
    <p class="lede" style="margin:0">Questions, recipes, updates, and answers submitted by family members. Approve to publish, or decline. Nothing is visible to others until you approve it.</p>
  </div>
  <?php if (!$PENDING): ?>
    <div class="panel"><p class="lede" style="margin:0">Nothing waiting. When a family member submits a question, recipe, update, or answer, it will appear here.</p></div>
  <?php else: $KN = ['question'=>'Question','recipe'=>'Recipe','update'=>'Update','healthtip'=>'Health Tip','answer'=>'Answer']; foreach ($PENDING as $s): ?>
    <div class="panel em-row">
      <div class="em-rowhead">
        <h3><span class="em-tag feat"><?= e($KN[$s['kind']] ?? $s['kind']) ?></span>
          <?php if ($s['kind']==='recipe'): ?><?= e($s['title']) ?><?php elseif ($s['kind']==='answer'): $q=comm_one($s['parent_id']); ?>Answer<?= $q ? ' to: '.e(mb_strimwidth($q['body'],0,60,'…')) : '' ?><?php else: ?><?= e(mb_strimwidth($s['body'],0,70,'…')) ?><?php endif; ?>
        </h3>
        <span class="em-by">From <?= e($s['author']) ?> &middot; <?= e(comm_ago($s['created_at'])) ?></span>
      </div>
      <?php if ($s['photo']): ?><div class="em-thumb" style="background-image:url('<?= e($s['photo']) ?>');margin-bottom:8px"></div><?php endif; ?>
      <?php if ($s['body']): ?><p class="fpr-body"><?= nl2br(e($s['body'])) ?></p><?php endif; ?>
      <div class="em-pendbtns">
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn gold" name="action" value="comm_approve">&#10003; Approve &amp; publish</button></form>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn" name="action" value="comm_decline">Decline</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this submission permanently?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn danger" name="action" value="comm_delete">Delete</button></form>
      </div>
    </div>
  <?php endforeach; endif; ?>
<?php endif; ?>

<script>
/* live length note under each Details box — reassures that long entries are fine */
document.querySelectorAll('textarea.nm-count').forEach(function(t){
  var n=document.createElement('div'); n.className='nm-count-note';
  t.parentNode.insertBefore(n, t.nextSibling);
  function upd(){
    var len=t.value.trim().length;
    n.textContent = len===0 ? '' :
      len<=190 ? len+' characters — the whole message fits on the card.'
               : len+' characters — the card shows the first 190 with a “Read the full story” link.';
  }
  t.addEventListener('input',upd); upd();
});
</script>

<?php page_foot();
