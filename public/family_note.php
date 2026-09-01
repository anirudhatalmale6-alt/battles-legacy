<?php
/** The monthly note to the family.
 *
 *  Ten people have accounts and the site never tells any of them when
 *  something new has appeared, so there has never been a reason to come back.
 *  This is that reason, and it is built out of real rows — the calendar, the
 *  photographs table, the stories, the news — so it cannot promise anything
 *  the site does not actually have.
 *
 *  He edits every word before it goes, he can post one copy to himself first,
 *  and it leaves on the same kind of clock as the invitations. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/notes.php';
require_role('admin');
$me = current_user();
note_tick();

$goto = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'save' || $act === 'send' || $act === 'test') {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body    = trim((string)($_POST['body'] ?? ''));
        if ($subject === '' || $body === '') {
            flash('A note needs both a subject line and something in the box. Nothing was saved.');
            $id = 0;
        } else {
            $id = note_save($subject, $body, $me, $id);
            if (!$id) {
                flash('Sorry — that could not be saved.');
            } elseif ($act === 'save') {
                flash('Saved. Nothing has been sent.');
            } elseif ($act === 'test') {
                list($ok, $why) = note_send_test($id, $me);
                flash($ok ? 'One copy is on its way to you. ' . $why : 'It did not go: ' . $why);
            } else {
                $n = note_queue($id);
                $p = note_progress($id);
                if ($n > 0) {
                    flash($n . ' ' . ($n === 1 ? 'copy is' : 'copies are') . ' in the queue. '
                        . 'The first goes now and the rest follow about ' . (int)note_gap_minutes()
                        . ' minutes apart — you do not have to stay on this page.');
                } elseif ($p['queued'] > 0) {
                    flash('Everyone on the list already has this note, or has one waiting. Nothing was queued twice.');
                } else {
                    flash('There is nobody to send it to yet.');
                }
                $goto = '#queue';
            }
        }
    } elseif ($act === 'send_next') {
        list($ok, $why) = note_release();
        flash($ok ? $why : 'Not yet: ' . $why);
        $goto = '#queue';
    } elseif ($act === 'stop') {
        note_queue_clear($id);
        flash('Stopped. Nothing further will go out. Whatever has already been sent has been sent.');
        $goto = '#queue';
    }
    header('Location: family_note.php' . ($id ? '?id=' . $id : '') . $goto);
    exit;
}

$open = (int)($_GET['id'] ?? 0);
$note = $open ? note_get($open) : null;

/* No note open: offer this month's draft, built fresh. */
$draft = null;
if (!$note) {
    $draft = note_draft();
    $note  = ['id' => 0, 'subject' => $draft['subject'], 'body' => $draft['body'], 'queued_at' => null];
}

$recips  = note_recipients();
$optOut  = note_opted_out_count();
$prog    = !empty($note['id']) ? note_progress((int)$note['id']) : ['queued'=>0,'sent'=>0,'failed'=>0,'waiting'=>0];
$pending = note_pending_count();
list($rOk, $rWhy, $rWait) = note_ready();
$history = note_all(12);

/* Sending carries on by itself after he leaves the page, so coming back to a
   blank new draft with copies still on the way would look like nothing had
   happened. Name the note that is still going out, and link to it. */
$busy = null;
if ($pending > 0) {
    $row = note_pending(1);
    if ($row && (int)$row[0]['note_id'] !== (int)$note['id']) $busy = note_get($row[0]['note_id']);
}

/* Exactly what one person receives — the greeting and the footer are added by
   note_render(), not by the box, so this is the whole message and not a
   flattering approximation of it. */
$sample = note_render($note, ['name' => $me['name'], 'email' => $me['email'], 'token' => 'preview']);

page_head('The monthly family note', ['body_class' => 'em']);
?>
<h1>The monthly note</h1>
<p class="lede">A short note to the <?= count($recips) ?> <?= count($recips) === 1 ? 'person who has' : 'people who have' ?>
  an account, telling them what has appeared on the site since the last one. Nothing here is sent until you press send,
  and you can post a copy to yourself first.</p>
<p style="margin:10px 0 4px"><a class="btn" href="admin.php">&larr; Back to the admin page</a></p>

<?php if ($busy): $bp = note_progress((int)$busy['id']); ?>
  <p class="inv-audit">&#9993; <b><?= e($busy['subject']) ?></b> is still going out &mdash;
    <b><?= (int)$bp['sent'] ?></b> sent, <b><?= (int)$bp['waiting'] ?></b> still to go<?php
    if ($bp['failed']): ?>, <b><?= (int)$bp['failed'] ?></b> the mail server refused<?php endif; ?>.
    It keeps going on its own. <a href="family_note.php?id=<?= (int)$busy['id'] ?>">Open it</a></p>
<?php endif; ?>

<div class="panel">
  <h2 style="margin:0 0 6px"><?= $open ? 'Note from ' . e(date('j F Y', strtotime($note['created_at']))) : 'This month&rsquo;s draft' ?></h2>
  <?php if (!$open): ?>
    <p class="muted" style="margin:0 0 12px">Written for you out of what has actually changed since
      <?= e(date('j F', strtotime($draft['since']))) ?> &mdash; the calendar, new photographs, new stories,
      family news, and anybody who has joined. Change any of it. If a section is missing it is because
      there was nothing to put in it, not because it was forgotten.</p>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$note['id'] ?>">

    <label>Subject line</label>
    <input type="text" name="subject" value="<?= e($note['subject']) ?>" maxlength="200" required>

    <label>The note itself</label>
    <textarea name="body" rows="20" required style="font-family:inherit;line-height:1.6"><?= e($note['body']) ?></textarea>
    <p class="muted" style="margin:-4px 0 0;font-size:13px">Plain words. No formatting &mdash; whatever you type is
      exactly what arrives. <b>Hello Danielle,</b> at the top and the way to stop receiving it at the bottom are
      added for you, so every copy is addressed to the person reading it.</p>

    <div class="inv-bulkrow" style="gap:10px;margin-top:14px">
      <button class="btn" name="action" value="save" style="margin:0">Save it for now</button>
      <button class="btn" name="action" value="test" style="margin:0">Post one copy to me first</button>
      <button class="btn gold" name="action" value="send" style="margin:0"
              onclick="return confirm('Send this to <?= count($recips) ?> people? They go out a few minutes apart, and you can stop it part-way.')">
        Send it to <?= count($recips) ?> <?= count($recips) === 1 ? 'person' : 'people' ?></button>
    </div>
  </form>
</div>

<div class="panel" style="margin-top:18px">
  <h2 style="margin:0 0 6px">What one person will see</h2>
  <p class="muted" style="margin:0 0 10px">This is the whole message, top to bottom, as it would arrive
    for <?= e($me['name']) ?>. It changes when you save.</p>
  <div class="fact" style="border-left-color:var(--gold)">
    <div class="v" style="white-space:pre-wrap;font-size:15px"><?= e($sample) ?></div>
  </div>
</div>

<div class="panel" id="queue" style="margin-top:18px">
  <h2 style="margin:0 0 6px">Who it goes to, and how fast</h2>
  <p class="muted" style="margin:0 0 12px">
    Copies leave <b><?= (int)note_gap_minutes() ?> minutes apart</b>, at most <b><?= (int)note_per_day() ?> a day</b>.
    Ten messages leaving this domain inside a minute is the same shape as the 49 invitations in August, and the family
    would read this one out of a spam folder or not at all. They keep going on their own while you get on with
    something else &mdash; you do not have to leave this page open.</p>

  <p style="margin:0 0 10px">
    <b><?= count($recips) ?></b> <?= count($recips) === 1 ? 'person' : 'people' ?> on the list<?php
      if ($optOut): ?> &middot; <b><?= $optOut ?></b> asked not to be written to<?php endif; ?>
    <?php if ($prog['queued']): ?>
      &middot; <b><?= $prog['sent'] ?></b> sent
      <?php if ($prog['waiting']): ?>&middot; <b><?= $prog['waiting'] ?></b> still to go<?php endif; ?>
      <?php if ($prog['failed']): ?>&middot; <b><?= $prog['failed'] ?></b> the mail server refused<?php endif; ?>
    <?php endif; ?>
    <?php if ($pending && !$rOk): ?>
      &middot; <span class="muted"><?= $rWait > 0 ? 'next one in about ' . max(1, (int)ceil($rWait / 60)) . ' min' : e($rWhy) ?></span>
    <?php elseif ($pending && $rOk): ?>
      &middot; <span class="muted">the next one can go now</span>
    <?php endif; ?>
  </p>

  <?php if ($recips): ?>
    <p class="muted" style="margin:0 0 10px">Going to:
      <?php
        $names = [];
        foreach (array_slice($recips, 0, 8) as $r) $names[] = trim((string)$r['name']) !== '' ? $r['name'] : $r['email'];
        echo e(implode(', ', $names));
        if (count($recips) > 8) echo ', and ' . (count($recips) - 8) . ' more';
      ?>.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 10px">Nobody has an account with an email address on it yet.</p>
  <?php endif; ?>

  <?php if ($pending): ?>
    <div class="inv-bulkrow" style="gap:10px">
      <form method="post" style="margin:0"><?= csrf_field() ?>
        <input type="hidden" name="action" value="send_next"><input type="hidden" name="id" value="<?= (int)$note['id'] ?>">
        <button class="btn" style="margin:0"<?= $rOk ? '' : ' disabled title="' . e($rWait > 0 ? 'Too soon after the last one' : $rWhy) . '"' ?>>Send the next one now</button>
      </form>
      <form method="post" style="margin:0" onsubmit="return confirm('Stop sending? Copies already sent stay sent.')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="stop"><input type="hidden" name="id" value="<?= (int)$note['id'] ?>">
        <button class="btn danger" style="margin:0">Stop sending</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($history): ?>
<div class="panel" style="margin-top:18px">
  <h2 style="margin:0 0 6px">Notes you have written</h2>
  <table class="list">
    <tr><th>When</th><th>Subject</th><th>Sent</th><th></th></tr>
    <?php foreach ($history as $h): $hp = note_progress((int)$h['id']); ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= e($h['created_at'] ? date('j M Y', strtotime($h['created_at'])) : '') ?></td>
        <td><?= e($h['subject']) ?></td>
        <td class="muted" style="white-space:nowrap">
          <?php if (!$hp['queued']): ?>not sent
          <?php else: ?><?= (int)$hp['sent'] ?> of <?= (int)$hp['queued'] ?><?php
            if ($hp['waiting']) echo ' · ' . (int)$hp['waiting'] . ' waiting';
            if ($hp['failed'])  echo ' · ' . (int)$hp['failed'] . ' refused';
          endif; ?>
        </td>
        <td><a class="btn2" href="family_note.php?id=<?= (int)$h['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="margin:10px 0 0"><a href="family_note.php">Start this month&rsquo;s note</a> &mdash;
    a fresh draft built from whatever has changed since the last one you sent.</p>
</div>
<?php endif; ?>

<?php page_foot();
