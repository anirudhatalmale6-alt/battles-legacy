<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';
require_login();               // any signed-in family member may submit
ent_migrate();

$u   = current_user();
$who = trim($u['name'] ?? '') ?: trim($u['email'] ?? '') ?: 'Family member';

/** trim to at most $max words (local backstop for the description cap) */
function esub_cap($s, $max = 120) {
    $s = trim($s);
    if ($s === '') return '';
    $w = preg_split('/\s+/', $s);
    return count($w) <= $max ? $s : implode(' ', array_slice($w, 0, $max));
}

$err  = '';
$type = $_POST['type'] ?? 'business';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if ($type === 'business') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') { $err = 'Please enter the business or profession name.'; }
            else {
                list($photo, $perr) = ent_store_photo('photo');
                if ($perr) { $err = $perr; }
                else {
                    $cat_type = ($_POST['cat_type'] ?? 'Business') === 'Profession' ? 'Profession' : 'Business';
                    $blurb    = esub_cap($_POST['blurb'] ?? '', 120);
                    q("INSERT INTO enterprise_businesses (name,owner,category,cat_type,location,blurb,link,phone,email,photo,sample,sort,status,submitted_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?,0,0,'pending',?)",
                      [$name, trim($_POST['owner']??''), trim($_POST['category']??''), $cat_type, trim($_POST['location']??''),
                       $blurb, trim($_POST['link']??''), trim($_POST['phone']??''), trim($_POST['email']??''), $photo, $who]);
                }
            }
        } elseif ($type === 'video') {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { $err = 'Please give the video a title.'; }
            else {
                q("INSERT INTO enterprise_videos (title,description,url,duration,featured,sample,sort,status,submitted_by)
                   VALUES (?,?,?,?,0,0,0,'pending',?)",
                  [$title, trim($_POST['description']??''), trim($_POST['url']??''), trim($_POST['duration']??''), $who]);
            }
        } elseif ($type === 'resource') {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { $err = 'Please give the resource a title.'; }
            else {
                $icon = array_key_exists($_POST['icon'] ?? '', ent_fin_icons()) ? $_POST['icon'] : 'seed';
                q("INSERT INTO enterprise_finance (icon,title,tips,link,sample,sort,status,submitted_by)
                   VALUES (?,?,?,?,0,0,'pending',?)",
                  [$icon, $title, trim($_POST['tips']??''), trim($_POST['link']??''), $who]);
            }
        } elseif ($type === 'saying') {
            $quote = trim($_POST['quote'] ?? '');
            if ($quote === '') { $err = 'Please enter the saying or quote.'; }
            else {
                q("INSERT INTO enterprise_sayings (quote,author,sample,sort,status,submitted_by)
                   VALUES (?,?,0,0,'pending',?)",
                  [$quote, trim($_POST['author']??''), $who]);
            }
        } else {
            $err = 'Please choose what you would like to add.';
        }
    } catch (Exception $ex) {
        $err = 'Sorry — that could not be submitted. If one field is very long, try shortening it and submit again.';
    }
    if ($err === '') {
        flash('Thank you! Your submission has been sent to William for review. Once approved, it will appear on the Enterprise page.');
        header('Location: enterprise_submit.php?done=1'); exit;
    }
}

$done = ($_GET['done'] ?? '') === '1';
/* this member's own submissions still awaiting review */
$mine = [];
foreach (ent_pending_all() as $p) { if (($p['submitted_by'] ?? '') === $who) $mine[] = $p; }
function esub_old($k) { return isset($_POST[$k]) ? e($_POST[$k]) : ''; }

page_head('Submit to the Enterprise Page', ['body_class' => 'em']);
?>
<h1>Submit to the Enterprise page</h1>
<p class="lede">Share your family business, a video, a helpful financial resource, or a saying. Your submission goes to
   William for review &mdash; once he approves it, it appears on the Enterprise page for the whole family to see.</p>
<p style="margin:10px 0 14px"><a class="btn" href="enterprise.php">&larr; Back to the Enterprise page</a></p>

<?php if ($done): ?>
  <div class="panel" style="border-left:4px solid var(--gold)">
    <h2 style="margin-top:0">Thank you &mdash; it&rsquo;s on its way!</h2>
    <p>Your submission has been sent for review. You&rsquo;ll see it on the Enterprise page once it&rsquo;s approved.
       Want to add something else? Just fill in the form below.</p>
  </div>
<?php endif; ?>

<?php if ($err): ?><div class="panel" style="border-left:4px solid #b3452f;color:#7a2e1f"><b>Please check this:</b> <?= e($err) ?></div><?php endif; ?>

<div class="panel em-add">
  <form method="post" enctype="multipart/form-data" class="em-form" id="subform">
    <?= csrf_field() ?>
    <label>What would you like to add?</label>
    <select name="type" id="subtype">
      <option value="business"<?= $type==='business'?' selected':'' ?>>A family business or profession</option>
      <option value="video"<?= $type==='video'?' selected':'' ?>>A video</option>
      <option value="resource"<?= $type==='resource'?' selected':'' ?>>A financial resource / guidance card</option>
      <option value="saying"<?= $type==='saying'?' selected':'' ?>>A saying or quote</option>
    </select>

    <!-- BUSINESS -->
    <div class="sub-group" data-for="business">
      <div class="em-grid">
        <div><label>Business / profession name *</label><input type="text" name="name" value="<?= esub_old('name') ?>" placeholder="e.g. Holmes Airport Transportation"></div>
        <div><label>Owner / family member</label><input type="text" name="owner" value="<?= esub_old('owner') ?>" placeholder="e.g. Bill Holmes"></div>
        <div><label>Category / profession</label><input type="text" name="category" value="<?= esub_old('category') ?>" placeholder="e.g. Airport Transportation"></div>
        <div><label>Type</label><select name="cat_type"><option>Business</option><option<?= ($_POST['cat_type']??'')==='Profession'?' selected':'' ?>>Profession</option></select></div>
        <div><label>Location</label><input type="text" name="location" value="<?= esub_old('location') ?>" placeholder="e.g. Dallas, TX"></div>
        <div><label>Website (optional)</label><input type="text" name="link" value="<?= esub_old('link') ?>" placeholder="https://..."></div>
        <div><label>Phone (optional)</label><input type="text" name="phone" value="<?= esub_old('phone') ?>"></div>
        <div><label>Email (optional)</label><input type="text" name="email" value="<?= esub_old('email') ?>"></div>
      </div>
      <label>Short description <span class="lbl-hint">(up to 120 words)</span></label>
      <textarea name="blurb" data-wc placeholder="A sentence or two about the business."><?= esub_old('blurb') ?></textarea>
      <div class="em-wc"><b>0</b> / 120 words</div>
      <label>Photo / logo (optional &mdash; JPG/PNG, up to 12 MB)</label>
      <input type="file" name="photo" accept="image/*">
    </div>

    <!-- VIDEO -->
    <div class="sub-group" data-for="video" style="display:none">
      <div class="em-grid">
        <div><label>Video title *</label><input type="text" name="title" value="<?= esub_old('title') ?>" placeholder="e.g. 2025 Family Reunion"></div>
        <div><label>Length (optional)</label><input type="text" name="duration" value="<?= esub_old('duration') ?>" placeholder="e.g. 4:18"></div>
      </div>
      <label>Video link (YouTube or Vimeo)</label>
      <input type="text" name="url" value="<?= esub_old('url') ?>" placeholder="https://youtube.com/watch?v=...">
      <label>Short description</label>
      <textarea name="description" placeholder="What is this video about?"><?= esub_old('description') ?></textarea>
    </div>

    <!-- RESOURCE -->
    <div class="sub-group" data-for="resource" style="display:none">
      <div class="em-grid">
        <div><label>Resource title *</label><input type="text" name="title_r" placeholder="e.g. First-Time Homebuyer Tips"></div>
        <div><label>Icon</label><select name="icon"><?php foreach (ent_fin_icons() as $k=>$lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></div>
      </div>
      <label>Tips <span class="lbl-hint">(one per line)</span></label>
      <textarea name="tips" placeholder="Save for a down payment&#10;Check your credit score&#10;Compare lenders"><?= esub_old('tips') ?></textarea>
      <label>&ldquo;Learn More&rdquo; link (optional)</label>
      <input type="text" name="link_r" placeholder="https://...">
    </div>

    <!-- SAYING -->
    <div class="sub-group" data-for="saying" style="display:none">
      <label>Saying / quote *</label>
      <textarea name="quote" placeholder="e.g. Hard work and faith carry a family further than any inheritance."><?= esub_old('quote') ?></textarea>
      <label>Who said it (optional)</label>
      <input type="text" name="author" value="<?= esub_old('author') ?>" placeholder="e.g. Booker T. Washington">
    </div>

    <button class="btn gold" type="submit" style="margin-top:14px">Submit for review</button>
  </form>
</div>

<?php if ($mine): ?>
  <div class="panel">
    <h2 style="margin-top:0">Your submissions awaiting review (<?= count($mine) ?>)</h2>
    <ul class="sub-mine">
      <?php foreach ($mine as $m):
        $lbl = ['biz'=>'Business','vid'=>'Video','fin'=>'Resource','say'=>'Saying'][$m['_type']] ?? 'Entry';
        $ttl = $m['name'] ?? $m['title'] ?? $m['quote'] ?? '';
      ?>
        <li><span class="sub-kind"><?= e($lbl) ?></span> <?= e(mb_strimwidth($ttl, 0, 70, '…')) ?> <span class="sub-wait">&middot; awaiting review</span></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<script>
(function(){
  var sel = document.getElementById('subtype');
  var groups = document.querySelectorAll('#subform .sub-group');
  function sync(){
    groups.forEach(function(g){
      var on = g.getAttribute('data-for') === sel.value;
      g.style.display = on ? '' : 'none';
      // only the active group's fields should submit their required-ness via JS check
      g.querySelectorAll('input,textarea,select').forEach(function(f){ f.disabled = !on; });
    });
  }
  sel.addEventListener('change', sync); sync();

  // resource fields reuse different names to avoid clashing with video's title/link;
  // remap them to the names the server expects just before submit
  document.getElementById('subform').addEventListener('submit', function(){
    if (sel.value === 'resource'){
      var t = document.querySelector('[name=title_r]'); if (t) t.setAttribute('name','title');
      var l = document.querySelector('[name=link_r]');  if (l) l.setAttribute('name','link');
    }
  });

  // live word counter on the description
  var MAX = 120;
  document.querySelectorAll('textarea[data-wc]').forEach(function(ta){
    var wc = ta.parentNode.querySelector('.em-wc'); if(!wc) return;
    var num = wc.querySelector('b');
    function upd(){ var v=ta.value.trim(); var n=v?v.split(/\s+/).length:0; if(num)num.textContent=n; wc.classList.toggle('over', n>MAX); }
    ta.addEventListener('input', upd); upd();
  });
})();
</script>

<?php page_foot();
