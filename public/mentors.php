<?php
/** Mentor Connect.
 *
 *  William: "Could you activate the tabs at the bottom of the enterprise page?
 *  I wanted to mentor for starting your own business." So this is the one that
 *  matters — the other three cards were dead too, but this one he wants to use
 *  himself, today.
 *
 *  Behind sign-in, unlike the business directory. A mentor card carries a
 *  living relative's name, where they live and what they do; everywhere else on
 *  this site living relatives are hidden from anyone who is not signed in, and
 *  a page that quietly published thirty of them would undo that in one go.
 *
 *  No mentor's email or phone is printed unless that mentor has chosen to show
 *  it. The default is that a message goes to William and he passes it on, which
 *  also means nobody has to hand out their mobile number to be helpful. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';
require_once __DIR__ . '/../src/mentor_data.php';
require_login();

$me  = current_user();
$err = '';
$did = (string)($_GET['done'] ?? '');

/* Who am I already listed as? A member who is already a mentor should be shown
   "edit your listing", not invited to create a second one. */
$mine = null;
foreach (ment_list(true) as $m) {
    if ((int)$m['uid'] === (int)$me['id']) { $mine = $m; break; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { header('Location: mentors.php?done=ask'); exit; }   // honeypot
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'ask') {
        $mid = (int)($_POST['mentor_id'] ?? 0);
        $msg = trim((string)($_POST['message'] ?? ''));
        $top = trim((string)($_POST['topic'] ?? ''));
        if (mb_strlen($msg) < 10) {
            $err = 'Please write a line or two about what you would like help with — it is what they will answer.';
        } else {
            ask_add([
                'kind' => 'mentor', 'mentor_id' => $mid ?: null,
                'name' => $me['name'], 'email' => $me['email'],
                'phone' => trim((string)($_POST['phone'] ?? '')),
                'topic' => $top, 'message' => $msg, 'uid' => $me['id'],
            ]);
            header('Location: mentors.php?done=ask'); exit;
        }
    } elseif ($act === 'offer') {
        $topics = trim((string)($_POST['topics'] ?? ''));
        if (ment_topics($topics) === []) {
            $err = 'Please put in at least one thing you are happy to be asked about, one per line.';
        } else {
            $contact = array_key_exists((string)($_POST['contact'] ?? ''), ment_contact_opts())
                     ? (string)$_POST['contact'] : 'site';
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            /* Asked to show an address they have not given: quietly fall back to
               the safe option rather than publishing a card with a blank link. */
            if (($contact === 'email' || $contact === 'both') && !filter_var($email, FILTER_VALIDATE_EMAIL)) $contact = 'site';
            if (($contact === 'phone' || $contact === 'both') && $phone === '') $contact = 'site';

            $f = [mb_substr(trim((string)($_POST['role_line'] ?? '')), 0, 200),
                  mb_substr($topics, 0, 2000),
                  mb_substr(trim((string)($_POST['about'] ?? '')), 0, 2000),
                  mb_substr(trim((string)($_POST['location'] ?? '')), 0, 160),
                  $contact, mb_substr($email, 0, 190), mb_substr($phone, 0, 60)];
            if ($mine) {
                /* Editing your own listing does not send it back for review —
                   William already let it through once, and making somebody wait
                   two days to fix a typo is how a page stops being kept up. */
                q("UPDATE enterprise_mentors SET role_line=?,topics=?,about=?,location=?,contact=?,email=?,phone=? WHERE id=? AND uid=?",
                  array_merge($f, [(int)$mine['id'], (int)$me['id']]));
                header('Location: mentors.php?done=edited'); exit;
            }
            /* An admin listing themselves is live at once; anyone else waits for
               William, the same as a photograph or a business does. */
            $status = role_at_least('admin') ? 'published' : 'pending';
            q("INSERT INTO enterprise_mentors (uid,name,role_line,topics,about,location,contact,email,phone,status,sort)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)",
              array_merge([(int)$me['id'], $me['name']], $f, [$status, 99]));
            try {
                if ($status === 'pending') {
                    ask_add(['kind' => 'involved', 'name' => $me['name'], 'email' => $me['email'],
                             'topic' => 'Wants to be listed as a mentor',
                             'offers' => implode('; ', ment_topics($topics)),
                             'message' => 'This one is waiting for you on the Mentors screen — approve it and '
                                        . 'the card appears on Mentor Connect.',
                             'uid' => $me['id']]);
                }
            } catch (\Throwable $e) {}
            header('Location: mentors.php?done=' . ($status === 'pending' ? 'offered' : 'listed')); exit;
        }
    }
}

$MENTORS = ment_list();
/* ?ask=12 opens the form addressed to one person; ?ask=0 opens the same form
   with nobody named, for "I don't know who to ask". Both have to count as
   "open", so this asks whether the parameter is there at all — reading it as a
   number would make the second case indistinguishable from no parameter. */
$askOpen = array_key_exists('ask', $_GET);
$askFor  = (int)($_GET['ask'] ?? 0);
$askM    = $askFor ? ment_get($askFor) : null;
if ($askM && $askM['status'] !== 'published') $askM = null;

page_head('Mentor Connect', ['body_class' => 'home entpage']);
?>
<section class="dir-head">
  <p class="dir-eyebrow"><a href="enterprise.php">&larr; Enterprise</a></p>
  <h1>Mentor Connect</h1>
  <p class="dir-sub">Family who have already done the thing you are about to try, and are willing to
     talk about it. Ask for whatever you need &mdash; nobody here is charging you and nobody is
     selling you anything.</p>
</section>

<?php if ($did === 'ask'): ?>
  <div class="panel note-ok mn-done">
    <h2>That has gone to William.</h2>
    <p>He will put you in touch. Nothing of yours has been published on the site, and no email
       address was shown to anyone &mdash; the message went through the site, not from your inbox.</p>
    <p style="margin-top:12px"><a class="btn gold" href="mentors.php">Back to the mentors</a></p>
  </div>
<?php elseif ($did === 'offered'): ?>
  <div class="panel note-ok mn-done">
    <h2>Thank you &mdash; that is with William.</h2>
    <p>He reads every listing before it goes up, the same as he does with photographs and
       businesses. Once he approves it your card appears on this page and family can ask for you.</p>
    <p style="margin-top:12px"><a class="btn gold" href="mentors.php">Back to the mentors</a></p>
  </div>
<?php elseif ($did === 'listed' || $did === 'edited'): ?>
  <div class="panel note-ok mn-done">
    <h2>Your listing is live.</h2>
    <p>It is on this page now. You can change it whenever you like &mdash; scroll to the bottom.</p>
  </div>
<?php endif; ?>

<?php if ($err): ?><div class="err" style="max-width:760px;margin:0 auto 18px"><?= e($err) ?></div><?php endif; ?>

<?php /* --------------------------------------------------------------- ask */ ?>
<?php $askShow = $askOpen || (isset($_POST['action']) && $_POST['action'] === 'ask' && $err !== ''); ?>
<?php if ($askShow): ?>
  <?php if (!$askM && !empty($_POST['mentor_id'])) $askM = ment_get((int)$_POST['mentor_id']); ?>
  <form class="panel mn-askform" method="post" id="ask">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="ask">
    <input type="hidden" name="mentor_id" value="<?= (int)($askM ? $askM['id'] : 0) ?>">
    <div style="position:absolute;left:-9999px" aria-hidden="true">
      <label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <h2><?= $askM ? 'Ask ' . e($askM['name']) : 'Ask for a mentor' ?></h2>
    <p class="muted"><?= $askM
        ? 'This goes to William, who passes it to ' . e($askM['name']) . '. Their email address is not shown to you and yours is not shown to them.'
        : 'Not sure who to ask? Say what you are trying to do and William will point you at the right person.' ?></p>

    <label>What is it about?</label>
    <input type="text" name="topic" maxlength="200" value="<?= e($_POST['topic'] ?? '') ?>"
           placeholder="e.g. Starting my own business">

    <label>What would you like help with?</label>
    <textarea name="message" rows="5" required maxlength="4000"
      placeholder="A few lines is plenty. Where you are up to, and what you are stuck on."><?= e($_POST['message'] ?? '') ?></textarea>

    <label>A number they can reach you on (optional)</label>
    <input type="tel" name="phone" maxlength="60" value="<?= e($_POST['phone'] ?? '') ?>">

    <button class="btn gold" style="width:100%;margin-top:14px">Send it</button>
    <p class="muted" style="text-align:center;margin-top:12px">
      Signed in as <?= e($me['name']) ?>. <a href="mentors.php">Cancel</a></p>
  </form>
<?php endif; ?>

<?php /* ---------------------------------------------------------- the list */ ?>
<?php if ($MENTORS): ?>
<div class="mn-grid">
  <?php foreach ($MENTORS as $m): $tp = ment_topics($m['topics']); ?>
    <article class="mn-card">
      <div class="mn-top">
        <?php if (trim((string)$m['photo']) !== ''): ?>
          <span class="mn-face" style="background-image:url('<?= e($m['photo']) ?>')"></span>
        <?php else: ?>
          <span class="mn-face mn-ini"><?= e(ment_initials($m['name'])) ?></span>
        <?php endif; ?>
        <div>
          <h3><?= e($m['name']) ?></h3>
          <?php if (trim((string)$m['role_line']) !== ''): ?><div class="mn-role"><?= e($m['role_line']) ?></div><?php endif; ?>
          <?php if (trim((string)$m['location']) !== ''): ?><div class="mn-loc"><?= ent_icon('home') ?><?= e($m['location']) ?></div><?php endif; ?>
        </div>
      </div>

      <?php if ($tp): ?>
        <p class="mn-lab">Ask them about</p>
        <ul class="mn-topics"><?php foreach ($tp as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>

      <?php if (trim((string)$m['about']) !== ''): ?><p class="mn-about"><?= e($m['about']) ?></p><?php endif; ?>

      <div class="mn-acts">
        <a class="btn gold" href="mentors.php?ask=<?= (int)$m['id'] ?>#ask">Ask <?= e(ment_first_name($m['name'])) ?></a>
        <?php if (ment_shows_email($m)): ?>
          <a class="mn-direct" href="mailto:<?= e($m['email']) ?>"><?= ent_icon('mail') ?>Email direct</a>
        <?php endif; ?>
        <?php if (ment_shows_phone($m)): ?>
          <a class="mn-direct" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $m['phone'])) ?>"><?= ent_icon('phone') ?><?= e($m['phone']) ?></a>
        <?php endif; ?>
      </div>
      <?php if ((int)$m['uid'] === (int)$me['id']): ?>
        <p class="mn-yours">This is your listing &mdash; <a href="#offer">edit it</a>.</p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <div class="panel dir-none">
    <h2>Nobody is listed yet.</h2>
    <p class="muted">If you have run a business, held a trade, or simply done the thing somebody else
       in this family is about to attempt, put your name down below. One name is enough to start.</p>
  </div>
<?php endif; ?>

<?php if (!$askShow): ?>
<p class="mn-nosure">Not sure who to ask? <a href="mentors.php?ask=0#ask">Describe what you need</a>
   and William will point you at the right person.</p>
<?php endif; ?>

<?php /* --------------------------------------------------- become a mentor */ ?>
<form class="panel mn-offer" method="post" id="offer">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="offer">
  <div style="position:absolute;left:-9999px" aria-hidden="true">
    <label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

  <h2><?= $mine ? 'Your mentor listing' : 'Offer to mentor' ?></h2>
  <p class="muted"><?= $mine
      ? 'Change anything here and it updates straight away on the page above.'
      : 'You do not need a company or a title. If you have done it, somebody in this family wants to ask you about it.' ?></p>

  <label>What you do, in a line (optional)</label>
  <input type="text" name="role_line" maxlength="200"
         value="<?= e($mine ? $mine['role_line'] : ($_POST['role_line'] ?? '')) ?>"
         placeholder="e.g. GMW Transportation &mdash; Dallas, TX">

  <label>What you are happy to be asked about &mdash; one per line</label>
  <textarea name="topics" rows="4" required maxlength="2000"
    placeholder="Starting your own business&#10;Getting your first customers&#10;Hiring your first employee"><?= e($mine ? $mine['topics'] : ($_POST['topics'] ?? '')) ?></textarea>

  <label>Anything you want to say to whoever reads it (optional)</label>
  <textarea name="about" rows="3" maxlength="2000"><?= e($mine ? $mine['about'] : ($_POST['about'] ?? '')) ?></textarea>

  <label>Where you are (optional)</label>
  <input type="text" name="location" maxlength="160"
         value="<?= e($mine ? $mine['location'] : ($_POST['location'] ?? '')) ?>" placeholder="e.g. Dallas, TX">

  <label>How should family reach you?</label>
  <select name="contact">
    <?php $csel = $mine ? $mine['contact'] : ($_POST['contact'] ?? 'site');
          foreach (ment_contact_opts() as $v => $lbl): ?>
      <option value="<?= e($v) ?>"<?= $csel === $v ? ' selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <p class="muted" style="margin:-4px 0 0;font-size:13px">Anything you do not choose to show stays
    private. Going through the site means your address never appears on a page.</p>

  <label>Your email (only shown if you chose to show it)</label>
  <input type="email" name="email" maxlength="190"
         value="<?= e($mine ? $mine['email'] : ($_POST['email'] ?? $me['email'])) ?>">

  <label>Your phone (only shown if you chose to show it)</label>
  <input type="tel" name="phone" maxlength="60"
         value="<?= e($mine ? $mine['phone'] : ($_POST['phone'] ?? '')) ?>">

  <button class="btn gold" style="width:100%;margin-top:14px">
    <?= $mine ? 'Save my listing' : 'Put my name down' ?></button>
  <?php if (!$mine && !role_at_least('admin')): ?>
    <p class="muted" style="text-align:center;margin-top:12px">William reads it before it goes up.</p>
  <?php endif; ?>
</form>

<?php legacy_footer(); page_foot();
