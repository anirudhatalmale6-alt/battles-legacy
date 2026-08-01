<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';
require_role('admin');
ent_migrate();

$UPLOAD_REL = 'assets/enterprise/uploads';

/** Save an uploaded business photo; returns [relPath, errorString]. Keeps $existing if no new file. */
function ent_save_photo($existing) {
    global $UPLOAD_REL;
    if (empty($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) return [$existing, ''];
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) return [$existing, 'The photo could not be uploaded — please try again.'];
    $tmp = $_FILES['photo']['tmp_name'];
    $info = @getimagesize($tmp);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!$info || !isset($allowed[$info['mime']])) return [$existing, 'That file is not a photo (JPG, PNG, GIF or WEBP only).'];
    if ($_FILES['photo']['size'] > 12 * 1024 * 1024) return [$existing, 'That image is larger than 12 MB — please pick a smaller one.'];
    $ext = $allowed[$info['mime']];
    $fname = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $absDir = __DIR__ . '/' . $UPLOAD_REL;
    @mkdir($absDir, 0775, true);
    if (!move_uploaded_file($tmp, $absDir . '/' . $fname)) return [$existing, 'Sorry — the photo could not be saved.'];
    return [$UPLOAD_REL . '/' . $fname, ''];
}

function ent_next_sort($table) {
    $r = one("SELECT MAX(sort) m FROM $table");
    return ($r && $r['m'] !== null) ? ((int)$r['m'] + 1) : 0;
}

/** Trim text to at most $max words (server-side backstop for the word cap). */
function ent_cap_words($s, $max = 120) {
    $s = trim($s);
    if ($s === '') return '';
    $words = preg_split('/\s+/', $s);
    if (count($words) <= $max) return $s;
    return implode(' ', array_slice($words, 0, $max));
}
const ENT_BLURB_MAXWORDS = 120;

$tab = 'businesses';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if (strpos($act, 'vid') === 0) $tab = 'videos';
    elseif (strpos($act, 'say') === 0) $tab = 'sayings';
    else $tab = 'businesses';
    $id = (int)($_POST['id'] ?? 0);

    /* ---------- BUSINESSES ---------- */
    try {
    if ($act === 'biz_save') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { flash('Please fill in the Name field — it is required to save a business.'); }
        else {
            $cur = $id ? one("SELECT photo FROM enterprise_businesses WHERE id=?", [$id]) : null;
            list($photo, $perr) = ent_save_photo($cur['photo'] ?? '');
            if (!empty($_POST['remove_photo'])) $photo = '';
            if ($perr) flash($perr);
            $cat_type = ($_POST['cat_type'] ?? 'Business') === 'Profession' ? 'Profession' : 'Business';
            $status   = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
            $blurb    = ent_cap_words($_POST['blurb'] ?? '', ENT_BLURB_MAXWORDS);
            $f = [
              trim($_POST['name'] ?? ''), trim($_POST['owner'] ?? ''), trim($_POST['category'] ?? ''),
              $cat_type, trim($_POST['location'] ?? ''), $blurb,
              trim($_POST['link'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''),
              $photo, (int)($_POST['sort'] ?? 0), $status,
            ];
            if ($id) {
                q("UPDATE enterprise_businesses SET name=?,owner=?,category=?,cat_type=?,location=?,blurb=?,link=?,phone=?,email=?,photo=?,sort=?,status=?,sample=0 WHERE id=?",
                  array_merge($f, [$id]));
                flash('Business updated.');
            } else {
                q("INSERT INTO enterprise_businesses (name,owner,category,cat_type,location,blurb,link,phone,email,photo,sample,sort,status)
                   VALUES (?,?,?,?,?,?,?,?,?,?,0,?,?)",
                  [trim($_POST['name']??''),trim($_POST['owner']??''),trim($_POST['category']??''),$cat_type,
                   trim($_POST['location']??''),$blurb,trim($_POST['link']??''),
                   trim($_POST['phone']??''),trim($_POST['email']??''),$photo,
                   ent_next_sort('enterprise_businesses'),$status]);
                flash('Business added — open the Enterprise page to see it live.');
            }
        }
    } elseif ($act === 'biz_delete' && $id) {
        q("DELETE FROM enterprise_businesses WHERE id=?", [$id]); flash('Business removed.');

    /* ---------- VIDEOS ---------- */
    } elseif ($act === 'vid_save') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { flash('A video needs a title.'); }
        else {
            $featured = !empty($_POST['featured']) ? 1 : 0;
            $status   = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
            if ($featured) q("UPDATE enterprise_videos SET featured=0"); // only one featured
            if ($id) {
                q("UPDATE enterprise_videos SET title=?,description=?,url=?,duration=?,featured=?,sort=?,status=?,sample=0 WHERE id=?",
                  [$title, trim($_POST['description']??''), trim($_POST['url']??''), trim($_POST['duration']??''), $featured, (int)($_POST['sort']??0), $status, $id]);
                flash('Video updated.');
            } else {
                q("INSERT INTO enterprise_videos (title,description,url,duration,featured,sample,sort,status) VALUES (?,?,?,?,?,0,?,?)",
                  [$title, trim($_POST['description']??''), trim($_POST['url']??''), trim($_POST['duration']??''), $featured, ent_next_sort('enterprise_videos'), $status]);
                flash('Video added.');
            }
        }
    } elseif ($act === 'vid_delete' && $id) {
        q("DELETE FROM enterprise_videos WHERE id=?", [$id]); flash('Video removed.');

    /* ---------- SAYINGS ---------- */
    } elseif ($act === 'say_save') {
        $quote = trim($_POST['quote'] ?? '');
        if ($quote === '') { flash('A saying needs its text.'); }
        else {
            $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
            if ($id) {
                q("UPDATE enterprise_sayings SET quote=?,author=?,sort=?,status=?,sample=0 WHERE id=?",
                  [$quote, trim($_POST['author']??''), (int)($_POST['sort']??0), $status, $id]);
                flash('Saying updated.');
            } else {
                q("INSERT INTO enterprise_sayings (quote,author,sample,sort,status) VALUES (?,?,0,?,?)",
                  [$quote, trim($_POST['author']??''), ent_next_sort('enterprise_sayings'), $status]);
                flash('Saying added.');
            }
        }
    } elseif ($act === 'say_delete' && $id) {
        q("DELETE FROM enterprise_sayings WHERE id=?", [$id]); flash('Saying removed.');
    }
    } catch (Exception $ex) {
        flash('Sorry — that could not be saved. Please try again; if one field is very long, try shortening it a little.');
    }
    header('Location: enterprise_manage.php?tab=' . $tab); exit;
}

$tab = in_array($_GET['tab'] ?? '', ['businesses','videos','sayings'], true) ? $_GET['tab'] : 'businesses';
$BIZ = ent_businesses(true);
$VIDS = ent_videos(true);
$SAYS = ent_sayings(true);

function em_type_opts($sel) {
    $o = '';
    foreach (['Business','Profession'] as $t) $o .= '<option'.($sel===$t?' selected':'').'>'.$t.'</option>';
    return $o;
}
function em_status_opts($sel) {
    $o = '';
    foreach (['published'=>'Visible on the page','hidden'=>'Hidden'] as $v=>$lbl) $o .= '<option value="'.$v.'"'.($sel===$v?' selected':'').'>'.$lbl.'</option>';
    return $o;
}

page_head('Manage Enterprise', ['body_class' => 'em']);
?>
<h1>Manage the Enterprise page</h1>
<p class="lede">Add, edit, or remove the Family Businesses, Videos and Sayings here. Fill in the form, then click the
   button at the bottom of it (the <b>Name</b> field is required). Entries marked as samples show an "Example" tag until you edit them.</p>
<p style="margin:10px 0 4px"><a class="btn gold" href="enterprise.php" target="_blank" rel="noopener">View the Enterprise page &#8599;</a>
   <span class="muted" style="margin-left:10px">Opens in a new tab. If a change doesn't show, refresh that tab.</span></p>

<div class="em-tabs">
  <a href="?tab=businesses" class="<?= $tab==='businesses'?'on':'' ?>">Businesses (<?= count($BIZ) ?>)</a>
  <a href="?tab=videos" class="<?= $tab==='videos'?'on':'' ?>">Videos (<?= count($VIDS) ?>)</a>
  <a href="?tab=sayings" class="<?= $tab==='sayings'?'on':'' ?>">Sayings (<?= count($SAYS) ?>)</a>
</div>

<?php if ($tab === 'businesses'): ?>
  <div class="panel em-add">
    <h2>Add a business or profession</h2>
    <form method="post" enctype="multipart/form-data" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <div class="em-grid">
        <div><label>Name *</label><input type="text" name="name" required placeholder="e.g. Holmes Airport Transportation"></div>
        <div><label>Owner / family member</label><input type="text" name="owner" placeholder="e.g. Bill Holmes"></div>
        <div><label>Category / profession</label><input type="text" name="category" placeholder="e.g. Airport Transportation"></div>
        <div><label>Type</label><select name="cat_type"><?= em_type_opts('Business') ?></select></div>
        <div><label>Location</label><input type="text" name="location" placeholder="e.g. Dallas, TX"></div>
        <div><label>Website (optional)</label><input type="text" name="link" placeholder="https://..."></div>
        <div><label>Phone (optional)</label><input type="text" name="phone"></div>
        <div><label>Email (optional)</label><input type="text" name="email"></div>
      </div>
      <label>Short description <span class="lbl-hint">(up to 120 words)</span></label>
      <textarea name="blurb" data-wc placeholder="A sentence or two about the business."></textarea>
      <div class="em-wc"><b>0</b> / 120 words</div>
      <label>Photo (optional — JPG/PNG, up to 12 MB)</label>
      <input type="file" name="photo" accept="image/*">
      <button class="btn gold" name="action" value="biz_save" style="margin-top:12px">Add business</button>
    </form>
  </div>

  <?php foreach ($BIZ as $b): ?>
    <div class="panel em-row">
      <form method="post" enctype="multipart/form-data" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <div class="em-rowhead">
          <h3><?= e($b['name']) ?><?= $b['sample'] ? ' <span class="em-tag">Example</span>' : '' ?><?= $b['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="biz_delete" onclick="return confirm('Remove this business?')">Delete</button>
        </div>
        <div class="em-media">
          <div class="em-thumb"<?= $b['photo'] ? ' style="background-image:url(\''.e($b['photo']).'\')"' : '' ?>><?= $b['photo']?'':'No photo' ?></div>
          <div class="em-mediactl">
            <label>Replace photo</label><input type="file" name="photo" accept="image/*">
            <?php if ($b['photo']): ?><label class="em-check"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?>
          </div>
        </div>
        <div class="em-grid">
          <div><label>Name *</label><input type="text" name="name" required value="<?= e($b['name']) ?>"></div>
          <div><label>Owner / family member</label><input type="text" name="owner" value="<?= e($b['owner']) ?>"></div>
          <div><label>Category / profession</label><input type="text" name="category" value="<?= e($b['category']) ?>"></div>
          <div><label>Type</label><select name="cat_type"><?= em_type_opts($b['cat_type']) ?></select></div>
          <div><label>Location</label><input type="text" name="location" value="<?= e($b['location']) ?>"></div>
          <div><label>Website</label><input type="text" name="link" value="<?= e($b['link']) ?>"></div>
          <div><label>Phone</label><input type="text" name="phone" value="<?= e($b['phone']) ?>"></div>
          <div><label>Email</label><input type="text" name="email" value="<?= e($b['email']) ?>"></div>
          <div><label>Order</label><input type="number" name="sort" value="<?= (int)$b['sort'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= em_status_opts($b['status']) ?></select></div>
        </div>
        <label>Short description <span class="lbl-hint">(up to 120 words)</span></label>
        <textarea name="blurb" data-wc><?= e($b['blurb']) ?></textarea>
        <div class="em-wc"><b>0</b> / 120 words</div>
        <button class="btn gold" name="action" value="biz_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'videos'): ?>
  <div class="panel em-add">
    <h2>Add a video</h2>
    <form method="post" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <div class="em-grid">
        <div><label>Title *</label><input type="text" name="title" required placeholder="e.g. 2025 Family Reunion"></div>
        <div><label>Length (optional)</label><input type="text" name="duration" placeholder="e.g. 4:18"></div>
      </div>
      <label>Video link (YouTube or Vimeo)</label>
      <input type="text" name="url" placeholder="https://youtube.com/watch?v=...">
      <label>Short description</label>
      <textarea name="description" placeholder="What is this video about?"></textarea>
      <label class="em-check"><input type="checkbox" name="featured" value="1"> Make this the big featured video</label>
      <button class="btn gold" name="action" value="vid_save" style="margin-top:12px">Add video</button>
    </form>
  </div>

  <?php foreach ($VIDS as $v): ?>
    <div class="panel em-row">
      <form method="post" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
        <div class="em-rowhead">
          <h3><?= e($v['title']) ?><?= $v['featured']?' <span class="em-tag feat">Featured</span>':'' ?><?= $v['sample']?' <span class="em-tag">Example</span>':'' ?><?= $v['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="vid_delete" onclick="return confirm('Remove this video?')">Delete</button>
        </div>
        <div class="em-grid">
          <div><label>Title *</label><input type="text" name="title" required value="<?= e($v['title']) ?>"></div>
          <div><label>Length</label><input type="text" name="duration" value="<?= e($v['duration']) ?>"></div>
          <div><label>Order</label><input type="number" name="sort" value="<?= (int)$v['sort'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= em_status_opts($v['status']) ?></select></div>
        </div>
        <label>Video link (YouTube or Vimeo)</label>
        <input type="text" name="url" value="<?= e($v['url']) ?>" placeholder="https://youtube.com/watch?v=...">
        <label>Short description</label>
        <textarea name="description"><?= e($v['description']) ?></textarea>
        <label class="em-check"><input type="checkbox" name="featured" value="1" <?= $v['featured']?'checked':'' ?>> Featured video</label>
        <button class="btn gold" name="action" value="vid_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php else: /* sayings */ ?>
  <div class="panel em-add">
    <h2>Add a saying</h2>
    <form method="post" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <label>Saying / quote *</label>
      <textarea name="quote" required placeholder="e.g. Hard work and faith carry a family further than any inheritance."></textarea>
      <label>Who said it (optional)</label>
      <input type="text" name="author" placeholder="e.g. Booker T. Washington">
      <button class="btn gold" name="action" value="say_save" style="margin-top:12px">Add saying</button>
    </form>
  </div>

  <?php foreach ($SAYS as $s): ?>
    <div class="panel em-row">
      <form method="post" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <div class="em-rowhead">
          <h3>&ldquo;<?= e(mb_strimwidth($s['quote'],0,60,'…')) ?>&rdquo;<?= $s['sample']?' <span class="em-tag">Example</span>':'' ?><?= $s['status']==='hidden'?' <span class="em-tag hid">Hidden</span>':'' ?></h3>
          <button class="btn danger" name="action" value="say_delete" onclick="return confirm('Remove this saying?')">Delete</button>
        </div>
        <label>Saying / quote *</label>
        <textarea name="quote" required><?= e($s['quote']) ?></textarea>
        <div class="em-grid">
          <div><label>Who said it</label><input type="text" name="author" value="<?= e($s['author']) ?>"></div>
          <div><label>Order</label><input type="number" name="sort" value="<?= (int)$s['sort'] ?>"></div>
          <div><label>Visibility</label><select name="status"><?= em_status_opts($s['status']) ?></select></div>
        </div>
        <button class="btn gold" name="action" value="say_save" style="margin-top:12px">Save changes</button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<script>
(function(){
  var MAX = 120;
  document.querySelectorAll('textarea[data-wc]').forEach(function(ta){
    var wc = (ta.nextElementSibling && ta.nextElementSibling.classList.contains('em-wc'))
      ? ta.nextElementSibling : ta.parentNode.querySelector('.em-wc');
    if (!wc) return;
    var num = wc.querySelector('b');
    var form = ta.closest('form');
    var saveBtn = form ? form.querySelector('button[value=biz_save]') : null;
    function upd(){
      var v = ta.value.trim();
      var n = v ? v.split(/\s+/).length : 0;
      if (num) num.textContent = n;
      var over = n > MAX;
      wc.classList.toggle('over', over);
      if (saveBtn) saveBtn.disabled = over;
    }
    ta.addEventListener('input', upd); upd();
  });
})();
</script>

<?php page_foot();
