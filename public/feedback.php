<?php
/** Share Your Thoughts — where everyone reviewing the site leaves an opinion,
 *  a suggestion, or tells us something is broken. No login needed: William is
 *  inviting people who don't have one yet. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/feedback_data.php';

try { feedback_migrate(); } catch (\Throwable $e) {}

$u       = current_user();
$isAdmin = role_at_least('admin');
$err     = '';
$sent    = isset($_GET['thanks']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'agree') {
        fb_agree((int)($_POST['id'] ?? 0));
        header('Location: feedback.php#saying'); exit;
    }
    if (!empty($_POST['website'])) { header('Location: feedback.php?thanks=1'); exit; } // honeypot
    $body = trim($_POST['body'] ?? '');
    $name = trim($_POST['name'] ?? '') ?: ($u['name'] ?? '');
    if ($name === '')            $err = 'Please tell us your name so William knows who to thank.';
    elseif (mb_strlen($body) < 5) $err = 'Please write a little more so we know what you mean.';
    elseif (fb_too_fast())        $err = 'That is a lot of notes at once — please give it a few minutes and send the next one.';
    else {
        try {
            fb_add(['name'=>$name, 'contact'=>$_POST['contact'] ?? '', 'area'=>$_POST['area'] ?? 'overall',
                    'kind'=>$_POST['kind'] ?? 'suggestion', 'rating'=>$_POST['rating'] ?? 0, 'body'=>$body], $u);
            fb_mark_sent();
            header('Location: feedback.php?thanks=1'); exit;
        } catch (\Throwable $ex) { $err = 'Sorry — that could not be saved. Please try once more.'; }
    }
}

$AREA  = fb_area_ok($_GET['area'] ?? '') ? $_GET['area'] : fb_area_from_referer();
$WALL  = [];
try { $WALL = fb_shared(12); } catch (\Throwable $e) {}
list($AVG, $RATED) = fb_avg_rating();
$NEW = $isAdmin ? fb_new_count() : 0;

page_head('Share Your Thoughts', ['body_class' => 'home fbpage']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="feedback_manage.php">&#128172; Read what people sent<?= $NEW ? ' ('.$NEW.' new)' : '' ?></a>
  </div>
<?php endif; ?>

<section class="fb-hero">
  <h1>Share Your Thoughts</h1>
  <p class="script fb-motto">This is everyone&rsquo;s project.</p>
  <p>Look around the site and tell us what you think &mdash; what you like, what you&rsquo;d change, what&rsquo;s missing,
     or anything that doesn&rsquo;t work right. Every note goes straight to William. You don&rsquo;t need an account.</p>
  <?php if ($RATED >= 3): ?>
    <div class="fb-avg"><?= fb_stars(round($AVG)) ?> <b><?= e(number_format($AVG, 1)) ?></b> out of 5 &middot; <?= (int)$RATED ?> family members have rated the site</div>
  <?php endif; ?>
</section>

<div class="fb-wrap">

<?php if ($sent): ?>
  <div class="panel fb-thanks">
    <h2>Thank you &mdash; that came through.</h2>
    <p>William reads every one of these. If you think of something else, just come back to this page and send another.</p>
    <p style="margin-top:14px"><a class="btn gold" href="feedback.php">Send another thought</a>
       <a class="btn2" href="index.php">Back to the site</a></p>
  </div>
<?php else: ?>

  <?php if ($err): ?><div class="panel fb-err"><b>Please check this:</b> <?= e($err) ?></div><?php endif; ?>

  <div class="panel fb-form">
    <form method="post">
      <?= csrf_field() ?>
      <input type="text" name="website" class="fp-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="fb-q">1. What kind of thought is this?</div>
      <div class="fb-kinds">
        <?php $ki = 0; foreach (fb_kinds() as $k => $v): $ki++; ?>
          <label class="fb-kind">
            <input type="radio" name="kind" value="<?= e($k) ?>"<?= $ki === 1 ? ' checked' : '' ?>>
            <span><?= fb_icon($v[1], 22) ?><b><?= e($v[0]) ?></b></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="fb-q">2. Which part of the site?</div>
      <select name="area" class="fb-area">
        <?php foreach (fb_areas() as $k => $label): ?>
          <option value="<?= e($k) ?>"<?= $k === $AREA ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="fb-q">3. Tell us in your own words <span class="fb-req">*</span></div>
      <textarea name="body" required maxlength="4000" placeholder="For example: I&rsquo;d love to see more pictures of the Alabama side of the family. Or: the tree is hard to read on my phone."></textarea>
      <div class="fb-count"><span id="fbn">0</span> characters &mdash; write as much as you like.</div>

      <div class="fb-q">4. How would you rate the site so far? <span class="fb-opt">(optional)</span></div>
      <div class="fb-rate" id="fbrate">
        <input type="hidden" name="rating" id="fbrating" value="0">
        <?php for ($i = 1; $i <= 5; $i++): ?><button type="button" class="fb-star" data-v="<?= $i ?>" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">&#9733;</button><?php endfor; ?>
        <span class="fb-ratelbl" id="fbratelbl">Not rated</span>
      </div>

      <div class="fb-two">
        <div>
          <div class="fb-q">5. Your name <span class="fb-req">*</span></div>
          <input type="text" name="name" required maxlength="120" value="<?= e($u['name'] ?? '') ?>" placeholder="e.g. Carolyn Battles">
        </div>
        <div>
          <div class="fb-q">6. Best way to reach you <span class="fb-opt">(optional)</span></div>
          <input type="text" name="contact" maxlength="160" placeholder="Phone number or email &mdash; only if you'd like a reply">
        </div>
      </div>

      <button class="btn gold fb-send" type="submit">Send my thoughts to William</button>
      <p class="fb-private">&#128274; Only William sees your note. Nothing appears on the site unless he chooses to share it below.</p>
    </form>
  </div>
<?php endif; ?>

<section class="fb-saying" id="saying">
  <h2><?= fb_icon('chat', 24) ?> What the family is saying</h2>
  <?php if ($WALL): ?>
    <p class="fb-sub">A few of the thoughts William has chosen to share. Agree with one? Tap &ldquo;I think so too.&rdquo;</p>
    <div class="fb-wall">
      <?php foreach ($WALL as $w): ?>
        <article class="fb-note">
          <div class="fb-nhead">
            <span class="fb-av"><?= fb_initials($w['name']) ?></span>
            <div><b><?= e($w['name'] ?: 'Family member') ?></b>
                 <span class="fb-when"><?= e(fb_area_label($w['area'])) ?> &middot; <?= e(fb_ago($w['created_at'])) ?></span></div>
            <?= fb_stars($w['rating']) ?>
          </div>
          <p><?= nl2br(e($w['body'])) ?></p>
          <?php if (trim((string)$w['reply']) !== ''): ?>
            <div class="fb-reply"><b>William:</b> <?= nl2br(e($w['reply'])) ?></div>
          <?php endif; ?>
          <form method="post" class="fb-agreeform">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="agree"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
            <button class="fb-agree<?= fb_agreed($w['id']) ? ' did' : '' ?>"<?= fb_agreed($w['id']) ? ' disabled' : '' ?>>
              &#128077; <?= fb_agreed($w['id']) ? 'You agree' : 'I think so too' ?><?= (int)$w['agrees'] ? ' <i>'.(int)$w['agrees'].'</i>' : '' ?>
            </button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="fb-sub">Nothing shared here yet &mdash; be the first to send a thought using the form above.</p>
  <?php endif; ?>
</section>

</div>

<script>
(function(){
  var t=document.querySelector('.fb-form textarea'), n=document.getElementById('fbn');
  if(t&&n){t.addEventListener('input',function(){n.textContent=t.value.length;});}
  var box=document.getElementById('fbrate');
  if(box){
    var hid=document.getElementById('fbrating'), lbl=document.getElementById('fbratelbl'),
        stars=box.querySelectorAll('.fb-star'),
        words=['Not rated','Needs work','Getting there','Good','Really good','Love it'];
    function paint(v){stars.forEach(function(s){s.classList.toggle('on',+s.dataset.v<=v);});lbl.textContent=words[v]||words[0];}
    stars.forEach(function(s){
      s.addEventListener('click',function(){hid.value=s.dataset.v;paint(+s.dataset.v);});
      s.addEventListener('mouseenter',function(){paint(+s.dataset.v);});
    });
    box.addEventListener('mouseleave',function(){paint(+hid.value);});
  }
})();
</script>

<?php legacy_footer(); page_foot();
