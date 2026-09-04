<?php
/** The Enterprise video, on a page of its own.
 *
 *  Same reason as familyvideo.php: attachments sent through chat do not open
 *  on William's phone, they just spin. A link always works, and a page can put
 *  the download button and the Facebook steps beside the video instead of in a
 *  message he has to scroll back to find.
 *
 *  This one carries a written post as well. Last time I sent the wording for
 *  the group in chat and he had to find it again days later when he came to
 *  post; it belongs next to the file it describes. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';

$VIDEO = 'assets/video/battles-enterprise.mp4';
$have  = is_file(__DIR__ . '/' . $VIDEO);
$bytes = $have ? filesize(__DIR__ . '/' . $VIDEO) : 0;
/* Cut to the length of William's own song, which ends by itself at 56 seconds.
   Kept as one number here because it is quoted on the page and again on the
   Enterprise page's video row, and the two must not drift apart. */
$SECS  = 56;

/* Counted, not typed in — the video says thirteen because there were thirteen
   when it was made, and if he adds a fourteenth this page should not go on
   claiming otherwise. */
$NBIZ = 0;
try { ent_migrate(); $NBIZ = count(ent_businesses()); } catch (\Throwable $e) {}

$POST = "Something I have been meaning to show you all.\n\n"
      . "We have family running businesses all over Texas and beyond — transportation, catering, "
      . "construction, childcare, hair, books, law enforcement. This is them.\n\n"
      . "Every one of them is on our family website with a phone number and a link, "
      . "on the Enterprise page: thebattlesfamily.com\n\n"
      . "If you are in business yourself and you are not on there, say so in the comments "
      . "and I will add you. It costs nothing. Let us keep it in the family.";

page_head('Our Family in Business', ['body_class' => 'fvid']);
?>
<section class="fvid-hero">
  <h1>Building Tomorrow. Honoring Our Legacy.</h1>
  <p><?= $NBIZ ? (int)$NBIZ . ' family businesses' : 'Our family in business' ?>, <?= (int)$SECS ?> seconds. Made to share.</p>
</section>

<div class="wrap fvid-wrap">
  <?php if (!$have): ?>
    <div class="panel"><p class="muted">The video isn&rsquo;t on the server yet.</p></div>
  <?php else: ?>
    <div class="fvid-player">
      <video controls muted playsinline preload="metadata" poster="assets/video/battles-enterprise.jpg">
        <source src="<?= e($VIDEO) ?>" type="video/mp4">
        Your browser can&rsquo;t play video &mdash; use the download button below.
      </video>
    </div>

    <div class="fvid-actions">
      <a class="btn gold" href="<?= e($VIDEO) ?>" download="Battles-Family-Enterprise.mp4">&#11015; Download the video</a>
      <span class="fvid-size"><?= (int)$SECS ?> seconds &middot; <?= (int)round($bytes / 1048576) ?> MB</span>
    </div>

    <div class="panel fvid-how">
      <h2>Putting it on Facebook</h2>
      <p class="muted" style="margin:0 0 12px">A group banner can only be a still picture &mdash; groups
        can&rsquo;t play a video up there. So the video goes in as a post, and there&rsquo;s a matching
        banner below it for the top of the group.</p>

      <h3 class="fvid-h3">The video &mdash; as a post</h3>
      <ol>
        <li><b>Download it first.</b> Press the gold button above. On a phone it may ask where to save
          it &mdash; Photos or Downloads is fine.</li>
        <li><b>Open your Battles Family group</b> and start a new post, the way you would for a photograph.</li>
        <li><b>Attach the video</b> from wherever you saved it, paste the words below, and post.</li>
        <li><b>Then pin it.</b> Press the three dots on your own post and choose Pin post, so it stays at
          the top of the group.</li>
      </ol>

      <h3 class="fvid-h3">Something to write with it</h3>
      <p class="muted" style="margin:0 0 10px">Yours to change &mdash; it is only here so there is
        something to start from.</p>
      <div class="bvid-post">
        <textarea id="bvid-text" readonly rows="9"><?= e($POST) ?></textarea>
        <button type="button" class="btn2" id="bvid-copy">Copy these words</button>
      </div>

      <h3 class="fvid-h3">The banner &mdash; the still picture</h3>
      <div class="fvid-cover">
        <img src="assets/video/enterprise-cover.jpg" alt="The Battles Family Enterprise — group banner">
        <a class="btn gold" href="assets/video/enterprise-cover.jpg" download="Battles-Enterprise-Facebook-Banner.jpg">&#11015; Download the banner</a>
      </div>
      <p class="muted" style="margin:10px 0 14px">Made at the size Facebook wants for a group cover
        (1640 &times; 856), with the wording kept to the middle so nothing important is cut off on a phone.
        In the group, press the camera on the current banner and choose Upload photo.</p>

      <p class="muted">Anyone who asks how to get on the site: send them to <b>thebattlesfamily.com</b>
        &mdash; there&rsquo;s an <i>Ask to join</i> link on the sign-in page and the request comes to you.
        The Enterprise page itself is open to everybody, signed in or not, so the businesses can be shared
        outside the family too.</p>
    </div>
  <?php endif; ?>
</div>

<script>
/* Copy without a share sheet: this page is opened on a laptop as often as a
   phone, and a button that says Copy has to copy on both. Relabelled only
   after the copy actually resolves, never optimistically. */
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
