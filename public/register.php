<?php
require __DIR__ . '/../src/bootstrap.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$inv = $token ? one("SELECT * FROM invites WHERE token=? AND used_at IS NULL", [$token]) : null;
if ($inv && $inv['expires_at'] && strtotime($inv['expires_at']) < time()) $inv = null;

$err = '';
if ($inv && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? $inv['email']));
    $pass  = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    if (strlen($name) < 2)              $err = 'Please enter your name.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email.';
    elseif (strlen($pass) < 8)          $err = 'Password must be at least 8 characters.';
    elseif (one("SELECT id FROM users WHERE email=?", [$email])) $err = 'An account with that email already exists — try logging in.';
    else {
        /* If the invitation knew which person in the tree this is, the account
           inherits it. That is what lets the site later say "this is your own
           page" rather than making somebody search for themselves. */
        require_once __DIR__ . '/../src/people_pick.php';
        pp_migrate();
        q("INSERT INTO users (name,email,phone,pass_hash,role,status,pid) VALUES (?,?,?,?,?, 'active',?)",
          [$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), $inv['role'], (string)($inv['pid'] ?? '')]);
        $uid = insert_id();
        q("UPDATE invites SET used_at=CURRENT_TIMESTAMP WHERE id=?", [$inv['id']]);
        $_SESSION['uid'] = $uid;
        flash('Welcome to the family hub, ' . $name . '!');
        header('Location: index.php'); exit;
    }
}

page_head('Join the family hub');
if (!$inv): ?>
  <div class="panel" style="max-width:480px;margin:50px auto;text-align:center">
    <h1>Invitation not found</h1>
    <p class="lede" style="margin:12px auto">This invitation link is invalid, has expired, or has already been used.
      Please ask a family admin to send you a fresh invite.</p>
    <a class="btn" href="login.php">Go to login</a>
  </div>
<?php else: ?>
  <form class="card panel" method="post">
    <h1 style="text-align:center">Set up your account</h1>
    <p class="muted" style="text-align:center;margin-top:6px">You've been invited as a <b><?= e(ucfirst($inv['role'])) ?></b>.</p>
    <?php if ($err): ?><div class="err" style="margin-top:16px"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>Your name</label>
    <input type="text" name="name" required value="<?= e($_POST['name'] ?? $inv['name']) ?>">
    <label>Email</label>
    <input type="email" name="email" required value="<?= e($_POST['email'] ?? $inv['email']) ?>">
    <label>Mobile (optional — for text notifications later)</label>
    <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
    <label>Choose a password (8+ characters)</label>
    <input type="password" name="password" required>
    <button class="btn gold" style="width:100%">Create my account</button>
  </form>
<?php endif;
page_foot();
