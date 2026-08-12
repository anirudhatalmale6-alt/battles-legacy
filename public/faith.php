<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/faith_data.php';
faith_migrate();

/* ---- prayer request + prayer-warrior signup (family submits) ---- */
$sent  = ($_GET['sent'] ?? '') === '1';
$wsent = ($_GET['warrior'] ?? '') === '1';
$perr = ''; $werr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { header('Location: faith.php'); exit; } // honeypot
    $act = $_POST['action'] ?? '';
    if ($act === 'prayer_submit') {
        $body = trim($_POST['body'] ?? '');
        if ($body === '') { $perr = 'Please write your prayer request before submitting.'; }
        else {
            $u = current_user();
            faith_add_prayer(
                trim($_POST['name'] ?? ''), trim($_POST['subject'] ?? ''), mb_substr($body, 0, 2000),
                !empty($_POST['is_private']), !empty($_POST['may_contact']), $u['id'] ?? null
            );
            header('Location: faith.php?sent=1#prayer'); exit;
        }
    } elseif ($act === 'warrior_signup') {
        $wname = trim($_POST['wname'] ?? '');
        if ($wname === '') { $werr = 'Please add your name to sign up.'; }
        else {
            $u = current_user();
            faith_add_warrior($wname, trim($_POST['wcontact'] ?? ''), trim($_POST['wnote'] ?? ''), $u['id'] ?? null);
            header('Location: faith.php?warrior=1#warrior'); exit;
        }
    }
}
$MINISTERS = faith_ministers();

/* ---- authored content (matches the mockup; can be made editable later) ---- */
$SALVATION = [
  ['icon'=>'heart', 'lead'=>'Dear Lord God,',   'text'=>'I believe with all my heart that <b>Jesus Christ is the Son of God.</b> I believe that He died for my sins, was buried, and rose again from the dead.'],
  ['icon'=>'person','lead'=>'Today,',            'text'=>'I confess with my mouth that <b>Jesus Christ is Lord,</b> and I accept Him as the Lord and Savior of my life.'],
  ['icon'=>'cross', 'lead'=>'Lord Jesus,',       'text'=>'forgive me of my sins and cleanse me from all unrighteousness. <b>Come into my heart and take control</b> of my life. Help me to follow You as I live according to Your Word.'],
  ['icon'=>'book',  'lead'=>'',                  'text'=>'I believe that through Jesus Christ, <b>I have forgiveness of sins, eternal life, and a new beginning.</b>'],
];

$MINISTRY = [
  ['icon'=>'cross',  'label'=>'Pastors'],
  ['icon'=>'globe',  'label'=>'Missionaries'],
  ['icon'=>'book',   'label'=>'Teachers'],
  ['icon'=>'people', 'label'=>'Leaders'],
  ['icon'=>'hands',  'label'=>'Volunteers'],
];

$SCRIPTURE_LIB = [
  ['icon'=>'faith',    'title'=>'Faith',    'refs'=>['Hebrews 11:1','Proverbs 3:5-6','Mark 11:24']],
  ['icon'=>'comfort',  'title'=>'Comfort',  'refs'=>['Psalm 23','Matthew 5:4','2 Corinthians 1:3-4']],
  ['icon'=>'strength', 'title'=>'Strength', 'refs'=>['Isaiah 41:10','Philippians 4:13','Psalm 28:7']],
  ['icon'=>'family',   'title'=>'Family',   'refs'=>['Joshua 24:15','Proverbs 22:6','Psalm 133:1']],
  ['icon'=>'healing',  'title'=>'Healing',  'refs'=>['Psalm 103:2-3','Jeremiah 17:14','James 5:15']],
  ['icon'=>'hope',     'title'=>'Hope',     'refs'=>['Jeremiah 29:11','Romans 15:13','Isaiah 40:31']],
];

$VALUES = [
  ['icon'=>'cross',  'title'=>'Continue the Legacy', 'text'=>'Our faith was their foundation. Our family is their legacy.'],
  ['icon'=>'heart',  'title'=>'One in Faith',        'text'=>'Different places, one family, one God.'],
  ['icon'=>'people', 'title'=>'Join Us in Prayer',   'text'=>'We believe in the power of prayer.'],
  ['icon'=>'book',   'title'=>'His Word, Our Guide',  'text'=>'Rooted in Scripture. Focused on Christ.'],
  ['icon'=>'dove',   'title'=>'Serve Together',      'text'=>'Using our gifts. Loving our community.'],
];

/* line-icon set (stroke inherits from CSS; fill:none) */
function faith_icon($k) {
  $p = [
    'cross'   => '<path d="M10 3h4v5h5v4h-5v9h-4v-9H5V8h5z"/>',
    'heart'   => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'person'  => '<circle cx="12" cy="8" r="3.4"/><path d="M5.5 20v-1a6.5 6.5 0 0 1 13 0v1"/>',
    'book'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5zM20 18v3H6.5A2.5 2.5 0 0 1 4 18.5"/><path d="M12 6v9"/>',
    'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.7 2.6 15.3 0 18M12 3c-2.6 2.7-2.6 15.3 0 18"/>',
    'people'  => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
    'hands'   => '<path d="M12 21c4-2.5 7-5.6 7-9.3A3.3 3.3 0 0 0 12 9a3.3 3.3 0 0 0-7 2.7C5 15.4 8 18.5 12 21z"/>',
    'dove'    => '<path d="M3 13c4 .5 7-1.5 9-5 0 4 2 6 5 6 2 0 4-1.4 4-1.4-1 4-4.4 6.4-8 6.4-4.6 0-8-2.6-10-6z"/><path d="M12 8V4"/>',
    'faith'   => '<path d="M10 3h4v4h4v4h-4v10h-4V11H6V7h4z"/>',
    'comfort' => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'strength'=> '<path d="M6 12l4-8 4 5 4-3-2 12H6z"/><path d="M6 20h12"/>',
    'family'  => '<circle cx="8" cy="8" r="2.4"/><circle cx="16" cy="8" r="2.4"/><path d="M4 19v-1a4 4 0 0 1 4-4M20 19v-1a4 4 0 0 0-4-4M9.5 20a3 3 0 0 1 5 0"/>',
    'healing' => '<path d="M12 4v16M4 12h16" stroke-width="2.2"/>',
    'hope'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'play'    => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5l6 3.5-6 3.5z"/>',
    'candle'  => '<path d="M12 2c1.6 3.2.6 4.9-.8 6.6-1.3 1.6-2.7 3.1-2.7 5.4a3.5 3.5 0 0 0 7 0c0-1.4-.6-2.5-1.2-3.4 1.9 1 3.2 2.9 3.2 5.1a6.5 6.5 0 1 1-13 0C6.7 8.9 12 8 12 2z"/>',
    'lily'    => '<path d="M12 21c0-5-3-8-8-8 0-3 3-5 8-2 5-3 8-1 8 2-5 0-8 3-8 8z"/><path d="M12 21V9"/>',
  ];
  $inner = $p[$k] ?? '<circle cx="12" cy="12" r="8"/>';
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
}

$isAdmin = role_at_least('admin');
$pendPrayers = $isAdmin ? faith_prayer_count() : 0;
$FVIDS = faith_videos();   // featured first, then the rest

page_head('Faith', ['body_class' => 'home faith']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>You're signed in as an editor.</span>
    <a class="ent2-editbtn" href="faith_manage.php?tab=ministers">&#9998; Manage ministers</a>
    <a class="ent2-editbtn" href="faith_manage.php">&#128591; Prayer requests<?= $pendPrayers ? ' (' . $pendPrayers . ')' : '' ?></a>
  </div>
<?php endif; ?>

<div class="faith-grid">

  <!-- LEFT: Prayer of Salvation -->
  <aside class="faith-side">
    <div class="fs-head">
      <div class="fs-fig"><?= faith_icon('person') ?></div>
      <h2>Prayer<br><span>of Salvation</span></h2>
    </div>
    <div class="fs-card">
      <?php foreach ($SALVATION as $s): ?>
        <div class="fs-line">
          <span class="fs-ic"><?= faith_icon($s['icon']) ?></span>
          <p><?php if ($s['lead']): ?><b class="fs-lead"><?= e($s['lead']) ?></b> <?php endif; ?><?= $s['text'] /* authored HTML */ ?></p>
        </div>
      <?php endforeach; ?>
      <div class="fs-thanks">
        <span class="fs-ic gold"><?= faith_icon('heart') ?></span>
        <p>Thank You, Lord, for saving me. I am now a child of God, I am born again, and my life belongs to You.</p>
      </div>
    </div>
    <div class="fs-amen">In Jesus&rsquo; Name, Amen! <span class="script">Hallelujah!</span></div>

    <div class="fs-need">
      <h3>Become a Prayer Warrior</h3>
      <p>Stand in the gap for our family. Sign up to lift up the prayer requests that come in. You are never alone.</p>
      <a class="btn gold" href="#warrior">Sign Up to Be a Prayer Warrior</a>
    </div>

    <!-- Featured Videos — sermons, testimonies, songs -->
    <?php if ($FVIDS || $isAdmin): ?>
    <section class="fs-vids" id="videos">
      <div class="fs-vhead"><?= faith_icon('play') ?> <h3>Featured Videos</h3>
        <?php if ($isAdmin): ?><a class="fs-vedit" href="faith_manage.php?tab=videos">Manage &rsaquo;</a><?php endif; ?></div>
      <?php if ($FVIDS): $FV = $FVIDS[0]; $FREST = array_slice($FVIDS, 1); /* all of them — hiding some with no way to reach them is worse than a longer list */ ?>
        <?php $fu = faith_video_url($FV); $fth = faith_video_thumb($FV); ?>
        <?php if ($fu): ?><a class="fs-vfeat" href="<?= e($fu) ?>" target="_blank" rel="noopener"<?php else: ?><div class="fs-vfeat"<?php endif; ?>
          <?= $fth ? ' style="background-image:url(\'' . e($fth) . '\')"' : '' ?>>
          <span class="fs-vplay"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
          <span class="fs-vcap">
            <b><?= e($FV['title']) ?></b>
            <?php if (trim((string)$FV['description']) !== ''): ?><i><?= e($FV['description']) ?></i><?php endif; ?>
            <?php if ($FV['duration']): ?><em class="dur"><?= e($FV['duration']) ?></em><?php endif; ?>
          </span>
        <?php if ($fu): ?></a><?php else: ?></div><?php endif; ?>

        <?php if ($FREST): ?>
        <div class="fs-vlist">
          <?php foreach ($FREST as $v): $vu = faith_video_url($v); $vt = faith_video_thumb($v); ?>
            <?php if ($vu): ?><a class="fs-vrow" href="<?= e($vu) ?>" target="_blank" rel="noopener"><?php else: ?><div class="fs-vrow"><?php endif; ?>
              <span class="fs-vthumb"<?= $vt ? ' style="background-image:url(\'' . e($vt) . '\')"' : '' ?>><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
              <span class="fs-vmeta"><b><?= e($v['title']) ?></b><?php if ($v['duration']): ?><em class="dur"><?= e($v['duration']) ?></em><?php endif; ?></span>
            <?php if ($vu): ?></a><?php else: ?></div><?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <p class="fs-vempty">No videos yet &mdash; add a sermon, testimony or song from <a href="faith_manage.php?tab=videos">Manage</a> and it will show here.</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="fs-photo"><img src="assets/faith/pray1.jpg" alt="A family member in prayer"></div>
    <blockquote class="fs-verse">&ldquo;Call unto me, and I will answer thee, and shew thee great and mighty things, which thou knowest not.&rdquo;
      <cite>&mdash; Jeremiah 33:3</cite></blockquote>
  </aside>

  <!-- RIGHT: main faith content -->
  <div class="faith-body">

    <!-- HERO -->
    <section class="faith-hero">
      <div class="fh-text">
        <h1>Our Family&rsquo;s <span class="script">Legacy of Faith</span></h1>
        <div class="fh-orn">&#10086; &nbsp;&bull;&nbsp; &#10086;</div>
        <p class="fh-sub">Honoring all who have dedicated their lives to ministry &mdash; past and present.</p>
        <blockquote class="fh-quote">&ldquo;As for me and my house, we will serve the Lord.&rdquo;<cite>&mdash; Joshua 24:15</cite></blockquote>
        <a class="fh-watch" href="#featured"><span class="fh-play"><?= faith_icon('play') ?></span> Watch Featured Message</a>
        <p class="fh-note">A message of faith, service, and dedication.</p>
      </div>
      <img class="fh-img" src="assets/faith/hero.jpg" alt="A cross draped in cloth beside an open Bible">
    </section>

    <!-- HONORING OUR MINISTRY FAMILY + PRAYER REQUESTS -->
    <div class="faith-two">
      <section class="fpanel" id="ministry">
        <div class="fp-title"><?= faith_icon('people') ?> Honoring Our Ministry Family</div>
        <p class="fp-sub">We honor the men and women &mdash; past and present &mdash; who answered the call to serve God through ministry. Click a photo to read their story.</p>
        <?php if ($MINISTERS): ?>
          <div class="fmins">
            <?php foreach ($MINISTERS as $m): ?>
              <a class="fmin-card" href="minister.php?id=<?= (int)$m['id'] ?>">
                <span class="fmin-photo"<?= $m['photo'] ? ' style="background-image:url(\''.e($m['photo']).'\')"' : ' data-empty="1"' ?>>
                  <?php if (!$m['photo']): ?><span class="fmin-mono"><?= faith_mono($m['name']) ?></span><?php endif; ?>
                  <?php if ($m['era'] === 'past'): ?><span class="fmin-era">In Memory</span><?php endif; ?>
                </span>
                <span class="fmin-name"><?= e($m['name']) ?></span>
                <?php if ($m['role']): ?><span class="fmin-role"><?= e($m['role']) ?></span><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php elseif ($isAdmin): ?>
          <p class="fp-empty">No ministers added yet &mdash; use &ldquo;Add or edit ministers&rdquo; below to add them, each with a photo and a profile.</p>
        <?php else: ?>
          <p class="fp-empty">Our ministry family will be honored here soon.</p>
        <?php endif; ?>
        <div class="fmin fmin-legend">
          <?php foreach ($MINISTRY as $mc): ?>
            <div class="fmin-cat"><span class="fmin-ic"><?= faith_icon($mc['icon']) ?></span><span><?= e($mc['label']) ?></span></div>
          <?php endforeach; ?>
        </div>
        <div class="fmin-actions">
          <a class="btn2 solid" href="ministers.php">View Our Ministry Family</a>
          <?php if ($isAdmin): ?><a class="btn2" href="faith_manage.php?tab=ministers">&#9998; Add or edit ministers</a><?php endif; ?>
        </div>
      </section>

      <section class="fpanel" id="prayer">
        <div class="fp-title"><?= faith_icon('hands') ?> Prayer Requests</div>
        <p class="fp-sub">Let us pray for one another. Submit your prayer request and allow our family to stand in faith with you.</p>
        <?php if ($sent): ?>
          <div class="fp-sent"><b>Thank you.</b> Your prayer request has been received &mdash; our family will stand with you in prayer.</div>
        <?php endif; ?>
        <?php if ($perr): ?><div class="fp-sent err"><?= e($perr) ?></div><?php endif; ?>
        <form method="post" class="fpray">
          <?= csrf_field() ?><input type="hidden" name="action" value="prayer_submit">
          <input type="text" name="website" class="fp-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <input type="text" name="name" placeholder="Your Name" value="<?= e(current_user()['name'] ?? '') ?>">
          <input type="text" name="subject" placeholder="Who are we praying for?">
          <textarea name="body" placeholder="Prayer Request" required></textarea>
          <label class="fp-check"><input type="checkbox" name="is_private" value="1"> Keep this request private</label>
          <label class="fp-check"><input type="checkbox" name="may_contact" value="1"> May family members contact you?</label>
          <button type="submit" class="btn2 solid">Submit Prayer Request</button>
        </form>
      </section>
    </div>

    <!-- PRAYER WARRIOR SIGNUP -->
    <section class="fpanel fwarrior" id="warrior">
      <div class="fp-title"><?= faith_icon('hands') ?> Sign Up to Be a Prayer Warrior</div>
      <p class="fp-sub">Prayer warriors stand in faith with our family, lifting up the requests that come in. Add your name to join the team.</p>
      <?php if ($wsent): ?><div class="fp-sent"><b>Welcome, prayer warrior!</b> Thank you for standing with our family in prayer.</div><?php endif; ?>
      <?php if ($werr): ?><div class="fp-sent err"><?= e($werr) ?></div><?php endif; ?>
      <form method="post" class="fpray fwar">
        <?= csrf_field() ?><input type="hidden" name="action" value="warrior_signup">
        <input type="text" name="website" class="fp-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="fwar-grid">
          <input type="text" name="wname" placeholder="Your Name" value="<?= e(current_user()['name'] ?? '') ?>">
          <input type="text" name="wcontact" placeholder="Email or phone (optional)">
        </div>
        <textarea name="wnote" placeholder="Anything you'd like to share (optional)"></textarea>
        <button type="submit" class="btn2 solid">Sign Me Up</button>
      </form>
    </section>

    <!-- SCRIPTURE OF THE WEEK + LIBRARY -->
    <div class="faith-two scrip">
      <section class="fpanel fweek">
        <img src="assets/faith/scripture.jpg" alt="An open Bible at sunrise">
        <div class="fw-body">
          <h3>Scripture of the Week</h3>
          <blockquote>&ldquo;God is our refuge and strength, a very present help in trouble.&rdquo;<cite>&mdash; Psalm 46:1</cite></blockquote>
          <a class="btn2 solid" href="#scriptures">Browse More Scriptures</a>
        </div>
      </section>

      <section class="fpanel flib" id="scriptures">
        <div class="fp-title small"><?= faith_icon('book') ?> Scripture Library</div>
        <div class="flib-grid">
          <?php foreach ($SCRIPTURE_LIB as $c): ?>
            <div class="flib-cat">
              <div class="flib-h"><span class="flib-ic"><?= faith_icon($c['icon']) ?></span><b><?= e($c['title']) ?></b></div>
              <ul><?php foreach ($c['refs'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endforeach; ?>
        </div>
        <a class="flib-all" href="#scriptures">View All Scriptures &rarr;</a>
      </section>
    </div>

    <!-- THREE CARDS -->
    <div class="faith-cards">
      <div class="fcard praise">
        <span class="fc-ic"><?= faith_icon('hands') ?></span>
        <h3>Praise Reports</h3>
        <p>Share how God is moving in your life! Your testimony will encourage someone else.</p>
        <a class="btn2 solid" href="#prayer">Share a Praise Report</a>
      </div>
      <div class="fcard devos">
        <span class="fc-ic"><?= faith_icon('book') ?></span>
        <h3>Family Devotionals</h3>
        <p>Be encouraged with devotionals from our family ministers and members.</p>
        <a class="btn2 solid" href="#scriptures">Read Devotionals</a>
      </div>
      <div class="fcard memory">
        <span class="fc-ic"><?= faith_icon('lily') ?></span>
        <h3>In Loving Memory</h3>
        <p>Remembering our spiritual leaders who faithfully served God and have gone home to be with the Lord.</p>
        <a class="btn2 solid" href="ministers.php?era=past">View Ministry Memorials</a>
      </div>
    </div>

  </div><!-- /faith-body -->
</div><!-- /faith-grid -->

<!-- VALUE STRIP -->
<section class="faith-values">
  <?php foreach ($VALUES as $v): ?>
    <div class="fv"><span class="fv-ic"><?= faith_icon($v['icon']) ?></span><div><b><?= e($v['title']) ?></b><span><?= e($v['text']) ?></span></div></div>
  <?php endforeach; ?>
</section>

<!-- CLOSING VERSE -->
<section class="faith-verse">
  <span class="fvq">&ldquo;</span>One generation shall praise thy works to another, and shall declare thy mighty acts.<span class="fvr">&mdash; Psalm 145:4</span>
</section>

<?php legacy_footer(); page_foot();
