<?php
/** Support & Fund — the fourth of the dead cards.
 *
 *  "Help family businesses thrive through support, partnerships, and
 *  investments." That is four different offers wearing one button, so the form
 *  asks which one rather than leaving William to work it out from a paragraph.
 *
 *  Nothing here takes money and nothing here promises anybody money. It records
 *  an offer and tells William about it; every arrangement that follows is
 *  between two people who are related to each other, which is the only sane
 *  place for it to sit. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';
require_once __DIR__ . '/../src/mentor_data.php';

$me   = logged_in() ? current_user() : null;
$err  = '';
$sent = ($_GET['sent'] ?? '') === '1';

/* The ways somebody can help. The key is stored, the label is what William
   reads in his email, so renaming one later does not rewrite history. */
$WAYS = [
  'mentor'    => 'Mentor somebody — give my time',
  'hire'      => 'Hire or buy from family businesses',
  'refer'     => 'Send them customers and referrals',
  'partner'   => 'Partner on something together',
  'invest'    => 'Invest in a family business',
  'resource'  => 'Share a resource, template or contact',
  'skill'     => 'Give a skill — design, accounts, legal, marketing',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { header('Location: get_involved.php?sent=1'); exit; }   // honeypot

    $name  = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $picked = [];
    foreach ((array)($_POST['ways'] ?? []) as $w) {
        if (isset($WAYS[$w])) $picked[] = $WAYS[$w];
    }
    $msg = trim((string)($_POST['message'] ?? ''));

    if (mb_strlen($name) < 2) {
        $err = 'Please put your name in, so William knows who is offering.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please give an email address he can answer on.';
    } elseif (!$picked) {
        $err = 'Please tick at least one way you would like to help.';
    } else {
        ask_add([
            'kind' => 'involved', 'name' => $name, 'email' => $email,
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'topic' => 'Offering to help',
            'offers' => implode('; ', $picked),
            'message' => $msg,
            'uid' => $me ? $me['id'] : null,
        ]);
        header('Location: get_involved.php?sent=1'); exit;
    }
}

/* resources.php links here with ?offer=resource so the right box is already
   ticked for somebody who arrived meaning to do exactly that. */
$pre = (string)($_GET['offer'] ?? '');
$preTicked = isset($WAYS[$pre]) ? [$pre] : [];
$ticked = $_SERVER['REQUEST_METHOD'] === 'POST' ? (array)($_POST['ways'] ?? []) : $preTicked;

page_head('Support a family business', ['body_class' => 'home entpage']);
?>
<section class="dir-head">
  <p class="dir-eyebrow"><a href="enterprise.php">&larr; Enterprise</a></p>
  <h1>Support &amp; Fund</h1>
  <p class="dir-sub">A family business does not usually fail for want of a big investor. It fails for
     want of the first ten customers, an introduction, or somebody who has done the accounts before.
     All of that counts, and all of it is on the list below.</p>
</section>

<?php if ($sent): ?>
  <div class="panel note-ok mn-done" style="max-width:760px;margin:0 auto">
    <h2>Thank you &mdash; that has gone to William.</h2>
    <p>He will come back to you himself. Nothing has been published on the site, and your details
       have not been shown to anyone.</p>
    <p style="margin-top:14px">
      <a class="btn gold" href="enterprise.php">Back to Enterprise</a>
      <a class="btn" href="businesses.php" style="margin-left:8px">See the family businesses</a></p>
  </div>
<?php else: ?>

<form class="panel gi-form" method="post">
  <?= csrf_field() ?>
  <div style="position:absolute;left:-9999px" aria-hidden="true">
    <label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

  <?php if ($err): ?><div class="err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

  <h2>How would you like to help?</h2>
  <p class="muted">Tick everything that applies. None of it is a commitment &mdash; it just tells
     William who to come to when the need comes up.</p>

  <div class="gi-ways">
    <?php foreach ($WAYS as $k => $lbl): ?>
      <label class="gi-way">
        <input type="checkbox" name="ways[]" value="<?= e($k) ?>"<?= in_array($k, $ticked, true) ? ' checked' : '' ?>>
        <span><?= e($lbl) ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <label>Your name</label>
  <input type="text" name="name" required maxlength="160"
         value="<?= e($_POST['name'] ?? ($me ? $me['name'] : '')) ?>">

  <label>Your email</label>
  <input type="email" name="email" required maxlength="190"
         value="<?= e($_POST['email'] ?? ($me ? $me['email'] : '')) ?>">

  <label>Your phone (optional)</label>
  <input type="tel" name="phone" maxlength="60" value="<?= e($_POST['phone'] ?? '') ?>">

  <label>Anything you want to say (optional)</label>
  <textarea name="message" rows="4" maxlength="4000"
    placeholder="What you do, what you would be good for, or a business you already have in mind."><?= e($_POST['message'] ?? '') ?></textarea>

  <button class="btn gold" style="width:100%;margin-top:16px">Send this to William</button>
  <p class="muted" style="text-align:center;margin-top:14px">
    This is not a payment page and nothing is taken from you here. It goes to William as a message
    and he answers it himself.</p>
</form>

<?php endif; ?>
<?php legacy_footer(); page_foot();
