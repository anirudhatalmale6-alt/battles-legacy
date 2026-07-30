<?php
require __DIR__ . '/../src/bootstrap.php';

// The main hub sections from the family's vision. Real content pages are being
// built out; until then each nav item lands on a themed placeholder so nothing is a dead link.
$sections = [
  'history'   => ['History', 'Where the Battles family began, and the generations that carried us here.'],
  'faith'     => ['Faith', 'The ministers, the churches, and the faith that has anchored our family.'],
  'enterprise'=> ['Enterprise', 'The businesses, professions, and hard work of the Battles family.'],
  'health'    => ['Health', 'Family health, wellness, and the wisdom we pass down.'],
  'news'      => ['Family News', 'Births, marriages, milestones — the news that keeps us connected.'],
  'memorial'  => ['Memorial', 'Honoring the loved ones who have gone before us.'],
  'aahistory' => ['African American History', 'Our family\'s place in the wider story of African American history.'],
];

$s = $_GET['s'] ?? '';
if (!isset($sections[$s])) {
    http_response_code(404);
    page_head('Not found');
    echo '<div class="panel" style="text-align:center;max-width:640px;margin:30px auto"><h1>Page not found</h1><p class="lede">That section isn\'t here. <a href="index.php" style="color:var(--gold2)">Back home</a>.</p></div>';
    page_foot();
    exit;
}

[$title, $blurb] = $sections[$s];
page_head($title);
?>
<div class="panel" style="text-align:center;max-width:720px;margin:34px auto">
  <h1><?= e($title) ?></h1>
  <p class="lede" style="margin:14px auto 6px"><?= e($blurb) ?></p>
  <p class="muted" style="margin:18px auto 4px">This part of the family hub is being prepared. We're gathering the
     stories, photographs, and records for this section — please check back soon.</p>
  <a class="btn" href="index.php" style="margin-top:20px">← Back to Home</a>
</div>
<?php page_foot();
