<?php
require __DIR__ . '/../src/bootstrap.php';
$u = current_user();
$np = one("SELECT COUNT(*) c FROM persons")['c'] ?? 0;
$nph = one("SELECT COUNT(*) c FROM photos WHERE status='approved'")['c'] ?? 0;

page_head('Home');
?>
<?php if ($u): ?>
  <h1>Welcome home, <?= e(explode(' ', $u['name'])[0]) ?>.</h1>
  <p class="lede">This is the private hub for the Battles family — our tree, our photographs, and the stories
     that hold it all together. Everything here is visible only to family.</p>

  <div class="grid cols3" style="margin-top:28px">
    <a class="tile" href="tree.php"><h3>Explore the Family Tree</h3>
       <p><?= (int)$np ?> relatives across the generations. As a signed-in member you can see living family too —
          zoom, pan, and open anyone to read their story.</p></a>
    <a class="tile" href="upload.php"><h3>Add a Photo or Memory</h3>
       <p>Share a photograph and pin it to the right person. A moderator gives it a quick look, then it appears on their profile.</p></a>
    <?php if (role_at_least('moderator')): ?>
    <a class="tile" href="moderate.php"><h3>Review Queue</h3>
       <p>Approve or decline the photos family members have submitted. Nothing goes public until you say so.</p></a>
    <?php endif; ?>
    <?php if (role_at_least('admin')): ?>
    <a class="tile" href="admin.php"><h3>Invite Family</h3>
       <p>Send invitations and set who is an Admin, Moderator, or Member.</p></a>
    <?php endif; ?>
  </div>

  <div class="panel" style="margin-top:28px">
    <h2>Our archive so far</h2>
    <p class="lede"><b style="color:var(--gold2)"><?= (int)$np ?></b> people in the tree ·
       <b style="color:var(--gold2)"><?= (int)$nph ?></b> photographs pinned and growing.</p>
  </div>
<?php else: ?>
  <div class="panel" style="text-align:center;max-width:720px;margin:30px auto">
    <h1><span class="script">The Battles</span> Legacy</h1>
    <p class="lede" style="margin:14px auto">A private home for our family's history — the tree, the photographs,
       and the memories that connect us. This hub is for family. Sign in to see everyone, including our living relatives.</p>
    <div style="margin-top:22px">
      <a class="btn gold" href="login.php">Family Login</a>
      <a class="btn" href="tree.php" style="margin-left:8px">Preview the Tree</a>
    </div>
    <p class="muted" style="margin-top:18px">Living relatives' names and photos stay hidden from the public preview — family sees everything once signed in.</p>
  </div>
<?php endif;
page_foot();
