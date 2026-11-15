<?php
/** "Together Again" - the reunion slideshow, for William to post.
 *
 *  Unlike the other video pages this one is for SIGNED-IN FAMILY ONLY: the
 *  photographs are of living relatives, and the site shows living relatives to
 *  members and nobody else. The video and its poster come through media.php,
 *  which checks the login on every request - so the file cannot be fetched by
 *  anyone who simply knows its address. */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$F     = 'together-again.mp4';
$abs   = dirname(__DIR__) . '/private_media/' . $F;
$have  = is_file($abs);
$bytes = $have ? filesize($abs) : 0;
$ver   = $have ? '&v=' . filemtime($abs) : '';
$SRC   = 'media.php?f=' . $F . $ver;
$POSTER = 'media.php?f=together-again.jpg' . $ver;
$SECS  = 197;

$POST = "Together again.\n\n"
      . "A few memories from our family reunion. So good to see everybody in one room - "
      . "the laughing, the hugging, the plates of food, and all the pictures being taken.\n\n"
      . "Thank you to everyone who came, and to everyone who made it happen. "
      . "Until we meet again.";

page_head('Together Again', ['body_class' => 'fvid']);
?>
<section class="fvid-hero">
  <h1>Together Again</h1>
  <p>Your reunion photographs and your song. 3 minutes 17 seconds.</p>
</section>

<div class="wrap fvid-wrap">
  <?php if (!$have): ?>
    <div class="panel"><p class="muted">The video isn&rsquo;t on the server yet.</p></div>
  <?php else: ?>
    <div class="fvid-player">
      <video controls muted playsinline preload="metadata" poster="<?= e($POSTER) ?>">
        <source src="<?= e($SRC) ?>" type="video/mp4">
        Your browser can&rsquo;t play video &mdash; use the download button below.
      </video>
    </div>

    <div class="fvid-actions">
      <a class="btn gold" href="<?= e($SRC . '&dl=1') ?>">&#11015; Download the video</a>
      <span class="fvid-size">3 min 17 s &middot; <?= max(1, (int)round($bytes / 1048576)) ?> MB &middot; square, for the feed</span>
    </div>

    <div class="panel fvid-how">
      <h2>Putting it on Facebook</h2>
      <ol>
        <li><b>Download it first.</b> Press the gold button above. On a phone it may ask where to save
          it &mdash; Photos or Downloads is fine.</li>
        <li><b>Start a new post</b> on your page or in the family group, the way you would for a photograph.</li>
        <li><b>Attach the video</b>, paste the words below if you want them, and post.</li>
      </ol>

      <h3 class="fvid-h3">Something to write with it</h3>
      <p class="muted" style="margin:0 0 10px">Yours to change.</p>
      <div class="bvid-post">
        <textarea id="bvid-text" readonly rows="8"><?= e($POST) ?></textarea>
        <button type="button" class="btn2" id="bvid-copy">Copy these words</button>
      </div>

      <p class="muted" style="margin-top:14px">This page is only for family who are signed in, because
        the photographs are of living relatives &mdash; the same rule as the rest of the site. Once you post
        the video on Facebook, Facebook&rsquo;s own privacy settings decide who sees it there.</p>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var b = document.getElementById('bvid-copy'), t = document.getElementById('bvid-text');
  if (!b || !t) return;
  b.addEventListener('click', function () {
    var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy these words'; }, 2200); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(t.value).then(done, function () { t.select(); document.execCommand('copy'); done(); });
    } else {
      t.select(); t.setSelectionRange(0, 99999);
      try { document.execCommand('copy'); done(); } catch (e) { b.textContent = 'Select it and copy'; }
    }
  });
})();
</script>
<script src="assets/vidsound.js?v=<?= @filemtime(__DIR__ . '/assets/vidsound.js') ?: 1 ?>"></script>

<?php legacy_footer();
page_foot();
