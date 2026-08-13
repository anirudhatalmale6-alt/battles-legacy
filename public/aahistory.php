<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/community_data.php';
require_once __DIR__ . '/../src/aah_data.php';
try { community_migrate(); $QLIST = comm_list('question', 'published', 3); }
catch (Exception $ex) { $QLIST = []; }
aah_migrate();

$logged  = logged_in();
$isAdmin = role_at_least('admin');

/* ---- banner music settings (admin only) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && ($_POST['act'] ?? '') === 'music') {
    csrf_check();
    if (!empty($_POST['remove'])) {
        aah_remove_music();
        flash('The banner music has been removed.');
    } else {
        list($rel, $err) = aah_store_music('track');
        if ($err) {
            flash($err);
        } else {
            if ($rel !== '') { aah_remove_music(); aah_meta_set('music_file', $rel); }
            aah_meta_set('music_title', trim($_POST['music_title'] ?? ''));
            aah_meta_set('music_auto', empty($_POST['music_auto']) ? '0' : '1');
            flash($rel !== '' ? 'The music is on the banner now.' : 'Music settings saved.');
        }
    }
    header('Location: aahistory.php'); exit;
}
$MUSIC = aah_music();

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
$NAV = [
  ['star','Trailblazers','#trailblazers'], ['mega','Civil Rights','#civil'], ['bulb','Inventions &amp; Innovation','#inventions'],
  ['gov','Politics &amp; Leadership','#politics'], ['arts','Arts &amp; Culture','#arts'], ['sci','Science &amp; Medicine','#science'],
  ['sport','Sports','#sports'], ['clock','Timeline','#timeline'],
];
/* Everyone on this page now lives in the database so each name has its own
   page. Admins also see the ones they've hidden while they're still writing. */
$TRAILBLAZERS = aah_people('trailblazers', $isAdmin);
$INVENTIONS   = aah_people('inventions',   $isAdmin);
$POLITICS     = aah_people('politics',     $isAdmin);
$SCIENCE      = aah_people('science',      $isAdmin);
$SPORTS       = aah_people('sports',       $isAdmin);

$CIVIL = ['Fought for equality','Challenged injustice','Changed laws','Inspired generations'];
$ACHIEVEMENTS = ['The 13th, 14th &amp; 15th Amendments','Brown v. Board of Education (1954)','The Civil Rights Act (1964)','The Voting Rights Act (1965)','End of Legal Segregation'];
$ARTS = [['arts','Music'],['arts','Visual Arts'],['book','Literature &amp; Theater'],['star','Dance']];

/** One clickable portrait tile. Every name opens its own page. */
function aah_tile($p, $fallbackIcon = '') {
    $face = $p['photo']
        ? '<span class="aah-face" style="background-image:url(\'' . e($p['photo']) . '\')"></span>'
        : ($fallbackIcon ? '<span class="aah-face inv">' . aah_icon($fallbackIcon) . '</span>'
                         : '<span class="aah-face">' . aah_mono_name($p['name']) . '</span>');
    $hidden = ($p['status'] ?? 'published') !== 'published' ? '<i class="aah-hid">hidden</i>' : '';
    return '<a class="aah-person" href="aahperson.php?p=' . e($p['slug']) . '">'
         . $face . '<b>' . e($p['name']) . $hidden . '</b><span>' . e($p['role']) . '</span></a>';
}
/** A quiet "add someone" link under each section — admins only, so it never
 *  disturbs the grid the family sees. */
function aah_addlink($cat) {
    return '<a class="aah-addlink" href="aahperson.php?new=1&amp;cat=' . e($cat) . '">+ Add someone to this section</a>';
}
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

    <?php if ($MUSIC): ?>
    <!-- Banner music. Browsers refuse to start sound before a visitor touches
         the page, so it also arms itself on the first click, key or scroll. -->
    <div class="aah-music" data-auto="<?= $MUSIC['auto'] ? '1' : '0' ?>">
      <audio id="aahTrack" loop preload="auto" src="<?= e($MUSIC['file']) ?>"></audio>
      <button type="button" class="aah-mbtn" id="aahMbtn" aria-pressed="false">
        <span class="aah-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="aah-mlab">Play the music</span>
      </button>
      <?php if (trim($MUSIC['title'])): ?><span class="aah-mtitle"><?= e($MUSIC['title']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($isAdmin): ?>
<section class="aah-adminbar">
  <details<?= $MUSIC ? '' : ' open' ?>>
    <summary>
      <span class="aah-abt">Banner music</span>
      <span class="aah-abs"><?= $MUSIC ? 'Playing: ' . e($MUSIC['title'] ?: basename($MUSIC['file'])) : 'No track uploaded yet' ?></span>
    </summary>
    <form method="post" enctype="multipart/form-data" class="aah-mform">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="music">
      <label class="aah-mf">Music file (MP3)<input type="file" name="track" accept="audio/*"></label>
      <label class="aah-mf">Name of the track (shown beside the button)
        <input type="text" name="music_title" maxlength="120" value="<?= e($MUSIC['title'] ?? '') ?>" placeholder="Lift Every Voice and Sing"></label>
      <label class="aah-mchk"><input type="checkbox" name="music_auto" value="1"<?= (!$MUSIC || $MUSIC['auto']) ? ' checked' : '' ?>>
        Start playing as soon as someone opens the page</label>
      <p class="aah-mnote">A visitor can always stop it with the button on the banner, and their choice is remembered.
        Some browsers and phones will not make sound until the visitor taps the page once — the button starts flashing gold when that happens.</p>
      <div class="aah-mact">
        <button class="btn2 solid" type="submit">Save</button>
        <?php if ($MUSIC): ?><button class="aah-mdel" type="submit" name="remove" value="1"
          onclick="return confirm('Remove the banner music?')">Remove the music</button><?php endif; ?>
      </div>
    </form>
  </details>
</section>
<?php endif; ?>

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
        <?php foreach ($TRAILBLAZERS as $t): ?><?= aah_tile($t) ?><?php endforeach; ?>
      </div>
      <?php if ($isAdmin) echo aah_addlink('trailblazers'); ?>
    </section>

    <section class="aah-card" id="inventions">
      <h2>Inventions &amp; Innovations</h2>
      <p class="aah-note">Brilliant minds. Powerful ideas. Real impact.</p>
      <div class="aah-people four">
        <?php foreach ($INVENTIONS as $t): ?><?= aah_tile($t, 'bulb') ?><?php endforeach; ?>
      </div>
      <?php if ($isAdmin) echo aah_addlink('inventions'); ?>
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
        <?php foreach ($POLITICS as $t): ?><?= aah_tile($t) ?><?php endforeach; ?>
      </div>
      <?php if ($isAdmin) echo aah_addlink('politics'); ?>
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
        <?php foreach ($SCIENCE as $s): ?>
          <li><a class="aah-lrow" href="aahperson.php?p=<?= e($s['slug']) ?>">
            <?= $s['photo'] ? '<span class="aah-mini" style="background-image:url(\''.e($s['photo']).'\')"></span>' : '<span class="aah-ci">'.aah_icon('check').'</span>' ?>
            <div><b><?= e($s['name']) ?></b><span class="aah-role"><?= e($s['role']) ?></span></div>
          </a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($isAdmin) echo aah_addlink('science'); ?>
    </section>
  </div>

  <!-- SPORTS -->
  <section class="aah-card" id="sports" style="margin-bottom:20px">
    <h2 class="sm">Sports</h2>
    <p class="aah-note">Champions who changed the game &mdash; and the country.</p>
    <div class="aah-people four">
      <?php foreach ($SPORTS as $t): ?><?= aah_tile($t) ?><?php endforeach; ?>
    </div>
    <?php if ($isAdmin) echo aah_addlink('sports'); ?>
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

<section class="aah-credit">
  Historical photographs are in the public domain (Library of Congress, U.S. federal government portraits and Wikimedia Commons).
</section>

<!-- CLOSING -->
<section class="aah-closing">
  <span class="fvq">&ldquo;</span>The past is our teacher. The present is our responsibility. The future is our legacy.
</section>

<?php if ($MUSIC): ?>
<script>
(function(){
  var box = document.querySelector('.aah-music');
  var a   = document.getElementById('aahTrack');
  var btn = document.getElementById('aahMbtn');
  var lab = btn && btn.querySelector('.aah-mlab');
  if (!box || !a || !btn) return;

  var KEY = 'aah_music';                       // remember whether they wanted it
  var pref = null;
  try { pref = localStorage.getItem(KEY); } catch (e) {}
  var wantAuto = box.getAttribute('data-auto') === '1' && pref !== 'off';
  var armed = false;                           // waiting for the first tap

  function paint(playing){
    btn.classList.toggle('on', playing);
    btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
    if (lab) lab.textContent = playing ? 'Pause the music' : (armed ? 'Tap to play the music' : 'Play the music');
  }
  /* ease the volume up so it never startles anyone */
  function fadeIn(){
    a.volume = 0;
    var v = 0, t = setInterval(function(){
      v += 0.06; if (v >= 0.55) { v = 0.55; clearInterval(t); }
      try { a.volume = v; } catch (e) { clearInterval(t); }
    }, 70);
  }
  function play(remember){
    var p = a.play();
    if (p && p.catch) {
      p.then(function(){ armed = false; box.classList.remove('waiting'); fadeIn(); paint(true);
                         if (remember) { try { localStorage.setItem(KEY,'on'); } catch(e){} } })
       .catch(function(){ arm(); });
    } else { fadeIn(); paint(true); }
  }
  /* the browser said no until the visitor interacts — wait for that instead */
  function arm(){
    if (armed) return;
    armed = true;
    box.classList.add('waiting');
    paint(false);
    var go = function(ev){
      if (ev && btn.contains(ev.target)) return;   // the button handles itself
      off(); play(false);
    };
    var off = function(){
      document.removeEventListener('click', go, true);
      document.removeEventListener('keydown', go, true);
      document.removeEventListener('touchend', go, true);
    };
    document.addEventListener('click', go, true);
    document.addEventListener('keydown', go, true);
    document.addEventListener('touchend', go, true);
  }

  btn.addEventListener('click', function(){
    if (a.paused) { armed = false; box.classList.remove('waiting'); play(true); }
    else { a.pause(); paint(false); try { localStorage.setItem(KEY,'off'); } catch(e){} }
  });
  a.addEventListener('pause', function(){ paint(false); });
  a.addEventListener('play',  function(){ paint(true); });

  paint(false);
  if (wantAuto) play(false);
})();
</script>
<?php endif; ?>

<?php legacy_footer(); page_foot();
