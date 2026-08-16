<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/install.php';
require_once __DIR__ . '/../src/pwreset.php';
require_once __DIR__ . '/../src/access_data.php';
require_once __DIR__ . '/../src/invites.php';
require_role('admin');
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'invite') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = $_POST['role'] ?? 'member';
        if ($name === '' && $email === '') {
            flash('Put in a name, an email address, or both.');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('That doesn\'t look like an email address: ' . $email);
        } elseif ($was = invite_existing($email)) {
            flash($email . ' is already ' . ($was === 'member' ? 'a member.' : 'holding an invitation — it\'s in the list below.'));
        } else {
            list($token, $url) = invite_create($name, $email, $role, $me['id']);
            $who = $name ?: $email;
            if ($email === '') {
                flash('Invitation ready for ' . $who . '. There\'s no email address for them, so use one of the send buttons below.');
            } else {
                $inv = one("SELECT * FROM invites WHERE token=?", [$token]);
                flash(invite_mail($inv, $me)
                    ? 'Invitation for ' . $who . ' handed to the mail server. If it hasn\'t arrived in ten minutes, check their spam folder — or use "Send it myself" below, which always gets through.'
                    : 'Invitation ready for ' . $who . ', but the mail server wouldn\'t take it. Use "Send it myself" below.');
            }
        }
    } elseif ($act === 'invite_bulk') {
        /* A pasted list — one person per line, in whatever shape it came out of
           his address book or the Facebook group. */
        $lines = preg_split('/\r\n|\r|\n/', (string)($_POST['bulk'] ?? ''));
        $role  = $_POST['role'] ?? 'member';
        $send  = !empty($_POST['bulk_send']);
        $made = 0; $mailed = 0; $skipped = []; $bad = [];
        foreach ($lines as $line) {
            $p = invite_parse_line($line);
            if (!$p) continue;
            if ($p['email'] !== '' && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) { $bad[] = trim($line); continue; }
            if ($p['email'] !== '' && ($was = invite_existing($p['email']))) { $skipped[] = ($p['name'] ?: $p['email']) . ' (already ' . $was . ')'; continue; }
            list($token, $url) = invite_create($p['name'], $p['email'], $role, $me['id']);
            $made++;
            if ($send && $p['email'] !== '') {
                $inv = one("SELECT * FROM invites WHERE token=?", [$token]);
                if ($inv && invite_mail($inv, $me)) $mailed++;
            }
        }
        if (!$made && !$skipped && !$bad) flash('Nothing to read in that box.');
        else {
            $msg = $made . ' invitation' . ($made === 1 ? '' : 's') . ' made';
            if ($send) $msg .= ', ' . $mailed . ' handed to the mail server';
            $msg .= '. They are all listed below with a send button each.';
            flash($msg);
            if ($skipped) flash('Left alone: ' . implode('; ', array_slice($skipped, 0, 12)) . (count($skipped) > 12 ? ' …and ' . (count($skipped) - 12) . ' more' : ''));
            if ($bad) flash('Couldn\'t read: ' . implode('; ', array_slice($bad, 0, 8)));
        }
    } elseif ($act === 'invite_send') {
        $inv = invite_by_id($_POST['iid'] ?? 0);
        if (!$inv) flash('That invitation is no longer waiting.');
        elseif (trim((string)$inv['email']) === '') flash('There\'s no email address on that invitation — use "Send it myself".');
        else flash(invite_mail($inv, $me)
            ? 'Sent again to ' . $inv['email'] . '. If it still doesn\'t arrive, use "Send it myself".'
            : 'The mail server wouldn\'t take it. Use "Send it myself".');
    } elseif ($act === 'invite_delete') {
        invite_delete($_POST['iid'] ?? 0);
        flash('Invitation cancelled — that link no longer works.');
    } elseif ($act === 'rename') {
        $uid = (int)($_POST['uid'] ?? 0);
        $newname = trim($_POST['newname'] ?? '');
        if ($newname === '') { flash('Please enter a name.'); }
        elseif ($uid) { q("UPDATE users SET name=? WHERE id=?", [mb_substr($newname,0,120), $uid]); flash('Name updated to ' . $newname . '.'); }
    } elseif ($act === 'role') {
        $uid = (int)$_POST['uid'];
        $role = in_array($_POST['newrole'] ?? '', ['member','moderator','admin'], true) ? $_POST['newrole'] : 'member';
        if ($uid !== (int)$me['id']) { q("UPDATE users SET role=? WHERE id=?", [$role, $uid]); flash('Role updated.'); }
        else flash('You can\'t change your own role.');
    } elseif ($act === 'suspend') {
        $uid = (int)$_POST['uid'];
        if ($uid !== (int)$me['id']) { q("UPDATE users SET status='suspended' WHERE id=?", [$uid]); flash('Member suspended.'); }
    } elseif ($act === 'restore') {
        q("UPDATE users SET status='active' WHERE id=?", [(int)$_POST['uid']]);
        flash('Member restored.');
    } elseif ($act === 'pwreset') {
        /* A member who has forgotten their password and can't get the email —
           this makes them the same one-time link, for William to pass on. */
        $uid = (int)($_POST['uid'] ?? 0);
        $who = $uid ? one("SELECT * FROM users WHERE id=?", [$uid]) : null;
        if ($who) {
            pwreset_create($who['id'], 'admin');
            flash('Reset link created for ' . ($who['name'] ?: $who['email']) . ' — copy it from the list below and send it to them.');
        }
    } elseif ($act === 'pwcancel') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid) { try { q("UPDATE password_resets SET used_at=CURRENT_TIMESTAMP WHERE user_id=? AND used_at IS NULL", [$uid]); } catch (\Throwable $e) {} }
        flash('Those reset links have been cancelled.');
    } elseif ($act === 'ar_approve') {
        $res = ar_approve((int)($_POST['rid'] ?? 0), $_POST['role'] ?? 'member', $me['id']);
        if (!$res) flash('That request has already been dealt with.');
        else flash($res['emailed']
            ? 'Approved, and their invitation was handed to the mail server. It is also in the list below in case it doesn\'t arrive.'
            : 'Approved — their invitation link is in the list below. Use one of the send buttons to get it to them.');
    } elseif ($act === 'ar_decline') {
        ar_decline((int)($_POST['rid'] ?? 0), $me['id']);
        flash('Request declined. They are not told, and nothing is sent.');
    } elseif ($act === 'ar_delete') {
        ar_delete((int)($_POST['rid'] ?? 0));
        flash('Request removed from the list.');
    } elseif ($act === 'importphotos') {
        $dir = trim($_POST['photo_dir'] ?? '');
        $r = install_photos($dir);
        if (!$r['ok']) flash('Photos: ' . $r['error']);
        else {
            $s = $r['stats'];
            flash("Photos imported — matched {$s['matched']}, newly added {$s['copied']}, already present {$s['skipped']}, unmatched {$s['unmatched']}.");
            if ($r['unmatched']) flash('Could not match (need a name): ' . implode(', ', array_slice($r['unmatched'], 0, 30)) . (count($r['unmatched']) > 30 ? ' …and ' . (count($r['unmatched']) - 30) . ' more' : ''));
        }
    } elseif ($act === 'refreshtree') {
        $path = trim($_POST['ged_path'] ?? '');
        $r = install_gedcom($path);
        flash($r['ok'] ? "Tree refreshed — {$r['individuals']} people, {$r['families']} families ({$r['living']} living kept private)." : ('Tree: ' . $r['error']));
    }
    header('Location: admin.php'); exit;
}

$users = all("SELECT * FROM users ORDER BY role='admin' DESC, role='moderator' DESC, name");
$reqNew  = ar_list('new');
$reqDone = array_slice(array_filter(ar_list('all'), function ($r) { return $r['status'] !== 'new'; }), 0, 12);
$resets = pwreset_open();
$invites = invite_open();

page_head('Members');
?>
<h1>Family members</h1>
<p class="lede">Invite family, and set who is an Admin, Moderator or Member. Invitation links are private — send them directly to the person.</p>

<?php if ($reqNew): ?>
<div class="panel arq" style="margin-top:20px;border-left:3px solid var(--gold)">
  <h2>People asking to join (<?= count($reqNew) ?>)</h2>
  <p class="muted">Somebody in the family has shared the site with them. Nobody gets in until you say so &mdash;
    approving makes them an invitation link for you to send, and they choose their own password from it.</p>
  <?php foreach ($reqNew as $r): $hits = ar_tree_matches($r['name']); ?>
    <div class="arq-card">
      <div class="arq-who">
        <b><?= e($r['name']) ?></b>
        <span><?= e($r['email']) ?><?= $r['phone'] ? ' &middot; ' . e($r['phone']) : '' ?></span>
        <i><?= e(date('j M Y', strtotime($r['created_at']))) ?></i>
      </div>
      <div class="arq-body">
        <p><b>Related to:</b> <?= e($r['relation']) ?></p>
        <?php if (trim($r['referred_by']) !== ''): ?><p><b>Heard about it from:</b> <?= e($r['referred_by']) ?></p><?php endif; ?>
        <?php if (trim($r['note']) !== ''): ?><p class="arq-note"><?= nl2br(e($r['note'])) ?></p><?php endif; ?>
        <?php if ($hits): ?>
          <p class="arq-hits"><b>In the family tree:</b>
            <?php foreach ($hits as $h): ?>
              <a href="person.php?pid=<?= e(urlencode($h['pid'])) ?>" target="_blank" rel="noopener"><?= e($h['name']) ?><?= yr($h['birth_date']) ? ' (' . e(yr($h['birth_date'])) . ')' : '' ?></a>
            <?php endforeach; ?>
          </p>
        <?php else: ?>
          <p class="arq-hits none">No one of that name is in the tree — worth a phone call before you approve.</p>
        <?php endif; ?>
      </div>
      <div class="arq-act">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="ar_approve"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
          <select name="role"><option value="member">as a Member</option><option value="moderator">as a Moderator</option><option value="admin">as an Admin</option></select>
          <button class="btn gold" style="margin:0">Approve</button>
        </form>
        <form method="post" onsubmit="return confirm('Decline <?= e(addslashes($r['name'])) ?>? They are not told either way.')">
          <?= csrf_field() ?><input type="hidden" name="action" value="ar_decline"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
          <button class="arq-no" type="submit">Decline</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($reqDone): ?>
<div class="panel" style="margin-top:18px">
  <h2>Requests you&rsquo;ve already dealt with</h2>
  <table class="list">
    <tr><th>Name</th><th>Email</th><th>Outcome</th><th></th></tr>
    <?php foreach ($reqDone as $r): ?>
      <tr>
        <td><?= e($r['name']) ?></td>
        <td class="muted"><?= e($r['email']) ?></td>
        <td><span class="pill <?= $r['status'] === 'approved' ? 'approved' : 'pending' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
        <td>
          <form method="post" style="margin:0">
            <?= csrf_field() ?><input type="hidden" name="action" value="ar_delete"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
            <button class="btn" style="margin:0;padding:5px 10px;font-size:14px">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:20px">
  <h2>Invite a family member</h2>
  <p class="muted">Put in an email address and the website will email the invitation. Whether it does or
    doesn&rsquo;t get through, the link also appears below with a <b>Send it myself</b> button that opens your own
    email &mdash; that one always arrives, because it comes from you rather than from a website.</p>
  <form method="post" class="inv-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="invite">
    <div><label>Name</label><input type="text" name="name" placeholder="e.g. Dianne Battles"></div>
    <div><label>Email</label><input type="email" name="email" placeholder="dianne@example.com"></div>
    <div><label>Role</label><select name="role"><option value="member">Member</option><option value="moderator">Moderator</option><option value="admin">Admin</option></select></div>
    <button class="btn gold" style="margin:0">Invite</button>
  </form>

  <details class="inv-bulk">
    <summary>Invite a lot of people at once</summary>
    <form method="post" style="margin-top:12px">
      <?= csrf_field() ?><input type="hidden" name="action" value="invite_bulk">
      <label>One person per line</label>
      <textarea name="bulk" rows="7" placeholder="Dianne Battles, dianne@example.com&#10;Sam Battles &lt;sam@example.com&gt;&#10;cousin.ray@example.com&#10;Anthony Battles"></textarea>
      <p class="muted" style="margin:6px 0 10px">Name and address in any order, separated by a comma, a space or
        angle brackets &mdash; whatever your address book pastes in. A line with only a name still gets a link;
        a line with only an address is fine too. Anyone already a member is skipped.</p>
      <div class="inv-bulkrow">
        <label style="margin:0">Role
          <select name="role"><option value="member">Member</option><option value="moderator">Moderator</option><option value="admin">Admin</option></select>
        </label>
        <label class="inv-check"><input type="checkbox" name="bulk_send" value="1" checked> Try to email them as well</label>
        <button class="btn gold" style="margin:0">Make the invitations</button>
      </div>
    </form>
  </details>
</div>

<div class="panel" style="margin-top:18px">
  <h2>Photos &amp; tree</h2>
  <p class="muted">Point these at files already on your server — the tools do the rest.</p>
  <form method="post" style="margin-top:12px">
    <?= csrf_field() ?><input type="hidden" name="action" value="importphotos">
    <label>Auto-pin photos from a server folder</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <input type="text" name="photo_dir" placeholder="e.g. /home/thebattl/public_html/photos" style="flex:1;min-width:260px">
      <button class="btn" style="margin:0">Import photos</button>
    </div>
    <p class="muted" style="margin-top:6px">Reads every photo's filename and pins it to the right person. Safe to run again — it skips ones already added.</p>
  </form>
  <form method="post" style="margin-top:14px">
    <?= csrf_field() ?><input type="hidden" name="action" value="refreshtree">
    <label>Refresh the tree from an updated GEDCOM</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <input type="text" name="ged_path" value="<?= e(dirname(__DIR__) . '/battlesfamily.ged') ?>" style="flex:1;min-width:260px">
      <button class="btn" style="margin:0">Refresh tree</button>
    </div>
  </form>
</div>

<?php if ($invites): ?>
<div class="panel" style="margin-top:18px">
  <h2>Invitations waiting (<?= count($invites) ?>)</h2>
  <p class="muted">These people have a link but haven&rsquo;t signed up yet. <b>Send it myself</b> opens your own
    email with the whole message already written &mdash; you just press send. Links last
    <?= INVITE_DAYS ?> days.</p>
  <div class="inv-list">
    <?php foreach ($invites as $inv):
      $url  = invite_url($inv['token']);
      $m    = invite_message($inv, $url, $me);
      $mail = trim((string)$inv['email']);
      $exp  = $inv['expires_at'] ? strtotime($inv['expires_at']) : 0;
      $days = $exp ? (int)ceil(($exp - time()) / 86400) : null;
    ?>
      <div class="inv-row">
        <div class="inv-who">
          <b><?= e($inv['name'] ?: $mail ?: '—') ?></b>
          <span class="pill admin"><?= e(ucfirst($inv['role'])) ?></span>
          <?php if ($mail): ?><span class="inv-mail"><?= e($mail) ?></span><?php endif; ?>
        </div>
        <div class="inv-state">
          <?php if (!$mail): ?>
            <span class="inv-dot none"></span>No email address on file
          <?php elseif (empty($inv['emailed_at'])): ?>
            <span class="inv-dot none"></span>Not emailed yet
          <?php elseif (!empty($inv['email_ok'])): ?>
            <span class="inv-dot ok"></span>Handed to the mail server <?= e(date('j M, g:ia', strtotime($inv['emailed_at']))) ?><?php
              if ((int)$inv['sent_count'] > 1) echo ' &middot; ' . (int)$inv['sent_count'] . ' times'; ?>
          <?php else: ?>
            <span class="inv-dot bad"></span>The mail server refused it
          <?php endif; ?>
          <?php if ($days !== null): ?><span class="inv-exp"><?= $days > 0 ? 'expires in ' . $days . ' day' . ($days === 1 ? '' : 's') : 'expired' ?></span><?php endif; ?>
        </div>
        <div class="inv-link">
          <input type="text" readonly value="<?= e($url) ?>" onclick="this.select()" id="inv<?= (int)$inv['id'] ?>">
          <button type="button" class="btn2" data-copy="inv<?= (int)$inv['id'] ?>">Copy link</button>
        </div>
        <div class="inv-acts">
          <?php if ($mail): ?>
            <a class="btn gold" href="<?= e(mailto_link($mail, $m['subject'], $m['body'])) ?>">&#9993; Send it myself</a>
            <form method="post" style="margin:0;display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="invite_send"><input type="hidden" name="iid" value="<?= (int)$inv['id'] ?>">
              <button class="btn2" type="submit"><?= empty($inv['emailed_at']) ? 'Let the website email it' : 'Email it again' ?></button>
            </form>
          <?php else: ?>
            <span class="muted" style="font-size:13px">Copy the link and send it however you like.</span>
          <?php endif; ?>
          <form method="post" style="margin:0;display:inline" onsubmit="return confirm('Cancel this invitation? The link will stop working.')">
            <?= csrf_field() ?><input type="hidden" name="action" value="invite_delete"><input type="hidden" name="iid" value="<?= (int)$inv['id'] ?>">
            <button class="inv-x" type="submit">Cancel</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($resets): ?>
<div class="panel" style="margin-top:18px">
  <h2>Password reset links</h2>
  <p class="muted">A link works once and expires 24 hours after it was made. Send it to the person privately.
    &ldquo;Asked for it&rdquo; means they used the Forgotten password form themselves.</p>
  <div class="inv-list">
    <?php foreach ($resets as $r):
      $url = base_url() . '/reset.php?token=' . $r['token'];
      $first = trim((string)$r['name']) !== '' ? explode(' ', trim($r['name']))[0] : 'there';
      $rsub = 'Your password for The Battles Legacy';
      $rbod = "Hello $first,\n\n"
            . "Here is a link to set a new password for the family website:\n\n"
            . $url . "\n\n"
            . "It works once and stops working " . PWRESET_HOURS . " hours after it was made.\n\n"
            . (trim((string)$me['name']) ?: 'William') . "\n";
    ?>
      <div class="inv-row">
        <div class="inv-who">
          <b><?= e($r['name'] ?: $r['email']) ?></b>
          <?php if ($r['email']): ?><span class="inv-mail"><?= e($r['email']) ?></span><?php endif; ?>
        </div>
        <div class="inv-state">
          <?php if ($r['source'] === 'self'): ?>
            <span class="inv-dot <?= $r['emailed'] ? 'ok' : 'bad' ?>"></span>They asked for it
            &middot; <?= $r['emailed'] ? 'handed to the mail server' : 'the email didn\'t go' ?>
          <?php else: ?>
            <span class="inv-dot none"></span>You made this one
          <?php endif; ?>
        </div>
        <div class="inv-link">
          <input type="text" readonly value="<?= e($url) ?>" onclick="this.select()" id="pwr<?= (int)$r['id'] ?>">
          <button type="button" class="btn2" data-copy="pwr<?= (int)$r['id'] ?>">Copy link</button>
        </div>
        <div class="inv-acts">
          <?php if (trim((string)$r['email']) !== ''): ?>
            <a class="btn gold" href="<?= e(mailto_link($r['email'], $rsub, $rbod)) ?>">&#9993; Send it myself</a>
          <?php endif; ?>
          <form method="post" style="margin:0;display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="pwcancel"><input type="hidden" name="uid" value="<?= (int)$r['user_id'] ?>">
            <button class="inv-x" type="submit">Cancel</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:18px">
  <h2>Members (<?= count($users) ?>)</h2>
  <p class="muted">Forgotten a password? Press <b>Reset link</b> beside their name and send them the link that appears above.</p>
  <table class="list">
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
    <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <form method="post" class="rename-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <input type="text" name="newname" value="<?= e($u['name']) ?>" aria-label="Name">
            <button type="submit" title="Save this name">Save</button>
          </form>
          <?= $u['id'] == $me['id'] ? '<span class="muted" style="font-size:12px">(you)</span>' : '' ?>
        </td>
        <td class="muted"><?= e($u['email']) ?></td>
        <td>
          <?php if ($u['id'] == $me['id']): ?><span class="pill admin"><?= e(ucfirst($u['role'])) ?></span>
          <?php else: ?>
          <form method="post" style="margin:0;display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <select name="newrole" onchange="this.form.submit()" style="padding:5px 8px;font-size:14px;width:auto">
              <?php foreach (['member','moderator','admin'] as $r): ?><option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
            </select>
          </form>
          <?php endif; ?>
        </td>
        <td><span class="pill <?= $u['status']==='active'?'approved':'pending' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <?php if ($u['status']==='active'): ?>
          <form method="post" style="margin:0;display:inline">
            <?= csrf_field() ?><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <button class="btn" name="action" value="pwreset" style="margin:0;padding:5px 10px;font-size:14px">Reset link</button>
          </form>
          <?php endif; ?>
          <?php if ($u['id'] != $me['id']): ?>
          <form method="post" style="margin:0;display:inline">
            <?= csrf_field() ?><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <?php if ($u['status']==='active'): ?><button class="btn" name="action" value="suspend" style="margin:0;padding:5px 10px;font-size:14px">Suspend</button>
            <?php else: ?><button class="btn" name="action" value="restore" style="margin:0;padding:5px 10px;font-size:14px">Restore</button><?php endif; ?>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
/* Copy a link to the clipboard. The modern call needs a secure context and
   permission, so when it isn't there we fall back to selecting the field and
   using the old execCommand, which works everywhere this site is opened. */
document.addEventListener('click', function (ev) {
  var t = ev.target;
  if (!t || typeof t.closest !== 'function') return;
  var b = t.closest('[data-copy]');
  if (!b) return;
  var f = document.getElementById(b.getAttribute('data-copy'));
  if (!f) return;
  var done = function () {
    var was = b.textContent;
    b.textContent = 'Copied';
    b.classList.add('is-copied');
    setTimeout(function () { b.textContent = was; b.classList.remove('is-copied'); }, 1600);
  };
  f.focus(); f.select(); f.setSelectionRange(0, 99999);
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(f.value).then(done, function () { try { document.execCommand('copy'); done(); } catch (e) {} });
  } else {
    try { document.execCommand('copy'); done(); } catch (e) {}
  }
});
</script>
<?php page_foot();
