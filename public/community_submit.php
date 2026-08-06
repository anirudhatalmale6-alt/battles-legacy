<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/community_data.php';
require_login();
community_migrate();

$u    = current_user();
$kind = $_GET['kind'] ?? ($_POST['kind'] ?? 'update');
if (!in_array($kind, ['question','recipe','update','answer'], true)) $kind = 'update';
$parent = (int)($_GET['parent'] ?? $_POST['parent'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

$LABEL = ['question'=>'Ask a Question','recipe'=>'Share a Recipe','update'=>'Post an Update','answer'=>'Add an Answer'][$kind];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { header('Location: news.php'); exit; } // honeypot
    $body = trim($_POST['body'] ?? '');
    $title = trim($_POST['title'] ?? '');
    if ($kind === 'recipe' && $title === '') { $err = 'Please give the recipe a name.'; }
    elseif ($kind !== 'recipe' && $body === '') { $err = 'Please write something before submitting.'; }
    else {
        list($photo, $perr) = ($kind === 'recipe' || $kind === 'update') ? comm_store_photo('photo') : ['',''];
        if ($perr) { $err = $perr; }
        else {
            $f = ['title'=>$title, 'body'=>mb_substr($body,0,4000), 'author'=>trim($_POST['author'] ?? '') ?: ($u['name'] ?? 'Family member'), 'photo'=>$photo];
            comm_add($kind, $f, $u, $kind === 'answer' ? $parent : 0);
            $back = $kind === 'answer' ? ('community_view.php?id=' . $parent) : 'news.php';
            $msg  = $isAdmin ? 'Posted — it is live now.' : 'Thank you! Your submission has been sent for approval and will appear once approved.';
            flash($msg); header('Location: ' . $back); exit;
        }
    }
}

page_head($LABEL, ['body_class' => 'em']);
?>
<h1><?= e($LABEL) ?></h1>
<p class="lede"><?php
  if ($kind==='question') echo 'Ask the family anything — about our history, a photo, a recipe, or a relative. ';
  elseif ($kind==='recipe') echo 'Share a family recipe so it&rsquo;s passed down for generations. ';
  elseif ($kind==='answer') echo 'Share what you know. ';
  else echo 'Share a family update, a photo, or a word of encouragement. ';
  echo $isAdmin ? 'As an editor, your post goes live right away.' : 'Your post goes to William for approval before it appears.';
?></p>
<p style="margin:10px 0 14px"><a class="btn" href="<?= $kind==='answer' ? 'community_view.php?id='.(int)$parent : 'news.php' ?>">&larr; Back</a></p>

<?php if ($err): ?><div class="panel" style="border-left:4px solid #b3452f;color:#7a2e1f"><b>Please check this:</b> <?= e($err) ?></div><?php endif; ?>

<div class="panel em-add">
  <form method="post" enctype="multipart/form-data" class="em-form">
    <?= csrf_field() ?>
    <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="parent" value="<?= (int)$parent ?>">
    <input type="text" name="website" class="fp-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <?php if ($kind === 'recipe'): ?>
      <label>Recipe name *</label>
      <input type="text" name="title" required placeholder="e.g. Grandma Louisa&rsquo;s Sweet Potato Pie">
      <label>Ingredients &amp; directions</label>
      <textarea name="body" style="min-height:150px" placeholder="List the ingredients, then the steps."></textarea>
      <label>Photo (optional — JPG/PNG, up to 12 MB)</label>
      <input type="file" name="photo" accept="image/*">
    <?php elseif ($kind === 'question'): ?>
      <label>Your question *</label>
      <textarea name="body" required placeholder="e.g. Does anyone have information about our ancestor Susan Mipus?"></textarea>
    <?php elseif ($kind === 'answer'): ?>
      <label>Your answer *</label>
      <textarea name="body" required placeholder="Share what you know&hellip;"></textarea>
    <?php else: /* update */ ?>
      <label>Your update *</label>
      <textarea name="body" required placeholder="e.g. The Johnson cousins reunited in Atlanta this weekend!"></textarea>
      <label>Photo (optional — JPG/PNG, up to 12 MB)</label>
      <input type="file" name="photo" accept="image/*">
    <?php endif; ?>
    <button class="btn gold" type="submit" style="margin-top:12px"><?= $isAdmin ? 'Post now' : 'Submit for approval' ?></button>
  </form>
</div>

<?php page_foot();
