<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/access_data.php';
if (logged_in()) { header('Location: index.php'); exit; }

$sent = ($_GET['sent'] ?? '') === '1';
$err  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { header('Location: request.php?sent=1'); exit; }   // honeypot
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $rel   = trim($_POST['relation'] ?? '');
    if (mb_strlen($name) < 3)                            $err = 'Please give your full name — it is how William will recognise you.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))  $err = 'Please enter an email address so you can be sent a way in.';
    elseif ($rel === '')                                 $err = 'Please say who you are related to. It is the quickest way to be recognised.';
    else {
        $already = ar_already($email);
        if ($already === 'member') {
            $err = 'There is already an account with that email. Try signing in, or use "Forgotten your password?".';
        } elseif ($already === 'pending') {
            header('Location: request.php?sent=1'); exit;   // same answer as a fresh one
        } else {
            ar_add([
                'name' => $name, 'email' => $email,
                'phone' => $_POST['phone'] ?? '', 'relation' => $rel,
                'note' => $_POST['note'] ?? '', 'referred_by' => $_POST['referred_by'] ?? '',
            ]);
            header('Location: request.php?sent=1'); exit;
        }
    }
}

page_head('Ask to join the family site');
?>
<form class="card panel" method="post">
  <h1 style="text-align:center">Ask to join</h1>
  <?php if ($sent): ?>
    <p class="lede" style="margin-top:14px;text-align:center">
      Thank you &mdash; your request has gone to William.</p>
    <p class="muted" style="text-align:center;margin-top:12px">
      He looks every name up in the family records before letting anyone in, so it may take a day or two.
      When he recognises you, you&rsquo;ll be sent a link to set up your own account.</p>
    <p style="text-align:center;margin-top:18px"><a class="btn" href="index.php">Back to the site</a></p>
  <?php else: ?>
    <p class="muted" style="text-align:center;margin-top:6px">
      This is a private site for the Battles family. Tell us who you are and
      William will check the family records before opening it to you.</p>
    <?php if ($err): ?><div class="err" style="margin-top:16px"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
      <label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <label>Your full name</label>
    <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Dianne Battles Holmes">

    <label>Email</label>
    <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">

    <label>Mobile (optional)</label>
    <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">

    <label>Who are you related to?</label>
    <input type="text" name="relation" required value="<?= e($_POST['relation'] ?? '') ?>"
           placeholder="e.g. My grandmother was Settie Battles">

    <label>Who told you about the site? (optional)</label>
    <input type="text" name="referred_by" value="<?= e($_POST['referred_by'] ?? '') ?>" placeholder="e.g. my cousin Brian">

    <label>Anything else you&rsquo;d like William to know (optional)</label>
    <textarea name="note" rows="3" maxlength="1000"><?= e($_POST['note'] ?? '') ?></textarea>

    <button class="btn gold" style="width:100%;margin-top:14px">Send my request</button>
    <p class="muted" style="text-align:center;margin-top:16px">
      Already have an account? <a href="login.php">Sign in</a>.
    </p>
  <?php endif; ?>
</form>
<?php page_foot();
