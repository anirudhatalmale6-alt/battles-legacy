<?php
/** "We Stand on Their Shoulders" — William's own video, with a get-out-and-vote
 *  ending added.
 *
 *  Same reason as the other video pages: attachments sent through the chat do
 *  not open on his phone, and a link always does. Not linked from the menu; it
 *  is a page he goes to when he wants the file.
 *
 *  The video is his. Only the last six seconds are mine, built in his video's
 *  own typeface and colours (see video/build_wesot_vote.py). It is about voting,
 *  not about anyone to vote for, and - at his request - it sends nobody to a
 *  website: "I just want to say get out and vote." The suggested post below
 *  follows the same rule. */
require __DIR__ . '/../src/bootstrap.php';

$VIDEO = 'assets/video/we-stand.mp4';
$have  = is_file(__DIR__ . '/' . $VIDEO);
$bytes = $have ? filesize(__DIR__ . '/' . $VIDEO) : 0;
/* Stamped with the file's own time. The video was replaced in place on 16 Oct
   (portraits re-cropped) under the same name, and he had already played the
   first version - without this his phone could go on showing him the old one
   and he would see nothing changed. */
$ver   = $have ? '?v=' . filemtime(__DIR__ . '/' . $VIDEO) : '';
$POSTER = 'assets/video/we-stand.jpg';
$pver  = is_file(__DIR__ . '/' . $POSTER) ? '?v=' . filemtime(__DIR__ . '/' . $POSTER) : '';
$SECS  = 66;

$POST = "They fought so we could vote.\n\n"
      . "Frederick Douglass. Booker T. Washington. George Washington Carver. "
      . "Dr. Daniel Hale Williams. Althea Gibson. Thurgood Marshall. Colin Powell. "
      . "Dr. Mae Jemison.\n\n"
      . "We stand on their shoulders. This election matters.\n\n"
      . "Get out and vote. Tuesday, November 3.";

page_head('We Stand on Their Shoulders', ['body_class' => 'fvid']);
?>
<section class="fvid-hero">
  <h1>We Stand on Their Shoulders</h1>
  <p>Your video, with the ending added. <?= (int)$SECS ?> seconds.</p>
</section>

<div class="wrap fvid-wrap">
  <?php if (!$have): ?>
    <div class="panel"><p class="muted">The video isn&rsquo;t on the server yet.</p></div>
  <?php else: ?>
    <div class="fvid-player fvid-tall">
      <video controls muted playsinline preload="metadata" poster="<?= e($POSTER . $pver) ?>">
        <source src="<?= e($VIDEO . $ver) ?>" type="video/mp4">
        Your browser can&rsquo;t play video &mdash; use the download button below.
      </video>
    </div>

    <div class="fvid-actions">
      <a class="btn gold" href="<?= e($VIDEO . $ver) ?>" download="We-Stand-on-Their-Shoulders-Vote.mp4">&#11015; Download the video</a>
      <span class="fvid-size"><?= (int)$SECS ?> seconds &middot; <?= max(1, (int)round($bytes / 1048576)) ?> MB &middot; tall, for phones</span>
    </div>

    <div class="panel fvid-how">
      <h2>Putting it on Facebook</h2>
      <p class="muted" style="margin:0 0 12px">It&rsquo;s tall, the shape of a phone screen, so it suits a
        Reel or a Story as well as an ordinary post.</p>
      <ol>
        <li><b>Download it first.</b> Press the gold button above. On a phone it may ask where to save
          it &mdash; Photos or Downloads is fine.</li>
        <li><b>Start a new post</b> &mdash; or a Reel &mdash; the way you would for a photograph.</li>
        <li><b>Attach the video</b>, paste the words below if you want them, and post.</li>
      </ol>

      <h3 class="fvid-h3">Something to write with it</h3>
      <p class="muted" style="margin:0 0 10px">Yours to change. No links in it, as you asked &mdash;
        just the message.</p>
      <div class="bvid-post">
        <textarea id="bvid-text" readonly rows="9"><?= e($POST) ?></textarea>
        <button type="button" class="btn2" id="bvid-copy">Copy these words</button>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
/* Same copy button as the other video pages: it says Copied only once the copy
   has actually happened. */
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
