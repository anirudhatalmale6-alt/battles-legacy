<?php
/**
 * One-time browser installer. Upload the app, create a MySQL database in cPanel,
 * then open this page, fill the form, and it builds everything.
 * DELETE this file after a successful install.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
$done = false; $errors = []; $results = [];
$configExists = is_file(__DIR__ . '/../config.php');

// sensible default guesses for the photo library / gedcom location
$homeGuess = dirname(__DIR__, 2);
$defaults = [
    'db_host' => 'localhost',
    'ged_path' => dirname(__DIR__) . '/battlesfamily.ged',
    'photo_dir' => '',
    'base_url' => (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = fn($k) => trim($_POST[$k] ?? '');
    // 1) test DB connection first (don't poison config cache)
    try {
        $test = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $f('db_host'), $f('db_name')), $f('db_user'), $f('db_pass'));
        $test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
        $errors[] = 'Could not connect to the database — check the name, user and password. (' . $e->getMessage() . ')';
        $test = null;
    }
    if ($f('admin_name') === '' || !filter_var($f('admin_email'), FILTER_VALIDATE_EMAIL) || strlen($f('admin_pass')) < 8)
        $errors[] = 'Enter the admin name, a valid email, and a password of at least 8 characters.';

    if (!$errors && $test) {
        require __DIR__ . '/../src/install.php';   // lazy; config() not called yet
        [$okc, $msgc] = write_config([
            'db_driver' => 'mysql', 'db_host' => $f('db_host'), 'db_name' => $f('db_name'),
            'db_user' => $f('db_user'), 'db_pass' => $f('db_pass'),
            'base_url' => $f('base_url'),
        ]);
        if (!$okc) { $errors[] = $msgc; }
        else {
            $results[] = 'Configuration saved.';
            $m = install_migrate(); $results[] = 'Database tables created (' . implode(', ', $m['tables']) . ').';

            if ($f('ged_path') && is_file($f('ged_path'))) {
                $g = install_gedcom($f('ged_path'));
                $results[] = $g['ok'] ? "Family tree imported: {$g['individuals']} people, {$g['families']} families ({$g['living']} living kept private)." : ('GEDCOM: ' . $g['error']);
            } else {
                $results[] = 'GEDCOM not found at that path — skipped (you can import it later from the tools).';
            }

            if ($f('photo_dir') && is_dir($f('photo_dir'))) {
                $p = install_photos($f('photo_dir'));
                if ($p['ok']) { $s = $p['stats'];
                    $results[] = "Photos auto-pinned: matched {$s['matched']}, copied {$s['copied']}, unmatched {$s['unmatched']}.";
                    if ($p['unmatched']) $results[] = 'Unmatched (need a name): ' . implode(', ', array_slice($p['unmatched'], 0, 40)) . (count($p['unmatched']) > 40 ? ' …' : '');
                } else $results[] = 'Photos: ' . $p['error'];
            } else {
                $results[] = 'Photo folder not set/found — skipped (import later once you know the path).';
            }

            $a = install_admin($f('admin_name'), $f('admin_email'), $f('admin_pass'));
            $results[] = $a['ok'] ? "Admin account ready: {$a['email']}" : ('Admin: ' . $a['error']);
            $done = true;
        }
    }
}
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install — The Battles Legacy</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=EB+Garamond&family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head><body>
<header class="nav"><a class="brand" href="#"><span class="script">The Battles</span> Legacy — Installer</a></header>
<main class="wrap">
<?php if ($done): ?>
  <div class="panel">
    <h1>Installed — welcome home.</h1>
    <ul class="lede" style="margin-top:12px">
      <?php foreach ($results as $r): ?><li style="margin-bottom:6px">✓ <?= htmlspecialchars($r) ?></li><?php endforeach; ?>
    </ul>
    <div class="err" style="margin-top:18px">Important: delete this <b>setup.php</b> file now (via cPanel File Manager) so no one can re-run it.</div>
    <a class="btn gold" href="login.php" style="margin-top:16px">Go to the login</a>
  </div>
<?php else: ?>
  <h1>Set up your family hub</h1>
  <p class="lede">First create a MySQL database + user in cPanel (Databases → MySQL Databases, add the user to the database with All Privileges), then fill this in once.</p>
  <?php foreach ($errors as $e): ?><div class="err" style="margin-top:12px"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <?php if ($configExists): ?><div class="flash" style="margin:12px 0">Note: a config already exists — submitting will reconfigure and re-import.</div><?php endif; ?>
  <form class="panel" method="post" style="max-width:620px;margin-top:16px">
    <h2>Database</h2>
    <label>Database host</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? $defaults['db_host']) ?>">
    <label>Database name</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="e.g. thebattl_legacy" required>
    <label>Database user</label><input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
    <label>Database password</label><input type="text" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" required>

    <h2 style="margin-top:20px">Your admin account</h2>
    <label>Your name</label><input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
    <label>Your email (used only to log in)</label><input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
    <label>Choose a password (8+ characters)</label><input type="text" name="admin_pass" value="<?= htmlspecialchars($_POST['admin_pass'] ?? '') ?>" required>

    <h2 style="margin-top:20px">Data (optional — can import later)</h2>
    <label>Path to your GEDCOM file on the server</label>
    <input type="text" name="ged_path" value="<?= htmlspecialchars($_POST['ged_path'] ?? $defaults['ged_path']) ?>">
    <label>Full path to your photo library folder on the server</label>
    <input type="text" name="photo_dir" value="<?= htmlspecialchars($_POST['photo_dir'] ?? $defaults['photo_dir']) ?>" placeholder="e.g. /home/thebattl/public_html/photos">
    <label>Site address</label><input type="text" name="base_url" value="<?= htmlspecialchars($_POST['base_url'] ?? $defaults['base_url']) ?>">

    <button class="btn gold">Install now</button>
  </form>
<?php endif; ?>
</main>
</body></html>
