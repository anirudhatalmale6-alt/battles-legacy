<?php
/** William's inbox for everything sent through Share Your Thoughts. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/feedback_data.php';
require_role('admin');
feedback_migrate();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($act === 'tab') {
        fb_meta_set('tab', empty($_POST['on']) ? '0' : '1');
        flash(empty($_POST['on'])
            ? 'The "Your thoughts" tab is hidden now.'
            : 'The "Your thoughts" tab is showing on every page again.');
        header('Location: feedback_manage.php'); exit;
    }
    if ($act === 'markall') {
        $n = fb_mark_all_read();
        flash($n ? $n . ' moved out of New, so the number beside Feedback in the menu is gone. Nothing was deleted — they are all under "Looking into it".'
                 : 'There was nothing new to clear.');
        header('Location: feedback_manage.php'); exit;
    }
    if ($id && fb_one($id)) {
        if ($act === 'status')      { fb_set_status($id, $_POST['status'] ?? 'new'); flash('Moved.'); }
        elseif ($act === 'share')   {
            fb_set_shared($id, !empty($_POST['on']));
            /* He asked whether sharing sends it to that person. It does not, and
               the page has to say where it actually goes - Share Your Thoughts
               needs no login, so this is the one page on the site the public can
               read. */
            flash(!empty($_POST['on'])
                ? 'Shared. It is now on the Share Your Thoughts page, where anyone who opens the site can read it — that page needs no sign-in. Nothing was sent to anybody.'
                : 'Taken off the Share Your Thoughts page.');
        }
        elseif ($act === 'reply' || $act === 'reply_send') {
            fb_set_reply($id, $_POST['reply'] ?? '');
            /* Writing a note back IS having read it. Leaving it in New was why
               the number never went down. */
            $r = fb_one($id);
            if ($r && $r['status'] === 'new' && trim((string)$r['reply']) !== '') fb_set_status($id, 'reading');
            if ($act === 'reply_send') {
                list($ok, $why) = fb_send_reply($id, $me);
                flash($ok ? 'Saved and sent. ' . $why : 'Saved, but it did not go: ' . $why);
            } else {
                flash('Your note was saved. Nothing has been sent — use "Save and email it" for that, or the share button to text it.');
            }
        }
        elseif ($act === 'delete')  { fb_delete($id); flash('Deleted.'); }
    }
    header('Location: feedback_manage.php?tab=' . urlencode($_POST['back'] ?? 'new')); exit;
}

$TAB   = in_array($_GET['tab'] ?? '', ['new','reading','done','all'], true) ? $_GET['tab'] : 'new';
$ROWS  = fb_all($TAB === 'all' ? '' : $TAB);
$COUNT = ['new'=>0,'reading'=>0,'done'=>0,'all'=>0];
foreach (fb_all() as $r) { $COUNT['all']++; if (isset($COUNT[$r['status']])) $COUNT[$r['status']]++; }
list($AVG, $RATED) = fb_avg_rating();

page_head('What people are saying', ['body_class' => 'em']);
?>
<h1>What people are saying</h1>
<p class="lede">Everything sent through <a href="feedback.php">Share Your Thoughts</a> lands here. Nobody else can see this page.</p>
<p class="muted" style="max-width:760px;margin:-6px 0 14px">Two different things, and it is worth keeping them apart.
  <b>Emailing your note back</b> goes to that one person and nobody else sees it.
  <b>Putting it on the Thoughts page</b> sends nothing to anyone &mdash; it publishes their words, their name and your
  note back on <a href="feedback.php">Share Your Thoughts</a>, which is the one page on this site that needs no
  sign-in, so anybody who has the address can read it.</p>

<div class="fbm-top">
  <div class="fbm-stat"><b><?= (int)$COUNT['all'] ?></b><span>thoughts in total</span></div>
  <div class="fbm-stat"><b><?= (int)$COUNT['new'] ?></b><span>you haven&rsquo;t read yet</span></div>
  <?php if ($COUNT['new']): ?>
    <?php /* The number beside Feedback in the menu is this count and nothing
             else. Saving a note back now clears it on its own; this is for the
             ones he has read and does not want to answer. */ ?>
    <form method="post" class="fbm-markall" onsubmit="return confirm('Clear the number beside Feedback? Nothing is deleted — they all move to \'Looking into it\'.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="markall">
      <b>The number beside Feedback in the menu</b>
      <span>is these <?= (int)$COUNT['new'] ?>. It goes when they leave New.</span>
      <button class="btn2 solid">Clear it</button>
    </form>
  <?php endif; ?>
  <div class="fbm-stat"><b><?= $RATED ? e(number_format($AVG, 1)) . ' / 5' : '&mdash;' ?></b><span><?= $RATED ? (int)$RATED . ' star ratings' : 'no ratings yet' ?></span></div>
  <form method="post" class="fbm-tabsw">
    <?= csrf_field() ?><input type="hidden" name="action" value="tab">
    <input type="hidden" name="on" value="<?= fb_tab_on() ? '0' : '1' ?>">
    <b>The &ldquo;Your thoughts&rdquo; tab</b>
    <span><?= fb_tab_on() ? 'is showing in the corner of every page.' : 'is hidden right now.' ?></span>
    <button class="btn2<?= fb_tab_on() ? '' : ' solid' ?>"><?= fb_tab_on() ? 'Hide it' : 'Show it' ?></button>
  </form>
</div>

<div class="fbm-tabs">
  <?php foreach (['new'=>'New','reading'=>'Looking into it','done'=>'Handled','all'=>'Everything'] as $k => $label): ?>
    <a class="fbm-tab<?= $TAB === $k ? ' on' : '' ?>" href="feedback_manage.php?tab=<?= e($k) ?>"><?= e($label) ?> <i><?= (int)$COUNT[$k] ?></i></a>
  <?php endforeach; ?>
</div>

<?php if (!$ROWS): ?>
  <div class="panel"><p><?= $TAB === 'new' ? 'Nothing new right now. When someone sends a thought it will appear here.' : 'Nothing in this list.' ?></p></div>
<?php endif; ?>

<?php foreach ($ROWS as $r): $K = fb_kinds()[$r['kind']] ?? fb_kinds()['suggestion']; ?>
  <div class="panel fbm-item<?= $r['status'] === 'new' ? ' isnew' : '' ?>">
    <div class="fbm-head">
      <span class="fb-av"><?= fb_initials($r['name']) ?></span>
      <div class="fbm-who">
        <b><?= e($r['name'] ?: 'Family member') ?></b>
        <?php if (trim((string)$r['contact']) !== ''): ?><span class="fbm-contact"><?= e($r['contact']) ?></span><?php endif; ?>
        <span class="fbm-meta"><?= fb_icon($K[1], 15) ?> <?= e($K[0]) ?> &middot; <?= e(fb_area_label($r['area'])) ?> &middot; <?= e(fb_ago($r['created_at'])) ?></span>
      </div>
      <?= fb_stars($r['rating']) ?>
      <?php /* It used to read "Shared with the family", which is what made him
               ask whether it had been sent to that person. It is neither sent
               nor family-only: it is on a page with no sign-in. */ ?>
      <?php if ($r['shared']): ?><span class="fbm-badge">On the public Thoughts page<?= (int)$r['agrees'] ? ' &middot; ' . (int)$r['agrees'] . ' agree' : '' ?></span><?php endif; ?>
    </div>

    <p class="fbm-body"><?= nl2br(e($r['body'])) ?></p>

    <?php
      $rmail = fb_reply_email($r);
      $rtext = trim((string)$r['reply']) !== '' ? fb_reply_text($r, trim((string)$me['name']) ?: 'William') : '';
    ?>
    <form method="post" class="fbm-reply">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($TAB) ?>">
      <label>Your note back</label>
      <textarea name="reply" rows="2" placeholder="e.g. Good idea — I'll add the Alabama photos this week."><?= e($r['reply']) ?></textarea>
      <div class="fbm-replybtns">
        <button class="btn2" name="action" value="reply">Save it only</button>
        <?php if ($rmail !== ''): ?>
          <button class="btn2 solid" name="action" value="reply_send">&#9993; Save and email it to <?= e($rmail) ?></button>
        <?php endif; ?>
      </div>
      <p class="fbm-replynote">
        <?php if ($r['reply_sent_at']): ?>
          <?= (int)$r['reply_ok'] ? '&#10003; Emailed ' . e(date('j M, g:ia', strtotime($r['reply_sent_at']))) . ' — handed to the mail server, which is not proof it was read.'
                                  : '&#9888; Tried to email it ' . e(date('j M, g:ia', strtotime($r['reply_sent_at']))) . ' and the mail server refused it.' ?>
        <?php elseif ($rmail !== ''): ?>
          Nothing has been sent yet. Saving alone only stores it.
        <?php else: ?>
          <?= trim((string)$r['contact']) !== ''
                ? 'They left &ldquo;' . e($r['contact']) . '&rdquo; to be reached on, which is not an email address, so there is nothing here to email.'
                : 'They left no way to reach them, so there is nothing here to email.' ?>
          <?php /* Do not point at a button that is not on the page yet: it only
                   appears once there is something to send. */ ?>
          <?= $rtext !== '' ? 'You can still send it yourself with the button below.'
                            : 'Write your note back and save it, and a button appears here to text it.' ?>
        <?php endif; ?>
        Your note also appears under their thought <b>if</b> you put it on the Thoughts page below.
      </p>
    </form>
    <?php if ($rtext !== ''): ?>
      <?php /* The same share sheet as the invitations. A phone number in the
               contact box is useless to a mail server and perfectly good to a
               text message, and this is the only route that reaches those. */ ?>
      <p style="margin:0 0 10px">
        <button type="button" class="btn2 sharebtn" data-share="<?= e($rtext) ?>"
                data-title="Re: what you sent through the family site">&#128172; Text this reply / Messenger</button>
      </p>
    <?php endif; ?>

    <div class="fbm-acts">
      <?php foreach (['new'=>'Mark new','reading'=>'Looking into it','done'=>'Handled'] as $s => $label): if ($s === $r['status']) continue; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="<?= e($s) ?>">
          <input type="hidden" name="back" value="<?= e($TAB) ?>"><button class="btn2"><?= e($label) ?></button></form>
      <?php endforeach; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="share">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="on" value="<?= $r['shared'] ? '0' : '1' ?>">
        <input type="hidden" name="back" value="<?= e($TAB) ?>">
        <button class="btn2<?= $r['shared'] ? '' : ' solid' ?>"
                title="<?= $r['shared'] ? 'Take it off the public Share Your Thoughts page' : 'Puts it on the Share Your Thoughts page, which anyone can read without signing in. It sends nothing to anybody.' ?>"><?= $r['shared'] ? 'Stop sharing' : 'Put it on the Thoughts page' ?></button></form>
      <form method="post" onsubmit="return confirm('Delete this thought for good?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="back" value="<?= e($TAB) ?>"><button class="btn2 fbm-del">Delete</button></form>
    </div>
  </div>
<?php endforeach; ?>

<?php /* same file the Members page uses, so the two cannot drift */ ?>
<script src="assets/share.js"></script>
<?php page_foot();
