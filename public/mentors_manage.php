<?php
/** Editor screen for the three things behind the Enterprise page's action
 *  cards: who has written in, who is offering to mentor, and the resource links.
 *
 *  Deliberately a page of its own rather than three more tabs bolted onto
 *  enterprise_manage.php — that file is already 700 lines and five tabs, and
 *  the top menu is already twenty-one items. It is reached from the tab strip
 *  on the Enterprise editor, which is where somebody editing that page will
 *  look for it. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/enterprise_data.php';
require_once __DIR__ . '/../src/mentor_data.php';
require_role('admin');
ment_migrate();

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');
    $id  = (int)($_POST['id'] ?? 0);
    $tab = 'inbox';
    try {
        /* ---------- the inbox ---------- */
        if ($act === 'ask_done' && $id)      { ask_done($id, $me['id']); flash('Marked as dealt with.'); }
        elseif ($act === 'ask_reopen' && $id){ ask_reopen($id);          flash('Put back in the inbox.'); }
        elseif ($act === 'ask_delete' && $id){ ask_delete($id);          flash('Message deleted.'); }

        /* ---------- mentors ---------- */
        elseif ($act === 'men_save') {
            $tab = 'mentors';
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') { flash('A mentor listing needs a name.'); }
            else {
                $contact = array_key_exists((string)($_POST['contact'] ?? ''), ment_contact_opts()) ? (string)$_POST['contact'] : 'site';
                $status  = in_array($_POST['status'] ?? '', ['published','hidden','pending'], true) ? $_POST['status'] : 'published';
                $cur     = $id ? ment_get($id) : null;
                list($photo, $perr) = ent_store_photo('photo');
                if ($perr) flash($perr);
                if (!$photo) $photo = $cur ? (string)$cur['photo'] : '';
                if (!empty($_POST['remove_photo'])) $photo = '';
                $f = [$name, trim((string)($_POST['role_line'] ?? '')), trim((string)($_POST['topics'] ?? '')),
                      trim((string)($_POST['about'] ?? '')), trim((string)($_POST['location'] ?? '')),
                      $contact, trim((string)($_POST['email'] ?? '')), trim((string)($_POST['phone'] ?? '')),
                      $photo, (int)($_POST['sort'] ?? 0), $status];
                if ($id) {
                    q("UPDATE enterprise_mentors SET name=?,role_line=?,topics=?,about=?,location=?,contact=?,email=?,phone=?,photo=?,sort=?,status=? WHERE id=?",
                      array_merge($f, [$id]));
                    flash('Mentor listing updated.');
                } else {
                    q("INSERT INTO enterprise_mentors (name,role_line,topics,about,location,contact,email,phone,photo,sort,status)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?)", $f);
                    flash('Mentor added — open Mentor Connect to see it.');
                }
            }
        }
        elseif ($act === 'men_approve' && $id) {
            $tab = 'mentors';
            q("UPDATE enterprise_mentors SET status='published' WHERE id=?", [$id]);
            flash('Approved — their card is live on Mentor Connect.');
        }
        elseif ($act === 'men_delete' && $id) {
            $tab = 'mentors';
            q("DELETE FROM enterprise_mentors WHERE id=?", [$id]);
            flash('Mentor listing removed.');
        }

        /* ---------- resources ---------- */
        elseif ($act === 'res_save') {
            $tab = 'resources';
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') { flash('A resource needs a title.'); }
            else {
                $cat    = in_array($_POST['category'] ?? '', res_categories(), true) ? $_POST['category'] : 'Other';
                $icon   = array_key_exists($_POST['icon'] ?? '', ent_fin_icons()) ? $_POST['icon'] : 'doc';
                $status = ($_POST['status'] ?? 'published') === 'hidden' ? 'hidden' : 'published';
                $f = [$title, trim((string)($_POST['blurb'] ?? '')), trim((string)($_POST['url'] ?? '')),
                      $cat, $icon, trim((string)($_POST['cost'] ?? '')), trim((string)($_POST['caution'] ?? '')),
                      (int)($_POST['sort'] ?? 0), $status];
                if ($id) {
                    q("UPDATE enterprise_resources SET title=?,blurb=?,url=?,category=?,icon=?,cost=?,caution=?,sort=?,status=? WHERE id=?",
                      array_merge($f, [$id]));
                    flash('Resource updated.');
                } else {
                    q("INSERT INTO enterprise_resources (title,blurb,url,category,icon,cost,caution,sort,status)
                       VALUES (?,?,?,?,?,?,?,?,?)", $f);
                    flash('Resource added.');
                }
            }
        }
        elseif ($act === 'res_delete' && $id) {
            $tab = 'resources';
            q("DELETE FROM enterprise_resources WHERE id=?", [$id]);
            flash('Resource removed.');
        }
    } catch (\Throwable $ex) {
        flash('Sorry — that could not be saved. Please try again.');
    }
    header('Location: mentors_manage.php?tab=' . $tab); exit;
}

$tab = in_array($_GET['tab'] ?? '', ['inbox','mentors','resources'], true) ? $_GET['tab'] : 'inbox';
$NEW  = ask_list('new');
$DONE = ask_list('done');
$MEN  = ment_list(true);
$RES  = res_list(true);
$PENDM = ment_pending_count();

function mm_opts($map, $sel) {
    $o = '';
    foreach ($map as $v => $lbl) $o .= '<option value="' . e($v) . '"' . ($sel === $v ? ' selected' : '') . '>' . e($lbl) . '</option>';
    return $o;
}
function mm_when($ts) {
    $t = strtotime((string)$ts);
    return $t ? date('j M Y, g:ia', $t) : '';
}

page_head('Mentors & Resources', ['body_class' => 'em']);
?>
<h1>Mentor Connect, Resources &amp; the inbox</h1>
<p class="lede">The four cards at the foot of the Enterprise page now lead somewhere. This is where
   the two that collect anything land: somebody asking for a mentor or offering to help, the family
   who have put their names down as mentors, and the resource links.</p>
<p style="margin:10px 0 4px">
  <a class="btn gold" href="mentors.php" target="_blank" rel="noopener">View Mentor Connect &#8599;</a>
  <a class="btn" href="resources.php" target="_blank" rel="noopener" style="margin-left:8px">View Resources &#8599;</a>
  <a class="btn" href="enterprise_manage.php" style="margin-left:8px">&larr; Enterprise editor</a></p>

<div class="em-tabs">
  <a href="?tab=inbox" class="<?= $tab==='inbox'?'on':'' ?><?= $NEW?' has-pend':'' ?>">Inbox<?= $NEW ? ' <span class="em-penddot">'.count($NEW).'</span>' : ' (0)' ?></a>
  <a href="?tab=mentors" class="<?= $tab==='mentors'?'on':'' ?><?= $PENDM?' has-pend':'' ?>">Mentors (<?= count($MEN) ?>)<?= $PENDM ? ' <span class="em-penddot">'.$PENDM.'</span>' : '' ?></a>
  <a href="?tab=resources" class="<?= $tab==='resources'?'on':'' ?>">Resources (<?= count($RES) ?>)</a>
</div>

<?php /* ============================================================ INBOX */ ?>
<?php if ($tab === 'inbox'): ?>
  <div class="panel em-add">
    <h2>Waiting for you</h2>
    <?php if (!$NEW): ?>
      <p class="lede" style="margin:0">Nothing waiting. When somebody asks for a mentor or offers to
         help, it appears here <em>and</em> you get an email &mdash; you do not have to keep checking
         this page.</p>
    <?php else: ?>
      <p class="lede" style="margin:0">Each of these was also emailed to you. Answer the person
         yourself, then mark it dealt with so the count goes back to zero.</p>
    <?php endif; ?>
  </div>

  <?php foreach ($NEW as $a): $named = !empty($a['mentor_id']) ? ment_get($a['mentor_id']) : null; ?>
    <div class="panel mm-ask">
      <div class="mm-askhead">
        <b><?= e($a['name']) ?></b>
        <span class="mm-kind <?= $a['kind'] === 'involved' ? 'k-help' : 'k-ask' ?>">
          <?= $a['kind'] === 'involved' ? 'Offering to help' : ($named ? 'Asking for ' . e($named['name']) : 'Looking for a mentor') ?>
        </span>
        <span class="muted"><?= e(mm_when($a['created_at'])) ?></span>
      </div>
      <p class="mm-contact">
        <?php if (trim((string)$a['email']) !== ''): ?>
          <a href="mailto:<?= e($a['email']) ?>?subject=<?= rawurlencode('The Battles Legacy') ?>"><?= e($a['email']) ?></a>
        <?php endif; ?>
        <?php if (trim((string)$a['phone']) !== ''): ?>
          &middot; <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $a['phone'])) ?>"><?= e($a['phone']) ?></a>
        <?php endif; ?>
      </p>
      <?php if (trim((string)$a['topic']) !== ''): ?><p class="mm-topic"><b>About:</b> <?= e($a['topic']) ?></p><?php endif; ?>
      <?php if (trim((string)$a['offers']) !== ''): ?><p class="mm-topic"><b>Offering:</b> <?= e($a['offers']) ?></p><?php endif; ?>
      <?php if (trim((string)$a['message']) !== ''): ?><p class="mm-msg"><?= nl2br(e($a['message'])) ?></p><?php endif; ?>
      <form method="post" class="mm-acts">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="btn gold" name="action" value="ask_done">Dealt with</button>
        <button class="btn danger" name="action" value="ask_delete"
                onclick="return confirm('Delete this message for good?')">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>

  <?php if ($DONE): ?>
    <div class="panel em-add" style="margin-top:26px">
      <h2>Already dealt with (<?= count($DONE) ?>)</h2>
      <table class="mm-table">
        <?php foreach ($DONE as $a): ?>
          <tr>
            <td><?= e($a['name']) ?></td>
            <td class="muted"><?= e($a['kind'] === 'involved' ? 'Offered help' : 'Asked for a mentor') ?></td>
            <td class="muted"><?= e(mm_when($a['created_at'])) ?></td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn small" name="action" value="ask_reopen">Reopen</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

<?php /* ========================================================== MENTORS */ ?>
<?php elseif ($tab === 'mentors'): ?>
  <div class="panel em-add">
    <h2>Add a mentor</h2>
    <p class="lede" style="margin:0 0 8px">Family can put their own names down from Mentor Connect and
       you approve them. You can also add somebody here directly &mdash; useful for a relative who is
       willing but is never going to fill in a form.</p>
    <p class="muted" style="margin:0">Your own listing was written by me from what you told me
       (&ldquo;starting your own business&rdquo;). Change the wording to your own &mdash; it is your
       card and it is the first one on the page.</p>
    <form method="post" enctype="multipart/form-data" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <label>Name</label><input type="text" name="name" required>
      <label>What they do, in a line</label><input type="text" name="role_line" placeholder="e.g. GMW Transportation — Dallas, TX">
      <label>Ask them about — one per line</label><textarea name="topics" rows="4" placeholder="Starting your own business&#10;Getting your first customers"></textarea>
      <label>A note from them</label><textarea name="about" rows="3"></textarea>
      <label>Where they are</label><input type="text" name="location">
      <label>How family reach them</label><select name="contact"><?= mm_opts(ment_contact_opts(), 'site') ?></select>
      <label>Their email</label><input type="email" name="email">
      <label>Their phone</label><input type="tel" name="phone">
      <label>Photograph (optional)</label><input type="file" name="photo" accept="image/*">
      <label>Order on the page</label><input type="number" name="sort" value="0">
      <label>Visible?</label><select name="status"><?= mm_opts(['published'=>'On the page','hidden'=>'Hidden','pending'=>'Waiting for review'], 'published') ?></select>
      <button class="btn gold" name="action" value="men_save">Add this mentor</button>
    </form>
  </div>

  <?php foreach ($MEN as $m): ?>
    <div class="panel em-add<?= $m['status'] === 'pending' ? ' mm-pending' : '' ?>">
      <h2><?= e($m['name']) ?>
        <?php if ($m['status'] === 'pending'): ?><span class="mm-kind k-ask">Waiting for your approval</span>
        <?php elseif ($m['status'] === 'hidden'): ?><span class="mm-kind">Hidden</span><?php endif; ?>
      </h2>
      <?php if ($m['status'] === 'pending'): ?>
        <form method="post" style="margin:0 0 10px">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn gold" name="action" value="men_approve">Approve &mdash; put them on the page</button>
        </form>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <label>Name</label><input type="text" name="name" required value="<?= e($m['name']) ?>">
        <label>What they do, in a line</label><input type="text" name="role_line" value="<?= e($m['role_line']) ?>">
        <label>Ask them about — one per line</label><textarea name="topics" rows="4"><?= e($m['topics']) ?></textarea>
        <label>A note from them</label><textarea name="about" rows="3"><?= e($m['about']) ?></textarea>
        <label>Where they are</label><input type="text" name="location" value="<?= e($m['location']) ?>">
        <label>How family reach them</label><select name="contact"><?= mm_opts(ment_contact_opts(), $m['contact']) ?></select>
        <label>Their email</label><input type="email" name="email" value="<?= e($m['email']) ?>">
        <label>Their phone</label><input type="tel" name="phone" value="<?= e($m['phone']) ?>">
        <label>Photograph<?= trim((string)$m['photo']) !== '' ? ' (replace)' : ' (optional)' ?></label>
        <input type="file" name="photo" accept="image/*">
        <?php if (trim((string)$m['photo']) !== ''): ?>
          <label class="mm-chk"><input type="checkbox" name="remove_photo" value="1"> Remove the photograph</label>
        <?php endif; ?>
        <label>Order on the page</label><input type="number" name="sort" value="<?= (int)$m['sort'] ?>">
        <label>Visible?</label><select name="status"><?= mm_opts(['published'=>'On the page','hidden'=>'Hidden','pending'=>'Waiting for review'], $m['status']) ?></select>
        <button class="btn gold" name="action" value="men_save">Save</button>
        <button class="btn danger" name="action" value="men_delete"
                onclick="return confirm('Remove <?= e(addslashes($m['name'])) ?> from Mentor Connect?')">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php /* ======================================================== RESOURCES */ ?>
<?php else: ?>
  <div class="panel em-add">
    <h2>Add a resource</h2>
    <p class="lede" style="margin:0">The list starts with the free government and non-profit help
       &mdash; SBA guides, SCORE mentoring, the IRS EIN page, the Texas filings page. Anything you
       add appears in the group you choose. The <b>warning</b> field puts a red line under a link;
       it is there for things like &ldquo;the IRS never charges for an EIN&rdquo;.</p>
    <form method="post" class="em-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="0">
      <label>Title</label><input type="text" name="title" required>
      <label>Link</label><input type="text" name="url" placeholder="https://">
      <label>One or two lines about it</label><textarea name="blurb" rows="3"></textarea>
      <label>Group</label><select name="category"><?php foreach (res_categories() as $c) echo '<option>' . e($c) . '</option>'; ?></select>
      <label>Icon</label><select name="icon"><?= mm_opts(ent_fin_icons(), 'doc') ?></select>
      <label>Badge (e.g. &ldquo;Free&rdquo;, leave empty for none)</label><input type="text" name="cost" value="Free">
      <label>Warning (optional)</label><textarea name="caution" rows="2"></textarea>
      <label>Order</label><input type="number" name="sort" value="0">
      <button class="btn gold" name="action" value="res_save">Add it</button>
    </form>
  </div>

  <?php foreach ($RES as $r): ?>
    <div class="panel em-add">
      <h2><?= e($r['title']) ?><?php if ($r['status'] === 'hidden'): ?> <span class="mm-kind">Hidden</span><?php endif; ?></h2>
      <form method="post" class="em-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <label>Title</label><input type="text" name="title" required value="<?= e($r['title']) ?>">
        <label>Link</label><input type="text" name="url" value="<?= e($r['url']) ?>">
        <label>One or two lines about it</label><textarea name="blurb" rows="3"><?= e($r['blurb']) ?></textarea>
        <label>Group</label><select name="category"><?php foreach (res_categories() as $c) echo '<option' . ($c === $r['category'] ? ' selected' : '') . '>' . e($c) . '</option>'; ?></select>
        <label>Icon</label><select name="icon"><?= mm_opts(ent_fin_icons(), $r['icon']) ?></select>
        <label>Badge</label><input type="text" name="cost" value="<?= e($r['cost']) ?>">
        <label>Warning</label><textarea name="caution" rows="2"><?= e($r['caution']) ?></textarea>
        <label>Order</label><input type="number" name="sort" value="<?= (int)$r['sort'] ?>">
        <label>Visible?</label><select name="status"><?= mm_opts(['published'=>'On the page','hidden'=>'Hidden'], $r['status']) ?></select>
        <button class="btn gold" name="action" value="res_save">Save</button>
        <button class="btn danger" name="action" value="res_delete"
                onclick="return confirm('Remove this resource?')">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php page_foot();
