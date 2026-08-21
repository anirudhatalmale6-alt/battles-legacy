<?php
/** Business Resources — the second of the four dead cards.
 *
 *  The card promised "templates, guides, funding resources, legal forms and
 *  tools" and led nowhere. Rather than ship an empty page with an "ask William
 *  to add some links" note, this opens with a set of real ones: the SBA guides,
 *  the free mentoring bodies, the filings pages, and the two warnings that
 *  matter most to anybody starting out — the IRS never charges for an EIN, and
 *  a genuine grant never asks you for a fee. Every one of them is free.
 *
 *  They are rows in a table, not text in this file, so William can edit, hide
 *  or replace any of them from the manage screen without me. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';
require_once __DIR__ . '/../src/mentor_data.php';

$GROUPS = res_grouped();
$TOTAL  = 0;
foreach ($GROUPS as $g) $TOTAL += count($g);

page_head('Business Resources', ['body_class' => 'home entpage']);
?>
<section class="dir-head">
  <p class="dir-eyebrow"><a href="enterprise.php">&larr; Enterprise</a></p>
  <h1>Business Resources</h1>
  <p class="dir-sub">Everything below is free, and almost all of it comes from the agencies whose job
     it is to help small businesses. Most people never find out this help exists.</p>
</section>

<?php if (role_at_least('admin')): ?>
  <div class="ent2-adminbar" style="max-width:1000px;margin:0 auto 18px">
    <span>You&rsquo;re signed in as an editor.</span>
    <a class="ent2-editbtn" href="mentors_manage.php?tab=resources">&#9998; Edit these resources</a>
  </div>
<?php endif; ?>

<?php if (!$TOTAL): ?>
  <div class="panel dir-none"><h2>Nothing here yet.</h2>
    <p class="muted">The resource list is empty. An editor can add links from the manage screen.</p></div>
<?php else: ?>
  <?php foreach ($GROUPS as $cat => $rows): ?>
    <section class="rs-group">
      <h2 class="rs-cat"><?= e($cat) ?></h2>
      <div class="rs-grid">
        <?php foreach ($rows as $r):
          $url = trim((string)$r['url']);
          if ($url !== '' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
          /* The host is printed under the title on purpose. A link that says
             irs.gov is one you can check before you click it, and half of what
             goes wrong for a new business owner starts with a lookalike site. */
          $host = $url ? preg_replace('~^www\.~i', '', (string)parse_url($url, PHP_URL_HOST)) : '';
        ?>
          <?php if ($url): ?><a class="rs-card" href="<?= e($url) ?>" target="_blank" rel="noopener">
          <?php else: ?><div class="rs-card">
          <?php endif; ?>
            <span class="rs-ic"><?= ent_icon($r['icon']) ?></span>
            <span class="rs-body">
              <span class="rs-title"><?= e($r['title']) ?>
                <?php if (trim((string)$r['cost']) !== ''): ?><b class="rs-free"><?= e($r['cost']) ?></b><?php endif; ?>
              </span>
              <?php if ($host): ?><span class="rs-host"><?= e($host) ?></span><?php endif; ?>
              <?php if (trim((string)$r['blurb']) !== ''): ?><span class="rs-blurb"><?= e($r['blurb']) ?></span><?php endif; ?>
              <?php if (trim((string)$r['caution']) !== ''): ?>
                <span class="rs-warn"><?= ent_icon('shield') ?><span><?= e($r['caution']) ?></span></span>
              <?php endif; ?>
            </span>
          <?php if ($url): ?></a><?php else: ?></div><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<section class="dir-cta">
  <div class="dir-ctabox">
    <h2>Rather ask a person?</h2>
    <p>Family who have already built something will talk it through with you. No charge, no pitch.</p>
    <a class="btn gold" href="<?= logged_in() ? 'mentors.php' : 'login.php' ?>">Find a mentor</a>
  </div>
  <div class="dir-ctabox">
    <h2>Know a resource we are missing?</h2>
    <p>If something helped you, it will help the next one of us. Send it over and it goes on this page.</p>
    <a class="btn gold" href="<?= logged_in() ? 'get_involved.php?offer=resource' : 'login.php' ?>">Suggest a resource</a>
  </div>
</section>

<?php legacy_footer(); page_foot();
