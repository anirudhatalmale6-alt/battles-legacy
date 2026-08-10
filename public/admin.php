<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/install.php';
require_role('admin');
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'invite') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['member','moderator','admin'], true) ? $_POST['role'] : 'member';
        $token = bin2hex(random_bytes(20));
        q("INSERT INTO invites (token,name,email,role,invited_by,expires_at) VALUES (?,?,?,?,?, ?)",
          [$token, $name, $email, $role, $me['id'], date('Y-m-d H:i:s', time() + 30*86400)]);
        flash('Invitation created — copy the link below and send it to ' . ($name ?: $email) . '.');
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
$invites = all("SELECT i.*, u.name AS by_name FROM invites i LEFT JOIN users u ON u.id=i.invited_by WHERE i.used_at IS NULL ORDER BY i.id DESC");

page_head('Members');
?>
<h1>Family members</h1>
<p class="lede">Invite family, and set who is an Admin, Moderator or Member. Invitation links are private — send them directly to the person.</p>

<div class="panel" style="margin-top:20px">
  <h2>Invite a family member</h2>
  <form method="post" style="display:grid;grid-template-columns:1fr 1fr auto auto;gap:12px;align-items:end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="invite">
    <div><label>Name</label><input type="text" name="name" placeholder="e.g. Dianne Battles"></div>
    <div><label>Email (optional)</label><input type="email" name="email"></div>
    <div><label>Role</label><select name="role"><option value="member">Member</option><option value="moderator">Moderator</option><option value="admin">Admin</option></select></div>
    <button class="btn gold" style="margin:0">Create invite</button>
  </form>
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
  <h2>Pending invitations</h2>
  <table class="list">
    <tr><th>Name</th><th>Role</th><th>Invitation link (copy &amp; send)</th></tr>
    <?php foreach ($invites as $inv): $url = base_url() . '/register.php?token=' . $inv['token']; ?>
      <tr>
        <td><?= e($inv['name'] ?: $inv['email'] ?: '—') ?></td>
        <td><span class="pill admin"><?= e(ucfirst($inv['role'])) ?></span></td>
        <td><input type="text" readonly value="<?= e($url) ?>" onclick="this.select()" style="font-size:13px"></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:18px">
  <h2>Members (<?= count($users) ?>)</h2>
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
        <td>
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
<?php page_foot();
