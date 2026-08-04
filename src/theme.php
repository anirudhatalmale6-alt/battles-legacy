<?php
/** Shared page chrome — burgundy + gold + sepia, matching the demo. */

function page_head($title, $opts = []) {
    $full = $opts['full'] ?? false; // full = no container padding (used by the tree)
    $bodyClass = $opts['body_class'] ?? ''; // extra body class (e.g. 'home')
    $u = current_user();
    $site = config('site_name');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e($site) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,500&family=EB+Garamond&family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="<?= trim(($full ? 'full ' : '') . $bodyClass) ?>">
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
    <a href="section.php?s=health">Health</a>
    <a href="section.php?s=news">Family News</a>
    <a href="memorial.php">Memorial</a>
    <a href="section.php?s=aahistory">African American History</a>
    <?php if ($u): ?>
      <a href="upload.php">Add a Photo</a>
      <?php if (role_at_least('moderator')): ?>
        <a href="moderate.php">Review Queue<?php $c = one("SELECT COUNT(*) c FROM photos WHERE status='pending'"); if ($c && $c['c']) echo ' <b class="badge">' . (int)$c['c'] . '</b>'; ?></a>
      <?php endif; ?>
      <?php if (role_at_least('admin')): ?><a href="admin.php">Members</a><a href="enterprise_manage.php">Edit Enterprise</a><a href="faith_manage.php">Prayers<?php if (function_exists('faith_prayer_count')) { $fc = @faith_prayer_count(); if ($fc) echo ' <b class="badge">' . (int)$fc . '</b>'; } ?></a><?php endif; ?>
      <span class="who"><?= e($u['name']) ?> · <?= e(ucfirst($u['role'])) ?></span>
      <a class="btn-ghost" href="logout.php">Sign out</a>
    <?php else: ?>
      <a class="btn-ghost" href="login.php">Login</a>
    <?php endif; ?>
  </nav>
</header>
<?php foreach ((flash() ?: []) as $f): ?><div class="flash"><?= e($f) ?></div><?php endforeach; ?>
<main class="<?= $full ? 'main-full' : 'wrap' ?>">
<?php
}

function page_foot() {
    ?>
</main>
<footer class="foot">A private home for the Battles family history · Members only</footer>
</body>
</html>
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
        <h4>Follow us on Faith, Family &amp; Love</h4>
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
