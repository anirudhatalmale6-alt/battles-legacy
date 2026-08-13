<?php
/** One person from the African American History page, with room for William to
 *  write as much about them as he likes. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/aah_data.php';
aah_migrate();

$isAdmin = role_at_least('admin');
$isNew   = $isAdmin && !empty($_GET['new']);
$person  = $isNew ? null : aah_person($_GET['p'] ?? '');

/* ---------------- save ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    csrf_check();
    $act = $_POST['act'] ?? '';

    if ($act === 'delete' && $person) {
        $back = aah_category_anchor($person['category']);
        aah_delete_person($person['id']);
        flash('Removed ' . $person['name'] . ' from the history page.');
        header('Location: ' . $back); exit;
    }

    if ($act === 'save') {
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $born = trim($_POST['born'] ?? '');
        $cat  = array_key_exists($_POST['category'] ?? '', aah_categories()) ? $_POST['category'] : 'trailblazers';
        $body = trim($_POST['body'] ?? '');
        $hide = !empty($_POST['hidden']);

        if ($name === '') {
            flash('Please give this person a name.');
        } else {
            list($photo, $perr) = aah_store_photo('photo');
            if ($perr) flash($perr);
            $keep = $person['photo'] ?? '';
            if (!empty($_POST['remove_photo'])) $keep = '';
            $finalPhoto = $photo !== '' ? $photo : $keep;

            if ($person) {
                q("UPDATE aah_people SET name=?, role=?, born=?, category=?, photo=?, body=?, status=? WHERE id=?",
                  [$name, $role, $born, $cat, $finalPhoto, $body, $hide ? 'hidden' : 'published', $person['id']]);
                $slug = $person['slug'];
                flash('Saved.');
            } else {
                $slug = aah_slug($name);
                q("INSERT INTO aah_people (slug,name,role,born,category,photo,body,sort,status) VALUES (?,?,?,?,?,?,?,?,?)",
                  [$slug, $name, $role, $born, $cat, $finalPhoto, $body, aah_next_sort($cat), $hide ? 'hidden' : 'published']);
                flash('Added ' . $name . ' to ' . aah_category_label($cat) . '.');
            }
            header('Location: aahperson.php?p=' . urlencode($slug)); exit;
        }
        header('Location: ' . ($person ? 'aahperson.php?p=' . urlencode($person['slug']) : 'aahperson.php?new=1')); exit;
    }
}

/* ---------------- not found ---------------- */
if (!$person && !$isNew) {
    http_response_code(404);
    page_head('Not found', ['body_class' => 'home aah']);
    echo '<div class="aah-wrap"><section class="aah-card" style="margin:30px 0"><h2>That name isn\'t here</h2>'
       . '<p class="aah-note">It may have been removed.</p>'
       . '<p><a class="btn2 solid" href="aahistory.php">&larr; Back to African American History</a></p></section></div>';
    legacy_footer(); page_foot(); exit;
}
if ($person && $person['status'] !== 'published' && !$isAdmin) {
    http_response_code(404);
    page_head('Not found', ['body_class' => 'home aah']);
    echo '<div class="aah-wrap"><section class="aah-card" style="margin:30px 0"><h2>That page isn\'t open yet</h2>'
       . '<p><a class="btn2 solid" href="aahistory.php">&larr; Back to African American History</a></p></section></div>';
    legacy_footer(); page_foot(); exit;
}

$cat   = $person['category'] ?? ($_GET['cat'] ?? 'trailblazers');
if (!array_key_exists($cat, aah_categories())) $cat = 'trailblazers';
$title = $isNew ? 'Add someone' : $person['name'];
$story = trim((string)($person['body'] ?? ''));

/* the rest of their section, so you can read straight through */
$SIBS = [];
foreach (aah_people($cat, $isAdmin) as $s) {
    if ($person && (int)$s['id'] === (int)$person['id']) continue;
    $SIBS[] = $s;
}

page_head($title, ['body_class' => 'home aah']);
?>
<div class="aah-wrap aahp-wrap">
  <p class="aahp-back"><a href="<?= e(aah_category_anchor($cat)) ?>">&larr; Back to <?= e(aah_category_label($cat)) ?></a></p>

<?php if (!$isNew): ?>
  <article class="aah-card aahp-main">
    <div class="aahp-head">
      <div class="aahp-photo"<?= $person['photo'] ? ' style="background-image:url(\''.e($person['photo']).'\')"' : '' ?>>
        <?php if (!$person['photo']): ?><span><?= aah_mono_name($person['name']) ?></span><?php endif; ?>
      </div>
      <div class="aahp-id">
        <span class="aahp-cat"><?= e(aah_category_label($cat)) ?></span>
        <h1><?= e($person['name']) ?></h1>
        <?php if (trim($person['role'])): ?><p class="aahp-role"><?= e($person['role']) ?></p><?php endif; ?>
        <?php if (trim((string)$person['born'])): ?><p class="aahp-born"><?= e($person['born']) ?></p><?php endif; ?>
        <?php if ($person['status'] !== 'published'): ?><span class="aahp-hidden">Hidden from the family for now</span><?php endif; ?>
      </div>
    </div>

    <div class="aahp-story">
      <?php if ($story !== ''): ?>
        <?php foreach (preg_split('/\n\s*\n/', $story) as $para): $para = trim($para); if ($para === '') continue; ?>
          <p><?= nl2br(e($para)) ?></p>
        <?php endforeach; ?>
      <?php elseif ($isAdmin): ?>
        <p class="aahp-empty">Nothing written here yet. Use the box below and it appears on this page straight away.</p>
      <?php else: ?>
        <p class="aahp-empty">A fuller account of <?= e($person['name']) ?> is being written.</p>
      <?php endif; ?>
    </div>
  </article>
<?php endif; ?>

<?php if ($isAdmin): ?>
  <section class="aah-card aahp-edit">
    <h2 class="sm"><?= $isNew ? 'Add someone to ' . e(aah_category_label($cat)) : 'Write about ' . e($person['name']) ?></h2>
    <p class="aah-note">Only you can see this box. Leave a blank line between paragraphs.</p>
    <form method="post" enctype="multipart/form-data" class="aahp-form">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="save">
      <div class="aahp-row">
        <label>Name<input type="text" name="name" value="<?= e($person['name'] ?? '') ?>" required maxlength="160"></label>
        <label>Known for<input type="text" name="role" value="<?= e($person['role'] ?? '') ?>" maxlength="220" placeholder="Abolitionist &amp; Author"></label>
      </div>
      <div class="aahp-row">
        <label>Years (optional)<input type="text" name="born" value="<?= e($person['born'] ?? '') ?>" maxlength="60" placeholder="1818 – 1895"></label>
        <label>Section
          <select name="category">
            <?php foreach (aah_categories() as $k => $lab): ?>
              <option value="<?= e($k) ?>"<?= $k === $cat ? ' selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="aahp-full">Their story
        <textarea name="body" rows="14" placeholder="Write as much as you like here — who they were, what they did, and why it matters to our family."><?= e($person['body'] ?? '') ?></textarea>
      </label>
      <div class="aahp-row">
        <label>Picture<input type="file" name="photo" accept="image/*"></label>
        <div class="aahp-checks">
          <?php if (!empty($person['photo'])): ?>
            <label class="aahp-chk"><input type="checkbox" name="remove_photo" value="1"> Remove the current picture</label>
          <?php endif; ?>
          <label class="aahp-chk"><input type="checkbox" name="hidden" value="1"<?= (($person['status'] ?? '') === 'hidden') ? ' checked' : '' ?>> Hide from the family while I work on it</label>
        </div>
      </div>
      <div class="aahp-actions">
        <button class="btn2 solid" type="submit"><?= $isNew ? 'Add this person' : 'Save' ?></button>
        <?php if (!$isNew): ?>
          <a class="btn2" href="aahperson.php?new=1&amp;cat=<?= e($cat) ?>">Add someone else</a>
        <?php endif; ?>
      </div>
    </form>
    <?php if (!$isNew): ?>
      <form method="post" class="aahp-del" onsubmit="return confirm('Remove <?= e(addslashes($person['name'])) ?> from the history page?')">
        <?= csrf_field() ?><input type="hidden" name="act" value="delete">
        <button type="submit" class="aahp-dbtn">Remove this person</button>
      </form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($SIBS): ?>
  <section class="aah-card aahp-more">
    <h2 class="sm">More in <?= e(aah_category_label($cat)) ?></h2>
    <div class="aah-people four">
      <?php foreach (array_slice($SIBS, 0, 8) as $s): ?>
        <a class="aah-person" href="aahperson.php?p=<?= e($s['slug']) ?>">
          <?php if ($s['photo']): ?>
            <span class="aah-face" style="background-image:url('<?= e($s['photo']) ?>')"></span>
          <?php else: ?>
            <span class="aah-face"><?= aah_mono_name($s['name']) ?></span>
          <?php endif; ?>
          <b><?= e($s['name']) ?></b><span><?= e($s['role']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
</div>

<?php legacy_footer(); page_foot();
