<?php
/** Together Again - the family reunion: the slideshow and every photograph.
 *
 *  William, 15 Nov 2026: "I want to use the photos and song on the website."
 *
 *  Signed-in family only, like every other picture of living relatives on this
 *  site. The pictures and the video are not in the public folder at all - they
 *  live in private_media/ and come out only through media.php, which checks the
 *  login on every single request. So the page is private AND the files are.
 *
 *  The album holds 50 of the 52 photographs he sent - every distinct one, burst
 *  shots included, because somebody may want the frame where THEY look best.
 *  Only 1189 (the same file as 1147, byte for byte) and 1187 (1147 again with a
 *  white block down one side) are left out. The slideshow uses its own
 *  selection of 39. */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$DIR = dirname(__DIR__) . '/private_media/reunion';
$photos = [];
if (is_dir($DIR)) {
    foreach (scandir($DIR) as $n) {
        if (preg_match('/^(100_11\d\d)\.jpg$/', $n, $m) && is_file($DIR . '/' . $m[1] . '_t.jpg')) {
            $photos[] = $m[1];
        }
    }
}
sort($photos);

$VID = dirname(__DIR__) . '/private_media/together-again.mp4';
$haveVid = is_file($VID);
$vv = $haveVid ? '&v=' . filemtime($VID) : '';

page_head('Together Again', ['body_class' => 'fvid']);
?>
<section class="fvid-hero">
  <h1>Together Again</h1>
  <p>Our family reunion &mdash; the slideshow, and every photograph.</p>
</section>

<div class="wrap fvid-wrap">
  <?php if ($haveVid): ?>
    <div class="fvid-player">
      <video controls muted playsinline preload="metadata" poster="<?= e('media.php?f=together-again.jpg' . $vv) ?>">
        <source src="<?= e('media.php?f=together-again.mp4' . $vv) ?>" type="video/mp4">
      </video>
    </div>
  <?php endif; ?>

  <div class="panel" style="margin-top:22px">
    <h2 style="margin-top:0">The photographs <span class="muted" style="font-size:.7em">(<?= count($photos) ?>)</span></h2>
    <p class="muted" style="margin:0 0 14px">Tap any picture to see it full size. Use the arrows, or swipe,
      to go through them, and the Save button to keep a copy.</p>
    <?php if (!$photos): ?>
      <p class="muted">The photographs aren&rsquo;t on the server yet.</p>
    <?php else: ?>
      <div class="gallery rgal">
        <?php foreach ($photos as $i => $p): ?>
          <a href="#" data-i="<?= (int)$i ?>" aria-label="Photograph <?= (int)$i + 1 ?>">
            <img src="<?= e('media.php?f=reunion/' . $p . '_t.jpg') ?>" alt="" loading="lazy">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p class="muted" style="margin:16px 0 0;font-size:14px">These pictures are for signed-in family only,
      the same as every other photograph of living relatives on the site.</p>
  </div>
</div>

<div id="rlb" class="rlb" aria-hidden="true">
  <button type="button" class="rlb-x" aria-label="Close">&times;</button>
  <button type="button" class="rlb-nav rlb-prev" aria-label="Previous">&#8249;</button>
  <img id="rlb-img" src="" alt="">
  <button type="button" class="rlb-nav rlb-next" aria-label="Next">&#8250;</button>
  <div class="rlb-bar"><span id="rlb-n"></span><a id="rlb-save" href="#">Save this photo</a></div>
</div>

<style>
/* The site's .gallery wants columns of at least 150 px. Inside a panel on a
   390-wide phone that is ONE column - fifty full-width photographs to scroll
   past. Three to a row on a phone, the site's usual grid from tablet up. */
.rgal{grid-template-columns:repeat(3,1fr);gap:8px}
@media (min-width:700px){.rgal{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}}
.rgal img{object-fit:cover}
.rlb{position:fixed;inset:0;z-index:60;background:rgba(10,4,5,.95);display:none;
  align-items:center;justify-content:center;flex-direction:column;padding:18px}
.rlb.show{display:flex}
.rlb img{max-width:94vw;max-height:78vh;border:4px solid #2a0e0e;outline:2px solid var(--gold);border-radius:4px}
.rlb-x{position:fixed;top:10px;right:14px;font-size:42px;line-height:1;background:none;border:0;
  color:var(--gold2);cursor:pointer;padding:6px 10px}
.rlb-nav{position:fixed;top:50%;transform:translateY(-50%);font-size:54px;line-height:1;width:54px;
  height:72px;background:rgba(28,6,8,.7);border:1px solid rgba(201,162,75,.6);border-radius:10px;
  color:var(--gold2);cursor:pointer}
.rlb-prev{left:8px}.rlb-next{right:8px}
.rlb-bar{margin-top:14px;display:flex;gap:22px;align-items:center;color:#e8d5a8;
  font-family:'EB Garamond',Georgia,serif;font-size:17px}
.rlb-bar a{color:var(--gold2)}
@media (max-width:600px){.rlb-nav{width:42px;height:60px;font-size:40px}}
</style>
<script>
/* One lightbox for the album. Swipe on a phone, arrow keys on a computer.
   "Save" fetches the full-size picture through media.php with ?dl=1, which
   sends it as a download - still behind the login. */
(function () {
  var N = <?= json_encode($photos) ?>, i = 0;
  var box = document.getElementById('rlb'), img = document.getElementById('rlb-img'),
      num = document.getElementById('rlb-n'), save = document.getElementById('rlb-save');
  if (!box || !N.length) return;
  function full(k) { return 'media.php?f=reunion/' + N[k] + '.jpg'; }
  function show(k) {
    i = (k + N.length) % N.length;
    img.src = full(i);
    num.textContent = (i + 1) + ' of ' + N.length;
    save.href = full(i) + '&dl=1';
    /* load the next one ahead, so the arrow feels instant on a slow phone */
    (new Image()).src = full((i + 1) % N.length);
  }
  function open(k) { show(k); box.classList.add('show'); box.setAttribute('aria-hidden', 'false'); }
  function close() { box.classList.remove('show'); box.setAttribute('aria-hidden', 'true'); img.src = ''; }
  document.querySelectorAll('.rgal a').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); open(+a.getAttribute('data-i')); });
  });
  box.querySelector('.rlb-x').addEventListener('click', close);
  box.querySelector('.rlb-prev').addEventListener('click', function () { show(i - 1); });
  box.querySelector('.rlb-next').addEventListener('click', function () { show(i + 1); });
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  document.addEventListener('keydown', function (e) {
    if (!box.classList.contains('show')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') show(i - 1);
    else if (e.key === 'ArrowRight') show(i + 1);
  });
  var x0 = null;
  box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', function (e) {
    if (x0 === null) return;
    var dx = e.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 50) show(i + (dx < 0 ? 1 : -1));
  });
})();
</script>
<script src="assets/vidsound.js?v=<?= @filemtime(__DIR__ . '/assets/vidsound.js') ?: 1 ?>"></script>

<?php legacy_footer();
page_foot();
