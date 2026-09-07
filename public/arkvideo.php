<?php
/** The Ark video, on a page of its own.
 *
 *  Third one of these now, for the same reason as the first two: attachments
 *  sent through chat don't open on William's phone, they just spin, and a link
 *  always works. The page keeps the file, the download button, the Facebook
 *  steps and the words to post with it all in one place.
 *
 *  This one is his own message rather than family history, so it deliberately
 *  isn't linked from the menu — it's a page he goes to when he wants the file. */
require __DIR__ . '/../src/bootstrap.php';

$VIDEO = 'assets/video/ark-60.mp4';
$have  = is_file(__DIR__ . '/' . $VIDEO);
$bytes = $have ? filesize(__DIR__ . '/' . $VIDEO) : 0;
$SECS  = 60;

/* No music yet — William is making the instrumental in Suno. When the mp3
   arrives this flips to false and the video tag picks up muted + vidsound.js,
   the same press-to-play behaviour as the other two videos. Kept as one flag
   so the page can never claim sound it hasn't got. */
$SILENT = true;

$POST = "What did I do today?\n\n"
      . "I keep coming back to that question. Did I worship God today? Did I plant a seed "
      . "that might lead somebody to Christ? Did I try to help one person in my family find salvation?\n\n"
      . "Time is running out. Look at what is happening in the world, and how fast it is changing. "
      . "In Noah's day people carried on living their lives right up until the flood came.\n\n"
      . "This is real. Let's get ready to load the ark. The end is near.\n\n"
      . "TheBattlesFamily.com";

page_head('Load The Ark', ['body_class' => 'fvid']);
?>
<section class="fvid-hero">
  <h1>What Did I Do Today?</h1>
  <p>Sixty seconds. <?= $SILENT ? 'Music still to come.' : 'Made to share.' ?></p>
</section>

<div class="wrap fvid-wrap">
  <?php if (!$have): ?>
    <div class="panel"><p class="muted">The video isn&rsquo;t on the server yet.</p></div>
  <?php else: ?>
    <div class="fvid-player">
      <?php if ($SILENT): ?>
        <video controls playsinline preload="metadata" poster="assets/video/ark-60.jpg">
      <?php else: ?>
        <video controls muted playsinline preload="metadata" poster="assets/video/ark-60.jpg">
      <?php endif; ?>
        <source src="<?= e($VIDEO) ?>" type="video/mp4">
        Your browser can&rsquo;t play video &mdash; use the download button below.
      </video>
    </div>

    <div class="fvid-actions">
      <a class="btn gold" href="<?= e($VIDEO) ?>" download="Load-The-Ark.mp4">&#11015; Download the video</a>
      <span class="fvid-size"><?= (int)$SECS ?> seconds &middot; <?= (int)round($bytes / 1048576) ?> MB &middot; 1080 &times; 1080</span>
    </div>

    <?php if ($SILENT): ?>
      <div class="panel fvid-how" style="border-color:rgba(214,180,110,.55)">
        <h2>This cut has no sound yet</h2>
        <p class="muted" style="margin:0">Everything else is finished &mdash; the words, the timings, the
          fades. Make the instrumental in Suno, send me the mp3 here, and I&rsquo;ll lay it underneath and
          match the fades to it. Nothing you see above changes.</p>
      </div>
    <?php endif; ?>

    <div class="panel fvid-how">
      <h2>Putting it on Facebook</h2>
      <p class="muted" style="margin:0 0 12px">It&rsquo;s square, 1080 &times; 1080, which is the shape
        that takes up the most room in the feed on a phone without being cropped.</p>
      <ol>
        <li><b>Download it first.</b> Press the gold button above. On a phone it may ask where to save
          it &mdash; Photos or Downloads is fine.</li>
        <li><b>Start a new post</b> on your own page or in the group, the way you would for a photograph.</li>
        <li><b>Attach the video</b> from wherever you saved it, paste the words below, and post.</li>
        <li><b>Pin it</b> if it&rsquo;s in the group &mdash; three dots on your own post, then Pin post.</li>
      </ol>

      <h3 class="fvid-h3">Something to write with it</h3>
      <p class="muted" style="margin:0 0 10px">Yours to change &mdash; it&rsquo;s only here so there is
        something to start from.</p>
      <div class="bvid-post">
        <textarea id="bvid-text" readonly rows="12"><?= e($POST) ?></textarea>
        <button type="button" class="btn2" id="bvid-copy">Copy these words</button>
      </div>

      <p class="muted" style="margin-top:14px">The video ends on the web address, so anyone it reaches
        can find the family. The Enterprise page is open to everybody, signed in or not, and there&rsquo;s
        an <i>Ask to join</i> link on the sign-in page for the rest of the site.</p>
    </div>
  <?php endif; ?>
</div>

<script>
/* Same copy button as the Enterprise page: this gets opened on a laptop as
   often as a phone, so no share sheet, and it only says Copied once the copy
   has actually resolved. */
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
<?php if (!$SILENT): ?>
<script src="assets/vidsound.js?v=<?= @filemtime(__DIR__ . '/assets/vidsound.js') ?: 1 ?>"></script>
<?php endif; ?>

<?php legacy_footer();
page_foot();
