<?php
require __DIR__ . '/../src/bootstrap.php';

/* ============================================================
   ENTERPRISE — built to match the approved design.
   Driven by simple arrays so it converts cleanly into a
   self-service admin screen (add / edit / remove businesses,
   videos, sayings) once the look is signed off.
   Sample businesses are illustrative (tagged "Example").
   ============================================================ */

/* --- Five pillars (top icon bar) --- */
$PILLARS = [
  ['icon'=>'bulb',  'title'=>'Entrepreneurs',          'text'=>'Building businesses, creating opportunities, and leading with vision.'],
  ['icon'=>'case',  'title'=>'Business Professionals', 'text'=>'Excellence in every field, leading with skill, integrity, and purpose.'],
  ['icon'=>'chart', 'title'=>'Motivation',             'text'=>'Inspiring the next generation to dream, to strive, and to achieve.'],
  ['icon'=>'users', 'title'=>'Family in Business',     'text'=>'Our legacy continues through partnership, support, and unity.', 'link'=>'#family-in-business'],
  ['icon'=>'star',  'title'=>'Member Spotlights',      'text'=>'Celebrating the achievements of our family and the impact we make.'],
];

/* --- Featured family businesses (sample directory) --- */
$BIZ = [
  ['img'=>'gmw',     'mono'=>'GMW', 'name'=>'GMW Transportation',        'who'=>'Bill Holmes',          'cat'=>'Airport Transportation',        'loc'=>'Dallas, TX',      'blurb'=>'Private airport transportation to DFW &amp; Love Field. Dependable, professional, and on time.'],
  ['img'=>'threads', 'mono'=>'T&amp;G', 'name'=>'Threads &amp; Grace Boutique','who'=>'Danielle Battles',     'cat'=>"Women's Fashion &amp; Accessories",'loc'=>'Fort Worth, TX', 'blurb'=>'Stylish fashion for every season. Empowering women to look and feel their best.'],
  ['img'=>'law',     'mono'=>'BLG', 'name'=>'Battles Law Group',          'who'=>'Tanisha Battles, Esq.','cat'=>'Personal Injury &bull; Estate Planning','loc'=>'Houston, TX','blurb'=>'Dedicated legal representation with compassion, integrity, and results.'],
  ['img'=>'cafe',    'mono'=>'B',   'name'=>'Battles Table Caf&eacute;',  'who'=>'James Battles Jr.',    'cat'=>'Caf&eacute; &amp; Catering',    'loc'=>'Frisco, TX',     'blurb'=>'Delicious food. Warm atmosphere. Bringing people together one meal at a time.'],
  ['img'=>'ksj',     'mono'=>'KSJ', 'name'=>'KSJ Consulting',             'who'=>'Katrina Smith-Jackson','cat'=>'Business Strategy &bull; Leadership','loc'=>'Atlanta, GA', 'blurb'=>'Helping organizations grow through strategy, leadership and operational excellence.'],
  ['img'=>'sons',    'mono'=>'B&amp;S','name'=>'Battles &amp; Sons Construction','who'=>'Robert Battles',  'cat'=>'General Contracting',           'loc'=>'Arlington, TX',  'blurb'=>'Quality construction. Strong foundations. Building for generations to come.'],
];

/* --- Featured videos --- */
$VID_FEATURE = ['title'=>'Legacy in Action', 'sub'=>'Words of Wisdom from Our Elders', 'dur'=>'5:42'];
$VIDEOS = [
  ['title'=>'Building a Business With Faith &amp; Purpose', 'dur'=>'4:18'],
  ['title'=>'Next Generation Entrepreneurs',                'dur'=>'3:57'],
  ['title'=>'Financial Freedom Starts Now',                 'dur'=>'6:21'],
  ['title'=>'Our Story. Our Legacy. Our Future.',           'dur'=>'4:09'],
];

/* --- Financial guidance cards --- */
$FINANCE = [
  ['icon'=>'seed',   'title'=>'Build Wealth',        'tips'=>['Budget Wisely','Save Consistently','Invest Early','Avoid Debt Traps']],
  ['icon'=>'home',   'title'=>'Buy &amp; Own',       'tips'=>['Homeownership Tips','Real Estate Investing','Building Equity','Family Property']],
  ['icon'=>'shield', 'title'=>'Protect Your Future', 'tips'=>['Insurance Essentials','Emergency Fund','Estate Planning','Wills &amp; Trusts']],
  ['icon'=>'cap',    'title'=>'Invest in Education', 'tips'=>['College Savings Plans','Scholarships','Student Loan Tips','Skill Development']],
];

/* --- Bottom action row --- */
$ACTIONS = [
  ['icon'=>'search', 'title'=>'Hire Family First',   'text'=>'Need a service or professional? Search our family business directory and support one another.', 'cta'=>'Search Directory'],
  ['icon'=>'doc',    'title'=>'Business Resources',  'text'=>'Access templates, guides, funding resources, legal forms, and tools to help you grow.',        'cta'=>'Browse Resources'],
  ['icon'=>'mentor', 'title'=>'Mentor Connect',      'text'=>'Learn from those who have walked the path. Find a mentor or become one.',                       'cta'=>'Find a Mentor'],
  ['icon'=>'heart',  'title'=>'Support &amp; Fund',  'text'=>'Help family businesses thrive through support, partnerships, and investments.',                 'cta'=>'Get Involved'],
];

/* line-icon set (stroke inherits from CSS; fill:none) */
function ent_icon($k) {
  $p = [
    'bulb'   => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.8.7 1 1.4 1 2.5h6c0-1.1.2-1.8 1-2.5A6 6 0 0 0 12 3z"/>',
    'case'   => '<rect x="3" y="7.5" width="18" height="12.5" rx="2"/><path d="M8.5 7.5V5.5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2M3 12.5h18"/>',
    'chart'  => '<path d="M3 21h18"/><rect x="5" y="12" width="3" height="6"/><rect x="10.5" y="8" width="3" height="10"/><rect x="16" y="4" width="3" height="14"/>',
    'users'  => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
    'star'   => '<path d="M12 3.2l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.4l5.9-.8z"/>',
    'seed'   => '<path d="M12 21v-7.5M12 13.5C12 10.5 9.8 8.3 6.5 8.3c0 3 2.2 5.2 5.5 5.2zM12 11.8c0-3.1 2.2-5.3 5.5-5.3 0 3.1-2.2 5.3-5.5 5.3z"/>',
    'home'   => '<path d="M3 11l9-7 9 7M5 9.7V20h14V9.7M10 20v-6h4v6"/>',
    'shield' => '<path d="M12 3.2l7 2.6v5c0 4.4-3 7.4-7 8.9-4-1.5-7-4.5-7-8.9v-5z"/><path d="M9 12l2 2 4-4"/>',
    'cap'    => '<path d="M12 5L2 9l10 4 10-4-10-4zM6 11v4c0 1.6 2.7 3 6 3s6-1.4 6-3v-4M20 9.5v4.5"/>',
    'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.7-4.7"/>',
    'doc'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h5"/>',
    'mentor' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20v-1a4 4 0 0 1 4-4h3a4 4 0 0 1 4 4v1M16.5 5.5l1.6 1.6L21.5 3.7"/>',
    'heart'  => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'film'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M8 5v14M16 5v14"/>',
    'bank'   => '<path d="M4 10h16M3 10l9-6 9 6M6 10v7M10 10v7M14 10v7M18 10v7M4 20h16"/>',
    'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.7 2.6 15.3 0 18M12 3c-2.6 2.7-2.6 15.3 0 18"/>',
    'phone'  => '<path d="M6 3h3l1.6 5-2 1.4a12 12 0 0 0 5.9 5.9l1.4-2 5 1.6v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2 2 2 0 0 1 6 3z"/>',
    'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
  ];
  $inner = $p[$k] ?? '<circle cx="12" cy="12" r="8"/>';
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
}

page_head('Enterprise', ['body_class' => 'home ent']);
?>
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

<!-- FEATURED FAMILY BUSINESSES -->
<section class="ent2-bizwrap" id="family-in-business">
  <div class="ent2-bizhead">
    <h2>Featured Family Businesses</h2>
    <p>Support our family. Strengthen our legacy.</p>
  </div>
  <form class="biz-search" onsubmit="return false;">
    <input type="text" placeholder="Search businesses by name, profession, or location...">
    <select aria-label="Category">
      <option>All Categories</option>
      <option>Business</option>
      <option>Profession</option>
      <option>Trades &amp; Construction</option>
      <option>Food &amp; Hospitality</option>
      <option>Professional Services</option>
    </select>
    <button type="submit" class="ent2-btn">Search</button>
  </form>
  <div class="biz-grid">
    <?php foreach ($BIZ as $b): ?>
      <article class="biz-card">
        <div class="biz-photo" style="background-image:url('assets/enterprise/biz_<?= e($b['img']) ?>.jpg')">
          <span class="ent-ex">Example</span>
          <span class="biz-mono"><?= $b['mono'] /* authored */ ?></span>
        </div>
        <div class="biz-body">
          <h3><?= $b['name'] /* authored */ ?></h3>
          <div class="biz-who"><?= $b['who'] /* authored */ ?></div>
          <div class="biz-cat"><?= $b['cat'] /* authored */ ?></div>
          <div class="biz-loc"><?= $b['loc'] /* authored */ ?></div>
          <p class="biz-blurb"><?= $b['blurb'] /* authored */ ?></p>
          <div class="biz-ico"><?= ent_icon('globe') . ent_icon('phone') . ent_icon('mail') ?></div>
          <button type="button" class="ent2-btn biz-view">View Business</button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <button type="button" class="ent2-btn center" style="margin-top:24px;">View All Family Businesses &rarr;</button>
</section>

<!-- MAIN: videos + finance -->
<div class="ent2-wrap">
  <div class="ent2-main">

    <!-- Featured Videos -->
    <section class="ent2-panel">
      <div class="ent2-sec-title"><?= ent_icon('film') ?> Featured Videos</div>
      <div class="ent2-vid-feature">
        <span class="ent2-vf-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
        <div class="ent2-vf-cap">
          <h3><?= e($VID_FEATURE['title']) ?></h3>
          <p><?= e($VID_FEATURE['sub']) ?> &nbsp;<span class="dur"><?= e($VID_FEATURE['dur']) ?></span></p>
        </div>
      </div>
      <div class="ent2-vlist">
        <?php foreach ($VIDEOS as $v): ?>
          <div class="ent2-vrow">
            <div class="ent2-vthumb"></div>
            <div class="ent2-vmeta">
              <h4><?= $v['title'] /* authored above */ ?></h4>
              <span class="dur"><?= e($v['dur']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="ent2-btn center">View All Videos &rsaquo;</button>
    </section>

    <!-- Financial Guidance -->
    <section class="ent2-panel">
      <div class="ent2-sec-title"><?= ent_icon('bank') ?> Financial Guidance &amp; Suggestions</div>
      <p class="ent2-sec-sub">Practical advice. Generational wealth. Financial freedom.</p>
      <div class="ent2-fin-grid">
        <?php foreach ($FINANCE as $f): ?>
          <div class="ent2-fin">
            <div class="ent2-fic"><?= ent_icon($f['icon']) ?></div>
            <h3><?= $f['title'] /* authored */ ?></h3>
            <ul><?php foreach ($f['tips'] as $t): ?><li><?= $t /* authored */ ?></li><?php endforeach; ?></ul>
            <button type="button" class="ent2-btn">Learn More</button>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  </div>
</div>

<!-- ACTIONS -->
<section class="ent2-actions">
  <div class="ent2-acts">
    <?php foreach ($ACTIONS as $a): ?>
      <div class="ent2-act">
        <div class="ent2-aic"><?= ent_icon($a['icon']) ?></div>
        <h3><?= $a['title'] /* authored */ ?></h3>
        <p><?= e($a['text']) ?></p>
        <button type="button" class="ent2-actlink"><?= $a['cta'] /* authored */ ?> &rarr;</button>
      </div>
    <?php endforeach; ?>
    <div class="ent2-submit">
      <h3>Submit Your Business</h3>
      <p>Are you a business owner? Add your business to our directory and be featured!</p>
      <button type="button" class="ent2-btn">Submit Business</button>
    </div>
  </div>
  <p class="ent2-note">This page is live with sample content so you can see the full design in place.
     Once you're happy with the look, I'll wire up the admin screen &mdash; you'll log in and add, edit,
     or remove businesses, videos and sayings yourself, no coding, and they'll appear here instantly.</p>
</section>

<?php legacy_footer(); page_foot();
