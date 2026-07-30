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
    <a href="section.php?s=history">History</a>
    <a href="tree.php">Family Tree</a>
    <a href="section.php?s=faith">Faith</a>
    <a href="section.php?s=enterprise">Enterprise</a>
    <a href="section.php?s=health">Health</a>
    <a href="section.php?s=news">Family News</a>
    <a href="section.php?s=memorial">Memorial</a>
    <a href="section.php?s=aahistory">African American History</a>
    <?php if ($u): ?>
      <a href="upload.php">Add a Photo</a>
      <?php if (role_at_least('moderator')): ?>
        <a href="moderate.php">Review Queue<?php $c = one("SELECT COUNT(*) c FROM photos WHERE status='pending'"); if ($c && $c['c']) echo ' <b class="badge">' . (int)$c['c'] . '</b>'; ?></a>
      <?php endif; ?>
      <?php if (role_at_least('admin')): ?><a href="admin.php">Members</a><?php endif; ?>
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
