<?php
/** The share video, on a page of its own.
 *
 *  It exists because attachments sent through Freelancer chat don't open on
 *  William's phone — they just spin. A link always works, and a page can hold
 *  the download button and the three steps for Facebook next to the video
 *  itself, instead of in a message he has to scroll back to find. */
require __DIR__ . '/../src/bootstrap.php';

$VIDEO = 'assets/video/battles-legacy.mp4';
$have  = is_file(__DIR__ . '/' . $VIDEO);

page_head('Our Family Video', ['body_class' => 'fvid']);
?>
<section class="fvid-hero">
  <h1>One Family. Many Stories.</h1>
  <p>Thirteen of our ancestors, and the family anthem. Made to share.</p>
</section>

<div class="wrap fvid-wrap">
  <?php if (!$have): ?>
    <div class="panel"><p class="muted">The video isn&rsquo;t on the server yet.</p></div>
  <?php else: ?>
    <div class="fvid-player">
      <video controls playsinline preload="metadata" poster="<?= e('assets/video/battles-legacy.jpg') ?>">
        <source src="<?= e($VIDEO) ?>" type="video/mp4">
        Your browser can&rsquo;t play video &mdash; use the download button below.
      </video>
    </div>

    <div class="fvid-actions">
      <a class="btn gold" href="<?= e($VIDEO) ?>" download="The-Battles-Legacy.mp4">&#11015; Download the video</a>
      <span class="fvid-size">57 seconds &middot; 5 MB</span>
    </div>

    <div class="panel fvid-how">
      <h2>Putting it on Facebook</h2>
      <ol>
        <li><b>Download it first.</b> Press the gold button above. On a phone it may ask where to
          save it &mdash; Photos or Downloads is fine.</li>
        <li><b>Open your Battles Family group</b> and start a new post, the way you would for a photograph.</li>
        <li><b>Attach the video</b> from wherever you saved it, add a line or two, and post.</li>
      </ol>
      <p class="muted">It plays by itself as people scroll past, and they tap it for sound. If anyone
        asks how to get on the site, send them to <b>thebattlesfamily.com</b> &mdash; there&rsquo;s an
        <i>Ask to join</i> link on the sign-in page, and the request comes to you.</p>
    </div>
  <?php endif; ?>
</div>

<?php legacy_footer();
page_foot();
