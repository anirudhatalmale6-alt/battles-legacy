<?php
require __DIR__ . '/../src/bootstrap.php';
$u = current_user();
$np = one("SELECT COUNT(*) c FROM persons")['c'] ?? 0;
$nph = one("SELECT COUNT(*) c FROM photos WHERE status='approved'")['c'] ?? 0;

page_head('Home', $u ? [] : ['body_class' => 'home']);
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
    <img class="hero-img" src="assets/hero-scene.jpg"
         alt="The Battles Legacy — One Family. Many Stories. One Legacy.">
    <!-- four ancestor portraits that fade fully out to the tree, then the next fades in — never two faces at once -->
    <a class="rslot" style="left:2.5%;top:19%;width:15%;height:76%"  href="tree.php" title="Open the family tree"><img class="rot" alt=""></a>
    <a class="rslot" style="left:18%;top:19%;width:15%;height:76%"  href="tree.php" title="Open the family tree"><img class="rot" alt=""></a>
    <a class="rslot" style="left:63%;top:19%;width:15%;height:76%"  href="tree.php" title="Open the family tree"><img class="rot" alt=""></a>
    <a class="rslot" style="left:78%;top:19%;width:15.5%;height:76%" href="tree.php" title="Open the family tree"><img class="rot" alt=""></a>
    <!-- clickable Explore Our Family Tree button (baked into the scene) -->
    <a class="hot" style="left:39.5%;top:78%;width:20.5%;height:13%" href="tree.php" title="Explore the family tree"></a>
  </section>
  <script>
  (function(){
    var PH=['p01','p02','p03','p04','p05','p06','p07','p08','p09','p10','p11','p12']
      .map(function(n){return 'assets/hero-rot/'+n+'.jpg';});
    // each slot cycles its own group of 3 photos, so no face is ever shown twice at once
    var SETS=[[0,1,2],[3,4,5],[6,7,8],[9,10,11]];
    var slots=[].slice.call(document.querySelectorAll('.hero .rslot'));
    slots.forEach(function(s,i){
      s._img=s.querySelector('.rot'); s._set=SETS[i]; s._k=0;
      s._img.src=PH[s._set[0]];
      requestAnimationFrame(function(){ s._img.classList.add('on'); });
    });
    function cycle(i){
      var s=slots[i];
      s._img.classList.remove('on');                 // fade current portrait out to the tree
      setTimeout(function(){                          // once it's gone, swap and fade the next one in
        s._k=(s._k+1)%s._set.length;
        s._img.src=PH[s._set[s._k]];
        s._img.classList.add('on');
      }, 1300);
    }
    slots.forEach(function(s,i){ setTimeout(function(){ setInterval(function(){cycle(i);}, 5200); }, 1600 + i*950); });
  })();
  </script>

  <!-- scripture value strip -->
  <section class="valuestrip">
    <div class="vs-inner">
      <blockquote class="vs-verse">"One generation shall praise thy works to another, and shall declare thy mighty acts."
        <span>— Psalm 145:4</span></blockquote>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-4 8 4 4 0 0 0 1 7h6a4 4 0 0 0 1-7 5 5 0 0 0-4-8z"/><line x1="12" y1="13" x2="12" y2="22"/></svg></span>
        <div><h4>Our Roots</h4><p>Remember where we come from.</p></div>
      </div>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><line x1="12" y1="3" x2="12" y2="21"/><line x1="7" y1="9" x2="17" y2="9"/></svg></span>
        <div><h4>Our Faith</h4><p>Faith is the foundation of our family.</p></div>
      </div>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><circle cx="8" cy="9" r="2.4"/><circle cx="16" cy="9" r="2.4"/><path d="M3.5 19c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/><path d="M11.5 19c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/></svg></span>
        <div><h4>Our Family</h4><p>Together is our favorite place to be.</p></div>
      </div>
      <div class="vs-item">
        <span class="vs-ic"><svg viewBox="0 0 24 24"><polygon points="12,3 14.6,9.1 21,9.7 16.2,14 17.6,20.3 12,16.9 6.4,20.3 7.8,14 3,9.7 9.4,9.1"/></svg></span>
        <div><h4>Our Legacy</h4><p>Building a legacy they can be proud of.</p></div>
      </div>
    </div>
  </section>

  <!-- feature cards -->
  <section class="homecards">
    <div class="hc-inner">
      <article class="hc">
        <h3>Family News</h3>
        <div class="hc-media"><img src="assets/home-news.jpg" alt="Holmes Family Reunion group photo"></div>
        <h5>Holmes Family Reunion</h5>
        <p class="hc-sub">June 21, 2025</p>
        <a class="btn2" href="login.php">Read More</a>
      </article>

      <article class="hc">
        <h3>Upcoming Events</h3>
        <ul class="hc-events">
          <li><span class="ev-d">May 25</span> Memorial Day Tribute</li>
          <li><span class="ev-d">June 21</span> Holmes Family Reunion</li>
          <li><span class="ev-d">July 4</span> Independence Day</li>
          <li><span class="ev-d">Aug 15</span> Battles Legacy Scholarship Deadline</li>
        </ul>
        <a class="btn2" href="login.php">View Calendar</a>
      </article>

      <article class="hc">
        <h3>Featured Memorial</h3>
        <div class="hc-media"><img src="assets/home-memorial.jpg" alt="Elizabeth Battles Holmes"></div>
        <h5>Elizabeth Battles Holmes</h5>
        <p class="hc-sub">Sept. 29, 1936 – June 17, 2022</p>
        <a class="btn2" href="login.php">View Memorial</a>
      </article>

      <article class="hc">
        <h3>Family Tree</h3>
        <div class="hc-media"><img src="assets/home-tree.jpg" alt="The Battles family tree"></div>
        <p class="hc-text">Explore our family tree and discover your roots.</p>
        <a class="btn2" href="tree.php">Explore Tree</a>
      </article>

      <article class="hc">
        <h3>Faith Corner</h3>
        <div class="hc-media"><img src="assets/home-faith.jpg" alt="Open Bible by candlelight"></div>
        <p class="hc-text">"Be strong and courageous. Do not be afraid; do not be discouraged, for the Lord your God will be with you wherever you go."</p>
        <p class="hc-sub">— Joshua 1:9</p>
      </article>
    </div>
  </section>

  <!-- rich footer -->
  <footer class="homefoot">
    <div class="hf-inner">
      <div class="hf-col">
        <h4>✉ Stay Connected</h4>
        <p>Subscribe for family updates and news.</p>
        <form class="hf-sub" onsubmit="return hfSub(this)">
          <input type="email" name="email" placeholder="Your email address" required>
          <button type="submit" class="btn gold">Subscribe</button>
          <span class="hf-thanks">Thank you — we'll keep you posted.</span>
        </form>
      </div>
      <div class="hf-col">
        <h4>🔒 Private Family Website</h4>
        <p>This is a private website for family members. Login to access all features.</p>
        <a class="btn2 solid" href="login.php">Login</a>
      </div>
      <div class="hf-col">
        <h4>Follow us on Faith, Family &amp; Love</h4>
        <p class="script hf-motto">Rooted in Faith. United in Love. Building Our Legacy.</p>
      </div>
      <div class="hf-col hf-brand">
        <span class="hf-tree"><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-4 8 4 4 0 0 0 1 7h6a4 4 0 0 0 1-7 5 5 0 0 0-4-8z"/><line x1="12" y1="13" x2="12" y2="22"/></svg></span>
        <div>Building on<br><b>The Battles Legacy</b></div>
      </div>
    </div>
  </footer>
  <script>
    function hfSub(f){f.querySelector('.hf-thanks').style.display='block';f.querySelector('input').value='';f.querySelector('input').disabled=true;return false;}
  </script>

<?php endif;
page_foot();
