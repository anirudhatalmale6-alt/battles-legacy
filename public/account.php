<?php
/** Your account — where a member changes their own password.
 *
 *  There was nowhere to do this. The only route was the Members page's
 *  "Reset link" button, which makes a link you then have to find and open, and
 *  which only an admin can see at all — so the other twelve people on the site
 *  had no way to change a password short of asking William, and William had no
 *  obvious way either. He was still using the one he was given.
 *
 *  The current password is required. Without it, anyone who found a signed-in
 *  phone left on a table could lock the owner out of their own family history. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/pwreset.php';
require_login();

$me  = current_user();
$err = '';
$ok  = '';

/* A password box that can be guessed at for ever is a password box worth
   guessing at. Five wrong tries and it rests for ten minutes. */
function acct_too_many() {
    $t = $_SESSION['pw_tries'] ?? [];
    $t = array_values(array_filter($t, function ($x) { return $x > time() - 600; }));
    $_SESSION['pw_tries'] = $t;
    return count($t) >= 5;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    csrf_check();
    $cur = (string)($_POST['current'] ?? '');
    $p1  = (string)($_POST['password'] ?? '');
    $p2  = (string)($_POST['password2'] ?? '');
    $row = one("SELECT * FROM users WHERE id=?", [(int)$me['id']]);

    if (acct_too_many()) {
        $err = 'That is several wrong tries in a row. Please wait ten minutes and try again.';
    } elseif (!$row || !$row['pass_hash'] || !password_verify($cur, $row['pass_hash'])) {
        $_SESSION['pw_tries'][] = time();
        $err = 'That is not your current password. If you cannot remember it, sign out and use '
             . '"Forgotten your password?" on the sign-in page.';
    } elseif (strlen($p1) < 8) {
        $err = 'Please choose a password of at least 8 characters.';
    } elseif ($p1 !== $p2) {
        $err = 'Those two passwords are not the same — please type the new one again.';
    } elseif ($p1 === $cur) {
        $err = 'That is the password you already have. Choose a different one.';
    } else {
        q("UPDATE users SET pass_hash=? WHERE id=?", [password_hash($p1, PASSWORD_DEFAULT), (int)$me['id']]);
        /* Any reset link sitting in an old email would still work otherwise,
           which rather defeats deliberately changing the password. */
        try { q("UPDATE password_resets SET used_at=CURRENT_TIMESTAMP WHERE user_id=? AND used_at IS NULL",
                [(int)$me['id']]); } catch (\Throwable $e) {}
        $_SESSION['pw_tries'] = [];
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$me['id'];
        flash('Your password has been changed. Nobody else has it, including me.');
        header('Location: account.php'); exit;
    }
}

page_head('Your account', ['body_class' => 'em']);
?>
<h1>Your account</h1>
<p class="lede">Your own details and your password. Only you can see this page.</p>

<div class="panel" style="max-width:620px">
  <h2 style="margin:0 0 10px">You</h2>
  <table class="list">
    <tr><td class="k" style="width:130px">Name</td><td><b><?= e($me['name']) ?></b></td></tr>
    <tr><td class="k">Email</td><td><?= e($me['email']) ?></td></tr>
    <tr><td class="k">On the site as</td><td><?= e(ucfirst($me['role'])) ?></td></tr>
  </table>
  <?php /* Say exactly which of these he can change and where. The Members page
           renames people, including himself; nothing in the site edits an
           account's own email address. */ ?>
  <p class="muted" style="margin:10px 0 0">
    <?php if (role_at_least('admin')): ?>
      Your name can be changed on the <a href="admin.php">Members page</a>. The email address you sign in with
      cannot be changed from any page yet &mdash; tell me and I will do it.
    <?php else: ?>
      To change your name or the email address you sign in with, ask William.
    <?php endif; ?>
  </p>
</div>

<div class="panel" style="max-width:620px;margin-top:18px">
  <h2 style="margin:0 0 10px">Change your password</h2>
  <?php if ($err): ?><div class="err" style="margin-bottom:14px"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="em-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <?php /* The browser needs the username field to offer to save the new one. */ ?>
    <input type="hidden" name="username" autocomplete="username" value="<?= e($me['email']) ?>">
    <label>Your password now</label>
    <input type="password" name="current" required autocomplete="current-password" autofocus>
    <label>New password (8 characters or more)</label>
    <input type="password" name="password" required autocomplete="new-password">
    <label>Type the new one once more</label>
    <input type="password" name="password2" required autocomplete="new-password">
    <button class="btn gold" style="margin-top:14px">Save my new password</button>
  </form>
  <p class="muted" style="margin:12px 0 0">Nobody else on the site can see your password, and neither can I &mdash;
    it is stored scrambled, so even looking straight at the database shows nothing usable. Changing it here also
    stops any old reset link from an earlier email working.</p>
</div>

<p style="margin-top:18px"><a class="btn" href="index.php">&larr; Back to the site</a></p>
<script src="assets/pwshow.js?v=<?= @filemtime(__DIR__ . '/assets/pwshow.js') ?: 1 ?>"></script>
<?php page_foot();
