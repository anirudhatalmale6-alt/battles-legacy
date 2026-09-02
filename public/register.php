<?php
require __DIR__ . '/../src/bootstrap.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$inv = $token ? one("SELECT * FROM invites WHERE token=? AND used_at IS NULL", [$token]) : null;
$stale = false;
if ($inv && $inv['expires_at'] && strtotime($inv['expires_at']) < time()) {
    /* The 30 days ran out, but this is still the right person holding the link
       that was posted to their own mailbox — and the alternative was a page
       telling them to go and ask William. Reopen the window and let them in.
       Bounded, so a link found in a very old mailbox does not live forever. */
    require_once __DIR__ . '/../src/invites.php';
    if (time() - strtotime($inv['expires_at']) < 180 * 86400) {
        $fresh = date('Y-m-d H:i:s', time() + INVITE_DAYS * 86400);
        try { q("UPDATE invites SET expires_at=? WHERE id=?", [$fresh, (int)$inv['id']]);
              $inv['expires_at'] = $fresh; $stale = true; } catch (\Throwable $e) { $inv = null; }
    } else {
        $inv = null;
    }
}

/* Getting this far with a working link is the one thing "we emailed it" cannot
   tell you: a real person is looking at the sign-up form. Recorded once, so the
   Members page can separate "hasn't opened it" from "opened it and stopped". */
if ($inv) {
    require_once __DIR__ . '/../src/invites.php';
    invite_mark_opened($inv['id']);
}

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
        pk_migrate();
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
    <p class="lede" style="margin:12px auto">This link is either not a valid invitation, or the
      account has already been set up.</p>
    <p class="muted" style="margin-top:12px">If you have already chosen a password, sign in.
      If you have not, the sign-in page has a <b>First time here?</b> box &mdash; put your email
      address in it and a fresh link comes straight back to you.</p>
    <a class="btn" href="login.php" style="margin-top:16px">Go to sign in</a>
  </div>
<?php else: ?>
  <form class="card panel" method="post">
    <h1 style="text-align:center">Set up your account</h1>
    <p class="muted" style="text-align:center;margin-top:6px">You've been invited as a <b><?= e(ucfirst($inv['role'])) ?></b>.</p>
    <?php if ($stale): ?><div class="note-ok" style="margin-top:16px">Your invitation had run past its
      30 days, so we have reopened it for you. Carry on below &mdash; nothing else to do.</div><?php endif; ?>
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
<?php endif; ?>
<script src="assets/pwshow.js?v=<?= @filemtime(__DIR__ . '/assets/pwshow.js') ?: 1 ?>"></script>
<?php page_foot();
