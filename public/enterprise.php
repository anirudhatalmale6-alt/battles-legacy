<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';

/* Content is managed from enterprise_manage.php (admin) and stored in the
   enterprise_* tables. Pillars / financial tips / action row are static. */
try {
    ent_migrate();
    $BIZ  = ent_businesses();
    $VIDS = ent_videos();
    $SAYS = ent_sayings();
    $FIN  = ent_finance();
} catch (Exception $ex) {
    $BIZ = $VIDS = $SAYS = $FIN = [];
}
$FEAT = $VIDS ? $VIDS[0] : null;
$REST = $VIDS ? array_slice($VIDS, 1) : [];

$PILLARS = [
  ['icon'=>'bulb',  'title'=>'Entrepreneurs',          'text'=>'Building businesses, creating opportunities, and leading with vision.'],
  ['icon'=>'case',  'title'=>'Business Professionals', 'text'=>'Excellence in every field, leading with skill, integrity, and purpose.'],
  ['icon'=>'chart', 'title'=>'Motivation',             'text'=>'Inspiring the next generation to dream, to strive, and to achieve.'],
  ['icon'=>'users', 'title'=>'Family in Business',     'text'=>'Our legacy continues through partnership, support, and unity.', 'link'=>'#family-in-business'],
  ['icon'=>'star',  'title'=>'Member Spotlights',      'text'=>'Celebrating the achievements of our family and the impact we make.'],
];

/* The four cards at the foot of the page. Every one of them used to be a
   <button> with nothing behind it — they looked live and did nothing at all.
   Each now goes somewhere real.

   Their words used to be typed into this file, which meant William could not
   rename a card or change a line without me. They are rows now, edited from
   the "The four cards" tab of the Enterprise editor.

   Mentor Connect is the only one kept behind sign-in: it lists living
   relatives by name, and everywhere else on this site living relatives are
   hidden from the public. That is the "members only" tick on the card. */
$ACTIONS = ent_actions();

page_head('Enterprise', ['body_class' => 'home ent']);
?>
<?php if (role_at_least('admin')): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="enterprise_manage.php">&#9998; Manage this page</a>
    <?php /* The four cards at the foot of this page now lead somewhere and two
             of them collect messages. The count is drawn here as well as on the
             editor's tab strip because this is the page he actually opens. */
      require_once __DIR__ . '/../src/mentor_data.php';
      $MMN = 0; try { $MMN = ask_count('new') + ment_pending_count(); } catch (\Throwable $e) {} ?>
    <a class="ent2-editbtn" href="mentors_manage.php">&#9998; Mentors &amp; Resources<?= $MMN ? ' <b class="badge">' . (int)$MMN . '</b>' : '' ?></a>
  </div>
<?php endif; ?>

<!-- HERO -->
<section class="ent2-hero">
  <img class="ent2-hero-img" src="assets/enterprise/hero-band.jpg"
       alt="Building Tomorrow. Honoring Our Legacy. Enterprising Our Future.">
  <div class="ent2-hero-inner">
    <h1 class="ent2-h1">Building Tomorrow.<br>Honoring Our Legacy.</h1>
    <span class="ent2-script">Enterprising Our Future.</span>
    <div class="ent2-orn">&#10086; &nbsp; &bull; &nbsp; &#10086;</div>
    <p class="ent2-intro">From vision to legacy, our family members are entrepreneurs, professionals,
       and leaders &mdash; building businesses, creating opportunities, and making an impact in their
       communities and beyond.</p>
  </div>
</section>

<!-- PILLARS -->
<section class="ent2-pillars">
  <?php foreach ($PILLARS as $p): $lnk = !empty($p['link']); ?>
    <<?= $lnk ? 'a href="'.e($p['link']).'"' : 'div' ?> class="ent2-pillar<?= $lnk ? ' linked' : '' ?>">
      <div class="ent2-pic"><?= ent_icon($p['icon']) ?></div>
      <h4><?= e($p['title']) ?></h4>
      <p><?= $p['text'] /* authored above */ ?></p>
    </<?= $lnk ? 'a' : 'div' ?>>
  <?php endforeach; ?>
</section>

<?php if ($SAYS): ?>
<!-- ROTATING SAYING -->
<section class="ent2-sayband">
  <span class="say-mark">&ldquo;</span>
  <blockquote id="say-text"><?= e($SAYS[0]['quote']) ?></blockquote>
  <?php if ($SAYS[0]['author']): ?><cite id="say-who"><?= e($SAYS[0]['author']) ?></cite><?php else: ?><cite id="say-who"></cite><?php endif; ?>
</section>
<?php endif; ?>

<!-- FEATURED FAMILY BUSINESSES -->
<section class="ent2-bizwrap" id="family-in-business">
  <div class="ent2-bizhead">
    <h2>Featured Family Businesses</h2>
    <p>Support our family. Strengthen our legacy.</p>
  </div>
  <?php /* This box used to be onsubmit="return false" — you could type in it and
           press Search all day and nothing happened. It now searches for real,
           on the full directory page, which is also where the button at the
           bottom of this section goes. */ ?>
  <form class="biz-search" method="get" action="businesses.php">
    <input type="text" name="q" placeholder="Search businesses by name, profession, or location...">
    <select name="type" aria-label="Category">
      <option value="">All Categories</option>
      <?php foreach (ent_types() as $v => $lbl): ?><option value="<?= e($v) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="ent2-btn">Search</button>
  </form>
  <?php if ($BIZ): ?>
  <div class="biz-grid">
    <?php foreach ($BIZ as $b): ?>
      <?php $btype = $b['cat_type']; $isWork = ($btype === 'Book' || $btype === 'Article');
            $fit = (($b['photo_fit'] ?? 'cover') === 'contain') ? ' fit' : ''; ?>
      <article class="biz-card">
        <div class="biz-photo<?= $fit ?>"<?= $b['photo'] ? ' style="background-image:url(\''.e($b['photo']).'\')"' : ' data-empty="1"' ?>>
          <?php if ($b['sample']): ?><span class="ent-ex">Example</span><?php endif; ?>
          <?php if ($isWork): ?><span class="biz-type"><?= $btype === 'Book' ? 'Published Book' : 'Published Article' ?></span><?php endif; ?>
          <span class="biz-mono"><?= ent_mono($b['name']) ?></span>
        </div>
        <div class="biz-body">
          <h3><?= e($b['name']) ?></h3>
          <?php if ($b['owner']): ?><div class="biz-who"><?= e($b['owner']) ?></div><?php endif; ?>
          <?php if ($b['category']): ?><div class="biz-cat"><?= e($b['category']) ?></div><?php endif; ?>
          <?php if ($b['location']): ?><div class="biz-loc"><?= e($b['location']) ?></div><?php endif; ?>
          <?php if ($b['blurb']): ?><p class="biz-blurb"><?= e($b['blurb']) ?></p><?php endif; ?>
          <div class="biz-ico">
            <?php
              $web = trim($b['link']); if ($web && !preg_match('~^https?://~i', $web)) $web = 'http://' . $web;
              echo $web ? '<a href="'.e($web).'" target="_blank" rel="noopener">'.ent_icon('globe').'</a>' : '<span class="off">'.ent_icon('globe').'</span>';
              echo $b['phone'] ? '<a href="tel:'.e(preg_replace('/[^0-9+]/','',$b['phone'])).'">'.ent_icon('phone').'</a>' : '<span class="off">'.ent_icon('phone').'</span>';
              echo $b['email'] ? '<a href="mailto:'.e($b['email']).'">'.ent_icon('mail').'</a>' : '<span class="off">'.ent_icon('mail').'</span>';
            ?>
          </div>
          <?php $cta = ent_cta($btype); ?>
          <?php if ($web): ?><a class="ent2-btn biz-view" href="<?= e($web) ?>" target="_blank" rel="noopener"><?= e($cta) ?></a>
          <?php else: ?><button type="button" class="ent2-btn biz-view"><?= e($cta) ?></button><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <a class="ent2-btn center" href="businesses.php" style="margin-top:24px;">View All Family Businesses &rarr;</a>
  <?php else: ?>
    <p class="ent2-note" style="text-align:center">No businesses added yet.</p>
  <?php endif; ?>
</section>

<!-- MAIN: videos + finance -->
<div class="ent2-wrap">
  <div class="ent2-main">

    <!-- Featured Videos — hidden from visitors while there is nothing to watch -->
    <?php if ($VIDS || role_at_least('admin')): ?>
    <section class="ent2-panel">
      <div class="ent2-sec-title"><?= ent_icon('film') ?> Featured Videos</div>
      <?php if (!$VIDS): ?>
        <p class="ent2-note" style="text-align:center">No videos here yet &mdash; add one and this section appears for the family.</p>
      <?php endif; ?>
      <?php if ($FEAT): ?>
      <?php $fu = video_url($FEAT['url']); $fth = video_pic($FEAT); ?>
      <?php if ($fu): ?><a class="ent2-vid-feature<?= $fth ? ' hasimg' : '' ?>" href="<?= e($fu) ?>" target="_blank" rel="noopener"<?php else: ?><div class="ent2-vid-feature<?= $fth ? ' hasimg' : '' ?>"<?php endif; ?>
        <?= $fth ? ' style="background-image:url(\'' . e($fth) . '\')"' : '' ?>>
        <span class="ent2-vf-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
        <div class="ent2-vf-cap">
          <h3><?= e($FEAT['title']) ?></h3>
          <p><?= e($FEAT['description']) ?> <?php if ($FEAT['duration']): ?>&nbsp;<span class="dur"><?= e($FEAT['duration']) ?></span><?php endif; ?></p>
        </div>
      <?php if ($fu): ?></a><?php else: ?></div><?php endif; ?>
      <?php endif; ?>
      <div class="ent2-vlist">
        <?php foreach ($REST as $v): $vu = video_url($v['url']); $vth = video_pic($v); ?>
          <?php if ($vu): ?><a class="ent2-vrow" href="<?= e($vu) ?>" target="_blank" rel="noopener"><?php else: ?><div class="ent2-vrow"><?php endif; ?>
            <div class="ent2-vthumb<?= $vth ? ' hasimg' : '' ?>"<?= $vth ? ' style="background-image:url(\'' . e($vth) . '\')"' : '' ?>></div>
            <div class="ent2-vmeta">
              <h4><?= e($v['title']) ?></h4>
              <?php if ($v['duration']): ?><span class="dur"><?= e($v['duration']) ?></span><?php endif; ?>
            </div>
          <?php if ($vu): ?></a><?php else: ?></div><?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php if (role_at_least('admin')): ?>
        <a class="ent2-btn center" href="enterprise_manage.php?tab=videos">Add or edit videos &rsaquo;</a>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Financial Guidance -->
    <section class="ent2-panel">
      <div class="ent2-sec-title"><?= ent_icon('bank') ?> Financial Guidance &amp; Suggestions</div>
      <p class="ent2-sec-sub">Practical advice. Generational wealth. Financial freedom.</p>
      <div class="ent2-fin-grid">
        <?php foreach ($FIN as $f): ?>
          <div class="ent2-fin">
            <div class="ent2-fic"><?= ent_icon($f['icon']) ?></div>
            <h3><?= e($f['title']) ?></h3>
            <ul><?php foreach (ent_tips($f['tips']) as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
            <?php if (!empty($f['link'])): $fl = trim($f['link']); if (!preg_match('~^https?://~i', $fl)) $fl = 'http://' . $fl; ?>
              <a class="ent2-btn" href="<?= e($fl) ?>" target="_blank" rel="noopener">Learn More</a>
            <?php else: ?>
              <button type="button" class="ent2-btn">Learn More</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  </div>
</div>

<!-- ACTIONS -->
<section class="ent2-actions">
  <div class="ent2-acts">
    <?php foreach ($ACTIONS as $a):
      /* Members-only card, seen by somebody who is not signed in: send them to
         the sign-in page and say so on the button, rather than letting them
         click through to a redirect that looks like the link is broken. */
      $locked = !empty($a['members']) && !logged_in();
      $label  = trim((string)$a['cta']);
      if ($label === '') $label = 'Open';
      /* Built from whatever he typed on the button, so renaming "Find a Mentor"
         renames the signed-out version with it and the two can never disagree. */
      if ($locked) $label = 'Sign in to ' . mb_strtolower($label);
      $href = $locked ? 'login.php' : trim((string)$a['href']);
      /* A card whose link William has emptied is not turned into a dead button
         again — it simply stops offering one. That is the whole bug this page
         was carrying, and it is not being reintroduced through the editor. */
    ?>
      <div class="ent2-act">
        <div class="ent2-aic"><?= ent_icon($a['icon']) ?></div>
        <h3><?= e($a['title']) ?></h3>
        <?php if (trim((string)$a['blurb']) !== ''): ?><p><?= e($a['blurb']) ?></p><?php endif; ?>
        <?php if ($href !== ''): ?>
          <a class="ent2-actlink" href="<?= e($href) ?>"><?= e($label) ?> &rarr;</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="ent2-submit">
      <h3>Submit Your Business</h3>
      <p>Are you a business owner? Add your business, a video, or a resource &mdash; we&rsquo;ll review it and feature it here!</p>
      <?php if (logged_in()): ?>
        <a class="ent2-btn" href="enterprise_submit.php">Submit for Review</a>
      <?php else: ?>
        <a class="ent2-btn" href="login.php">Sign In to Submit</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($SAYS): ?>
<script>
(function(){
  var S = <?= json_encode(array_map(function($s){ return [$s['quote'], $s['author']]; }, $SAYS), JSON_UNESCAPED_UNICODE) ?>;
  if (!S || S.length < 2) return;
  var t=document.getElementById('say-text'), w=document.getElementById('say-who'),
      box=document.querySelector('.ent2-sayband'), i=0;
  setInterval(function(){
    i=(i+1)%S.length; box.classList.add('fade');
    setTimeout(function(){ t.textContent=S[i][0]; w.textContent=S[i][1]||''; box.classList.remove('fade'); }, 500);
  }, 6000);
})();
</script>
<?php endif; ?>

<?php legacy_footer(); page_foot();
