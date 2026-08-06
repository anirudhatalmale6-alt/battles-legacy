<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/news_data.php';
try { news_migrate(); $POSTS = news_posts(); $EVENTS = news_events(); }
catch (Exception $ex) { $POSTS = $EVENTS = []; }

$isAdmin = role_at_least('admin');

/* line-icon set */
function news_icon($k) {
  $p = [
    'baby'    => '<circle cx="12" cy="8" r="3.4"/><path d="M6 21c0-3.3 2.7-6 6-6s6 2.7 6 6M9 8h.01M15 8h.01"/>',
    'cap'     => '<path d="M12 5L2 9l10 4 10-4-10-4zM6 11v4c0 1.6 2.7 3 6 3s6-1.4 6-3v-4M20 9.5v4.5"/>',
    'rings'   => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/><path d="M9 9l1.5-4h3L15 9"/>',
    'people'  => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
    'dove'    => '<path d="M3 13c4 .5 7-1.5 9-5 0 4 2 6 5 6 2 0 4-1.4 4-1.4-1 4-4.4 6.4-8 6.4-4.6 0-8-2.6-10-6z"/><path d="M12 8V4"/>',
    'hands'   => '<path d="M12 21c4-2.5 7-5.6 7-9.3A3.3 3.3 0 0 0 12 9a3.3 3.3 0 0 0-7 2.7C5 15.4 8 18.5 12 21z"/>',
    'news'    => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M7 9h7M7 12h10M7 15h6"/>',
    'lily'    => '<path d="M12 21c0-5-3-8-8-8 0-3 3-5 8-2 5-3 8-1 8 2-5 0-8 3-8 8z"/><path d="M12 21V9"/>',
    'calendar'=> '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
    'heart'   => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'chat'    => '<path d="M4 5h16v11H8l-4 3z"/>',
    'question'=> '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 4.5 1.5c0 1.5-2 2-2 3.5M12 17h.01"/>',
    'recipe'  => '<path d="M8 3v7M6 3v4a2 2 0 0 0 4 0V3M8 10v11M16 3c-1.5 0-2.5 2-2.5 5s1 4 2.5 4v9"/>',
    'plus'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
  ];
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$k] ?? '<circle cx="12" cy="12" r="8"/>') . '</svg>';
}

$STRIP = [
  ['baby','Births','Welcoming new life'],
  ['cap','Graduations','Celebrating achievements'],
  ['rings','Marriages','Two hearts, one legacy'],
  ['people','Reunions','Coming together, staying connected'],
  ['dove','Deaths','Remembering lives, honoring legacies'],
  ['hands','Prayers','Lift up one another in faith'],
];

/* sample content for the lower panels (matches the mockup; editable modules can be wired later) */
$PRAYERS = [
  ['Please keep my mother, Patricia, in your prayers as she recovers from surgery.','Tamisha B.','May 15, 2024',24],
  ['Pray for safe travels for our family as we come together for the reunion.','James B.','May 14, 2024',18],
  ['Prayers for wisdom and guidance as I start this new business venture.','Danielle B.','May 13, 2024',15],
];
$QUESTIONS = [
  ['Does anyone have information about our ancestor Susan Mipus?','Robert Battles','May 14, 2024',7],
  ['Can anyone share photos from the 1985 family reunion in Tyler?','Angela Johnson','May 13, 2024',12],
  ['Looking for recipes from Grandma Louisa&rsquo;s cookbook.','Monique Battles','May 12, 2024',5],
];
$RECIPES = [
  ['Grandma Louisa&rsquo;s Sweet Potato Pie','Patricia H.',42],
  ['Uncle Calvin&rsquo;s Famous BBQ Ribs','Michael B.',38],
  ['Aunt Settie&rsquo;s Cornbread Dressing','Diane K.',29],
];
$UPDATES = [
  ['The Johnson cousins reunited in Atlanta!','May 16, 2024'],
  ['Happy Mother&rsquo;s Day to all our amazing mothers!','May 12, 2024'],
  ['Visited the historic Garfield School in Tyler.','May 10, 2024'],
  ['Family prayer call tonight at 8 PM. All are welcome!','May 9, 2024'],
];
function fn_ini($name) { $p = preg_split('/\s+/', trim($name)); return e(strtoupper(substr($p[0] ?? '', 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : ''))); }

page_head('Family News', ['body_class' => 'home fnews']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="news_manage.php">&#9998; Manage Family News</a>
  </div>
<?php endif; ?>

<!-- HERO -->
<section class="fn-hero">
  <img src="assets/news/hero.jpg" alt="Family News — Stay Connected. Stay Informed. Keep up with what's happening in our family.">
</section>

<!-- CATEGORY STRIP -->
<section class="fn-strip">
  <?php foreach ($STRIP as $s): ?>
    <div class="fn-scat"><span class="fn-sic"><?= news_icon($s[0]) ?></span><div><b><?= e($s[1]) ?></b><span><?= e($s[2]) ?></span></div></div>
  <?php endforeach; ?>
</section>

<div class="fn-wrap">
  <div class="fn-main-grid">

    <!-- NEWS & ANNOUNCEMENTS -->
    <section class="fn-news">
      <div class="fn-head"><h2><?= news_icon('news') ?> Family News &amp; Announcements</h2><?php if ($isAdmin): ?><a class="fn-viewall" href="news_manage.php">Manage &rsaquo;</a><?php else: ?><a class="fn-viewall" href="#" onclick="return fnSoon(this)">View All News &rsaquo;</a><?php endif; ?></div>
      <?php if ($POSTS): ?>
      <div class="fn-cards">
        <?php foreach ($POSTS as $p): $cat = news_cat($p['category']); ?>
          <article class="fn-card">
            <div class="fn-photo <?= e($cat[2]) ?>"<?= $p['photo'] ? ' style="background-image:url(\''.e($p['photo']).'\')"' : ' data-empty="1"' ?>>
              <span class="fn-tag <?= e($cat[2]) ?>"><?= e($cat[0]) ?></span>
              <?php if (!$p['photo']): ?><span class="fn-mono"><?= news_mono($p['title']) ?></span><?php endif; ?>
              <?php if ($p['sample']): ?><span class="fn-ex">Example</span><?php endif; ?>
            </div>
            <div class="fn-body">
              <?php if ($p['date_label']): ?><div class="fn-date"><?= e($p['date_label']) ?></div><?php endif; ?>
              <h3><?= e($p['title']) ?></h3>
              <?php if ($p['body']): ?><p><?= e($p['body']) ?></p><?php endif; ?>
              <div class="fn-meta"><span><?= news_icon('heart') ?> Like (<?= (int)$p['likes'] ?>)</span><span><?= news_icon('chat') ?> Comment (<?= (int)$p['comments'] ?>)</span></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
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
      <div class="fn-head sm"><h3><?= news_icon('hands') ?> Prayer Requests</h3><a class="fn-viewall" href="faith.php#prayer">View All &rsaquo;</a></div>
      <ul class="fn-list">
        <?php foreach ($PRAYERS as $p): ?>
          <li><span class="fn-av"><?= fn_ini($p[1]) ?></span>
            <div class="fn-li"><p><?= $p[0] /* authored */ ?></p><span class="fn-by">Requested by <?= e($p[1]) ?> &middot; <?= e($p[2]) ?></span></div>
            <span class="fn-like"><?= news_icon('heart') ?> <?= (int)$p[3] ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a class="btn2 solid" href="faith.php#prayer">Submit a Prayer Request</a>
    </section>
    <section class="fn-col">
      <div class="fn-head sm"><h3><?= news_icon('question') ?> Ask Questions</h3><a class="fn-viewall" href="#" onclick="return fnSoon(this)">View All &rsaquo;</a></div>
      <ul class="fn-list">
        <?php foreach ($QUESTIONS as $q): ?>
          <li><span class="fn-av q"><?= news_icon('question') ?></span>
            <div class="fn-li"><p><?= $q[0] /* authored */ ?></p><span class="fn-by">Asked by <?= e($q[1]) ?> &middot; <?= e($q[2]) ?></span></div>
            <span class="fn-like"><?= news_icon('chat') ?> <?= (int)$q[3] ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a class="btn2 solid" href="#" onclick="return fnSoon(this)">Ask a Question</a>
      <span class="fn-soon">Coming soon</span>
    </section>
    <section class="fn-col">
      <div class="fn-head sm"><h3><?= news_icon('recipe') ?> Share a Recipe</h3><a class="fn-viewall" href="#" onclick="return fnSoon(this)">View All &rsaquo;</a></div>
      <ul class="fn-list">
        <?php foreach ($RECIPES as $r): ?>
          <li><span class="fn-av r"><?= news_icon('recipe') ?></span>
            <div class="fn-li"><p class="fn-rtitle"><?= $r[0] /* authored */ ?></p><span class="fn-by">Shared by <?= e($r[1]) ?></span></div>
            <span class="fn-like"><?= news_icon('heart') ?> <?= (int)$r[2] ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a class="btn2 solid" href="#" onclick="return fnSoon(this)">Share a Recipe</a>
      <span class="fn-soon">Coming soon</span>
    </section>
  </div>
</div>

<!-- STAY CONNECTED + RECENT UPDATES -->
<div class="fn-wrap" style="padding-bottom:34px">
  <div class="fn-connect">
    <section class="fn-col fn-stay">
      <div class="fn-head sm"><h3><?= news_icon('people') ?> Stay Connected</h3></div>
      <p class="fn-cmt">Share updates, photos, and words of encouragement. Let&rsquo;s keep our family strong and connected!</p>
      <a class="btn2 solid" href="#" onclick="return fnSoon(this)"><?= news_icon('plus') ?> Post an Update</a>
      <span class="fn-soon">Coming soon</span>
    </section>
    <section class="fn-col fn-recent">
      <div class="fn-head sm"><h3>Recent Family Updates</h3><a class="fn-viewall" href="#" onclick="return fnSoon(this)">View All &rsaquo;</a></div>
      <div class="fn-updates">
        <?php foreach ($UPDATES as $u): ?>
          <div class="fn-update"><span class="fn-uthumb"><?= news_icon('people') ?></span><div class="fn-li"><p><?= $u[0] /* authored */ ?></p><span class="fn-by"><?= e($u[1]) ?></span></div></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div>

<script>
function fnSoon(a){ var c=a.closest('.fn-col,.fn-events'); var s=c?c.querySelector('.fn-soon'):null; if(s) s.classList.add('show'); return false; }
</script>

<?php legacy_footer(); page_foot();
