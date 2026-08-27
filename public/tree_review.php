<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/tree_edit.php';
require_role('moderator');
te_migrate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($id && $act === 'approve') {
        $s0 = te_suggestion($id);                       // read the kind BEFORE it is marked applied
        $isStory = $s0 && $s0['kind'] === 'story';
        flash(te_apply_suggestion($id)
            ? ($isStory ? 'Approved — it has been added to their story.' : 'Approved — the tree has been updated.')
            : 'Sorry — that could not be applied (the person may have changed).');
    }
    elseif ($id && $act === 'decline') { te_decline_suggestion($id); flash('Suggestion declined.'); }
    header('Location: tree_review.php' . (($_POST['view'] ?? '') === 'handled' ? '?view=handled' : '')); exit;
}

$view = ($_GET['view'] ?? '') === 'handled' ? 'handled' : 'pending';
$rows = $view === 'handled'
      ? array_merge(te_suggestions('applied'), te_suggestions('declined'))
      : te_suggestions('pending');
$pendN = te_suggestion_count();

$KIND = ['edit'=>'Correction','add_child'=>'New child','add_spouse'=>'New spouse','add_sibling'=>'New brother/sister','story'=>'A memory'];
$FIELDS = ['given'=>'First name','surname'=>'Last name','sex'=>'Sex','birth_date'=>'Born','birth_place'=>'Birthplace','death_date'=>'Died','death_place'=>'Death place','living'=>'Living'];

function tr_ago($ts) {
    $t = strtotime((string)$ts); if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d/60) . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    if ($d < 2592000) return floor($d/86400) . ' day' . (floor($d/86400)==1?'':'s') . ' ago';
    return date('M j, Y', $t);
}
function tr_val($k, $v) {
    if ($k === 'living') return $v ? 'Yes' : 'No';
    if ($k === 'sex')    return $v === 'M' ? 'Male' : ($v === 'F' ? 'Female' : '—');
    return ($v === '' || $v === null) ? '—' : $v;
}

page_head('Tree Suggestions', ['body_class' => 'em']);
?>
<h1>Family Tree Suggestions</h1>
<p class="lede">Additions and corrections suggested by family members for their close relatives. Nothing changes on the tree until you approve it here.</p>
<p style="margin:10px 0 4px"><a class="btn" href="tree.php">&larr; Back to the tree</a></p>

<div class="em-tabs">
  <a href="?view=pending" class="<?= $view==='pending'?'on':'' ?>">Waiting for you<?= $pendN ? ' <span class="em-penddot">'.$pendN.'</span>' : ' (0)' ?></a>
  <a href="?view=handled" class="<?= $view==='handled'?'on':'' ?>">Already handled</a>
</div>

<?php if (!$rows): ?>
  <div class="panel"><p class="lede" style="margin:0"><?= $view==='handled' ? 'Nothing handled yet.' : 'No suggestions are waiting. When a family member suggests an addition or correction, it will appear here.' ?></p></div>
<?php else: foreach ($rows as $r):
    $f = json_decode($r['payload'] ?: '{}', true) ?: [];
    $target = one("SELECT * FROM persons WHERE pid=?", [$r['target_pid']]);
    $tname  = $target ? ($target['name'] ?: 'Unknown') : $r['target_pid'];
?>
  <div class="panel em-row">
    <div class="em-rowhead">
      <h3><span class="em-tag feat"><?= e($KIND[$r['kind']] ?? $r['kind']) ?></span>
        <?php if ($r['kind'] === 'edit'): ?>to <?= e($tname) ?>
        <?php elseif ($r['kind'] === 'story'): ?>of <?= e($tname) ?>
        <?php elseif ($r['kind'] === 'add_sibling'): ?>for <?= e($tname) ?>&rsquo;s family
        <?php else: ?>for <?= e($tname) ?><?php endif; ?>
        <?php if ($r['status']==='applied'): ?><span class="em-tag">Approved</span><?php elseif ($r['status']==='declined'): ?><span class="em-tag hid">Declined</span><?php endif; ?>
      </h3>
      <span class="em-by">From <?= e($r['submitter']) ?> &middot; <?= e(tr_ago($r['created_at'] ?? '')) ?></span>
    </div>

    <?php if ($r['kind'] === 'story'): ?>
      <div class="fact" style="border-left-color:var(--gold);margin-bottom:10px">
        <div class="v" style="font-size:15px;white-space:pre-line"><?= e((string)($f['story'] ?? '')) ?></div>
      </div>
      <p class="muted" style="margin:0 0 8px">Approving adds this to the foot of <?= e($tname) ?>&rsquo;s story, signed with <?= e($r['submitter']) ?>&rsquo;s name. It does not replace anything already there.</p>
    <?php else: ?>
    <table class="tr-tbl">
      <?php if ($r['kind'] === 'edit' && $target): ?>
        <tr><th></th><th>Now</th><th>Suggested</th></tr>
        <?php foreach ($FIELDS as $k=>$lbl):
              $old = tr_val($k, $target[$k] ?? '');
              $new = tr_val($k, $f[$k] ?? '');
              if ($old === $new) continue; ?>
          <tr class="chg"><td class="k"><?= e($lbl) ?></td><td class="old"><?= e($old) ?></td><td class="new"><?= e($new) ?></td></tr>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($FIELDS as $k=>$lbl): if (($f[$k] ?? '') === '' && $k!=='living') continue; if ($k==='living' && empty($f[$k])) continue; ?>
          <tr><td class="k"><?= e($lbl) ?></td><td colspan="2"><?= e(tr_val($k, $f[$k] ?? '')) ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
    <?php endif; ?>
    <?php if ($r['kind'] === 'edit' && $target):
        $anyChange = false;
        foreach ($FIELDS as $k=>$lbl) { if (tr_val($k,$target[$k]??'') !== tr_val($k,$f[$k]??'')) { $anyChange = true; break; } }
        if (!$anyChange): ?><p class="muted" style="margin:0 0 8px">No actual change from the current details.</p><?php endif;
    endif; ?>

    <?php if ($view === 'pending'): ?>
    <div class="em-pendbtns">
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn gold" name="action" value="approve">&#10003; Approve</button></form>
      <form method="post" style="display:inline" onsubmit="return confirm('Decline this suggestion?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn danger" name="action" value="decline">Decline</button></form>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; endif; ?>

<?php page_foot();
