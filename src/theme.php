<?php
/** Shared page chrome — burgundy + gold + sepia, matching the demo. */

function page_head($title, $opts = []) {
    $full = $opts['full'] ?? false; // full = no container padding (used by the tree)
    $bodyClass = $opts['body_class'] ?? ''; // extra body class (e.g. 'home')
    $u = current_user();
    $site = config('site_name');
    // private, built-in visit counter (never interrupts the page)
    if (is_file(__DIR__ . '/stats_data.php')) { require_once __DIR__ . '/stats_data.php'; stats_record($title); }
    // the suggestion tab + its unread badge; every call inside is failure-proof
    if (is_file(__DIR__ . '/feedback_data.php')) require_once __DIR__ . '/feedback_data.php';
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e($site) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,500&family=EB+Garamond&family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=<?= @filemtime(__DIR__ . '/../public/assets/app.css') ?: date('Ymd') ?>">
</head>
<body class="<?= trim(($full ? 'full ' : '') . $bodyClass . (preview_bar_on() ? ' haspv' : '')) ?>">
<header class="nav">
  <a class="brand" href="index.php">
    <span class="brand-name"><span class="script">The Battles</span> Legacy</span>
    <span class="brand-tag">Honoring Our Past. Inspiring Our Future.</span>
  </a>
  <nav class="links">
    <a href="index.php">Home</a>
    <a href="history.php">History</a>
    <a href="tree.php">Family Tree</a>
    <a href="faith.php">Faith</a>
    <a href="enterprise.php">Enterprise</a>
    <a href="health.php">Health</a>
    <a href="news.php">Family News</a>
    <a href="calendar.php">Calendar</a>
    <a href="memorial.php">Memorial</a>
    <a href="aahistory.php">African American History</a>
    <a href="feedback.php">Your Thoughts</a>
    <?php if ($u): ?>
      <a href="upload.php">Add a Photo</a>
      <?php if (role_at_least('moderator')): ?>
        <a href="moderate.php">Review Queue<?php $c = one("SELECT COUNT(*) c FROM photos WHERE status='pending'"); if ($c && $c['c']) echo ' <b class="badge">' . (int)$c['c'] . '</b>'; ?></a>
        <a href="tree_review.php">Tree Edits<?php try { $tc = one("SELECT COUNT(*) c FROM tree_suggestions WHERE status='pending'"); if ($tc && (int)$tc['c']) echo ' <b class="badge">' . (int)$tc['c'] . '</b>'; } catch (Exception $e) {} ?></a>
      <?php endif; ?>
      <?php if (role_at_least('admin')): ?><a href="admin.php">Members<?php if (is_file(__DIR__ . '/access_data.php')) { require_once __DIR__ . '/access_data.php'; $ac = ar_count(); if ($ac) echo ' <b class="badge">' . (int)$ac . '</b>'; } ?></a><a href="enterprise_manage.php">Edit Enterprise</a><a href="photos_manage.php">Photographs</a><a href="stats.php">Visitors</a><a href="faith_manage.php">Prayers<?php if (function_exists('faith_prayer_count')) { $fc = @faith_prayer_count(); if ($fc) echo ' <b class="badge">' . (int)$fc . '</b>'; } ?></a><a href="feedback_manage.php">Feedback<?php if (function_exists('fb_new_count')) { $nb = fb_new_count(); if ($nb) echo ' <b class="badge">' . (int)$nb . '</b>'; } ?></a><?php endif; ?>
      <span class="who"><?= e($u['name']) ?> · <?= e(ucfirst($u['role'])) ?></span>
      <a class="btn-ghost" href="logout.php">Sign out</a>
    <?php else: ?>
      <a class="btn-ghost" href="login.php">Login</a>
    <?php endif; ?>
  </nav>
</header>
<?php foreach ((flash() ?: []) as $f): ?><div class="flash"><?= e($f) ?></div><?php endforeach; ?>
<?php preview_bar(); ?>
<main class="<?= $full ? 'main-full' : 'wrap' ?>">
<?php
}

/** What a visitor who is not signed in is missing.
 *
 *  Living relatives are hidden from the public — that is deliberate, and it is
 *  most of the tree: of 778 people, 581 are living, and 505 of the 754
 *  photographs show one of them. So somebody who arrives without signing in
 *  sees the deceased and very little else, and has no way of knowing that is a
 *  preview rather than the whole site. William spotted that himself watching
 *  the visitor numbers: "I don't want them to think that is all of the website."
 *
 *  The counts are read from the tree, not typed in here, so the sentence stays
 *  true as the tree grows. */
/** Whether this page gets the bar. Asked twice — once to mark the body, once to
 *  draw it — because the home hero is pulled up by a negative margin that would
 *  otherwise slide underneath the bar and cut it in half. */
function preview_bar_on() {
    if (function_exists('logged_in') && logged_in()) return false;
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    /* Not on the pages whose whole job is to get you in — there it would be
       telling somebody reading the sign-in form to go and find the sign-in form. */
    $skip = ['login.php', 'register.php', 'forgot.php', 'reset.php', 'request.php', 'setup.php'];
    return !in_array($here, $skip, true);
}

function preview_bar() {
    if (!preview_bar_on()) return;

    $living = $photos = 0;
    try {
        $r = one("SELECT COUNT(*) c FROM persons WHERE living=1");  $living = $r ? (int)$r['c'] : 0;
        $p = one("SELECT COUNT(DISTINCT ph.id) c FROM photos ph
                  JOIN photo_people t ON t.photo_id = ph.id
                  JOIN persons p ON p.pid = t.pid
                  WHERE ph.status='approved' AND p.living=1");
        $photos = $p ? (int)$p['c'] : 0;
    } catch (\Throwable $e) { /* the bar still makes sense without the numbers */ }

    $bits = [];
    if ($living) $bits[] = number_format($living) . ' living relatives';
    if ($photos) $bits[] = number_format($photos) . ' more photographs';
    $missing = $bits ? implode(' and ', $bits) : 'the living side of the family';
    ?>
  <div class="pvbar">
    <div class="pvbar-in">
      <span class="pvbar-key">&#128274;</span>
      <p><b>You&rsquo;re seeing the public part of the site.</b>
        <?= e($missing) ?> &mdash; plus family news, the calendar and everyone&rsquo;s own page
        &mdash; are kept for family only. Sign in and they all appear.</p>
      <span class="pvbar-acts">
        <a class="btn gold" href="login.php">Sign in</a>
        <!-- straight to the box, not just the page: somebody who has never signed
             in should not have to spot it for themselves a second time -->
        <a class="pvbar-alt" href="login.php#first-time">Invited but never signed in?</a>
      </span>
    </div>
  </div>
<?php
}

function page_foot() {
    ?>
</main>
<?php feedback_tab(); ?>
<footer class="foot">A private home for the Battles family history · Members only</footer>
</body>
</html>
<?php
}

/** A quiet tab in the corner of every page so a reviewer never has to hunt for
 *  the suggestion form. William can switch it off from his feedback inbox. */
function feedback_tab() {
    if (!function_exists('fb_tab_on') || !fb_tab_on()) return;
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($here === 'feedback.php' || $here === 'feedback_manage.php' || $here === 'login.php') return;
    ?>
  <a class="fbtab" href="feedback.php" title="Tell us what you think">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3 1-5a8 8 0 1 1 17-6z"/></svg>
    <span>Your thoughts</span>
  </a>
<?php
}

/** The rich family footer shown on the public pages (home, history, ...). */
function legacy_footer() {
    ?>
  <footer class="homefoot">
    <div class="hf-inner">
      <div class="hf-col">
        <h4>&#9993; Stay Connected</h4>
        <p>Subscribe for family updates and news.</p>
        <form class="hf-sub" onsubmit="return hfSub(this)">
          <input type="email" name="email" placeholder="Your email address" required>
          <button type="submit" class="btn gold">Subscribe</button>
          <span class="hf-thanks">Thank you — we'll keep you posted.</span>
        </form>
      </div>
      <div class="hf-col">
        <h4>&#128274; Private Family Website</h4>
        <p>This is a private website for family members. Login to access all features.</p>
        <a class="btn2 solid" href="login.php">Login</a>
      </div>
      <div class="hf-col">
        <h4>&#128172; Tell Us What You Think</h4>
        <p>This site belongs to all of us. Share an opinion, an idea, or something we should fix.</p>
        <a class="btn2 solid" href="feedback.php">Share your thoughts</a>
        <p class="script hf-motto">Rooted in Faith. United in Love. Building Our Legacy.</p>
      </div>
      <div class="hf-col hf-brand">
        <span class="hf-tree"><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-4 8 4 4 0 0 0 1 7h6a4 4 0 0 0 1-7 5 5 0 0 0-4-8z"/><line x1="12" y1="13" x2="12" y2="22"/></svg></span>
        <div>Building on<br><b>The Battles Legacy</b></div>
      </div>
    </div>
  </footer>
  <script>
    function hfSub(f){f.querySelector('.hf-thanks').style.display='block';f.querySelector('input').value='';f.querySelector('input').disabled=true;return false;}
  </script>
<?php
}
