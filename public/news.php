<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/news_data.php';
require_once __DIR__ . '/../src/community_data.php';
$TOTAL = 0;
try {
    news_migrate(); community_migrate();
    $POSTS = news_posts(false, '', 12);  // three rotating pages of four; the rest live in the archive
    $TOTAL = news_count();
    $EVENTS = news_events();
    $QLIST = comm_list('question', 'published', 3);
    $RLIST = comm_list('recipe',   'published', 3);
    $ULIST = comm_list('update',   'published', 4);
} catch (\Throwable $ex) { $POSTS = $EVENTS = $QLIST = $RLIST = $ULIST = []; }

$isAdmin = role_at_least('admin');
$PENDSUB = $isAdmin ? comm_pending_count() : 0;

// each tile filters the archive, so "three deaths and two births" all stay findable
$STRIP = [
  ['baby','Births','Welcoming new life','birth'],
  ['cap','Graduations','Celebrating achievements','graduation'],
  ['rings','Marriages','Two hearts, one legacy','marriage'],
  ['people','Reunions','Coming together, staying connected','reunion'],
  ['dove','Deaths','Remembering lives, honoring legacies','memory'],
  ['hands','Prayers','Lift up one another in faith','prayer'],
];

$logged = logged_in();
$subUrl = function ($kind) use ($logged) { return $logged ? "community_submit.php?kind=$kind" : 'login.php'; };

page_head('Family News', ['body_class' => 'home fnews']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="news_manage.php">&#9998; Manage Family News</a>
    <a class="ent2-editbtn" href="news_manage.php?tab=submissions">&#128172; Family submissions<?= $PENDSUB ? ' ('.$PENDSUB.')' : '' ?></a>
  </div>
<?php endif; ?>

<!-- HERO -->
<section class="fn-hero">
  <img class="fn-hero-img" src="assets/news/hero.jpg" alt="Family News — Stay Connected. Stay Informed. Keep up with what's happening in our family.">
  <!-- The banner's words are painted into the photograph, so on a phone it
       shrinks to a strip an inch and a half tall and none of it can be read.
       Below 760px the picture steps aside and the same words are set as text,
       the way the Enterprise and Memorial banners already do it. -->
  <div class="fn-hero-inner">
    <h1 class="fn-h1">Stay Connected.<span class="fn-script">Stay Informed.</span></h1>
    <div class="fn-orn">&#10086; &nbsp; &bull; &nbsp; &#10086;</div>
    <p class="fn-intro">Keep up with what&rsquo;s happening in our family and celebrate life
       together &mdash; every step of the way.</p>
  </div>
</section>

<!-- CATEGORY STRIP -->
<section class="fn-strip">
  <?php foreach ($STRIP as $s): $n = news_count($s[3]); ?>
    <a class="fn-scat" href="news_all.php?cat=<?= e($s[3]) ?>" title="See all <?= e(strtolower($s[1])) ?>">
      <span class="fn-sic"><?= news_icon($s[0]) ?></span>
      <div><b><?= e($s[1]) ?><?= $n ? ' <i class="fn-n">'.$n.'</i>' : '' ?></b><span><?= e($s[2]) ?></span></div>
    </a>
  <?php endforeach; ?>
</section>

<div class="fn-wrap">
  <div class="fn-main-grid">

    <!-- NEWS & ANNOUNCEMENTS -->
    <section class="fn-news">
      <div class="fn-head"><h2><?= news_icon('news') ?> Family News &amp; Announcements</h2>
        <a class="fn-viewall" href="news_all.php">View All News<?= $TOTAL ? ' ('.$TOTAL.')' : '' ?> &rsaquo;</a>
        <?php if ($isAdmin): ?><a class="fn-viewall" href="news_manage.php">Manage &rsaquo;</a><?php endif; ?></div>
      <?php if ($POSTS): ?>
      <!-- The announcements rotate a row at a time, so a busy month doesn't
           turn this into a wall. Without JavaScript it stays a plain grid. -->
      <div class="fn-rot" data-rotate="7000">
        <button type="button" class="fn-arrow prev" aria-label="Previous announcements">&#8249;</button>
        <div class="fn-cards fn-track">
          <?php foreach ($POSTS as $p) echo news_card($p); ?>
        </div>
        <button type="button" class="fn-arrow next" aria-label="Next announcements">&#8250;</button>
        <div class="fn-dots" role="tablist" aria-label="Announcement pages"></div>
      </div>
      <?php if ($TOTAL > count($POSTS)): ?>
        <a class="btn2 solid fn-allbtn" href="news_all.php">See all <?= (int)$TOTAL ?> announcements</a>
      <?php endif; ?>
      <?php else: ?>
        <p class="fn-empty"><?= $isAdmin ? 'No news yet — add your first announcement from Manage Family News.' : 'Family news will be shared here soon.' ?></p>
      <?php endif; ?>
    </section>

    <!-- UPCOMING EVENTS -->
    <aside class="fn-events" id="events">
      <div class="fn-head"><h2><?= news_icon('calendar') ?> Upcoming Events</h2><a class="fn-viewall" href="calendar.php">View Calendar &rsaquo;</a></div>
      <?php if ($EVENTS): ?>
        <?php foreach ($EVENTS as $ev): ?>
          <div class="fn-event">
            <div class="fn-when"><span class="fn-mon"><?= e($ev['mon']) ?></span><span class="fn-day"><?= e($ev['day']) ?></span></div>
            <div class="fn-edet">
              <h4><?= e($ev['title']) ?></h4>
              <?php if ($ev['place']): ?><div class="fn-place"><?= e($ev['place']) ?></div><?php endif; ?>
              <?php if ($ev['time_label']): ?><div class="fn-time"><?= e($ev['time_label']) ?></div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <a class="btn2 solid fn-allbtn" href="calendar.php">Open the family calendar</a>
      <?php else: ?>
        <p class="fn-empty"><?= $isAdmin ? 'No events yet — add one from Manage Family News.' : 'Upcoming family events will appear here.' ?></p>
      <?php endif; ?>
      <span class="fn-soon">Coming soon</span>
    </aside>
  </div>
</div>

<!-- VERSE BAND -->
<section class="fn-verse">
  <span class="fvq">&ldquo;</span>Be devoted to one another in love. Honor one another above yourselves.<span class="fvr">&mdash; Romans 12:10</span>
</section>

<!-- THREE COLUMNS: prayer / questions / recipes -->
<div class="fn-wrap">
  <div class="fn-three">
    <section class="fn-col">
      <div class="fn-head sm"><h3><?= news_icon('hands') ?> Prayer Requests</h3></div>
      <p class="fn-cmt">Lift one another up. Share a prayer request and our prayer warriors will stand with you &mdash; requests are handled privately on the Faith page.</p>
      <a class="btn2 solid" href="faith.php#prayer">Submit a Prayer Request</a>
    </section>

    <section class="fn-col">
      <div class="fn-head sm"><h3><?= news_icon('question') ?> Ask Questions</h3><a class="fn-viewall" href="community_list.php?kind=question">View All &rsaquo;</a></div>
      <?php if ($QLIST): ?>
      <ul class="fn-list">
        <?php foreach ($QLIST as $q): ?>
          <li><span class="fn-av q"><?= news_icon('question') ?></span>
            <div class="fn-li"><p><a href="community_view.php?id=<?= (int)$q['id'] ?>"><?= e(mb_strimwidth($q['body'],0,90,'…')) ?></a></p><span class="fn-by">Asked by <?= e($q['author']) ?> &middot; <?= e(comm_ago($q['created_at'])) ?></span></div>
            <a class="fn-like" href="community_view.php?id=<?= (int)$q['id'] ?>"><?= news_icon('chat') ?> <?= comm_answer_count($q['id']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><p class="fn-cmt">No questions yet &mdash; be the first to ask the family.</p><?php endif; ?>
      <a class="btn2 solid" href="<?= e($subUrl('question')) ?>">Ask a Question</a>
    </section>

    <section class="fn-col">
      <div class="fn-head sm"><h3><?= news_icon('recipe') ?> Share a Recipe</h3><a class="fn-viewall" href="community_list.php?kind=recipe">View All &rsaquo;</a></div>
      <?php if ($RLIST): ?>
      <ul class="fn-list">
        <?php foreach ($RLIST as $r): ?>
          <li><span class="fn-av r<?= $r['photo'] ? ' pic' : '' ?>"<?= $r['photo'] ? ' style="background-image:url(\''.e($r['photo']).'\')"' : '' ?>><?= $r['photo'] ? '' : news_icon('recipe') ?></span>
            <div class="fn-li"><p class="fn-rtitle"><a href="community_view.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></p><span class="fn-by">Shared by <?= e($r['author']) ?></span></div>
            <span class="fn-like"><?= news_icon('heart') ?> <?= (int)$r['likes'] ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><p class="fn-cmt">No recipes yet &mdash; share a family favorite.</p><?php endif; ?>
      <a class="btn2 solid" href="<?= e($subUrl('recipe')) ?>">Share a Recipe</a>
    </section>
  </div>
</div>

<!-- STAY CONNECTED + RECENT UPDATES -->
<div class="fn-wrap" style="padding-bottom:34px">
  <div class="fn-connect">
    <section class="fn-col fn-stay">
      <div class="fn-head sm"><h3><?= news_icon('people') ?> Stay Connected</h3></div>
      <p class="fn-cmt">Share updates, photos, and words of encouragement. Let&rsquo;s keep our family strong and connected!</p>
      <a class="btn2 solid" href="<?= e($subUrl('update')) ?>"><?= news_icon('plus') ?> Post an Update</a>
    </section>
    <section class="fn-col fn-recent">
      <div class="fn-head sm"><h3>Recent Family Updates</h3><a class="fn-viewall" href="community_list.php?kind=update">View All &rsaquo;</a></div>
      <?php if ($ULIST): ?>
      <div class="fn-updates">
        <?php foreach ($ULIST as $u): ?>
          <a class="fn-update" href="community_view.php?id=<?= (int)$u['id'] ?>"><span class="fn-uthumb"<?= $u['photo'] ? ' style="background-image:url(\''.e($u['photo']).'\');background-size:cover"' : '' ?>><?= $u['photo']?'':news_icon('people') ?></span><div class="fn-li"><p><?= e(mb_strimwidth($u['body'],0,110,'…')) ?></p><span class="fn-by"><?= e($u['author']) ?> &middot; <?= e(comm_ago($u['created_at'])) ?></span></div></a>
        <?php endforeach; ?>
      </div>
      <?php else: ?><p class="fn-cmt" style="margin:0">No updates yet &mdash; be the first to share family news.</p><?php endif; ?>
    </section>
  </div>
</div>

<script>
function fnSoon(a){ var c=a.closest('.fn-col,.fn-events'); var s=c?c.querySelector('.fn-soon'):null; if(s) s.classList.add('show'); return false; }

/* Rotating announcements. The markup is a plain grid until this runs, so if a
   phone blocks scripts the family still sees every card. */
(function(){
  var rot = document.querySelector('.fn-rot');
  if (!rot) return;
  var track = rot.querySelector('.fn-track'),
      dots  = rot.querySelector('.fn-dots'),
      prev  = rot.querySelector('.fn-arrow.prev'),
      next  = rot.querySelector('.fn-arrow.next'),
      cards = Array.prototype.slice.call(track.children),
      still = window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      wait  = parseInt(rot.getAttribute('data-rotate'), 10) || 7000,
      page = 0, per = 1, pages = 1, timer = null, resume = null;

  if (cards.length < 2) return;
  rot.classList.add('on');

  function step(){
    var gap = parseFloat(getComputedStyle(track).columnGap || 16) || 16;
    return cards[0].getBoundingClientRect().width + gap;
  }
  function measure(){
    var s = step();
    per   = Math.max(1, Math.round(track.clientWidth / s));
    pages = Math.max(1, Math.ceil(cards.length / per));
    if (page > pages - 1) page = pages - 1;
    dots.innerHTML = '';
    if (pages < 2) { rot.classList.add('single'); return; }
    rot.classList.remove('single');
    for (var i = 0; i < pages; i++) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'fn-dot' + (i === page ? ' on' : '');
      b.setAttribute('aria-label', 'Announcements ' + (i + 1) + ' of ' + pages);
      b.addEventListener('click', (function(n){ return function(){ hold(); go(n); }; })(i));
      dots.appendChild(b);
    }
  }
  function paint(){
    var d = dots.children;
    for (var i = 0; i < d.length; i++) d[i].classList.toggle('on', i === page);
  }
  /* the last page is usually a partial one — the browser clamps the scroll
     there, so measure against the real maximum or the dots start lying */
  function maxScroll(){ return Math.max(0, track.scrollWidth - track.clientWidth); }
  function go(n){
    page = (n + pages) % pages;
    track.scrollTo({ left: Math.min(Math.round(page * per * step()), maxScroll()), behavior: still ? 'auto' : 'smooth' });
    paint();
  }
  /* a click or a hover means they're reading — stop moving under them */
  function stop(){ if (timer) { clearInterval(timer); timer = null; } }
  function start(){ if (still || pages < 2 || timer) return; timer = setInterval(function(){ go(page + 1); }, wait); }
  function hold(){ stop(); clearTimeout(resume); resume = setTimeout(start, 15000); }

  prev.addEventListener('click', function(){ hold(); go(page - 1); });
  next.addEventListener('click', function(){ hold(); go(page + 1); });
  rot.addEventListener('mouseenter', stop);
  rot.addEventListener('mouseleave', start);
  rot.addEventListener('focusin', stop);
  rot.addEventListener('touchstart', hold, { passive: true });
  document.addEventListener('visibilitychange', function(){ document.hidden ? stop() : start(); });

  /* a swipe changes scrollLeft directly — keep the dots honest */
  var settle;
  track.addEventListener('scroll', function(){
    clearTimeout(settle);
    settle = setTimeout(function(){
      var max = maxScroll();
      var n = max <= 0 ? 0 : Math.round(track.scrollLeft / max * (pages - 1));
      if (n !== page && n >= 0 && n < pages) { page = n; paint(); }
    }, 120);
  }, { passive: true });

  var resize;
  window.addEventListener('resize', function(){
    clearTimeout(resize);
    resize = setTimeout(function(){ measure(); go(page); }, 150);
  });

  measure(); go(0); start();
})();
</script>

<?php legacy_footer(); page_foot();
