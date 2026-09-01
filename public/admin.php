<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/install.php';
require_once __DIR__ . '/../src/pwreset.php';
require_once __DIR__ . '/../src/access_data.php';
require_once __DIR__ . '/../src/invites.php';
require_once __DIR__ . '/../src/people_pick.php';
require_once __DIR__ . '/../src/notes.php';
require_role('admin');
$me = current_user();
/* Keeps the queue moving without a cron job. Sends at most one, and only when
   both the gap and the daily cap allow it; otherwise it costs a COUNT and a MAX. */
invite_drip_tick();
note_tick();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act  = $_POST['action'] ?? '';
    /* Which row to come back to. Sixty invitations is a long page on a phone,
       and "it's in the list below" is no help if the list below is a thousand
       pixels of scrolling. */
    $goto = '';
    if ($act === 'invite') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = $_POST['role'] ?? 'member';
        /* Which person in the tree this is. The name box fills it in when he
           picks a suggestion; if he typed the name out in full instead, the
           name itself is enough — but only when it can mean one person, which
           pp_match() is careful about. */
        $pid = trim($_POST['pid'] ?? '');
        $per = pp_person($pid);
        if (!$per && $name !== '') { $per = pp_match($name); $pid = $per ? $per['p'] : ''; }
        if (!$per) $pid = '';
        if ($per) $name = $per['n'];               // the tree's spelling, not the typing
        if ($name === '' && $email === '') {
            flash('Put in a name, an email address, or both.');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('That doesn\'t look like an email address: ' . $email);
        } elseif ($was = invite_existing($email)) {
            if ($was === 'member') flash($email . ' is already a member.');
            else {
                $old = invite_pending_for($email);
                flash($email . ' is already holding an invitation — it\'s in the list below, and nothing has been changed.');
                if ($old) $goto = '#inv-' . (int)$old['id'];
            }
        } elseif ($per && $per['s'] !== '') {
            if ($per['s'] === 'member') flash($per['n'] . ' is already a member.');
            else {
                /* This is the case William hit: he retyped somebody with their
                   address corrected, and the page said "already invited" and
                   threw the correction away without ever showing him what the
                   old address was. Say it, and point at the box that fixes it. */
                $old = invite_pending_for_pid($pid ?: ($per['p'] ?? ''));
                $addr = $old ? trim((string)$old['email']) : '';
                if ($addr !== '' && $email !== '' && $email !== strtolower($addr)) {
                    flash($per['n'] . ' already has an invitation, and it is addressed to ' . $addr . '.');
                    flash('Nothing has been changed. If ' . $addr . ' is the wrong address, open "Change the address"'
                        . ' on their row below and put ' . $email . ' in — it keeps the same invitation.');
                } else {
                    flash($per['n'] . ' is already holding an invitation'
                        . ($addr !== '' ? ', addressed to ' . $addr : '') . ' — it\'s in the list below.');
                }
                if ($old) $goto = '#inv-' . (int)$old['id'];
            }
        } else {
            list($token, $url) = invite_create($name, $email, $role, $me['id'], $pid);
            $who = $name ?: $email;
            if ($per) {
                $bits = array_filter([$per['y'], $per['r']], 'strlen');
                flash($per['n'] . ' matched to the family tree'
                      . ($bits ? ' — ' . implode(', ', $bits) : '') . '.');
            }
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
        $made = 0; $mailed = 0; $skipped = []; $bad = []; $matched = 0; $unknown = [];
        foreach ($lines as $line) {
            $p = invite_parse_line($line);
            if (!$p) continue;
            if ($p['email'] !== '' && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) { $bad[] = trim($line); continue; }
            if ($p['email'] !== '' && ($was = invite_existing($p['email']))) { $skipped[] = ($p['name'] ?: $p['email']) . ' (already ' . $was . ')'; continue; }
            /* Same tree lookup as the single form, applied line by line — a
               pasted list is exactly where a misspelling slips through, and
               it is worth saying which names the tree doesn't recognise. */
            $per = $p['name'] !== '' ? pp_match($p['name']) : null;
            if ($per) { $p['name'] = $per['n']; $matched++; }
            elseif ($p['name'] !== '') $unknown[] = $p['name'];
            list($token, $url) = invite_create($p['name'], $p['email'], $role, $me['id'], $per ? $per['p'] : '');
            $made++;
            if ($send && $p['email'] !== '') {
                $inv = one("SELECT * FROM invites WHERE token=?", [$token]);
                /* Queued, not sent from inside this loop. Sending here is
                   literally what put 49 messages on the wire inside 14 minutes. */
                if ($inv) { invite_queue_add([$inv['id']]); $mailed++; }
            }
        }
        if (!$made && !$skipped && !$bad) flash('Nothing to read in that box.');
        else {
            $msg = $made . ' invitation' . ($made === 1 ? '' : 's') . ' made';
            if ($send) $msg .= ', ' . $mailed . ' put in the queue to go out a few a day';
            $msg .= '. They are all listed below with a send button each.';
            flash($msg);
            if ($matched) flash($matched . ' of them matched a name in the family tree, spelling and all.');
            if ($unknown) flash('Not in the tree — worth checking the spelling: '
                . implode('; ', array_slice($unknown, 0, 12))
                . (count($unknown) > 12 ? ' …and ' . (count($unknown) - 12) . ' more' : '')
                . '. The invitations were still made.');
            if ($skipped) flash('Left alone: ' . implode('; ', array_slice($skipped, 0, 12)) . (count($skipped) > 12 ? ' …and ' . (count($skipped) - 12) . ' more' : ''));
            if ($bad) flash('Couldn\'t read: ' . implode('; ', array_slice($bad, 0, 8)));
        }
    } elseif ($act === 'queue_unopened') {
        $n = invite_queue_add(null);
        flash($n ? $n . ' invitation' . ($n === 1 ? '' : 's') . ' put in the queue. They will go out a few a day, '
                 . 'minutes apart, on their own — you do not have to keep this page open.'
                 : 'There was nothing to add: every unopened invitation is already in the queue.');
        $goto = '#drip';
    } elseif ($act === 'queue_send_next') {
        list($sent, $why) = invite_drip_release($me);
        flash($why);
        $goto = '#drip';
    } elseif ($act === 'queue_clear') {
        invite_queue_clear();
        flash('The queue is empty. Nothing further will go out on its own.');
        $goto = '#drip';
    } elseif ($act === 'invite_send') {
        $inv = invite_by_id($_POST['iid'] ?? 0);
        if (!$inv) flash('That invitation is no longer waiting.');
        elseif (trim((string)$inv['email']) === '') flash('There\'s no email address on that invitation — use "Send it myself".');
        else flash(invite_mail($inv, $me)
            ? 'Sent again to ' . $inv['email'] . '. If it still doesn\'t arrive, use "Send it myself".'
            : 'The mail server wouldn\'t take it. Use "Send it myself".');
    } elseif ($act === 'invite_edit') {
        /* Correcting a mistyped address. Same invitation, same person — only
           where it gets posted changes. */
        $iid = (int)($_POST['iid'] ?? 0);
        $r   = invite_update($iid, $_POST['email'] ?? '');
        if (!$r['ok']) { flash($r['msg']); $goto = '#inv-' . $iid; }
        elseif (!$r['changed']) { flash('That is already the address on this invitation.'); $goto = '#inv-' . $iid; }
        else {
            $inv = $r['inv'];
            $who = trim((string)$inv['name']) ?: $r['email'];
            flash('Address changed for ' . $who . ' — ' . $r['old'] . ' is now ' . $r['email'] . '.');
            if ($r['reissued'])
                flash('The old link has been replaced, because it had already been emailed to ' . $r['old']
                    . ' and whoever owns that address could have used it. Only the new link works now.');
            if (!empty($_POST['and_send']))
                flash(invite_mail($inv, $me)
                    ? 'Handed to the mail server for ' . $r['email'] . '. If nothing arrives in ten minutes, check their spam folder — or use "Send it myself".'
                    : 'The mail server wouldn\'t take it. Use "Send it myself".');
            else
                flash('Nothing has been sent to the new address yet — use one of the send buttons on their row.');
            $goto = '#inv-' . $iid;
        }
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
    header('Location: admin.php' . $goto); exit;
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

<?php /* The queue panel below only appears when somebody is waiting in it, so on a
         quiet day there was nothing on this page to say the family can now put
         names forward at all. This line is always here. */ ?>
<p class="muted" style="margin-top:-6px">Family can now put names forward themselves &mdash; there is an
  <a href="invite_family.php">Invite Family</a> link in the menu for everyone who is signed in. Nothing
  they send goes out on its own; every name arrives on this page for you to approve first.</p>

<?php if ($reqNew): ?>
<div class="panel arq" style="margin-top:20px;border-left:3px solid var(--gold)">
  <h2>People waiting to be let in (<?= count($reqNew) ?>)</h2>
  <p class="muted">Two roads lead here: somebody found the site and asked, or a signed-in relative put
    their name forward. Nobody gets in until you say so &mdash; approving makes them an invitation link
    for you to send, and they choose their own password from it.</p>
  <?php foreach ($reqNew as $r): $hits = ar_tree_matches($r['name']); $byMember = ar_from_member($r); ?>
    <div class="arq-card">
      <div class="arq-who">
        <b><?= e($r['name']) ?></b>
        <span><?= e($r['email']) ?><?= $r['phone'] ? ' &middot; ' . e($r['phone']) : '' ?></span>
        <i><?= e(date('j M Y', strtotime($r['created_at']))) ?></i>
      </div>
      <div class="arq-body">
        <?php /* Who vouched matters more than anything else on this card: a name
                 put forward by a relative who is signed in already has one family
                 member standing behind it, which a stranger's request does not. */ ?>
        <?php if ($byMember): ?>
          <p class="arq-src"><b>Put forward by <?= e($r['referred_by']) ?></b>, signed in as a family member.</p>
        <?php else: ?>
          <p class="arq-src arq-src-self">Asked to join through the public form &mdash; nobody here has vouched for them.</p>
        <?php endif; ?>
        <p><b>Related to:</b> <?= e($r['relation']) ?></p>
        <?php if (!$byMember && trim($r['referred_by']) !== ''): ?><p><b>Heard about it from:</b> <?= e($r['referred_by']) ?></p><?php endif; ?>
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
  <p class="muted">Start typing a name and the tree suggests who you mean, with their years and their parents
    underneath so two cousins of the same name don&rsquo;t get mixed up. Pick one and the spelling comes
    straight off their page. A name the tree doesn&rsquo;t know still gets invited &mdash; it just says so, in
    case it&rsquo;s a typo.</p>
  <p class="muted">Put in an email address and the website will email the invitation. Whether it does or
    doesn&rsquo;t get through, the link also appears below with a <b>Send it myself</b> button that opens your own
    email &mdash; that one always arrives, because it comes from you rather than from a website.</p>
  <form method="post" class="inv-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="invite">
    <div class="pp-wrap">
      <label>Name</label>
      <input type="text" name="name" id="inv-name" placeholder="Start typing a name — e.g. Annie"
             autocomplete="off" spellcheck="false">
      <input type="hidden" name="pid" id="inv-pid" value="">
      <div class="pp-list" id="inv-list" role="listbox" hidden></div>
    </div>
    <div><label>Email</label><input type="email" name="email" placeholder="dianne@example.com"></div>
    <div><label>Role</label><select name="role"><option value="member">Member</option><option value="moderator">Moderator</option><option value="admin">Admin</option></select></div>
    <button class="btn gold" style="margin:0">Invite</button>
    <!-- full width under the row, so it can grow to two lines without shoving
         the Email box out of line with everything else -->
    <div class="pp-note" id="inv-note"></div>
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
        <label class="inv-check"><input type="checkbox" name="bulk_send" value="1" checked> Put them in the sending queue</label>
        <button class="btn gold" style="margin:0">Make the invitations</button>
      </div>
      <p class="muted" style="margin:10px 0 0">They go into the queue below rather than all leaving at once.
        The last time a list this size went out in one go, nearly all of it was filed as spam.</p>
    </form>
  </details>

  <?php
    /* The drip queue. */
    $qRows  = invite_queued();
    $qCount = count($qRows);
    list($dOk, $dWhy, $dWait) = drip_ready();
    $sentDay = drip_sent_last_day();
  ?>
  <div class="panel" id="drip" style="margin-top:18px">
    <h2 style="margin:0 0 6px">The sending queue</h2>
    <p class="muted" style="margin:0 0 12px">
      Invitations in here go out on their own, <b>at most <?= (int)drip_per_day() ?> a day</b> and never closer together
      than <b><?= (int)drip_gap_minutes() ?> minutes</b>. Counted from your own invitations on 1 September:
      the 49 that went out between 22:04 and 22:19 on 17 August produced <b>2</b> accounts &mdash; 4%. The ones sent
      one, two or three at a time on other days produced <b>7 out of 9</b> &mdash; 78%. Same words, same family,
      same addresses. The speed is the only thing that was different.</p>

    <p style="margin:0 0 10px">
      <b><?= $qCount ?></b> waiting &middot; <b><?= $sentDay ?></b> of <?= (int)drip_per_day() ?> sent in the last 24 hours
      <?php if ($qCount && !$dOk): ?>
        &middot; <span class="muted"><?= $dWait > 0 ? 'next one in about ' . max(1, (int)ceil($dWait / 60)) . ' min' : e($dWhy) ?></span>
      <?php elseif ($qCount && $dOk): ?>
        &middot; <span class="muted">the next one can go now</span>
      <?php endif; ?>
    </p>

    <?php if ($qRows): ?>
      <p class="muted" style="margin:0 0 10px">Next up:
        <?= e(implode(', ', array_map(function ($r) { return trim((string)$r['name']) ?: $r['email']; }, array_slice($qRows, 0, 5)))) ?><?= $qCount > 5 ? ', and ' . ($qCount - 5) . ' more' : '' ?>.</p>
    <?php endif; ?>

    <div class="inv-bulkrow" style="gap:10px">
      <form method="post" style="margin:0"><?= csrf_field() ?>
        <input type="hidden" name="action" value="queue_unopened">
        <button class="btn gold" style="margin:0">Queue everyone who never opened theirs</button>
      </form>
      <?php if ($qCount): ?>
        <form method="post" style="margin:0"><?= csrf_field() ?>
          <input type="hidden" name="action" value="queue_send_next">
          <button class="btn" style="margin:0"<?= $dOk ? '' : ' disabled title="' . e($dWait > 0 ? 'Too soon after the last one' : $dWhy) . '"' ?>>Send the next one now</button>
        </form>
        <form method="post" style="margin:0" onsubmit="return confirm('Empty the queue? Nothing more will go out on its own. No invitation is deleted.')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="queue_clear">
          <button class="btn danger" style="margin:0">Empty the queue</button>
        </form>
      <?php endif; ?>
    </div>
    <p class="muted" style="margin:10px 0 0">Nothing is deleted by any of these buttons &mdash; emptying the queue
      only stops them being sent automatically. Every invitation keeps its own send buttons in the list below.</p>
  </div>

  <?php
    /* Getting people in is only half of it. The other half is giving the ones
       who are already in a reason to come back, which nothing on the site
       currently does. */
    $noteProg = note_progress((int)(($lastNote = note_last_sent()) ? $lastNote['id'] : 0));
  ?>
  <div class="panel" id="note" style="margin-top:18px">
    <h2 style="margin:0 0 6px">The monthly note</h2>
    <p class="muted" style="margin:0 0 12px">A short note to the people who already have accounts, telling them what
      has appeared since the last one &mdash; whose birthday is coming up, which photographs have gone on, whose story
      somebody has written down. It is written for you out of what has actually changed; you edit it and press send.</p>
    <p style="margin:0 0 10px">
      <b><?= count(note_recipients()) ?></b> would receive it
      <?php if ($lastNote): ?>&middot; last one <?= e(date('j M Y', strtotime($lastNote['created_at']))) ?>,
        <b><?= (int)$noteProg['sent'] ?></b> sent<?php if ($noteProg['waiting']): ?>,
        <b><?= (int)$noteProg['waiting'] ?></b> still going out<?php endif; ?><?php
        /* A refused copy is the one thing on this line worth him seeing, so it
           does not get left off just because the sentence reads better. */
        if ($noteProg['failed']): ?>, <b><?= (int)$noteProg['failed'] ?></b> the mail server refused<?php endif; ?>
      <?php else: ?>&middot; none written yet<?php endif; ?>
    </p>
    <p style="margin:0"><a class="btn gold" href="family_note.php" style="margin:0">Write this month&rsquo;s note</a></p>
  </div>

  <script>
  /* Name suggestions on the invitation form.
     The whole tree is written into this page rather than fetched, because this
     page is admins only and living relatives' real names have no business on a
     public endpoint. It is about 60KB and it means the box answers instantly.

     The field itself is an ordinary text input and stays one. Whatever is typed
     is what gets submitted, suggestions or no suggestions — if this script never
     runs, the form behaves exactly as it did before. */
  (function(){
    var PEOPLE = <?= json_encode(pp_people(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var box  = document.getElementById('inv-name');
    var pid  = document.getElementById('inv-pid');
    var list = document.getElementById('inv-list');
    var note = document.getElementById('inv-note');
    if (!box || !PEOPLE || !PEOPLE.length) return;

    /* Same flattening the server does, so "D`Vonte" and "D'Vonte" agree. */
    function key(s){
      s = (s||'').toLowerCase();
      try { s = s.normalize('NFD').replace(/[\u0300-\u036f]/g,''); } catch(e){}
      return s.replace(/[^a-z0-9 ]+/g,'').replace(/\s+/g,' ').trim();
    }
    PEOPLE.forEach(function(p){ p._k = key(p.n); p._w = p._k.split(' '); });

    var byKey = {};
    PEOPLE.forEach(function(p){ (byKey[p._k] = byKey[p._k] || []).push(p); });

    function search(qs){
      var words = key(qs).split(' ').filter(Boolean);
      if (!words.length) return [];
      var hits = [];
      for (var i=0;i<PEOPLE.length && hits.length<400;i++){
        var p = PEOPLE[i], ok = true;
        for (var w=0; w<words.length; w++){
          var found = false;
          for (var j=0;j<p._w.length;j++) if (p._w[j].indexOf(words[w]) === 0) { found = true; break; }
          if (!found) { ok = false; break; }
        }
        if (ok) hits.push(p);
      }
      /* a name that starts with what was typed beats one that merely contains
         it, and somebody still with us beats somebody who isn't */
      var q0 = words.join(' ');
      hits.sort(function(a,b){
        var as = a._k.indexOf(q0) === 0 ? 0 : 1, bs = b._k.indexOf(q0) === 0 ? 0 : 1;
        if (as !== bs) return as - bs;
        if (a.l !== b.l) return b.l - a.l;
        return a.n.localeCompare(b.n);
      });
      return hits.slice(0, 8);
    }

    var open = [], cur = -1;

    function esc(s){ return String(s).replace(/[&<>"]/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

    function draw(hits){
      open = hits; cur = -1;
      if (!hits.length) { list.hidden = true; list.innerHTML = ''; return; }
      list.innerHTML = hits.map(function(p,i){
        var tag = p.s === 'member'  ? '<span class="pp-tag">already a member</span>'
                : p.s === 'invited' ? '<span class="pp-tag">already invited</span>' : '';
        var gone = p.l ? '' : '<span class="pp-tag gone">no longer with us</span>';
        var meta = [p.y, p.r].filter(Boolean).join(' · ');
        return '<div class="pp-opt" role="option" data-i="'+i+'">'
             + '<b>'+esc(p.n)+'</b>'+tag+gone
             + (meta ? '<span class="pp-meta">'+esc(meta)+'</span>' : '')
             + '</div>';
      }).join('');
      list.hidden = false;
    }

    function highlight(){
      var opts = list.querySelectorAll('.pp-opt');
      for (var i=0;i<opts.length;i++) opts[i].classList.toggle('on', i === cur);
    }

    /* Says what the site currently believes about the name in the box. It is
       recomputed on every keystroke — the moment the text stops matching the
       person who was picked, the link to that person is dropped. A picked id
       that outlives the text that earned it is how an invitation ends up
       quietly attached to the wrong cousin. */
    /* Nobody writes a middle name on an invitation, so a name that fits inside
       exactly one person's full name counts as that person — the same rule the
       server applies when the form is submitted, and it has to be the same or
       this line would tell him the tree doesn't know a name it is about to
       match. Three Keith Battles and it stays silent, as it should. */
    function subset(k){
      var want = k.split(' ').filter(Boolean);
      if (want.length < 2) return [];
      var sur = want[want.length - 1], out = [];
      for (var i=0;i<PEOPLE.length;i++){
        var have = PEOPLE[i]._w;
        if (have.indexOf(sur) < 0) continue;
        var all = true;
        for (var j=0;j<want.length;j++) if (have.indexOf(want[j]) < 0) { all = false; break; }
        if (all) out.push(PEOPLE[i]);
      }
      return out;
    }

    function found(p, alsoKnownAs){
      pid.value = p.p;
      var meta = [p.y, p.r].filter(Boolean).join(' · ');
      note.className = 'pp-note ok';
      note.innerHTML = '&#10003; In the family tree'
        + (alsoKnownAs ? ' as <b>' + esc(p.n) + '</b>' : '')
        + (meta ? ' — ' + esc(meta) : '')
        + (p.s ? ' <b>(already ' + p.s + ')</b>' : '');
    }

    function reflect(){
      var v = box.value.trim(), k = key(v);
      var exact = byKey[k];
      var near  = exact ? null : subset(k);
      if (exact && exact.length === 1) {
        found(exact[0], false);
      } else if (near && near.length === 1) {
        found(near[0], true);
      } else if (near && near.length > 1) {
        pid.value = '';
        note.className = 'pp-note warn';
        note.textContent = near.length + ' people in the tree could be ' + v
          + ' — pick the right one from the list so the invitation knows which.';
      } else if (exact && exact.length > 1) {
        pid.value = '';
        note.className = 'pp-note warn';
        note.textContent = 'There are ' + exact.length + ' people called ' + v
          + ' in the tree — pick the right one from the list so the invitation knows which.';
      } else if (open.length) {
        /* Still mid-word with names on offer. Warning him the tree doesn't know
           "And" while it is busy suggesting four Battles girls whose names all
           start that way would be nonsense. */
        pid.value = '';
        note.className = 'pp-note';
        note.textContent = 'Pick one from the list to take the spelling straight off their page.';
      } else if (k.length >= 3) {
        pid.value = '';
        note.className = 'pp-note warn';
        note.textContent = 'No one of that name in the tree. Fine if they married in — otherwise check the spelling.';
      } else {
        pid.value = '';
        note.className = 'pp-note';
        note.textContent = '';
      }
    }

    function choose(i){
      var p = open[i];
      if (!p) return;
      box.value = p.n;                 // the tree's spelling wins
      list.hidden = true; open = []; cur = -1;
      reflect();
      box.focus();
    }

    box.addEventListener('input', function(){ draw(search(box.value)); reflect(); });
    box.addEventListener('focus', function(){ if (box.value.trim()) draw(search(box.value)); });
    /* Once he has moved on, "pick one from the list" is no longer useful advice
       — there is no list any more — so the note falls back to saying plainly
       whether the name he settled on is one the tree knows. */
    box.addEventListener('blur', function(){
      setTimeout(function(){ list.hidden = true; open = []; cur = -1; reflect(); }, 180);
    });
    box.addEventListener('keydown', function(e){
      if (list.hidden || !open.length) return;
      if (e.key === 'ArrowDown') { cur = Math.min(cur + 1, open.length - 1); highlight(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { cur = Math.max(cur - 1, 0); highlight(); e.preventDefault(); }
      else if (e.key === 'Enter' && cur >= 0) { choose(cur); e.preventDefault(); }
      else if (e.key === 'Escape') { list.hidden = true; }
    });
    list.addEventListener('mousedown', function(e){
      var opt = e.target.closest ? e.target.closest('.pp-opt') : null;
      if (opt) { e.preventDefault(); choose(+opt.getAttribute('data-i')); }
    });
  })();
  </script>
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
  <?php
    /* "Emailed" was the only thing this page could report, and it says nothing
       about whether a human being ever saw it. Opened does. */
    $prog = invite_progress();
    /* A wrong address is the one failure that leaves no trace on this page:
       the mail server takes it, we report "handed to the mail server", and it
       bounces somewhere William never sees. So say up front how many look
       worth a second look. */
    $flagBad = 0; $flagWatch = 0;
    foreach ($invites as $iv) {
        $ad = trim((string)$iv['email']);
        if ($ad === '') continue;
        $c = invite_address_check($ad);
        if ($c['level'] === 'bad') $flagBad++;
        elseif ($c['level'] === 'watch') $flagWatch++;
    }
  ?>
  <p class="muted">These people have a link but haven&rsquo;t signed up yet.
    <b>Text it / Messenger</b> hands the whole message to your phone &mdash; pick the person,
    press send, no email address needed. <b>Send it myself</b> opens your own
    email with the same message already written. Links last <?= INVITE_DAYS ?> days.</p>
  <?php if ($prog['total']): ?>
  <p class="inv-sum"><b><?= (int)$prog['total'] ?></b> invitations sent &middot;
    <b><?= (int)$prog['joined'] ?></b> turned into an account &middot;
    <b><?= (int)$prog['opened'] ?></b> had the link opened &middot;
    <b><?= (int)$prog['unopened'] ?></b> still never opened.
    <?php if ($prog['unopened'] > $prog['opened']): ?>
      <span class="inv-sum-note">When most are &ldquo;never opened&rdquo;, the message is not being
      read &mdash; not the sign-up form. Worth sending those yourself from your own email.</span>
    <?php endif; ?></p>
  <?php endif; ?>
  <?php if ($flagBad || $flagWatch): ?>
  <p class="inv-audit">&#128269; <b><?= (int)($flagBad + $flagWatch) ?></b>
    <?= ($flagBad + $flagWatch) === 1 ? 'address is' : 'addresses are' ?> worth checking
    <?php if ($flagBad): ?>&mdash; <b><?= (int)$flagBad ?></b>
      <?= $flagBad === 1 ? 'has' : 'have' ?> no mail server at all, so nothing sent there can arrive<?php endif; ?><?php
      if ($flagWatch): ?><?= $flagBad ? ', and' : ' &mdash;' ?> <b><?= (int)$flagWatch ?></b>
      <?= $flagWatch === 1 ? 'is' : 'are' ?> a letter away from a commoner address<?php endif; ?>.
    They are marked below, with the box to correct them already open.</p>
  <?php endif; ?>
  <div class="inv-list">
    <?php foreach ($invites as $inv):
      $url  = invite_url($inv['token']);
      $m    = invite_message($inv, $url, $me);
      $shr  = invite_share_text($inv, $url, $me);
      $mail = trim((string)$inv['email']);
      $exp  = $inv['expires_at'] ? strtotime($inv['expires_at']) : 0;
      $days = $exp ? (int)ceil(($exp - time()) / 86400) : null;
      $chk  = $mail !== '' ? invite_address_check($mail) : ['level' => 'ok', 'note' => ''];
    ?>
      <div class="inv-row<?= $chk['level'] !== 'ok' ? ' inv-flag' : '' ?>" id="inv-<?= (int)$inv['id'] ?>">
        <div class="inv-who">
          <b><?= e($inv['name'] ?: $mail ?: '—') ?></b>
          <span class="pill admin"><?= e(ucfirst($inv['role'])) ?></span>
          <?php
            /* Which person in the tree this invitation is for. Worth showing:
               an invitation with no match is either somebody who married in or
               a name that was mistyped, and only he can tell which. */
            $tp = pp_person($inv['pid'] ?? '');
            if ($tp):
              $tm = array_filter([$tp['y'], $tp['r']], 'strlen'); ?>
            <span class="inv-tree" title="<?= e($tm ? implode(' · ', $tm) : '') ?>">&#127795; in the family tree</span>
          <?php elseif (trim((string)$inv['name']) !== ''): ?>
            <span class="inv-tree off">not matched to the tree</span>
          <?php endif; ?>
          <?php if ($mail): ?><span class="inv-mail<?= $chk['level'] !== 'ok' ? ' bad' : '' ?>"><?= e($mail) ?></span><?php endif; ?>
        </div>
        <?php if ($chk['note'] !== ''): ?>
          <div class="inv-warn<?= $chk['level'] === 'bad' ? ' hard' : '' ?>">
            <?= $chk['level'] === 'bad' ? '&#9888;' : '&#128269;' ?> <?= e($chk['note']) ?>
          </div>
        <?php endif; ?>
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
          <?php if (!empty($inv['opened_at'])): ?>
            <span class="inv-open">&#128065; opened <?= e(date('j M, g:ia', strtotime($inv['opened_at']))) ?>
              &mdash; but stopped before finishing</span>
          <?php elseif (!empty($inv['emailed_at'])): ?>
            <span class="inv-open off">link never opened</span>
          <?php endif; ?>
          <?php if ($days !== null): ?><span class="inv-exp"><?= $days > 0 ? 'expires in ' . $days . ' day' . ($days === 1 ? '' : 's') : 'expired' ?></span><?php endif; ?>
        </div>
        <div class="inv-link">
          <input type="text" readonly value="<?= e($url) ?>" onclick="this.select()" id="inv<?= (int)$inv['id'] ?>">
          <button type="button" class="btn2" data-copy="inv<?= (int)$inv['id'] ?>">Copy link</button>
        </div>
        <div class="inv-acts">
          <?php /* Needs no email address at all, which is the point: most of the
                   addresses on this page are old ones. On a phone this opens the
                   share sheet, so it is tap, pick the person, send. On a desktop
                   browser there is no share sheet, so the same button copies the
                   whole message instead and says so. */ ?>
          <button type="button" class="btn gold sharebtn" data-share="<?= e($shr) ?>"
                  data-title="<?= e($m['subject']) ?>">&#128172; Text it / Messenger</button>
          <?php if ($mail): ?>
            <a class="btn2" href="<?= e(mailto_link($mail, $m['subject'], $m['body'])) ?>">&#9993; Send it myself</a>
            <form method="post" style="margin:0;display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="invite_send"><input type="hidden" name="iid" value="<?= (int)$inv['id'] ?>">
              <button class="btn2" type="submit"><?= empty($inv['emailed_at']) ? 'Let the website email it' : 'Email it again' ?></button>
            </form>
          <?php endif; ?>
          <form method="post" style="margin:0;display:inline" onsubmit="return confirm('Cancel this invitation? The link will stop working.')">
            <?= csrf_field() ?><input type="hidden" name="action" value="invite_delete"><input type="hidden" name="iid" value="<?= (int)$inv['id'] ?>">
            <button class="inv-x" type="submit">Cancel</button>
          </form>
        </div>
        <?php /* Typing somebody in again with the address corrected used to be
                 refused as a duplicate, so the only way to fix a typo was to
                 cancel and start over. This changes it in place. */ ?>
        <details class="inv-fix"<?= $chk['level'] !== 'ok' ? ' open' : '' ?>>
          <summary><?= $mail === '' ? 'Add an email address' : 'Change the address' ?></summary>
          <form method="post" class="inv-fix-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="invite_edit">
            <input type="hidden" name="iid" value="<?= (int)$inv['id'] ?>">
            <input type="email" name="email" value="<?= e($mail) ?>" placeholder="their email address"
                   autocapitalize="off" autocorrect="off" spellcheck="false" required>
            <button class="btn2" type="submit" name="save" value="1">Save</button>
            <button class="btn gold" type="submit" name="and_send" value="1">Save and email it</button>
          </form>
          <p class="muted inv-fix-note">Same invitation, same person &mdash; only the address changes.
            <?php if (!empty($inv['emailed_at'])): ?>Because this one has already been emailed, changing the
            address also replaces the link, so the copy that went to the wrong mailbox stops working.<?php endif; ?></p>
        </details>
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
      $rshr = 'Hi ' . $first . ", it's " . (trim((string)$me['name']) ?: 'William') . '. '
            . "Here is your link to set a new password for the family website:\n"
            . $url . "\n\n"
            . 'It works once and runs out after ' . PWRESET_HOURS . ' hours.';
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
          <button type="button" class="btn gold sharebtn" data-share="<?= e($rshr) ?>"
                  data-title="<?= e($rsub) ?>">&#128172; Text it / Messenger</button>
          <?php if (trim((string)$r['email']) !== ''): ?>
            <a class="btn2" href="<?= e(mailto_link($r['email'], $rsub, $rbod)) ?>">&#9993; Send it myself</a>
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
  <p class="muted">Forgotten a password? Press <b>Reset link</b> beside their name and send them the link that appears above.
    <?php /* He looked for his own password on this page and it was not here.
             It is not a Members-page job, but this is where he looked. */ ?>
    <br>Changing <b>your own</b> password is on <a href="account.php">your account page</a> &mdash;
    or click your name in the menu at the top.</p>
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
<?php /* shared with feedback_manage.php - one copy, so the two cannot drift */ ?>
<script src="assets/share.js"></script>
<?php page_foot();
