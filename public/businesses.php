<?php
/** Hire Family First — the full family business directory.
 *
 *  The Enterprise page had a search box wired to onsubmit="return false" and a
 *  "View All Family Businesses" button that was a <button> with no handler. You
 *  could type a name, press Search, and nothing happened at all. This is where
 *  both of them now go, and the search actually runs.
 *
 *  Public on purpose: these entries are businesses, and a business exists to be
 *  found. Nothing here says who is alive or how they are related, so it does not
 *  cut across the rule that living relatives are kept for signed-in family. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';

try { ent_migrate(); $ALL = ent_businesses(); } catch (\Throwable $ex) { $ALL = []; }

$q    = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
if (!ent_type_ok($type)) $type = '';

/** Does this row match what was typed?
 *
 *  Every word has to appear somewhere in the row, but they may appear in
 *  different fields — so "battles houston" finds the Houston law firm without
 *  anybody having to guess which box the town was typed into. Matching one
 *  word out of two would put half the directory on screen and read as broken. */
function biz_matches($r, $q) {
    if ($q === '') return true;
    $hay = mb_strtolower(implode(' ', [
        $r['name'], $r['owner'], $r['category'], $r['cat_type'], $r['location'], $r['blurb'],
    ]));
    foreach (preg_split('/\s+/', mb_strtolower($q)) as $w) {
        if ($w === '') continue;
        if (mb_strpos($hay, $w) === false) return false;
    }
    return true;
}

$ROWS = [];
foreach ($ALL as $r) {
    if ($type !== '' && $r['cat_type'] !== $type) continue;
    if (!biz_matches($r, $q)) continue;
    $ROWS[] = $r;
}
$filtered = ($q !== '' || $type !== '');

/* Places already in the directory, offered as one-click filters. Built from the
   data rather than typed here, so it stays true as businesses are added. */
$PLACES = [];
foreach ($ALL as $r) {
    $loc = trim((string)$r['location']);
    if ($loc === '') continue;
    if (!isset($PLACES[$loc])) $PLACES[$loc] = 0;
    $PLACES[$loc]++;
}
ksort($PLACES);

page_head('Family Business Directory', ['body_class' => 'home entpage']);
?>
<section class="dir-head">
  <p class="dir-eyebrow"><a href="enterprise.php">&larr; Enterprise</a></p>
  <h1>Hire Family First</h1>
  <p class="dir-sub">Every business, profession, book and article the family has put on the site.
     Search it before you go looking elsewhere &mdash; the money stays in the family, and so does the
     goodwill.</p>
</section>

<form class="dir-search" method="get" action="businesses.php">
  <div class="dir-sfield">
    <?= ent_icon('search') ?>
    <input type="text" name="q" value="<?= e($q) ?>" autofocus
           placeholder="Name, trade, town &mdash; try &ldquo;law&rdquo; or &ldquo;Dallas&rdquo;">
  </div>
  <select name="type" aria-label="Category">
    <option value="">All categories</option>
    <?php foreach (ent_types() as $v => $lbl): ?>
      <option value="<?= e($v) ?>"<?= $type === $v ? ' selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="ent2-btn">Search</button>
  <?php if ($filtered): ?><a class="dir-clear" href="businesses.php">Clear</a><?php endif; ?>
</form>

<?php if ($PLACES && !$filtered): ?>
  <p class="dir-places"><span>Where they are:</span>
    <?php foreach ($PLACES as $loc => $n): ?>
      <a href="businesses.php?q=<?= rawurlencode($loc) ?>"><?= e($loc) ?> <b><?= (int)$n ?></b></a>
    <?php endforeach; ?>
  </p>
<?php endif; ?>

<p class="dir-count">
  <?php if (!$ALL): ?>
    Nothing has been added to the directory yet.
  <?php elseif ($filtered): ?>
    <b><?= count($ROWS) ?></b> of <?= count($ALL) ?>
    <?= count($ALL) === 1 ? 'entry' : 'entries' ?> match<?= count($ROWS) === 1 ? 'es' : '' ?>.
  <?php else: ?>
    <b><?= count($ALL) ?></b> <?= count($ALL) === 1 ? 'entry' : 'entries' ?> in the directory.
  <?php endif; ?>
</p>

<?php if ($ROWS): ?>
<div class="biz-grid dir-grid">
  <?php foreach ($ROWS as $b): ?>
    <?php $btype = $b['cat_type']; $isWork = ($btype === 'Book' || $btype === 'Article');
          $fit = ((isset($b['photo_fit']) ? $b['photo_fit'] : 'cover') === 'contain') ? ' fit' : ''; ?>
    <article class="biz-card">
      <div class="biz-photo<?= $fit ?>"<?= $b['photo'] ? ' style="background-image:url(\''.e($b['photo']).'\')"' : ' data-empty="1"' ?>>
        <?php if ($b['sample']): ?><span class="ent-ex">Example</span><?php endif; ?>
        <?php if ($isWork): ?><span class="biz-type"><?= $btype === 'Book' ? 'Published Book' : 'Published Article' ?></span><?php endif; ?>
        <span class="biz-mono"><?= ent_mono($b['name']) ?></span>
      </div>
      <div class="biz-body">
        <h3><?= e($b['name']) ?></h3>
        <?php if ($b['owner']): ?><div class="biz-who"><?= e($b['owner']) ?></div><?php endif; ?>
        <?php if ($b['category']): ?><div class="biz-cat"><?= e($b['category']) ?></div><?php endif; ?>
        <?php if ($b['location']): ?><div class="biz-loc"><?= e($b['location']) ?></div><?php endif; ?>
        <?php if ($b['blurb']): ?><p class="biz-blurb"><?= e($b['blurb']) ?></p><?php endif; ?>
        <div class="biz-ico">
          <?php
            $web = trim($b['link']); if ($web && !preg_match('~^https?://~i', $web)) $web = 'http://' . $web;
            echo $web ? '<a href="'.e($web).'" target="_blank" rel="noopener">'.ent_icon('globe').'</a>' : '<span class="off">'.ent_icon('globe').'</span>';
            echo $b['phone'] ? '<a href="tel:'.e(preg_replace('/[^0-9+]/','',$b['phone'])).'">'.ent_icon('phone').'</a>' : '<span class="off">'.ent_icon('phone').'</span>';
            echo $b['email'] ? '<a href="mailto:'.e($b['email']).'">'.ent_icon('mail').'</a>' : '<span class="off">'.ent_icon('mail').'</span>';
          ?>
        </div>
        <?php $cta = ent_cta($btype); ?>
        <?php if ($web): ?>
          <a class="ent2-btn biz-view" href="<?= e($web) ?>" target="_blank" rel="noopener"><?= e($cta) ?></a>
        <?php else: ?>
          <?php /* No website on the record. The card used to show a button that
                   did nothing; ask the family for the details instead, which is
                   the thing a visitor actually wants next. */ ?>
          <span class="biz-nolink">No website on file &mdash;
            <?= $b['phone'] ? 'call the number above' : 'ask William for the details' ?></span>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php elseif ($ALL): ?>
  <div class="panel dir-none">
    <h2>Nothing matched<?= $q !== '' ? ' &ldquo;' . e($q) . '&rdquo;' : '' ?>.</h2>
    <p class="muted">The directory is still small, so a lot of the family are not in it yet. Try a
       shorter word, or clear the search and read the whole list &mdash; it is not long.</p>
    <p style="margin-top:14px">
      <a class="btn gold" href="businesses.php">Show everything</a>
      <?php if (logged_in()): ?>
        <a class="btn" href="enterprise_submit.php" style="margin-left:8px">Add a business</a>
      <?php endif; ?>
    </p>
  </div>
<?php endif; ?>

<section class="dir-cta">
  <div class="dir-ctabox">
    <h2>Is your business missing?</h2>
    <p>Add it yourself &mdash; William reviews it and it appears here and on the Enterprise page.</p>
    <?php if (logged_in()): ?>
      <a class="btn gold" href="enterprise_submit.php">Add your business</a>
    <?php else: ?>
      <a class="btn gold" href="login.php">Sign in to add it</a>
    <?php endif; ?>
  </div>
  <div class="dir-ctabox">
    <h2>Thinking of starting one?</h2>
    <p>Family who have done it will talk it through with you, and the free help worth knowing about is
       gathered in one place.</p>
    <a class="btn gold" href="<?= logged_in() ? 'mentors.php' : 'login.php' ?>">Find a mentor</a>
    <a class="btn" href="resources.php" style="margin-left:8px">Business resources</a>
  </div>
</section>

<?php legacy_footer(); page_foot();
