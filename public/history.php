<?php
require __DIR__ . '/../src/bootstrap.php';

/* The family's "Our History" section — 11 chapters. Preview text is transcribed from the
   client's design; the full write-ups get dropped into $full as he supplies them. Images marked
   img=>'' render a tasteful placeholder rather than an invented face (per the family's wishes). */
$CHAPTERS = [
  ['n'=>1,'slug'=>'thank-you','title'=>'A Special Thank You','date'=>'Jan 22 2011','img'=>'',
   'ex'=>"I would like to whole heartedly thank Rodney Augustus Battles and Annie Pearl Hale for their untiring dedication and hard work in researching information regarding our family history."],
  ['n'=>2,'slug'=>'introduction','title'=>'Introduction','date'=>'Jan 15 2011','img'=>'c02.jpg',
   'ex'=>"In 1998, W.J. Battles organized a Labor Day Reunion for the descendants of Gus and Angie Battles. In 1999, Javaun \"Ree Ree\" Smith Jackson organized another reunion. At the 1999 reunion, we toured neighborhoods in Fort Worth."],
  ['n'=>3,'slug'=>'richmond-battles','title'=>'Richmond Battles','date'=>'Jul 14 2012','img'=>'',
   'ex'=>"A few slaves were imported from Africa as early as 1619. Sometime around 1814, a white man named John N. Battles purchased several slaves in Norfolk, Virginia and transported them to Monroe County, Mississippi Territory."],
  ['n'=>4,'slug'=>'acknowledgments','title'=>'Acknowledgments','date'=>'Jan 15 2011','img'=>'c04.jpg',
   'ex'=>"Annie Pearl Battles Hale worked diligently to provide information, copies of obituaries, and photographs for many of the Battles family members in East Texas for the first edition of the Battles' Book."],
  ['n'=>5,'slug'=>'william-bill-johnson','title'=>'William "Bill" Johnson','date'=>'Jan 15 2011','img'=>'',
   'ex'=>"William \"Bill\" Johnson is listed in the 1880 Census Report as black. The 1910 Census Report listed him as mulatto. According to family members, he was part black and part white with blue eyes and looked like a white man."],
  ['n'=>6,'slug'=>'about-the-us-census','title'=>'About The U.S. Census','date'=>'Nov 09 2011','img'=>'c06.jpg',
   'ex'=>"Researching a family's history most often begins with the U.S. Census Reports. The United States Constitution mandates that the census be taken at least once every 10 years, and that the number of members of each household be recorded."],
  ['n'=>7,'slug'=>'census-report-1870','title'=>'Census Report 1870','date'=>'Jul 14 2012','img'=>'c07.jpg',
   'ex'=>"In the 1870 Census Report, Richmond's name is incorrectly spelled as Richmond Batterley. His age is incorrectly listed as 26. His wife Louisa's age is incorrectly listed as 28."],
  ['n'=>8,'slug'=>'census-report-1880','title'=>'Census Report 1880','date'=>'Jul 14 2012','img'=>'c08.jpg',
   'ex'=>"The 1880 Census Report also includes the following information for Richmond and Louisa Battles. The information going across for each person is: name; color/race; gender; age; relation to head of household."],
  ['n'=>9,'slug'=>'census-report-1900','title'=>'Census Report 1900','date'=>'Jun 14 2012','img'=>'c09.jpg',
   'ex'=>"William Daniels' son, Joe Daniels, reportedly cut a white man down to the ground on Wall Street in downtown Tyler in 1925 when he was 28 and had to leave town. After the incident, Joe and Luther \"Chap\" Battles left as well."],
  ['n'=>10,'slug'=>'garfield-school','title'=>'Garfield School, Tyler, Texas','date'=>'Jan 17 2011','img'=>'c10.jpg',
   'ex'=>"Garfield School was one of the earliest schools for African American children in Tyler, Texas. Many members of the Battles family attended Garfield School during the late 1800s and early 1900s."],
  ['n'=>11,'slug'=>'family-bible','title'=>"Horatio & Lizzie Battles' Family Bible",'date'=>'Jan 16 2011','img'=>'c11.jpg',
   'ex'=>"This family Bible has been a treasured keepsake for generations. Recorded within its pages are births, marriages, and deaths — preserving the legacy and lineage of the Battles family."],
];
$bySlug = [];
foreach ($CHAPTERS as $c) $bySlug[$c['slug']] = $c;

$sel = $_GET['ch'] ?? '';
$current = $bySlug[$sel] ?? null;   // null = "All Chapters" view

// small helpers for placeholder / image markup
function hist_media($c) {
    if (!empty($c['img'])) {
        return '<div class="hc-img"><img src="assets/history/' . e($c['img']) . '" alt="' . e($c['title']) . '"></div>';
    }
    return '<div class="hc-img ph"><svg viewBox="0 0 24 24" class="ph-ic"><circle cx="12" cy="9" r="3.4"/><path d="M5 20c0-3.6 3-6 7-6s7 2.4 7 6"/></svg><span>Photo to be added</span></div>';
}

page_head($current ? $current['title'] : 'Our History', ['body_class' => 'home hist']);
?>
<section class="hist-hero">
  <div class="hh-photo hh-left"><img src="assets/history/hero_group.jpg" alt="Battles family, early generations"></div>
  <div class="hh-center">
    <h1 class="hist-title">Our History</h1>
    <div class="hist-orn">&#10086;</div>
    <p>Preserving our stories. Honoring our ancestors. Building our legacy.</p>
  </div>
  <div class="hh-photo hh-right"><img src="assets/history/hero_bible.jpg" alt="Battles Family Bible — Births"></div>
</section>

<div class="hist-body">
 <div class="hist-wrap">
  <aside class="hist-side">
    <h3>History Chapters</h3>
    <a class="hs-item hs-all<?= $current ? '' : ' on' ?>" href="history.php"><span class="hs-home">&#8962;</span> All Chapters</a>
    <?php foreach ($CHAPTERS as $c): ?>
      <a class="hs-item<?= $current && $current['slug']===$c['slug'] ? ' on' : '' ?>" href="history.php?ch=<?= e($c['slug']) ?>">
        <span class="hs-num"><?= $c['n'] ?></span><?= e($c['title']) ?></a>
    <?php endforeach; ?>
    <div class="hs-tree"><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-4 8 4 4 0 0 0 1 7h6a4 4 0 0 0 1-7 5 5 0 0 0-4-8z"/><line x1="12" y1="13" x2="12" y2="22"/></svg></div>
    <blockquote class="hs-quote">"Those who do not remember the past are condemned to repeat it."<span>&mdash; George Santayana</span></blockquote>
  </aside>

  <main class="hist-main">
  <?php if (!$current): /* ---------- ALL CHAPTERS ---------- */ ?>
    <div class="hist-grid">
      <?php foreach ($CHAPTERS as $c): ?>
        <article class="hist-card" id="ch-<?= $c['n'] ?>">
          <?= hist_media($c) ?>
          <div class="hc-body">
            <div class="hc-head"><span class="hc-num"><?= $c['n'] ?></span><h3><?= e($c['title']) ?></h3></div>
            <div class="hc-date"><?= e($c['date']) ?></div>
            <p class="hc-ex"><?= e($c['ex']) ?></p>
            <a class="btn2" href="history.php?ch=<?= e($c['slug']) ?>">Read Full Story &rsaquo;</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: /* ---------- SINGLE CHAPTER ---------- */
    $i = array_search($current['slug'], array_column($CHAPTERS, 'slug'));
    $prev = $i > 0 ? $CHAPTERS[$i-1] : null;
    $next = $i < count($CHAPTERS)-1 ? $CHAPTERS[$i+1] : null;
  ?>
    <article class="hist-full">
      <div class="hf-head"><span class="hc-num big"><?= $current['n'] ?></span>
        <div><h2><?= e($current['title']) ?></h2><div class="hc-date"><?= e($current['date']) ?></div></div></div>
      <?php if (!empty($current['img'])): ?>
        <div class="hf-img"><img src="assets/history/<?= e($current['img']) ?>" alt="<?= e($current['title']) ?>"></div>
      <?php endif; ?>
      <div class="hf-text">
        <p><?= e($current['ex']) ?></p>
        <p class="muted" style="margin-top:16px">The complete chapter will appear here soon.</p>
      </div>
      <div class="hf-nav">
        <?php if ($prev): ?><a href="history.php?ch=<?= e($prev['slug']) ?>">&lsaquo; <?= e($prev['title']) ?></a><?php else: ?><span></span><?php endif; ?>
        <a href="history.php" class="hf-all">All Chapters</a>
        <?php if ($next): ?><a href="history.php?ch=<?= e($next['slug']) ?>"><?= e($next['title']) ?> &rsaquo;</a><?php else: ?><span></span><?php endif; ?>
      </div>
    </article>
  <?php endif; ?>
  </main>
 </div>
</div>
<?php legacy_footer(); page_foot();
