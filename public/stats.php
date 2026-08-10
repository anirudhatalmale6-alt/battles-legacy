<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/stats_data.php';
require_role('admin');
stats_migrate();

$req  = (int)($_GET['days'] ?? 30);
$days = in_array($req, [7,30,90,365], true) ? $req : 30;
$T      = stats_totals($days);
$TOP    = stats_top_pages($days);
$DAILY  = stats_by_day(min($days, 14));
$MEM    = stats_members($days);
$RECENT = stats_recent(15);
$peak   = 1; foreach ($DAILY as $d) $peak = max($peak, $d['views']);

page_head('Who\'s Visiting', ['body_class' => 'em']);
?>
<h1>Who&rsquo;s Visiting</h1>
<p class="lede">A private count of visits to your site. Everything here is stored on your own server &mdash; nothing is sent
   to Google or anyone else, and no visitor&rsquo;s address is kept (only an anonymous marker so repeat visits aren&rsquo;t double-counted).</p>

<div class="em-tabs">
  <?php foreach ([7=>'Last 7 days',30=>'Last 30 days',90=>'Last 3 months',365=>'Last year'] as $d=>$lbl): ?>
    <a href="?days=<?= $d ?>" class="<?= $days===$d?'on':'' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<div class="st-cards">
  <div class="st-card"><b><?= number_format($T['views']) ?></b><span>page views</span></div>
  <div class="st-card"><b><?= number_format($T['visitors']) ?></b><span>different visitors</span></div>
  <div class="st-card"><b><?= number_format($T['members']) ?></b><span>signed-in family members</span></div>
</div>

<div class="panel">
  <h2>Visits by day</h2>
  <?php if ($T['views']): ?>
    <div class="st-chart">
      <?php foreach ($DAILY as $d => $v): $h = max(2, round(($v['views'] / $peak) * 100)); ?>
        <div class="st-bar" title="<?= e(date('M j', strtotime($d))) ?>: <?= (int)$v['views'] ?> views, <?= (int)$v['visitors'] ?> visitors">
          <span style="height:<?= $h ?>%"></span><em><?= e(date('j', strtotime($d))) ?></em>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted" style="margin-top:6px">Each bar is one day (hover for the numbers).</p>
  <?php else: ?>
    <p class="muted" style="margin:0">No visits recorded yet. Numbers will start appearing as soon as people open the site.</p>
  <?php endif; ?>
</div>

<div class="st-two">
  <div class="panel">
    <h2>Most viewed pages</h2>
    <?php if ($TOP): ?>
      <table class="st-tbl">
        <tr><th>Page</th><th>Views</th><th>Visitors</th></tr>
        <?php foreach ($TOP as $p): ?>
          <tr><td><b><?= e(stats_friendly($p['page'])) ?></b><span class="st-file"><?= e($p['page']) ?></span></td>
              <td><?= (int)$p['views'] ?></td><td><?= (int)$p['visitors'] ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?><p class="muted" style="margin:0">Nothing yet.</p><?php endif; ?>
  </div>

  <div class="panel">
    <h2>Family members visiting</h2>
    <?php if ($MEM): ?>
      <table class="st-tbl">
        <tr><th>Member</th><th>Views</th><th>Last seen</th></tr>
        <?php foreach ($MEM as $m): ?>
          <tr><td><b><?= e($m['member']) ?></b></td><td><?= (int)$m['views'] ?></td><td><?= e(stats_ago($m['last_seen'])) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?><p class="muted" style="margin:0">No signed-in visits yet &mdash; this fills in once family members have their own logins.</p><?php endif; ?>
  </div>
</div>

<div class="panel">
  <h2>Recent activity</h2>
  <?php if ($RECENT): ?>
    <table class="st-tbl">
      <tr><th>When</th><th>Page</th><th>Who</th></tr>
      <?php foreach ($RECENT as $r): ?>
        <tr><td><?= e(stats_ago($r['created_at'])) ?></td>
            <td><?= e(stats_friendly($r['page'])) ?></td>
            <td><?= $r['member'] ? e($r['member']) : '<span class="muted">a visitor</span>' ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?><p class="muted" style="margin:0">Nothing yet.</p><?php endif; ?>
</div>

<?php page_foot();
