<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$sel = $_GET['pid'] ?? ($_POST['pid'] ?? '');
$err = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = $_POST['pid'] ?? '';
    $caption = trim($_POST['caption'] ?? '');
    $person = one("SELECT pid FROM persons WHERE pid=?", [$pid]);
    if (!$person) {
        $err = 'Please choose which family member this photo is of.';
    } elseif (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Please choose a photo to upload.';
    } else {
        $tmp = $_FILES['photo']['tmp_name'];
        $info = @getimagesize($tmp);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (!$info || !isset($allowed[$info['mime']])) {
            $err = 'That file doesn\'t look like a photo (JP, PNG, GIF or WEBP only).';
        } elseif ($_FILES['photo']['size'] > 12 * 1024 * 1024) {
            $err = 'That photo is larger than 12 MB — please pick a smaller one.';
        } else {
            $ext = $allowed[$info['mime']];
            $orig = preg_replace('/[^A-Za-z0-9._-]+/', '_', $_FILES['photo']['name']);
            $fname = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
            $relDir = config('photos_dir') . '/' . trim($pid, '@');
            $absDir = __DIR__ . '/' . $relDir;
            @mkdir($absDir, 0775, true);
            if (move_uploaded_file($tmp, $absDir . '/' . $fname)) {
                q("INSERT INTO photos (pid,filename,path,caption,status,source,uploaded_by) VALUES (?,?,?,?, 'pending','upload',?)",
                  [$pid, $orig, $relDir . '/' . $fname, $caption, current_user()['id']]);
                /* The person it was uploaded for is the first face in it. A
                   moderator can add the rest from the profile once it's approved. */
                require_once __DIR__ . '/../src/photo_people.php';
                pp_tag((int)insert_id(), $pid);
                $ok = true; $sel = $pid;
            } else {
                $err = 'Sorry — the upload couldn\'t be saved. Please try again.';
            }
        }
    }
}

$people = all("SELECT pid,name,birth_date FROM persons ORDER BY name");

page_head('Add a Photo');
?>
<h1>Add a photo or memory</h1>
<p class="lede">Upload a photograph and pin it to the right person. A moderator gives every submission a quick look before it appears — so the tree stays accurate and respectful.</p>

<?php if ($ok): ?>
  <div class="panel" style="margin-top:18px">
    <h2>Thank you — it's in the queue</h2>
    <p class="lede">Your photo has been submitted and is waiting for a moderator to approve it. Once approved it will appear on the person's profile and in the tree.</p>
    <a class="btn" href="upload.php">Add another</a>
    <a class="btn" href="tree.php" style="margin-left:8px">Back to the tree</a>
  </div>
<?php else: ?>
  <form class="panel" method="post" enctype="multipart/form-data" style="max-width:560px;margin-top:18px">
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Who is this photo of?</label>
    <select name="pid" required>
      <option value="">— choose a family member —</option>
      <?php foreach ($people as $pp): $y = yr($pp['birth_date']); ?>
        <option value="<?= e($pp['pid']) ?>" <?= $pp['pid'] === $sel ? 'selected' : '' ?>><?= e($pp['name']) . ($y ? " ($y)" : '') ?></option>
      <?php endforeach; ?>
    </select>
    <label>Photo (JPG, PNG, GIF or WEBP — up to 12 MB)</label>
    <input type="file" name="photo" accept="image/*" required>
    <label>Caption or memory (optional)</label>
    <textarea name="caption" placeholder="Where / when was this taken? Who else is in it?"></textarea>
    <button class="btn gold">Submit for review</button>
  </form>
<?php endif;
page_foot();
