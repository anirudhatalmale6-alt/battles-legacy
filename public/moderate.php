<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('moderator');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    $ph = one("SELECT * FROM photos WHERE id=?", [$id]);
    if ($ph) {
        if ($act === 'approve') { q("UPDATE photos SET status='approved' WHERE id=?", [$id]); flash('Approved — it\'s now live on the profile.'); }
        elseif ($act === 'reject') {
            q("UPDATE photos SET status='rejected' WHERE id=?", [$id]);
            $abs = __DIR__ . '/' . $ph['path'];
            if (is_file($abs)) @unlink($abs);
            flash('Declined and removed.');
        }
    }
    header('Location: moderate.php'); exit;
}

$pending = all("SELECT ph.*, p.name AS person, u.name AS uploader
                FROM photos ph
                LEFT JOIN persons p ON p.pid = ph.pid
                LEFT JOIN users u ON u.id = ph.uploaded_by
                WHERE ph.status='pending' ORDER BY ph.id");

page_head('Review Queue');
?>
<h1>Review queue</h1>
<p class="lede"><?= count($pending) ? count($pending) . ' photo' . (count($pending) === 1 ? '' : 's') . ' waiting for your review.' : 'All caught up — nothing waiting.' ?></p>

<div class="grid cols3" style="margin-top:20px">
<?php foreach ($pending as $ph): ?>
  <div class="tile">
    <a href="#" onclick="lb('<?= e($ph['path']) ?>');return false"><img src="<?= e($ph['path']) ?>" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;border:1px solid rgba(201,162,75,.4)"></a>
    <h3 style="margin-top:10px"><?= e($ph['person'] ?? $ph['pid']) ?></h3>
    <p><?php if ($ph['caption']): ?>“<?= e($ph['caption']) ?>”<br><?php endif; ?>
       <span class="muted">by <?= e($ph['uploader'] ?? 'unknown') ?></span></p>
    <form method="post" style="display:flex;gap:8px;margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$ph['id'] ?>">
      <button class="btn gold" name="action" value="approve" style="margin:0;flex:1;padding:9px">Approve</button>
      <button class="btn" name="action" value="reject" style="margin:0;flex:1;padding:9px">Decline</button>
    </form>
  </div>
<?php endforeach; ?>
</div>

<div id="lightbox" onclick="closeLb()"><span class="x">×</span><img id="lightbox-img" onclick="event.stopPropagation()" src="" alt=""></div>
<script>
function lb(s){document.getElementById('lightbox-img').src=s;document.getElementById('lightbox').classList.add('show');}
function closeLb(){document.getElementById('lightbox').classList.remove('show');}
window.addEventListener('keydown',e=>{if(e.key==='Escape')closeLb();});
</script>
<?php page_foot();
