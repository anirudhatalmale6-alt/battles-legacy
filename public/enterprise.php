<?php
require __DIR__ . '/../src/bootstrap.php';

/* ============================================================
   ENTERPRISE — a starting layout for client review.
   Everything on this page is driven by the three arrays below.
   Once the layout is approved, these arrays get replaced by a
   small admin screen (add/edit/remove) so William can manage
   the businesses, sayings and videos himself from the website.
   The sample entries are illustrative only (marked "Example").
   ============================================================ */

/* --- Motivational sayings (rotate on the page) --- */
$SAYINGS = [
  ['If you don\'t like something, change it. If you can\'t change it, change your attitude.', 'Maya Angelou'],
  ['The time is always right to do what is right.', 'Dr. Martin Luther King Jr.'],
  ['Success is to be measured not so much by the position that one has reached in life as by the obstacles overcome.', 'Booker T. Washington'],
  ['Faith is taking the first step even when you don\'t see the whole staircase.', 'Dr. Martin Luther King Jr.'],
  ['Hard work, perseverance, and faith will carry a family further than any inheritance.', 'A Battles family saying'],
];

/* --- Member businesses & professions --- */
$VENTURES = [
  ['name'=>'Evergreen Catering Co.','cat'=>'Business','who'=>'Owned by a Battles family member',
   'blurb'=>'Full-service catering for reunions, weddings, and celebrations across the Fort Worth area.',
   'link'=>'', 'sample'=>true],
  ['name'=>'Registered Nurse','cat'=>'Profession','who'=>'A Battles family member',
   'blurb'=>'Caring for patients in the Fort Worth area for more than fifteen years.',
   'link'=>'', 'sample'=>true],
  ['name'=>'Battles &amp; Sons Contracting','cat'=>'Business','who'=>'Family-owned',
   'blurb'=>'Residential building, remodeling, and honest craftsmanship handed down through the generations.',
   'link'=>'', 'sample'=>true],
  ['name'=>'Educator &amp; School Principal','cat'=>'Profession','who'=>'A Battles family member',
   'blurb'=>'Three decades shaping young minds in the classroom and leading the school community.',
   'link'=>'', 'sample'=>true],
];

/* --- Videos --- */
$VIDEOS = [
  ['title'=>'Labor Day Family Reunion','desc'=>'Highlights from our yearly gathering of the Battles family.','sample'=>true],
  ['title'=>'A Word of Encouragement','desc'=>'A short message to lift up and inspire the family.','sample'=>true],
  ['title'=>'Business Spotlight','desc'=>'Meet a family entrepreneur and hear their story.','sample'=>true],
];

/* helper: initials monogram from a name */
function ent_mono($name) {
    $clean = trim(html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8'));
    $parts = preg_split('/\s+/', $clean);
    $ini = strtoupper(substr($parts[0], 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
    return $ini !== '' ? $ini : '&#10086;';
}

page_head('Enterprise', ['body_class' => 'home ent']);
?>
<section class="ent-hero">
  <h1 class="ent-title">Enterprise</h1>
  <div class="ent-orn">&#10086;</div>
  <p>Celebrating the ambition, craft, and hard work of the Battles family &mdash;
     our businesses, our professions, and the words that keep us striving.</p>
</section>

<!-- rotating motivational saying -->
<section class="ent-quote">
  <span class="eq-mark">&ldquo;</span>
  <blockquote id="eq-text"><?= e($SAYINGS[0][0]) ?></blockquote>
  <cite id="eq-who"><?= e($SAYINGS[0][1]) ?></cite>
</section>

<div class="ent-wrap">

  <!-- Businesses & Professions -->
  <div class="ent-head">
    <span class="eh-orn">&#10086;</span>
    <h2>Family in Business &amp; Profession</h2>
    <p>Recognizing the members of our family who own businesses or serve in their professions.
       Each card below is an example of how an entry will look &mdash; you'll add your own from your dashboard.</p>
  </div>

  <div class="ent-grid">
    <?php foreach ($VENTURES as $v): ?>
      <article class="ent-card">
        <div class="ent-mono"><?= ent_mono($v['name']) ?></div>
        <div class="ent-cbody">
          <div class="ent-chips">
            <span class="ent-chip <?= $v['cat']==='Business'?'biz':'prof' ?>"><?= e($v['cat']) ?></span>
            <?php if (!empty($v['sample'])): ?><span class="ent-chip sample">Example</span><?php endif; ?>
          </div>
          <h3><?= $v['name'] /* trusted, authored above */ ?></h3>
          <div class="ent-who"><?= e($v['who']) ?></div>
          <p class="ent-blurb"><?= e($v['blurb']) ?></p>
          <?php if (!empty($v['link'])): ?><a class="ent-link" href="<?= e($v['link']) ?>">Visit &rsaquo;</a><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <!-- add-tile: where a logged-in member/admin will add a new entry -->
    <article class="ent-card ent-add">
      <div class="ent-plus">+</div>
      <div class="ent-cbody"><h3>Add a business or profession</h3>
        <p class="ent-blurb">You'll be able to add family members and their ventures here, right from the website.</p></div>
    </article>
  </div>

  <!-- Videos -->
  <div class="ent-head">
    <span class="eh-orn">&#10086;</span>
    <h2>Watch &amp; Be Inspired</h2>
    <p>Reunion highlights, encouragement, and family spotlights &mdash; add a video link and it appears here.</p>
  </div>

  <div class="vid-grid">
    <?php foreach ($VIDEOS as $vid): ?>
      <article class="vid-card">
        <div class="vid-thumb">
          <span class="vid-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
          <?php if (!empty($vid['sample'])): ?><span class="ent-chip sample vid-tag">Example</span><?php endif; ?>
        </div>
        <div class="vid-body"><h3><?= e($vid['title']) ?></h3><p><?= e($vid['desc']) ?></p></div>
      </article>
    <?php endforeach; ?>
    <article class="vid-card vid-add">
      <div class="vid-thumb add"><span class="ent-plus">+</span></div>
      <div class="vid-body"><h3>Add a video</h3><p>Paste a YouTube or Vimeo link and it plays right here.</p></div>
    </article>
  </div>

  <p class="ent-note">This is a starting layout with sample content, so you have something concrete to react to.
     Once you're happy with the look, I'll build the simple admin screen so you can add, edit, and remove
     businesses, sayings, and videos yourself &mdash; no coding, all from your end.</p>

</div>

<script>
(function(){
  var S = <?= json_encode($SAYINGS, JSON_UNESCAPED_UNICODE) ?>;
  if (!S || S.length < 2) return;
  var t = document.getElementById('eq-text'), w = document.getElementById('eq-who'), i = 0;
  var box = document.querySelector('.ent-quote');
  setInterval(function(){
    i = (i + 1) % S.length;
    box.classList.add('fade');
    setTimeout(function(){ t.textContent = S[i][0]; w.textContent = S[i][1]; box.classList.remove('fade'); }, 500);
  }, 6000);
})();
</script>
<?php legacy_footer(); page_foot();
