<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/invites.php';
if (logged_in()) { header('Location: index.php'); exit; }

$err = ''; $ok = ''; $firstOpen = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'firstlink') {
        /* "First time here" — somebody who has an invitation but has never used
           it, asking for it again themselves. */
        $email = strtolower(trim($_POST['email2'] ?? ''));
        $firstOpen = true;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter the email address the invitation was sent to.';
        } else {
            $r = invite_resend_self($email);
            if ($r === 'sent') {
                $ok = 'Your sign-up link is on its way to ' . $email . '. Open it and you can '
                    . 'choose your own password. If it is not there in a few minutes, look in '
                    . 'your junk or spam folder.';
                $firstOpen = false;
            } elseif ($r === 'joined') {
                $err = 'You already have an account with that address — sign in above, or use '
                     . '"Forgotten your password?" if you cannot remember it.';
            } else {
                $err = 'There is no invitation waiting for that address. It may have been sent to '
                     . 'a different email — or ask William to invite you and it will arrive within '
                     . 'the day.';
            }
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        if (attempt_login($email, $_POST['password'] ?? '')) {
            header('Location: index.php'); exit;
        }
        /* Before saying "wrong password", check whether they even have one yet.
           An invited relative who has not opened their link has no password to
           get right, and telling them to try again just sends them round the
           same loop — which is exactly what happened to fourteen people. */
        $pending = invite_pending_for($email);
        if ($pending) {
            invite_resend_self($email);
            $ok = 'You have an invitation waiting, but your account is not set up yet — so there '
                . 'is no password of yours to type here. We have just sent your sign-up link to '
                . $email . '. Open it and you can choose your own password. Check your junk or '
                . 'spam folder if it does not appear in a few minutes.';
        } else {
            $err = 'That email and password didn\'t match. Please try again.';
        }
    }
}

page_head('Family Login');
?>
<form class="card panel signin" method="post">
  <h1 style="text-align:center">Welcome home</h1>
  <p class="muted" style="text-align:center;margin-top:6px">Sign in to the private Battles family hub.</p>
  <?php if ($ok): ?><div class="note-ok" style="margin-top:16px"><?= e($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err" style="margin-top:16px"><?= e($err) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Email</label>
  <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
  <label>Password</label>
  <input type="password" name="password" required>
  <button class="btn gold" style="width:100%">Sign in</button>
  <p class="muted" style="text-align:center;margin-top:16px">
    <a href="forgot.php">Forgotten your password?</a><br>
    <!-- on a phone the card below sits just off the bottom of the screen, so the
         pointer to it has to live inside the box that turned them away -->
    <a href="#first-time"><b>Never signed in before?</b> Get your sign-up link</a>
  </p>
</form>

<div class="card panel first-time" id="first-time">
  <h2>First time here?</h2>
  <p class="muted">If William has invited you but you have never signed in, you do not have a
    password yet &mdash; you set one from the link in your invitation. Lost it, or it has expired?
    Put your email address in below and a new link comes straight back to you.</p>
  <form method="post" style="margin-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="firstlink">
    <label>The email address your invitation was sent to</label>
    <input type="email" name="email2" required<?= $firstOpen ? ' autofocus' : '' ?>
           value="<?= e($_POST['email2'] ?? '') ?>">
    <button class="btn2 solid" style="width:100%;margin-top:12px">Send me my sign-up link</button>
  </form>
  <p class="muted" style="margin-top:14px">Family, but nobody has invited you yet?
    <a href="request.php">Ask to join</a> &mdash; William will see it on his Members page.</p>
</div>
<?php page_foot();
