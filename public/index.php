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
  <section class="hero">
    <img class="hero-img" src="assets/hero-full.jpg"
         alt="The Battles Legacy — One Family. Many Stories. One Legacy. Featuring Richmond Battles, John N. Battles, William Holmes and Lafane Battles Sr.">
    <!-- clickable areas over the patriarchs and the button — all open the family tree -->
    <a class="hot" style="left:37%;top:59%;width:26%;height:14%" href="tree.php" title="Explore the family tree"></a>
    <a class="hot" style="left:3%;top:22%;width:15%;height:56%"  href="tree.php" title="Richmond Battles — open the family tree"></a>
    <a class="hot" style="left:18.5%;top:22%;width:14.5%;height:56%" href="tree.php" title="John N. Battles — open the family tree"></a>
    <a class="hot" style="left:63.5%;top:22%;width:13.5%;height:56%" href="tree.php" title="William Holmes — open the family tree"></a>
    <a class="hot" style="left:77%;top:22%;width:14.5%;height:56%" href="tree.php" title="Lafane Battles Sr. — open the family tree"></a>
  </section>

  <div class="panel" style="text-align:center;max-width:720px;margin:30px auto">
    <p class="lede" style="margin:0 auto">This is the private home of the Battles family — our tree, our photographs,
       and the memories that connect us. Sign in to see everyone, including our living relatives.</p>
    <p class="muted" style="margin-top:16px">Living relatives' names and photos stay hidden from the public view — family sees everything once signed in.</p>
  </div>

<?php endif;
page_foot();
