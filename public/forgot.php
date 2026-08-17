<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/pwreset.php';
require_once __DIR__ . '/../src/invites.php';
if (logged_in()) { header('Location: index.php'); exit; }

$done = false; $err = ''; $invited = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter the email address you use to sign in.';
    } else {
        $u = one("SELECT * FROM users WHERE email=? AND status='active'", [$email]);
        /* The answer is the same whether or not that address has an account —
           otherwise this page tells a stranger who is in the family. */
        if ($u && !pwreset_throttled($u['id'])) {
            list($tok, $url) = pwreset_create($u['id'], 'self');
            $ok = pwreset_mail($u, $url);
            if ($ok) { try { q("UPDATE password_resets SET emailed=1 WHERE token=?", [$tok]); } catch (\Throwable $e) {} }
        }
        /* No account, but an invitation nobody has taken up: this is an invited
           relative who came here after the sign-in page turned them away. A
           reset link would be no use — there is no password to reset — so send
           the thing that does work, and say which one they are getting. */
        if (!$u && invite_resend_self($email) === 'sent') $invited = true;
        $done = true;
    }
}

page_head('Forgotten password');
?>
<form class="card panel" method="post">
  <h1 style="text-align:center">Forgotten your password?</h1>
  <?php if ($done && $invited): ?>
    <div class="note-ok" style="margin-top:16px">
      That address has an invitation waiting rather than an account &mdash; so there is no password
      to reset yet. We have sent your sign-up link instead. Open it and you can choose your own
      password, and after that this page will work for you like it does for everybody else.</div>
    <p class="muted" style="text-align:center;margin-top:14px">
      Nothing after a few minutes? Look in your junk or spam folder.</p>
    <p style="text-align:center;margin-top:18px"><a class="btn" href="login.php">Back to sign in</a></p>
  <?php elseif ($done): ?>
    <p class="lede" style="margin-top:14px;text-align:center">
      If that address belongs to a family account, a link to choose a new password is on its way.
      It works once and expires in 24 hours.</p>
    <p class="muted" style="text-align:center;margin-top:14px">
      Nothing after a few minutes? Check your junk folder &mdash; and if it still isn&rsquo;t there,
      ask William. He can send you a reset link straight away.</p>
    <p style="text-align:center;margin-top:18px"><a class="btn" href="login.php">Back to sign in</a></p>
  <?php else: ?>
    <p class="muted" style="text-align:center;margin-top:6px">
      Enter the email address you sign in with and we&rsquo;ll send you a link to set a new one.</p>
    <?php if ($err): ?><div class="err" style="margin-top:16px"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email</label>
    <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
    <button class="btn gold" style="width:100%">Send me a reset link</button>
    <p class="muted" style="text-align:center;margin-top:16px">
      Don&rsquo;t have the email you signed up with any more? Ask William to send you a reset link.<br>
      <a href="login.php">Back to sign in</a></p>
  <?php endif; ?>
</form>
<?php page_foot();
