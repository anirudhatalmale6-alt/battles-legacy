<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/pwreset.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$row   = pwreset_find($token);
$user  = $row ? one("SELECT * FROM users WHERE id=?", [(int)$row['user_id']]) : null;
if ($user && $user['status'] !== 'active') { $row = null; $user = null; }

$err = '';
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    if (strlen($p1) < 8)   $err = 'Please choose a password of at least 8 characters.';
    elseif ($p1 !== $p2)   $err = 'Those two passwords are not the same — please type it again.';
    else {
        pwreset_complete($row, $p1);
        /* sign them straight in; being bounced back to a login form after
           setting a password is where people get stuck a second time */
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        flash('Your password has been changed. Welcome back, ' . explode(' ', $user['name'])[0] . '.');
        header('Location: index.php'); exit;
    }
}

page_head('Choose a new password');
if (!$row): ?>
  <div class="panel" style="max-width:480px;margin:50px auto;text-align:center">
    <h1>That link has expired</h1>
    <p class="lede" style="margin:12px auto">Reset links work once and last 24 hours.
      Ask for a new one and it will be sent straight away.</p>
    <a class="btn gold" href="forgot.php">Send me a new link</a>
    <p class="muted" style="margin-top:14px"><a href="login.php">Back to sign in</a></p>
  </div>
<?php else: ?>
  <form class="card panel" method="post">
    <h1 style="text-align:center">Choose a new password</h1>
    <p class="muted" style="text-align:center;margin-top:6px">For <b><?= e($user['name'] ?: $user['email']) ?></b>.</p>
    <?php if ($err): ?><div class="err" style="margin-top:16px"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password (8 characters or more)</label>
    <input type="password" name="password" required autofocus autocomplete="new-password">
    <label>Type it once more</label>
    <input type="password" name="password2" required autocomplete="new-password">
    <button class="btn gold" style="width:100%">Save my new password</button>
    <p class="muted" style="text-align:center;margin-top:16px">This link stops working once you use it.</p>
  </form>
<?php endif; ?>
<script src="assets/pwshow.js?v=<?= @filemtime(__DIR__ . '/assets/pwshow.js') ?: 1 ?>"></script>
<?php page_foot();
