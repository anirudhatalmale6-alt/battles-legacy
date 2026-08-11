<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/news_data.php';
require_once __DIR__ . '/../src/community_data.php';
$TOTAL = 0;
try {
    news_migrate(); community_migrate();
    $POSTS = news_posts(false, '', 8);   // latest eight; the rest live in the archive
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
  <img src="assets/news/hero.jpg" alt="Family News — Stay Connected. Stay Informed. Keep up with what's happening in our family.">
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
      <div class="fn-cards">
        <?php foreach ($POSTS as $p) echo news_card($p); ?>
      </div>
      <?php if ($TOTAL > count($POSTS)): ?>
        <a class="btn2 solid fn-allbtn" href="news_all.php">See all <?= (int)$TOTAL ?> announcements</a>
      <?php endif; ?>
      <?php else: ?>
        <p class="fn-empty"><?= $isAdmin ? 'No news yet — add your first announcement from Manage Family News.' : 'Family news will be shared here soon.' ?></p>
      <?php endif; ?>
    </section>

    <!-- UPCOMING EVENTS -->
    <aside class="fn-events">
      <div class="fn-head"><h2><?= news_icon('calendar') ?> Upcoming Events</h2><a class="fn-viewall" href="#" onclick="return fnSoon(this)">View Calendar &rsaquo;</a></div>
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
        <button type="button" class="btn2 solid fn-allbtn" onclick="fnSoon(this)">View all Events</button>
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
          <li><span class="fn-av r"><?= news_icon('recipe') ?></span>
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
          <div class="fn-update"><span class="fn-uthumb"<?= $u['photo'] ? ' style="background-image:url(\''.e($u['photo']).'\');background-size:cover"' : '' ?>><?= $u['photo']?'':news_icon('people') ?></span><div class="fn-li"><p><?= e(mb_strimwidth($u['body'],0,110,'…')) ?></p><span class="fn-by"><?= e($u['author']) ?> &middot; <?= e(comm_ago($u['created_at'])) ?></span></div></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><p class="fn-cmt" style="margin:0">No updates yet &mdash; be the first to share family news.</p><?php endif; ?>
    </section>
  </div>
</div>

<script>
function fnSoon(a){ var c=a.closest('.fn-col,.fn-events'); var s=c?c.querySelector('.fn-soon'):null; if(s) s.classList.add('show'); return false; }
</script>

<?php legacy_footer(); page_foot();
