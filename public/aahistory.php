<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/community_data.php';
try { community_migrate(); $QLIST = comm_list('question', 'published', 3); }
catch (Exception $ex) { $QLIST = []; }

$logged  = logged_in();
$isAdmin = role_at_least('admin');

function aah_icon($k) {
  $p = [
    'star'   => '<path d="M12 3.2l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.4l5.9-.8z"/>',
    'mega'   => '<path d="M4 10v4h3l6 4V6l-6 4H4z"/><path d="M17 9a4 4 0 0 1 0 6"/>',
    'bulb'   => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.8.7 1 1.4 1 2.5h6c0-1.1.2-1.8 1-2.5A6 6 0 0 0 12 3z"/>',
    'gov'    => '<path d="M4 10h16M3 10l9-6 9 6M6 10v7M10 10v7M14 10v7M18 10v7M4 20h16"/>',
    'arts'   => '<path d="M12 4a8 8 0 1 0 0 16c1.5 0 2-1 1.4-2-.7-1.2.2-2.4 1.6-2.4H17a3.5 3.5 0 0 0 3.5-3.5C20.5 7.3 16.7 4 12 4z"/><circle cx="8" cy="10" r="1"/><circle cx="12" cy="8" r="1"/><circle cx="16" cy="10" r="1"/>',
    'sci'    => '<path d="M9 3v6l-4.5 8A2 2 0 0 0 6.3 20h11.4a2 2 0 0 0 1.8-3L15 9V3M8 3h8M8.5 14h7"/>',
    'sport'  => '<circle cx="12" cy="12" r="9"/><path d="M12 3c3 3 3 15 0 18M3 12h18M5 6c4 2 10 2 14 0M5 18c4-2 10-2 14 0"/>',
    'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'check'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
    'book'   => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M12 6v9"/>',
    'chat'   => '<path d="M4 5h16v11H8l-4 3z"/>',
    'people' => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
    'doc'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h5"/>',
    'ship'   => '<path d="M4 17h16l-2 4H6zM12 3v10M6 13h12l-1-4H7z"/>',
    'scroll' => '<path d="M6 4h11a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    'vote'   => '<rect x="3" y="12" width="18" height="8" rx="2"/><path d="M8 12V6h8v6M12 8v3"/>',
    'scale'  => '<path d="M12 3v18M5 7h14M7 7l-3 6h6zM17 7l-3 6h6z"/>',
    'hands'  => '<path d="M4 13l4-3 4 3 4-3 4 3M4 17l4-3 4 3 4-3 4 3"/>',
    'flag'   => '<path d="M6 21V4M6 4h11l-2 3 2 3H6"/>',
    'rise'   => '<path d="M4 18l5-5 3 3 7-7"/><path d="M15 9h4v4"/>',
  ];
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$k] ?? '<circle cx="12" cy="12" r="8"/>') . '</svg>';
}
function aah_mono($name) {
  $parts = array_values(array_filter(preg_split('/\s+/', trim($name))));
  if (!$parts) return '&#10022;';
  $ini = strtoupper(substr($parts[0],0,1) . (count($parts)>1 ? substr(end($parts),0,1) : ''));
  return e($ini);
}

$NAV = [
  ['star','Trailblazers','#trailblazers'], ['mega','Civil Rights','#civil'], ['bulb','Inventions &amp; Innovation','#inventions'],
  ['gov','Politics &amp; Leadership','#politics'], ['arts','Arts &amp; Culture','#arts'], ['sci','Science &amp; Medicine','#science'],
  ['sport','Sports','#sports'], ['clock','Timeline','#timeline'],
];
$TRAILBLAZERS = [
  ['Frederick Douglass','Abolitionist &amp; Author'],
  ['Harriet Tubman','Freedom Fighter &amp; Humanitarian'],
  ['Booker T. Washington','Educator &amp; Advisor'],
  ['Madam C.J. Walker','Entrepreneur &amp; Philanthropist'],
  ['Thurgood Marshall','Supreme Court Justice'],
];
$INVENTIONS = [
  ['Lewis Latimer','Improved the light bulb and electrical systems'],
  ['Alexander Miles','Invented the automatic elevator doors'],
  ['Garrett Morgan','Invented the traffic signal'],
  ['Thomas L. Jennings','Invented the dry cleaning process'],
];
$CIVIL = ['Fought for equality','Challenged injustice','Changed laws','Inspired generations'];
$POLITICS = [
  ['Shirley Chisholm','1st Black Woman in Congress'],
  ['Barack Obama','44th President of the United States'],
  ['Kamala Harris','1st Black &amp; South Asian Vice President'],
  ['Colin Powell','Chairman of the Joint Chiefs of Staff'],
  ['Condoleezza Rice','1st Black Woman Secretary of State'],
];
$ACHIEVEMENTS = ['The 13th, 14th &amp; 15th Amendments','Brown v. Board of Education (1954)','The Civil Rights Act (1964)','The Voting Rights Act (1965)','End of Legal Segregation'];
$ARTS = [['arts','Music'],['arts','Visual Arts'],['book','Literature &amp; Theater'],['star','Dance']];
$SCIENCE = [
  ['Dr. Daniel Hale Williams','Pioneered open-heart surgery'],
  ['Dr. Mae Jemison','1st Black Woman in Space'],
  ['George Washington Carver','Innovative Scientist &amp; Inventor'],
];
$SPORTS = [
  ['Jackie Robinson','Broke baseball&rsquo;s colour barrier, 1947'],
  ['Wilma Rudolph','Three Olympic golds, 1960'],
  ['Muhammad Ali','Champion in the ring and for conscience'],
  ['Althea Gibson','1st Black champion at Wimbledon'],
];
$TIMELINE = [
  ['ship','1619','First enslaved Africans arrive in America'],
  ['scroll','1863','Emancipation Proclamation'],
  ['people','1865','13th Amendment Abolishes Slavery'],
  ['vote','1920','19th Amendment &mdash; Women&rsquo;s Right to Vote'],
  ['scale','1954','Brown v. Board of Education'],
  ['hands','1964','Civil Rights Act Signed'],
  ['flag','2008','Barack Obama Elected President'],
  ['rise','2020+','Continuing the Legacy, Building the Future'],
];
$INVOLVED = [
  ['book','Share Family Stories','Add your family&rsquo;s contributions to our history.','update'],
  ['chat','Ask Questions','Learn more about our past and share what you know.','question'],
  ['doc','Contribute Resources','Add documents, photos, or historical information.','update'],
  ['people','Educate the Next Gen','Help our children know, respect, and be inspired.','update'],
];

page_head('African American History', ['body_class' => 'home aah']);
?>
<!-- HERO -->
<section class="aah-hero">
  <div class="aah-hero-in">
    <h1>African American<br><span>History</span></h1>
    <div class="aah-orn">&#10022;</div>
    <p class="aah-tag">Honoring our past. Celebrating our present. Inspiring our future.</p>
    <p class="aah-sub">From resilience to triumph, our history is filled with courageous leaders, brilliant minds,
       and everyday people who shaped our nation and the world.</p>
  </div>
</section>

<!-- QUICK NAV -->
<section class="aah-nav">
  <?php foreach ($NAV as $n): ?>
    <a class="aah-navitem" href="<?= e($n[2]) ?>"><span class="aah-nic"><?= aah_icon($n[0]) ?></span><span><?= $n[1] /* authored */ ?></span></a>
  <?php endforeach; ?>
</section>

<div class="aah-wrap">
  <!-- TRAILBLAZERS + INVENTIONS -->
  <div class="aah-two">
    <section class="aah-card" id="trailblazers">
      <h2>Trailblazers Who Changed the World</h2>
      <p class="aah-note">Courageous men and women who broke barriers and paved the way.</p>
      <div class="aah-people">
        <?php foreach ($TRAILBLAZERS as $t): ?>
          <div class="aah-person"><span class="aah-face"><?= aah_mono($t[0]) ?></span><b><?= e($t[0]) ?></b><span><?= $t[1] /* authored */ ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="aah-card" id="inventions">
      <h2>Inventions &amp; Innovations</h2>
      <p class="aah-note">Brilliant minds. Powerful ideas. Real impact.</p>
      <div class="aah-people four">
        <?php foreach ($INVENTIONS as $t): ?>
          <div class="aah-person"><span class="aah-face inv"><?= aah_icon('bulb') ?></span><b><?= e($t[0]) ?></b><span><?= e($t[1]) ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <!-- CIVIL RIGHTS + POLITICS -->
  <div class="aah-two">
    <section class="aah-card" id="civil">
      <h2>Civil Rights Movement</h2>
      <p class="aah-note">A movement of hope, unity, and unwavering determination.</p>
      <ul class="aah-list">
        <?php foreach ($CIVIL as $c): ?><li><span class="aah-ci"><?= aah_icon('check') ?></span><?= e($c) ?></li><?php endforeach; ?>
      </ul>
    </section>

    <section class="aah-card" id="politics">
      <h2>Politics &amp; Leadership</h2>
      <p class="aah-note">Leaders who have served, represented, and paved the way.</p>
      <div class="aah-people">
        <?php foreach ($POLITICS as $t): ?>
          <div class="aah-person"><span class="aah-face"><?= aah_mono($t[0]) ?></span><b><?= e($t[0]) ?></b><span><?= $t[1] /* authored */ ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <!-- ACHIEVEMENTS + ARTS + SCIENCE -->
  <div class="aah-three">
    <section class="aah-card">
      <h2 class="sm">Historic Achievements</h2>
      <p class="aah-note">Milestones that made history.</p>
      <ul class="aah-list star">
        <?php foreach ($ACHIEVEMENTS as $a): ?><li><span class="aah-ci"><?= aah_icon('star') ?></span><?= $a /* authored */ ?></li><?php endforeach; ?>
      </ul>
    </section>

    <section class="aah-card" id="arts">
      <h2 class="sm">Arts &amp; Culture</h2>
      <p class="aah-note">Our stories. Our music. Our influence.</p>
      <div class="aah-arts">
        <?php foreach ($ARTS as $a): ?><div class="aah-art"><span class="aah-aic"><?= aah_icon($a[0]) ?></span><span><?= $a[1] /* authored */ ?></span></div><?php endforeach; ?>
      </div>
    </section>

    <section class="aah-card" id="science">
      <h2 class="sm">Science &amp; Medicine</h2>
      <p class="aah-note">Pioneers in discovery and healing.</p>
      <ul class="aah-list">
        <?php foreach ($SCIENCE as $s): ?><li><span class="aah-ci"><?= aah_icon('check') ?></span><div><b><?= e($s[0]) ?></b><span class="aah-role"><?= $s[1] /* authored */ ?></span></div></li><?php endforeach; ?>
      </ul>
    </section>
  </div>

  <!-- SPORTS -->
  <section class="aah-card" id="sports" style="margin-bottom:20px">
    <h2 class="sm">Sports</h2>
    <p class="aah-note">Champions who changed the game &mdash; and the country.</p>
    <div class="aah-people four">
      <?php foreach ($SPORTS as $t): ?>
        <div class="aah-person"><span class="aah-face"><?= aah_mono($t[0]) ?></span><b><?= e($t[0]) ?></b><span><?= $t[1] /* authored */ ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- TIMELINE -->
  <section class="aah-card aah-timeline" id="timeline">
    <h2 class="center">A Timeline of Our History</h2>
    <div class="aah-tl">
      <?php foreach ($TIMELINE as $t): ?>
        <div class="aah-tli"><span class="aah-tic"><?= aah_icon($t[0]) ?></span><b><?= e($t[1]) ?></b><span><?= $t[2] /* authored */ ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- QUOTE + GET INVOLVED + ASK -->
  <div class="aah-three aah-bottom">
    <section class="aah-card aah-quote">
      <blockquote>Our history is not just African American history. It is American history.<cite>&mdash; Maya Angelou</cite></blockquote>
    </section>

    <section class="aah-card">
      <h2 class="sm center">How You Can Get Involved</h2>
      <div class="aah-inv">
        <?php foreach ($INVOLVED as $i): ?>
          <a class="aah-invi" href="<?= $logged ? 'community_submit.php?kind='.e($i[3]) : 'login.php' ?>">
            <span class="aah-aic"><?= aah_icon($i[0]) ?></span><b><?= e($i[1]) ?></b><span><?= $i[2] /* authored */ ?></span></a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="aah-card">
      <h2 class="sm"><?= aah_icon('chat') ?> Ask a Question</h2>
      <p class="aah-note">Have a question about our history? Ask and learn together.</p>
      <?php if ($QLIST): ?>
        <ul class="fn-list">
          <?php foreach ($QLIST as $q): ?>
            <li><span class="fn-av q"><?= aah_icon('chat') ?></span>
              <div class="fn-li"><p><a href="community_view.php?id=<?= (int)$q['id'] ?>"><?= e(mb_strimwidth($q['body'],0,70,'…')) ?></a></p><span class="fn-by">Asked by <?= e($q['author']) ?></span></div></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a class="btn2 solid" href="<?= $logged ? 'community_submit.php?kind=question' : 'login.php' ?>">Submit Question</a>
      <a class="h-browse" href="community_list.php?kind=question">Browse Questions &amp; Answers &rarr;</a>
    </section>
  </div>
</div>

<!-- CLOSING -->
<section class="aah-closing">
  <span class="fvq">&ldquo;</span>The past is our teacher. The present is our responsibility. The future is our legacy.
</section>

<?php legacy_footer(); page_foot();
