<?php
/** What's New — the newest things the family has added, photographs first.
 *
 *  Nothing on the site has ever said "this changed since you were last here",
 *  so a person who looked once had no reason to look twice. This page is
 *  different every time somebody adds a photograph, which is the only kind of
 *  page that earns a second visit.
 *
 *  It shows what is already on the site; it does not decide who may see what.
 *  A signed-out visitor is served the same page with the living relatives left
 *  out, which is the rule family.php and person.php already apply. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/whatsnew.php';

$member  = logged_in();
$isAdmin = role_at_least('admin');
$PHOTOS  = wn_photos(30, $member);
$RECENT  = wn_recent(18, $member);
$MONTH   = wn_photo_count(30, $member);

page_head("What's New", ['body_class' => 'home wnpage']);
?>
<section class="wn-hero">
  <h1>What&rsquo;s New</h1>
  <p>The newest things the family has put on the site &mdash; photographs first. It changes every time
    somebody adds one, so it is worth looking again.</p>
  <?php if ($MONTH): ?>
    <div class="wn-count"><b><?= (int)$MONTH ?></b> photograph<?= $MONTH === 1 ? '' : 's' ?> added in the last month</div>
  <?php endif; ?>
</section>

<div class="wn-wrap">

  <?php if ($PHOTOS): ?>
    <h2 class="wn-h2">The newest photographs</h2>
    <div class="wn-grid">
      <?php foreach ($PHOTOS as $ph):
        list($who, $cap) = wn_caption($ph);
        /* A photograph with no person on it still belongs here — it is new —
           but there is nowhere to send anyone, so it is not a link. */
        $href = trim((string)$ph['pid']) !== '' ? 'person.php?pid=' . urlencode($ph['pid']) : '';
        $tag  = $href !== '' ? 'a' : 'span';
      ?>
        <<?= $tag ?> class="wn-card"<?= $href !== '' ? ' href="' . e($href) . '"' : '' ?>>
          <span class="wn-img" style="background-image:url('<?= e($ph['path']) ?>')"></span>
          <span class="wn-cap">
            <?php if ($who !== ''): ?><b><?= e($who) ?></b><?php endif; ?>
            <?php if ($cap !== ''): ?><i><?= e($cap) ?></i><?php endif; ?>
            <em><?= e(wn_ago($ph['created_at'])) ?></em>
          </span>
        </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
    <p class="wn-more"><a class="btn gold" href="family.php">Browse the whole family</a>
      <?php if ($member): ?><a class="btn2" href="upload.php">Add a photograph</a><?php endif; ?></p>
  <?php else: ?>
    <div class="wn-empty">
      <h2>Nothing has been added yet</h2>
      <p>When somebody puts a photograph on the site, or writes down what they remember about a relative,
        it appears here first.</p>
      <?php if ($member): ?><p><a class="btn gold" href="upload.php">Be the first &mdash; add a photograph</a></p><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($RECENT): ?>
    <h2 class="wn-h2">Everything else that has happened</h2>
    <ul class="wn-list">
      <?php foreach ($RECENT as $r): ?>
        <li class="wn-li wn-<?= e($r['kind']) ?>">
          <span class="wn-when"><?= e(wn_ago($r['ts'])) ?></span>
          <span class="wn-what">
            <?php if ($r['href'] !== ''): ?><a href="<?= e($r['href']) ?>"><?= e($r['what']) ?></a>
            <?php else: ?><?= e($r['what']) ?><?php endif; ?>
            <?php if ($r['who'] !== ''): ?><i>&mdash; <?= e($r['who']) ?></i><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!$member): ?>
    <div class="wn-signin">
      <p>You are seeing the part of this that is open to everybody. Family members who sign in also see
        the living side of the family &mdash; their photographs, their birthdays and their pages.</p>
      <p><a class="btn gold" href="login.php">Sign in</a>
         <a class="btn2" href="request.php">Ask William to let you in</a></p>
    </div>
  <?php endif; ?>

</div>
<?php legacy_footer(); page_foot();
