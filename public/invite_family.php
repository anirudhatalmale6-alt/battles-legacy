<?php
/** "How do we invite others?" — asked by a member, and until now the answer was
 *  "you can't, tell William". Only an Admin could invite anybody, so every new
 *  name had to travel through him by phone or text before it could be typed in.
 *
 *  This does not hand out invitations. A member puts a name forward and it
 *  lands in the same "waiting to be let in" queue on William's Members page
 *  that the public "Ask to join" form feeds, where one tap approves and sends.
 *  He keeps the door; the family just get to knock on it for someone.
 *
 *  Deliberately reuses ar_add()/ar_approve() rather than invite_create(): an
 *  invitation born here is then identical to one he typed himself — same
 *  expiry, same send buttons, same audit trail. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/access_data.php';
require_once __DIR__ . '/../src/invites.php';
require_login();

$me   = current_user();
$sent = ($_GET['sent'] ?? '') === '1';
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { header('Location: invite_family.php?sent=1'); exit; }   // honeypot

    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $rel   = trim($_POST['relation'] ?? '');

    if (mb_strlen($name) < 3) {
        $err = 'Please give their full name — it is how William will recognise them.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter an email address for them, so there is somewhere to send the invitation.';
    } elseif ($rel === '') {
        $err = 'Please say how they are related. It is the quickest way for William to place them.';
    } else {
        $already = ar_already($email);
        if ($already === 'member') {
            $err = 'Good news — ' . $name . ' already has an account on the site. If they cannot get in, '
                 . 'they should use "Forgotten your password?" on the sign-in page.';
        } elseif ($already === 'pending') {
            /* Somebody has already put this name forward. Saying so is friendlier
               than silently swallowing it, and it stops William reading the same
               name three times because three cousins all thought of them. */
            $err = 'Someone has already put ' . $name . ' forward and William has not decided yet. '
                 . 'No need to send it twice.';
        } else {
            /* Two different "they already have one" cases, and they need
               opposite answers — which is why this is not one check.

               Same address: an invitation is sitting unopened at exactly the
               address just typed, so there is nothing new here to send; it
               wants re-sending, or the address is dead.

               Different address: this is the valuable one. Sixty invitations
               have gone out and a good number went to addresses the family had
               years ago. A cousin typing the address that person uses TODAY is
               the correction nobody else could supply. Neither the old address
               nor the new one is shown back to anyone — they belong to a third
               person — but the note tells William what he is looking at. */
            $note  = trim($_POST['note'] ?? '');
            $same  = invite_pending_for($email);
            $other = $same ? null : invite_pending_by_name($name, $email);
            if ($same) {
                $note = trim('An invitation is already waiting at this same address and has never been '
                           . 'opened. It needs sending again, or the address itself is wrong. ' . $note);
            } elseif ($other) {
                $note = trim('An invitation was already sent to this person at a different address and '
                           . 'was never opened. ' . $me['name'] . ' says the address above is the one '
                           . 'they use now. ' . $note);
            }
            ar_add([
                'name'  => $name,
                'email' => $email,
                'phone' => $_POST['phone'] ?? '',
                'relation'     => $rel,
                'note'         => $note,
                'referred_by'  => $me['name'],
                'source'       => 'member',
                'referred_uid' => $me['id'],
            ]);
            header('Location: invite_family.php?sent=1' . ($same ? '&re=same' : ($other ? '&re=other' : '')));
            exit;
        }
    }
}
$re = $_GET['re'] ?? '';

page_head('Invite a family member');
?>
<form class="card panel" method="post">
  <h1 style="text-align:center">Invite family</h1>

  <?php if ($sent): ?>
    <p class="lede" style="margin-top:14px;text-align:center">
      Thank you &mdash; that name has gone to William.</p>
    <?php if ($re === 'other'): ?>
      <p class="note-ok" style="margin-top:14px">Worth knowing: they were invited once before, at a
        different email address, and never opened it. That is very likely why you have not seen them
        here. William will see that yours is the address they use now.</p>
    <?php elseif ($re === 'same'): ?>
      <p class="note-ok" style="margin-top:14px">Worth knowing: an invitation was already sent to that
        exact address and has never been opened. William will send it again &mdash; and if it still does
        not reach them, it is worth asking whether that address is one they still use.</p>
    <?php endif; ?>
    <p class="muted" style="text-align:center;margin-top:12px">
      He checks every name against the family records before opening the site to anyone, so it may take
      a day or two. When he approves it, they are sent a link to set up their own account &mdash; nobody
      else ever chooses their password, and nothing is sent from your email.</p>
    <p style="text-align:center;margin-top:18px">
      <a class="btn gold" href="invite_family.php">Put another name forward</a>
      <a class="btn" href="index.php" style="margin-left:8px">Back to the site</a></p>

  <?php else: ?>
    <p class="muted" style="text-align:center;margin-top:6px">
      Know somebody in the family who should be here? Put their name forward and William will
      send them a way in. This is a private site, so he checks each name himself first.</p>

    <?php if ($err): ?><div class="err" style="margin-top:16px"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
      <label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <label>Their full name</label>
    <input type="text" name="name" required autofocus value="<?= e($_POST['name'] ?? '') ?>"
           placeholder="e.g. Dianne Battles Holmes">

    <label>Their email address</label>
    <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
    <p class="muted" style="margin:-4px 0 0;font-size:13px">The one they actually open. A lot of the
      addresses we already had turned out to be old ones, which is why some of the family never heard
      from us.</p>

    <label>Their mobile (optional)</label>
    <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">

    <label>How are they related?</label>
    <input type="text" name="relation" required value="<?= e($_POST['relation'] ?? '') ?>"
           placeholder="e.g. My cousin — her grandmother was Settie Battles">

    <label>Anything else William should know (optional)</label>
    <textarea name="note" rows="3" maxlength="1000"><?= e($_POST['note'] ?? '') ?></textarea>

    <button class="btn gold" style="width:100%;margin-top:14px">Send this to William</button>
    <p class="muted" style="text-align:center;margin-top:16px">
      You are signed in as <?= e($me['name']) ?>, and William will see the name came from you.</p>
  <?php endif; ?>
</form>
<?php page_foot();
