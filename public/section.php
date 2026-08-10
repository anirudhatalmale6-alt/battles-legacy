<?php
require __DIR__ . '/../src/bootstrap.php';

/* Sections that already have their own full pages — send visitors straight there. */
$realPages = ['history'=>'history.php','faith'=>'faith.php','enterprise'=>'enterprise.php','memorial'=>'memorial.php','news'=>'news.php','health'=>'health.php'];
$s = $_GET['s'] ?? '';
if (isset($realPages[$s])) { header('Location: ' . $realPages[$s]); exit; }

/* Landing pages for the remaining hub sections. */
$sections = [
  'health' => [
    'title'  => 'Health &amp; Wellness',
    'script' => 'Caring for Body, Mind &amp; Spirit',
    'intro'  => 'Good health is part of our legacy. This is a place to share the wisdom, habits, and care that keep our family strong &mdash; from the remedies passed down by our elders to the encouragement we give one another today.',
    'verse'  => ['Beloved, I wish above all things that thou mayest prosper and be in health, even as thy soul prospereth.', '3 John 1:2'],
    'cards'  => [
      ['heart',  'Family Health',        'Hereditary health notes and the conditions we watch for, so every generation can care for itself wisely.'],
      ['leaf',   'Wellness &amp; Wisdom','Recipes, remedies, and healthy habits handed down through the family &mdash; body, mind, and spirit.'],
      ['hands',  'Caring for One Another','Encouragement, resources, and support for loved ones walking through illness or recovery.'],
    ],
  ],
  'news' => [
    'title'  => 'Family News',
    'script' => 'Keeping Us Connected',
    'intro'  => 'Births, graduations, weddings, new jobs, reunions &mdash; the moments that keep our family close no matter the distance. This is where we celebrate one another and share what&rsquo;s happening in our lives.',
    'verse'  => ['That which we have seen and heard declare we unto you, that ye also may have fellowship with us.', '1 John 1:3'],
    'cards'  => [
      ['star',   'Milestones',       'Births, graduations, marriages, and achievements worth celebrating together as a family.'],
      ['calendar','Upcoming Events',  'Reunions, gatherings, and dates to remember, so no one misses a moment.'],
      ['news',   'Announcements',    'Share your good news with the whole family and cheer one another on.'],
    ],
  ],
  'aahistory' => [
    'title'  => 'African American History',
    'script' => 'Our Place in the Larger Story',
    'intro'  => 'Our family&rsquo;s journey is woven into the wider story of African American history &mdash; from slavery and emancipation to migration, faith, education, and enterprise. Here we honor that heritage and the shoulders we stand on.',
    'verse'  => ['One generation shall praise thy works to another, and shall declare thy mighty acts.', 'Psalm 145:4'],
    'cards'  => [
      ['book',   'Our Heritage',        'How our family&rsquo;s story connects to the greater journey of African Americans through the generations.'],
      ['flag',   'Milestones in History','The moments and movements &mdash; emancipation, migration, civil rights &mdash; that shaped our path.'],
      ['people', 'Standing on Shoulders','Honoring the ancestors and leaders whose courage and faith made our lives possible.'],
    ],
  ],
];

if (!isset($sections[$s])) {
    http_response_code(404);
    page_head('Not found');
    echo '<div class="panel" style="text-align:center;max-width:640px;margin:30px auto"><h1>Page not found</h1><p class="lede">That section isn\'t here. <a href="index.php" style="color:var(--gold2)">Back home</a>.</p></div>';
    page_foot(); exit;
}

function sec_icon($k) {
  $p = [
    'heart'  => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'leaf'   => '<path d="M5 19c0-8 6-14 14-14 0 8-6 14-14 14z"/><path d="M5 19c3-3 6-5 10-6"/>',
    'hands'  => '<path d="M12 21c4-2.5 7-5.6 7-9.3A3.3 3.3 0 0 0 12 9a3.3 3.3 0 0 0-7 2.7C5 15.4 8 18.5 12 21z"/>',
    'star'   => '<path d="M12 3.2l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.4l5.9-.8z"/>',
    'calendar'=> '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
    'news'   => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M7 9h7M7 12h10M7 15h6"/>',
    'book'   => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5zM20 18v3H6.5A2.5 2.5 0 0 1 4 18.5"/><path d="M12 6v9"/>',
    'flag'   => '<path d="M6 21V4M6 4h11l-2 3 2 3H6"/>',
    'people' => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
  ];
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$k] ?? '<circle cx="12" cy="12" r="8"/>') . '</svg>';
}

$d = $sections[$s];
page_head(html_entity_decode($d['title'], ENT_QUOTES), ['body_class' => 'home sec']);
?>
<section class="sec-hero">
  <h1><?= $d['title'] /* authored */ ?></h1>
  <div class="sec-script script"><?= $d['script'] /* authored */ ?></div>
  <div class="sec-orn">&#10086; &nbsp;&bull;&nbsp; &#10086;</div>
  <p class="sec-intro"><?= $d['intro'] /* authored */ ?></p>
</section>

<section class="sec-cards">
  <?php foreach ($d['cards'] as $c): ?>
    <div class="sec-card">
      <span class="sec-ic"><?= sec_icon($c[0]) ?></span>
      <h3><?= $c[1] /* authored */ ?></h3>
      <p><?= $c[2] /* authored */ ?></p>
    </div>
  <?php endforeach; ?>
</section>

<section class="sec-note">
  <p>We&rsquo;re gathering the stories, photos, and records for this section. Have something to add? Share it with the family
     &mdash; <?php if (logged_in()): ?>send it along and we&rsquo;ll add it here.<?php else: ?><a href="login.php">sign in</a> to contribute.<?php endif; ?></p>
</section>

<section class="sec-verse">
  <span class="fvq">&ldquo;</span><?= e($d['verse'][0]) ?><span class="fvr">&mdash; <?= e($d['verse'][1]) ?></span>
</section>

<?php legacy_footer(); page_foot();
