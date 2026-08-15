<?php
require __DIR__ . '/../src/bootstrap.php';
if (logged_in()) { header('Location: index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        header('Location: index.php'); exit;
    }
    $err = 'That email and password didn\'t match. Please try again.';
}

page_head('Family Login');
?>
<form class="card panel" method="post">
  <h1 style="text-align:center">Welcome home</h1>
  <p class="muted" style="text-align:center;margin-top:6px">Sign in to the private Battles family hub.</p>
  <?php if ($err): ?><div class="err" style="margin-top:16px"><?= e($err) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Email</label>
  <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
  <label>Password</label>
  <input type="password" name="password" required>
  <button class="btn gold" style="width:100%">Sign in</button>
  <p class="muted" style="text-align:center;margin-top:16px">
    <a href="forgot.php">Forgotten your password?</a><br>
    Have an invitation link? Open it to set up your account.<br>
    No account yet? Ask a family admin to invite you.
  </p>
</form>
<?php page_foot();
