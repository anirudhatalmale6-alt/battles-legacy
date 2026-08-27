<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/music.php';
require_once __DIR__ . '/../src/site_meta.php';
require_once __DIR__ . '/../src/calendar_data.php';
require_once __DIR__ . '/../src/news_data.php';
music_handle_post('home', 'index.php');

/* ---- the home page editor (admins only) ------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'homeedit' && role_at_least('admin')) {
    csrf_check();
    if (!empty($_POST['reset_defaults'])) {
        foreach (['home_news_title','home_news_date','home_news_photo','home_mem_pid','home_mem_photo',
                  'home_faith_verse','home_faith_ref','home_band_verse','home_band_ref'] as $k) sm_clear($k);
        flash('The home page is back to its original wording.');
    } else {
        /* Blank means "follow the newest announcement", so it has to clear the
           row rather than store an empty one — an empty stored value counts as
           set, and would pin the card to nothing at all. */
        foreach ([['news_title','home_news_title',120], ['news_date','home_news_date',60]] as $b) {
            $val = mb_substr(trim($_POST[$b[0]] ?? ''), 0, $b[2]);
            if ($val === '') sm_clear($b[1]); else sm_set($b[1], $val);
        }
        sm_set('home_mem_pid',    trim($_POST['mem_pid'] ?? ''));
        sm_set('home_faith_verse', mb_substr(trim($_POST['faith_verse'] ?? ''), 0, 600));
        sm_set('home_faith_ref',   mb_substr(trim($_POST['faith_ref']   ?? ''), 0, 120));
        sm_set('home_band_verse',  mb_substr(trim($_POST['band_verse']  ?? ''), 0, 600));
        sm_set('home_band_ref',    mb_substr(trim($_POST['band_ref']    ?? ''), 0, 120));
        $msgs = [];
        foreach ([['news_photo','home_news_photo'], ['mem_photo','home_mem_photo']] as $pair) {
            list($field, $key) = $pair;
            list($rel, $err) = news_store_photo($field, sm($key, ''));
            if ($err) $msgs[] = $err; elseif ($rel !== '' && $rel !== sm($key, '')) sm_set($key, $rel);
        }
        flash($msgs ? implode(' ', $msgs) : 'Home page updated.');
    }
    header('Location: index.php'); exit;
}

/* The queue moves on ordinary traffic rather than a cron job. One send at
   most, and only when the gap and the daily cap both allow it. */
require_once __DIR__ . '/../src/invites.php';
invite_drip_tick();
$u = current_user();
$np = one("SELECT COUNT(*) c FROM persons")['c'] ?? 0;
$nph = one("SELECT COUNT(*) c FROM photos WHERE status='approved'")['c'] ?? 0;

/* ---- what the five cards show ----------------------------------------- */
/* The Family News card follows the Family News page.
 *
 *  It used to be three lines of typed-in text with the mockup's wording as the
 *  default, and nobody ever changed them — so in August 2026 the front of the
 *  site was still advertising a reunion from June 2025 while the real newest
 *  announcement, a death in the family, sat unmentioned. The headline William
 *  types still wins; when he has not typed one, the card shows the latest
 *  announcement and keeps itself right from then on. */
try { $LATEST = news_latest(); } catch (\Throwable $ex) { $LATEST = null; }
/* Headline, date and picture are one card, so they follow or hold together.
   Taking them field by field is how you end up with a memorial photograph
   captioned "Battles Family Reunion". */
$NEWS_FOLLOW = ($LATEST && sm('home_news_title', '') === '');
if ($NEWS_FOLLOW) {
    $NEWS_TITLE = $LATEST['title'];
    $NEWS_DATE  = $LATEST['date_label'];
    $NEWS_PHOTO = $LATEST['photo'] ? $LATEST['photo'] : 'assets/home-news.jpg';
    $NEWS_HREF  = 'news_view.php?id=' . (int)$LATEST['id'];
} else {
    $NEWS_TITLE = sm('home_news_title', 'Battles Family Reunion');
    $NEWS_DATE  = sm('home_news_date',  '');
    $NEWS_PHOTO = sm('home_news_photo', 'assets/home-news.jpg');
    $NEWS_HREF  = 'news.php';
}

$MEM_PID   = sm('home_mem_pid', '@I29@');            // Horatio Battles
$MEM_PHOTO = sm('home_mem_photo', 'assets/home-memorial-horatio.jpg');
$mp = $MEM_PID ? one("SELECT * FROM persons WHERE pid=?", [$MEM_PID]) : null;
$MEM_NAME  = $mp ? person_display_name($mp) : 'Horatio Battles';
$MEM_DATES = $mp ? (lifespan($mp) ?: '') : '1865 – 1944';

$FAITH_VERSE = sm('home_faith_verse', '"Be strong and courageous. Do not be afraid; do not be discouraged, for the Lord your God will be with you wherever you go."');
$FAITH_REF   = sm('home_faith_ref',   '— Joshua 1:9');
$BAND_VERSE  = sm('home_band_verse',  '"One generation shall praise thy works to another, and shall declare thy mighty acts."');
$BAND_REF    = sm('home_band_ref',    '— Psalm 145:4');

/* The card shows the family's own dates when there are any — the reunion, the
   scholarship deadline — and falls back to the birthdays and remembrance days
   from the calendar when there are none, so it is never an empty box. */
try { $FAMEV = array_slice(news_events(), 0, 4); } catch (\Throwable $ex) { $FAMEV = []; }
try { $UPNEXT = $FAMEV ? [] : cal_upcoming(4); } catch (\Throwable $ex) { $UPNEXT = []; }
$isAdmin = role_at_least('admin');

page_head('Home', ['body_class' => 'home']);
?>
  <section class="hero">
    <img class="hero-img" src="assets/hero-scene.jpg"
         alt="The Battles Legacy — One Family. Many Stories. One Legacy.">
    <!-- four ancestor portraits that fade fully out to the tree, then the next fades in — never two faces at once -->
    <a class="rslot" data-set="0" style="left:2.5%;top:17%;width:15%;height:72%"  href="tree.php" title="Open the family tree"><img class="rot" alt=""><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
    <a class="rslot" data-set="1" style="left:18%;top:17%;width:15%;height:72%"  href="tree.php" title="Open the family tree"><img class="rot" alt=""><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
    <a class="rslot" data-set="2" style="left:63%;top:17%;width:15%;height:72%"  href="tree.php" title="Open the family tree"><img class="rot" alt=""><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
    <a class="rslot" data-set="3" style="left:78%;top:17%;width:15.5%;height:72%" href="tree.php" title="Open the family tree"><img class="rot" alt=""><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
    <!-- clickable Explore Our Family Tree button (baked into the scene) -->
    <a class="hot" style="left:39.5%;top:78%;width:20.5%;height:13%" href="tree.php" title="Explore the family tree"></a>

    <!-- The banner above has its words painted into the picture, which is right
         on a desktop and hopeless on a phone: the whole scene shrinks to a strip
         about an inch tall and the welcome becomes unreadable, with the four
         names running into each other. So below 900px the picture steps aside
         and the same words are set as real text over the same tree background,
         with the portraits underneath at a size you can actually see. It is the
         treatment the Enterprise and Memorial banners already use. -->
    <div class="hero-m">
      <h1 class="hero-m-title">One Family.<br>Many Stories.<br><span class="script">One Legacy.</span></h1>
      <p class="hero-m-lede">Welcome to our family&rsquo;s digital home &mdash; a place where generations come
        together to preserve our history, celebrate our faith, honor our ancestors, and build a stronger
        future for those who follow us.</p>
      <div class="hero-m-faces">
        <a class="mslot" data-set="0" href="tree.php"><span class="mfr"><img class="rot" alt=""></span><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
        <a class="mslot" data-set="1" href="tree.php"><span class="mfr"><img class="rot" alt=""></span><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
        <a class="mslot" data-set="2" href="tree.php"><span class="mfr"><img class="rot" alt=""></span><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
        <a class="mslot" data-set="3" href="tree.php"><span class="mfr"><img class="rot" alt=""></span><span class="rcap"><b class="rc-name"></b><span class="rc-yr"></span></span></a>
      </div>
      <a class="btn gold hero-m-cta" href="tree.php">Explore Our Family Tree</a>
    </div>
  </section>
  <!-- The family anthem. It sits under the scene rather than on it, because
       every part of that picture is already a link. -->
  <?php music_player('home', ['class' => 'mus-band', 'lead' => 'Our Family Anthem']); ?>
  <?php music_admin_box('home', 'Family anthem'); ?>

  <script>
  (function(){
    var IDS=['p01','p02','p03','p04','p05','p06','p07','p08','p09','p10','p11','p12','p13'];
    // [name, years, person-id] — clicking a portrait opens that person's page so anyone can learn who they are
    var META={
      p01:['L.J. Battles','1915 – 1984','@I38@'], p02:['Nathaniel Battles','1918 – 1952','@I39@'],
      p03:['Susie Johnson','1882 – 1974','@I300@'], p04:['Elizabeth Carey','1875 – 1933','@I30@'],
      p05:['James (JT) Battles','1911 – 1970','@I7@'], p06:['Horatio Battles','1865 – 1944','@I29@'],
      p07:['Settie Battles','1898 – 1991','@I32@'], p08:['Augustus (Gus) Battles','1905 – 1965','@I35@'],
      p09:['Johnnie Mae Battles','1903 – 1974','@I34@'], p10:['Anthony Battles','1888 – 1966','@I422@'],
      p11:['Sam Calvin Battles','1900 – 1972','@I33@'], p12:['Edmond Battles','1897 – 1957','@I31@'],
      p13:['Louisa Battles','c. 1842 – 1916','@I315@']
    };
    // each slot cycles its own group, so no face is ever shown twice at once
    var SETS=[[0,1,2],[3,4,5,12],[6,7,8],[9,10,11]];
    /* Two sets of slots — the ones sitting on the painted banner, and the ones
       in the phone version underneath. Only one set is ever on screen, so which
       group a slot cycles is read from data-set rather than from its position in
       the document; otherwise the fifth slot onwards would fall off the end. */
    var slots=[].slice.call(document.querySelectorAll('.hero .rslot, .hero .mslot'));
    function paint(s,id){
      s._img.src='assets/hero-rot/'+id+'.jpg';
      s._nm.textContent=META[id][0]; s._yr.textContent=META[id][1];
      s.setAttribute('href','person.php?pid='+encodeURIComponent(META[id][2]));
      s.setAttribute('title','Read about '+META[id][0]);
    }
    slots.forEach(function(s){
      s._img=s.querySelector('.rot'); s._cap=s.querySelector('.rcap');
      s._nm=s.querySelector('.rc-name'); s._yr=s.querySelector('.rc-yr');
      s._set=SETS[+(s.getAttribute('data-set')||0)]||SETS[0]; s._k=0; paint(s,IDS[s._set[0]]);
      requestAnimationFrame(function(){ s._img.classList.add('on'); s._cap.classList.add('on'); });
    });
    function cycle(i){
      var s=slots[i];
      s._img.classList.remove('on'); s._cap.classList.remove('on');   // fade portrait + name out to the tree
      setTimeout(function(){                                          // once gone, swap and fade the next in
        s._k=(s._k+1)%s._set.length; paint(s,IDS[s._set[s._k]]);
        s._img.classList.add('on'); s._cap.classList.add('on');
      }, 1300);
    }
    /* Stagger by which group the slot cycles, not by its place in the document,
       so the phone row starts turning over on the same rhythm as the banner
       instead of waiting for four slots that aren't on screen. */
    slots.forEach(function(s,i){
      var d=+(s.getAttribute('data-set')||0);
      setTimeout(function(){ setInterval(function(){cycle(i);}, 5200); }, 1600 + d*950);
    });
  })();
  </script>

  <?php if ($u): ?>
  <!-- signed-in family: quick shortcuts, without losing the home page -->
  <section class="memberbar">
    <div class="mb-inner">
      <div class="mb-hi">Welcome home, <b><?= e(explode(' ', $u['name'])[0]) ?></b>.
        <span><?= (int)$np ?> people in the tree &middot; <?= (int)$nph ?> photographs</span></div>
      <nav class="mb-links">
        <a href="tree.php">Family Tree</a>
        <a href="calendar.php">Family Calendar</a>
        <a href="upload.php">Add a Photo</a>
        <?php /* This bar already had an admin-only "Invite Family" that went to the
                 Members page. Putting a second link of the same name in the top menu,
                 going somewhere else, made the new one invisible: William looked for
                 "Invite Family", found the one he already knew, and reasonably
                 concluded nothing had changed. So the shortcut everyone gets keeps
                 the name, and his own link is renamed to match what the menu calls
                 that page. Two links, two names, two destinations. */ ?>
        <a href="invite_family.php" class="mb-new">Invite Family</a>
        <?php if (role_at_least('moderator')): ?><a href="moderate.php">Review Queue</a><?php endif; ?>
        <?php if (role_at_least('admin')): ?><a href="admin.php">Members</a><?php endif; ?>
      </nav>
    </div>
  </section>
  <?php endif; ?>

  <!-- scripture value strip -->
  <section class="valuestrip">
    <div class="vs-inner">
      <blockquote class="vs-verse"><?= e($BAND_VERSE) ?>
        <span><?= e($BAND_REF) ?></span></blockquote>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-4 8 4 4 0 0 0 1 7h6a4 4 0 0 0 1-7 5 5 0 0 0-4-8z"/><line x1="12" y1="13" x2="12" y2="22"/></svg></span>
        <div><h4>Our Roots</h4><p>Remember where we come from.</p></div>
      </div>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><line x1="12" y1="3" x2="12" y2="21"/><line x1="7" y1="9" x2="17" y2="9"/></svg></span>
        <div><h4>Our Faith</h4><p>Faith is the foundation of our family.</p></div>
      </div>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><circle cx="8" cy="9" r="2.4"/><circle cx="16" cy="9" r="2.4"/><path d="M3.5 19c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/><path d="M11.5 19c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/></svg></span>
        <div><h4>Our Family</h4><p>Together is our favorite place to be.</p></div>
      </div>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><polygon points="12,3 14.6,9.1 21,9.7 16.2,14 17.6,20.3 12,16.9 6.4,20.3 7.8,14 3,9.7 9.4,9.1"/></svg></span>
        <div><h4>Our Legacy</h4><p>Building a legacy they can be proud of.</p></div>
      </div>
    </div>
  </section>

  <!-- feature cards — every one of them now opens the page it is about -->
  <section class="homecards">
    <div class="hc-inner">
      <article class="hc">
        <h3><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M7 9h7M7 12h10M7 15h6"/></svg><span>Family News</span></h3>
        <div class="hc-media"><a href="<?= e($NEWS_HREF) ?>"><img src="<?= e($NEWS_PHOTO) ?>" alt="<?= e($NEWS_TITLE) ?>"></a></div>
        <h5><?= e($NEWS_TITLE) ?></h5>
        <?php if ($NEWS_DATE !== ''): ?><p class="hc-sub"><?= e($NEWS_DATE) ?></p><?php endif; ?>
        <a class="btn2" href="<?= e($NEWS_HREF) ?>">Read More</a>
      </article>

      <article class="hc">
        <h3><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/></svg><span>Upcoming Events</span></h3>
        <?php if ($FAMEV): ?>
          <ul class="hc-events">
            <?php foreach ($FAMEV as $ev): $d = strtotime($ev['next_date']); ?>
              <li><span class="ev-d"><?= e(news_month_label($d)) ?> <?= (int)date('j', $d) ?></span>
                <?= e($ev['title']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php elseif ($UPNEXT): ?>
          <ul class="hc-events">
            <?php foreach ($UPNEXT as $o): ?>
              <li><span class="ev-d"><?= e(cal_daylabel((int)$o['m'], (int)$o['d'])) ?></span>
                <?= e($o['title']) ?>
                <i class="ev-k"><?= e(cal_kinds()[$o['kind']][0] ?? 'Event') ?></i></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <ul class="hc-events"><li>Nothing on the calendar just yet.</li></ul>
        <?php endif; ?>
        <?php if (!$u && !$FAMEV): ?><p class="hc-priv">Sign in to see family birthdays and anniversaries.</p><?php endif; ?>
        <a class="btn2" href="calendar.php">View Calendar</a>
      </article>

      <article class="hc">
        <h3><svg viewBox="0 0 24 24"><path d="M8 21h8V10a4 4 0 0 0-8 0z"/><path d="M6 21h12M12 6V3M10.5 4.5h3"/></svg><span>Featured Memorial</span></h3>
        <div class="hc-media tall"><a href="<?= $mp ? 'tribute.php?pid=' . e(urlencode($MEM_PID)) : 'memorial.php' ?>"><img src="<?= e($MEM_PHOTO) ?>" alt="<?= e($MEM_NAME) ?>"></a></div>
        <h5><?= e($MEM_NAME) ?></h5>
        <?php if ($MEM_DATES !== ''): ?><p class="hc-sub"><?= e($MEM_DATES) ?></p><?php endif; ?>
        <a class="btn2" href="memorial.php">View Memorial</a>
      </article>

      <article class="hc">
        <h3><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-4 8 4 4 0 0 0 1 7h6a4 4 0 0 0 1-7 5 5 0 0 0-4-8z"/><path d="M12 13v9"/></svg><span>Family Tree</span></h3>
        <div class="hc-media"><a href="tree.php"><img src="assets/home-tree.jpg" alt="The Battles family tree"></a></div>
        <p class="hc-text">Explore our family tree and discover your roots.</p>
        <a class="btn2" href="tree.php">Explore Tree</a>
      </article>

      <article class="hc">
        <h3><svg viewBox="0 0 24 24"><path d="M12 7.5S10 5.5 6.5 5.5 2.5 7 2.5 7v11s1.5-1.5 4-1.5 5.5 2 5.5 2 3-2 5.5-2 4 1.5 4 1.5V7s-.5-1.5-4-1.5S12 7.5 12 7.5z"/><path d="M12 7.5V19"/></svg><span>Faith Corner</span></h3>
        <div class="hc-media"><a href="faith.php"><img src="assets/home-faith.jpg" alt="Open Bible by candlelight"></a></div>
        <p class="hc-text"><?= e($FAITH_VERSE) ?></p>
        <?php if ($FAITH_REF !== ''): ?><p class="hc-sub"><?= e($FAITH_REF) ?></p><?php endif; ?>
        <a class="btn2" href="faith.php">Read More</a>
      </article>
    </div>
  </section>

  <?php if ($isAdmin): ?>
  <!-- Everything on the cards above is text William can change himself. -->
  <section class="home-admin">
    <details>
      <summary><span class="ha-t">Edit the home page</span>
        <span class="ha-s">The featured story, the memorial, and both scriptures</span></summary>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="homeedit">

        <div class="ha-grid">
          <fieldset>
            <legend>Family News card</legend>
            <!-- These two boxes show what is *stored*, not what the card is
                 currently displaying. Filling them with the followed headline
                 would freeze it the first time William saved anything else. -->
            <p class="ha-note">Leave these empty and the card shows the newest announcement
              on the Family News page, keeping itself up to date<?= $LATEST ? ' (right now: &ldquo;' . e($LATEST['title']) . '&rdquo;)' : '' ?>.
              Type a headline only if you want it to stay put.</p>
            <label>Headline<input type="text" name="news_title" maxlength="120" value="<?= e(sm('home_news_title', '')) ?>" placeholder="follows the newest announcement"></label>
            <label>Date underneath<input type="text" name="news_date" maxlength="60" value="<?= e(sm('home_news_date', '')) ?>" placeholder="follows the newest announcement"></label>
            <label>Change the picture<input type="file" name="news_photo" accept="image/*"></label>
          </fieldset>

          <fieldset>
            <legend>Featured Memorial card</legend>
            <label>Who is featured
              <select name="mem_pid">
                <option value="">— choose someone —</option>
                <?php foreach (all("SELECT pid,name FROM persons WHERE living=0 AND name<>'' ORDER BY name") as $d): ?>
                  <option value="<?= e($d['pid']) ?>"<?= $d['pid'] === $MEM_PID ? ' selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select></label>
            <label>Change the picture<input type="file" name="mem_photo" accept="image/*"></label>
            <p class="ha-note">The name and dates come from that person&rsquo;s page in the tree, so they are always right.</p>
          </fieldset>

          <fieldset>
            <legend>Faith Corner verse</legend>
            <label>The verse<textarea name="faith_verse" rows="4" maxlength="600"><?= e($FAITH_VERSE) ?></textarea></label>
            <label>Where it is from<input type="text" name="faith_ref" maxlength="120" value="<?= e($FAITH_REF) ?>" placeholder="— Joshua 1:9"></label>
          </fieldset>

          <fieldset>
            <legend>Scripture band</legend>
            <label>The verse<textarea name="band_verse" rows="4" maxlength="600"><?= e($BAND_VERSE) ?></textarea></label>
            <label>Where it is from<input type="text" name="band_ref" maxlength="120" value="<?= e($BAND_REF) ?>" placeholder="— Psalm 145:4"></label>
          </fieldset>
        </div>

        <div class="ha-act">
          <button class="btn2 solid" type="submit">Save the home page</button>
          <button class="ha-reset" type="submit" name="reset_defaults" value="1"
            onclick="return confirm('Put every one of these back the way it started?')">Reset to the original wording</button>
        </div>
      </form>
    </details>
  </section>
  <?php endif; ?>

  <!-- everyone's project: the invitation to comment -->
  <section class="askband">
    <div class="ab-inner">
      <h3>This is everyone&rsquo;s project.</h3>
      <p>Have a look around, then tell us what you think &mdash; what you like, what you&rsquo;d change,
         and what&rsquo;s missing. Every opinion and suggestion goes straight to William.</p>
      <a class="btn gold" href="feedback.php">Share your thoughts</a>
    </div>
  </section>

  <?php music_script(); ?>

  <?php legacy_footer();
page_foot();
