<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/health_data.php';
require_once __DIR__ . '/../src/community_data.php';
try {
    health_migrate(); community_migrate();
    $TIPS = health_tips(); $EVENTS = health_events();
    $QLIST = comm_list('question', 'published', 3);
    $FTIPS = comm_list('healthtip', 'published', 6);   // tips the family shared
} catch (Exception $ex) { $TIPS = $EVENTS = $QLIST = $FTIPS = []; }

$isAdmin = role_at_least('admin');
$logged  = logged_in();

function h_icon($k) {
  $p = [
    'bulb'   => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.8.7 1 1.4 1 2.5h6c0-1.1.2-1.8 1-2.5A6 6 0 0 0 12 3z"/>',
    'dumbbell'=> '<path d="M4 9v6M7 7v10M17 7v10M20 9v6M7 12h10"/>',
    'apple'  => '<path d="M12 8c-3 0-5 2-5 5.5S9.5 21 12 21s5-4 5-7.5S15 8 12 8z"/><path d="M12 8c0-2 1-3.5 3-4"/>',
    'steth'  => '<path d="M6 3v5a4 4 0 0 0 8 0V3"/><path d="M10 12v3a5 5 0 0 0 9 3"/><circle cx="19" cy="16" r="2"/>',
    'chat'   => '<path d="M4 5h16v11H8l-4 3z"/>',
    'book'   => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M12 6v9"/>',
    'clip'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM8 11h8M8 15h6"/>',
    'heart'  => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'people' => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
    'cross'  => '<path d="M10 3h4v5h5v4h-5v9h-4v-9H5V8h5z"/>',
    'check'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
    'run'    => '<circle cx="14" cy="5" r="2"/><path d="M12 8l-3 4 3 2 1 5M9 12l-4 1M13 14l4 2"/>',
    'moon'   => '<path d="M20 14a8 8 0 1 1-10-10 7 7 0 0 0 10 10z"/>',
    'water'  => '<path d="M12 3s6 6.5 6 10a6 6 0 0 1-12 0c0-3.5 6-10 6-10z"/>',
    'focus'  => '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
    'calendar'=> '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
    'screen' => '<rect x="4" y="4" width="16" height="13" rx="2"/><path d="M9 21h6M12 17v4M8 10l2.5 2.5L16 7"/>',
    'food'   => '<path d="M8 3v7M6 3v4a2 2 0 0 0 4 0V3M8 10v11M16 3c-1.5 0-2.5 2-2.5 5s1 4 2.5 4v9"/>',
    'mind'   => '<path d="M12 4a5 5 0 0 0-5 5c0 1.5.6 2.6 1.4 3.6.7.9 1.1 1.6 1.1 2.9h5c0-1.3.4-2 1.1-2.9C16.4 11.6 17 10.5 17 9a5 5 0 0 0-5-5z"/><path d="M10 20h4"/>',
    'walk'   => '<circle cx="13" cy="4.5" r="2"/><path d="M11 8l-2 5 3 2 1 5M9 13l-3 6M14 15l4 1"/>',
  ];
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$k] ?? '<circle cx="12" cy="12" r="8"/>') . '</svg>';
}

/* Each pillar under the banner jumps to the part of the page it belongs to —
   they used to be plain text and William asked whether they went anywhere. */
$PILLARS = [
  ['heart','Body','Take care of your body.','#exercise'],
  ['apple','Mind','Take care of your thoughts.','#resources'],
  ['people','Family','Take care of each other.','#familytips'],
  ['cross','Faith','Take care of your spirit.','faith.php'],
];
$QUICKNAV = [
  ['bulb','Health Tips','#tips'], ['dumbbell','Exercise','#exercise'], ['apple','Nutrition','#nutrition'],
  ['steth','Doctor Visits','#checkups'], ['people','Family Tips','#familytips'], ['chat','Ask Questions','#ask'], ['book','Resources','#resources'], ['clip','Track Your Health','#track'],
];
$CHECKUPS = ['Detect problems early','Prevent illness','Manage chronic conditions','Stay up to date on screenings','Live longer and healthier'];
$EXERCISE = [
  ['dumbbell','STRENGTH','Build muscle and stay strong.'],
  ['heart','CARDIO','Keep your heart healthy.'],
  ['run','STRETCH','Improve flexibility and reduce pain.'],
  ['focus','BALANCE','Improve stability and prevent falls.'],
];
$EATING = ['Eat more fruits and vegetables','Choose whole foods','Limit sugar and processed foods','Drink plenty of water','Plan balanced meals'];
$TRACK  = ['Blood Pressure','Cholesterol','Weight','Blood Sugar','Medications','Appointments'];
$RESOURCES = [
  ['book','Health Articles'], ['screen','Recommended Screenings'], ['check','Vaccines &amp; Immunizations'],
  ['mind','Mental Health Support'], ['heart','Senior Health'], ['people','Community Resources'],
];
$CHALLENGE = [
  ['run','MOVE','30 Minutes Daily'], ['apple','EAT','Healthy Foods'], ['moon','SLEEP','7&ndash;8 Hours Nightly'],
  ['water','HYDRATE','Drink More Water'], ['focus','FOCUS','On Your Well-Being'],
];

page_head('Health', ['body_class' => 'home hlth']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="health_manage.php">&#9998; Manage health tips &amp; events</a>
  </div>
<?php endif; ?>

<!-- HERO -->
<section class="h-hero">
  <img class="h-hero-img" src="assets/health/hero.jpg" alt="Healthy Today, Stronger Tomorrow — better choices, stronger bodies, peace of mind.">
  <!-- Same as the Family News banner: the words are part of the picture, and
       the picture is an inch and a half tall on a phone. The four pillars are
       already repeated underneath in .h-pillars, so this only has to carry the
       headline and the sentence under it. -->
  <div class="h-hero-inner">
    <h1 class="h-h1">Healthy Today,<span class="h-script">Stronger Tomorrow</span></h1>
    <div class="h-orn">&#10084;</div>
    <p class="h-intro">Better choices. Stronger bodies. Peace of mind. Small steps today lead to a
       healthier tomorrow for you and generations to come.</p>
  </div>
</section>

<!-- PILLARS (under hero, mobile-friendly repeat of the hero's four) -->
<section class="h-pillars">
  <?php foreach ($PILLARS as $p): ?>
    <a class="h-pill" href="<?= e($p[3]) ?>"><span class="h-pic"><?= h_icon($p[0]) ?></span><div><b><?= e($p[1]) ?></b><span><?= e($p[2]) ?></span></div></a>
  <?php endforeach; ?>
</section>

<!-- QUICK NAV -->
<section class="h-nav">
  <?php foreach ($QUICKNAV as $n): ?>
    <a class="h-navitem" href="<?= e($n[2]) ?>"><span class="h-nic"><?= h_icon($n[0]) ?></span><span><?= e($n[1]) ?></span></a>
  <?php endforeach; ?>
</section>

<div class="h-wrap">
  <!-- DAILY TIP + CHECK-UPS -->
  <div class="h-two" id="tips">
    <section class="h-card h-tip">
      <div class="h-tipbody">
        <h2>Daily Health Tip</h2>
        <div class="h-orn">&#10084;</div>
        <?php if ($TIPS): ?>
          <blockquote id="h-tiptext"><?= e($TIPS[0]['tip']) ?></blockquote>
        <?php else: ?><blockquote>Small steps today lead to a healthier tomorrow.</blockquote><?php endif; ?>
        <?php if ($isAdmin): ?><a class="btn2" href="health_manage.php">More health tips</a><?php endif; ?>
      </div>
      <div class="h-tipimg" style="background-image:url('assets/health/tip.jpg')"></div>
    </section>

    <section class="h-card h-check" id="checkups">
      <div class="h-checkbody">
        <h2>Why Regular Check-Ups Matter</h2>
        <ul class="h-list">
          <?php foreach ($CHECKUPS as $c): ?><li><span class="h-ci"><?= h_icon('check') ?></span><?= e($c) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="h-checkimg" style="background-image:url('assets/health/doctor.jpg')"></div>
    </section>
  </div>

  <!-- EXERCISE + NUTRITION -->
  <div class="h-two">
    <section class="h-card h-pad" id="exercise">
      <h2>Exercise for Every Body</h2>
      <p class="h-sub">Find activities that fit your lifestyle and keep you moving!</p>
      <div class="h-ex">
        <?php foreach ($EXERCISE as $x): ?>
          <div class="h-exi"><span class="h-exic"><?= h_icon($x[0]) ?></span><b><?= e($x[1]) ?></b><span><?= e($x[2]) ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="h-card h-eat" id="nutrition">
      <div class="h-pad">
        <h2>Better Eating, Better Living</h2>
        <p class="h-sub">Fuel your body with nutritious foods.</p>
        <ul class="h-list">
          <?php foreach ($EATING as $c): ?><li><span class="h-ci"><?= h_icon('check') ?></span><?= e($c) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="h-eatimg" style="background-image:url('assets/health/food.jpg')"></div>
    </section>
  </div>

  <!-- TRACK / ASK / RESOURCES -->
  <div class="h-three">
    <section class="h-card h-pad" id="track">
      <h2 class="sm">Track Your Health</h2>
      <p class="h-sub">Keep track of what matters.</p>
      <ul class="h-list sm">
        <?php foreach ($TRACK as $t): ?><li><span class="h-ci"><?= h_icon('check') ?></span><?= e($t) ?></li><?php endforeach; ?>
      </ul>
      <p class="h-note">Bring these numbers to your next visit &mdash; a simple notebook or your phone works well.</p>
    </section>

    <section class="h-card h-pad" id="ask">
      <h2 class="sm"><?= h_icon('chat') ?> Ask Questions</h2>
      <p class="h-sub">Have a health question? Ask our family community.</p>
      <?php if ($QLIST): ?>
        <ul class="fn-list">
          <?php foreach ($QLIST as $q): ?>
            <li><span class="fn-av q"><?= h_icon('chat') ?></span>
              <div class="fn-li"><p><a href="community_view.php?id=<?= (int)$q['id'] ?>"><?= e(mb_strimwidth($q['body'],0,80,'…')) ?></a></p><span class="fn-by">Asked by <?= e($q['author']) ?></span></div></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p class="h-note">No questions yet &mdash; be the first to ask the family.</p><?php endif; ?>
      <a class="btn2 solid" href="<?= $logged ? 'community_submit.php?kind=question' : 'login.php' ?>">Submit a Question</a>
      <a class="h-browse" href="community_list.php?kind=question">Browse Questions &amp; Answers &rarr;</a>
    </section>

    <section class="h-card h-pad" id="resources">
      <h2 class="sm">Health Resources</h2>
      <p class="h-sub">Helpful information for you and your family.</p>
      <ul class="h-res">
        <?php foreach ($RESOURCES as $r): ?><li><span class="h-ri"><?= h_icon($r[0]) ?></span><?= $r[1] /* authored */ ?></li><?php endforeach; ?>
      </ul>
      <p class="h-note">Ask William to add links to the resources your family uses most.</p>
    </section>
  </div>

  <!-- SCRIPTURE + CHALLENGE + EVENTS -->
  <div class="h-three h-bottom">
    <section class="h-card h-verse">
      <h2 class="sm">Mind, Body &amp; Spirit</h2>
      <div class="h-orn">&#10084;</div>
      <blockquote>I wish above all things that thou mayest prosper and be in health, even as thy soul prospereth.<cite>&mdash; 3 John 1:2</cite></blockquote>
    </section>

    <section class="h-card h-pad h-chal">
      <h2 class="sm">Join Our Healthy Family Challenge</h2>
      <p class="h-sub">Small changes. Big impact.</p>
      <div class="h-chalrow">
        <?php foreach ($CHALLENGE as $c): ?>
          <div class="h-chali"><span class="h-exic"><?= h_icon($c[0]) ?></span><b><?= e($c[1]) ?></b><span><?= $c[2] /* authored */ ?></span></div>
        <?php endforeach; ?>
      </div>
      <a class="btn2 solid" href="<?= $logged ? 'community_submit.php?kind=healthtip' : 'login.php' ?>">I&rsquo;m In!</a>
    </section>

    <section class="h-card h-pad h-events">
      <h2 class="sm"><?= h_icon('calendar') ?> Upcoming Health Events</h2>
      <?php if ($EVENTS): ?>
        <?php foreach ($EVENTS as $ev): ?>
          <div class="h-event">
            <span class="h-eic"><?= h_icon($ev['icon']) ?></span>
            <div><b><?= e($ev['title']) ?></b><span><?= e(trim($ev['mon'] . ' ' . $ev['day'])) ?><?= $ev['detail'] ? ' &middot; ' . e($ev['detail']) : '' ?></span></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?><p class="h-note"><?= $isAdmin ? 'No events yet — add one from Manage health tips & events.' : 'Health events will be posted here.' ?></p><?php endif; ?>
      <?php if ($isAdmin): ?><a class="h-browse" href="health_manage.php?tab=events">Manage events &rarr;</a><?php endif; ?>
    </section>
  </div>

  <!-- FAMILY-SHARED TIPS -->
  <section class="h-card h-pad h-fam" id="familytips">
    <div class="h-famhead">
      <h2 class="sm"><?= h_icon('people') ?> Tips From Our Family</h2>
      <?php if ($FTIPS): ?><a class="h-browse" href="community_list.php?kind=healthtip">See them all &rarr;</a><?php endif; ?>
    </div>
    <p class="h-sub">What has actually worked for us. Anything you share here lands on this page.</p>
    <?php if ($FTIPS): ?>
      <div class="h-famgrid">
        <?php foreach ($FTIPS as $t): ?>
          <a class="h-fami" href="community_view.php?id=<?= (int)$t['id'] ?>">
            <span class="h-famthumb"<?= $t['photo'] ? ' style="background-image:url(\''.e($t['photo']).'\')"' : '' ?>><?= $t['photo'] ? '' : h_icon('heart') ?></span>
            <span class="h-famtxt">
              <?php if (trim($t['title']) !== ''): ?><b><?= e($t['title']) ?></b><?php endif; ?>
              <span class="h-fambody"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $t['body']), 0, 120, '…')) ?></span>
              <span class="fn-by"><?= e($t['author']) ?> &middot; <?= e(comm_ago($t['created_at'])) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="h-note">No tips from the family yet &mdash; share the first one below and it appears right here.</p>
    <?php endif; ?>
  </section>

  <!-- SHARE A TIP -->
  <section class="h-share">
    <span class="h-shic"><?= h_icon('people') ?></span>
    <div><b>We&rsquo;re stronger when we take care of each other.</b><span>Share tips, encourage one another, and live healthy together.</span></div>
    <a class="btn2 solid" href="<?= $logged ? 'community_submit.php?kind=healthtip' : 'login.php' ?>"><?= h_icon('heart') ?> Share a Health Tip</a>
  </section>
</div>

<!-- CLOSING VERSE -->
<section class="h-closing">
  <span class="fvq">&ldquo;</span>Do you not know that your body is a temple of the Holy Spirit within you, whom you have from God, and that you are not your own?<span class="fvr">&mdash; 1 Corinthians 6:19</span>
</section>

<?php if (count($TIPS) > 1): ?>
<script>
(function(){
  var T = <?= json_encode(array_map(function($t){ return $t['tip']; }, $TIPS), JSON_UNESCAPED_UNICODE) ?>;
  var el = document.getElementById('h-tiptext'); if(!el || T.length < 2) return;
  var i = 0;
  setInterval(function(){ i=(i+1)%T.length; el.style.opacity=0; setTimeout(function(){ el.textContent=T[i]; el.style.opacity=1; }, 400); }, 7000);
})();
</script>
<?php endif; ?>

<?php legacy_footer(); page_foot();
