<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/calendar_data.php';
require_once __DIR__ . '/../src/news_data.php';

$member = logged_in();
$MONTHS = cal_month_names();

$m = (int)($_GET['m'] ?? date('n'));
if ($m < 1 || $m > 12) $m = (int)date('n');
$prev = $m === 1 ? 12 : $m - 1;
$next = $m === 12 ? 1 : $m + 1;

/* The calendar repeats every year, so it is drawn on the current year's
   weekdays — the squares are a layout, not a claim about 1936. */
$year   = (int)date('Y');
$first  = (int)date('N', mktime(0, 0, 0, $m, 1, $year));   // 1 = Mon
$lead   = $first % 7;                                       // grid starts on Sunday
$days   = (int)date('t', mktime(0, 0, 0, $m, 1, $year));
$isNow  = $m === (int)date('n');
$today  = (int)date('j');

try { $BYDAY = cal_by_day($m, $member); } catch (\Throwable $ex) { $BYDAY = []; }
$COUNT = 0; foreach ($BYDAY as $list) $COUNT += count($list);

$KINDS  = cal_kinds();
$UPNEXT = cal_upcoming(8, null, $member);
$isAdmin = role_at_least('admin');

/* how many of each kind are hidden from a visitor who isn't signed in */
$hidden = 0;
if (!$member) { foreach (cal_occasions() as $o) if (!empty($o['private']) && (int)$o['m'] === $m) $hidden++; }

page_head('Family Calendar', ['body_class' => 'cal']);
?>
<?php if ($isAdmin): ?>
  <div class="ent2-adminbar">
    <span>Birthdays, anniversaries and remembrance days come from the family tree — edit a person and the calendar follows.</span>
    <a class="ent2-editbtn" href="news_manage.php">&#9998; Add a family event</a>
  </div>
<?php endif; ?>

<section class="cal-hero">
  <div class="cal-hi">
    <h1>The Family Calendar</h1>
    <p>Birthdays, wedding anniversaries, the days we remember our people, and everything
       the family has coming up &mdash; all in one place.</p>
  </div>
</section>

<div class="wrap cal-wrap">

  <div class="cal-legend">
    <?php foreach ($KINDS as $k => $info): ?>
      <span class="cal-lg <?= e($info[1]) ?>"><i></i><?= e($info[0]) ?></span>
    <?php endforeach; ?>
  </div>

  <div class="cal-bar">
    <a class="cal-nav" href="calendar.php?m=<?= $prev ?>" rel="prev">&larr; <?= e($MONTHS[$prev]) ?></a>
    <h2><?= e($MONTHS[$m]) ?><?php if ($isNow): ?> <span>this month</span><?php endif; ?></h2>
    <a class="cal-nav" href="calendar.php?m=<?= $next ?>" rel="next"><?= e($MONTHS[$next]) ?> &rarr;</a>
  </div>

  <div class="cal-grid" role="table" aria-label="<?= e($MONTHS[$m]) ?>">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?>
      <div class="cal-dow"><?= $dn ?></div>
    <?php endforeach; ?>
    <?php for ($i = 0; $i < $lead; $i++): ?><div class="cal-cell empty"></div><?php endfor; ?>
    <?php for ($d = 1; $d <= $days; $d++): $items = $BYDAY[$d] ?? []; ?>
      <div class="cal-cell<?= $items ? ' has' : '' ?><?= ($isNow && $d === $today) ? ' now' : '' ?>">
        <span class="cal-dnum"><?= $d ?></span>
        <?php foreach (array_slice($items, 0, 3) as $o): $cls = $KINDS[$o['kind']][1] ?? 'event'; ?>
          <a class="cal-ev <?= e($cls) ?>" href="#d<?= $d ?>" title="<?= e($o['title'] . ' — ' . $o['sub']) ?>">
            <i></i><span><?= e($o['title']) ?></span></a>
        <?php endforeach; ?>
        <?php if (count($items) > 3): ?>
          <a class="cal-more" href="#d<?= $d ?>">+<?= count($items) - 3 ?> more</a>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>

  <?php if (!$member): ?>
    <div class="cal-note">
      <b>You're seeing the public calendar.</b>
      <?= $hidden ? 'Birthdays and anniversaries of living family (' . (int)$hidden . ' this month) are kept private.' : 'Birthdays and anniversaries of living family are kept private.' ?>
      <a href="login.php">Sign in</a> to see them.
    </div>
  <?php endif; ?>

  <div class="cal-cols">
    <section class="cal-list">
      <h3><?= e($MONTHS[$m]) ?> in the family<?= $COUNT ? ' <i>' . (int)$COUNT . '</i>' : '' ?></h3>
      <?php if (!$COUNT): ?>
        <p class="muted">Nothing recorded in <?= e($MONTHS[$m]) ?> yet.
          Dates come from the family tree &mdash; add a birth, marriage or passing to a person and it appears here.</p>
      <?php else: ?>
        <?php for ($d = 1; $d <= $days; $d++): if (empty($BYDAY[$d])) continue; ?>
          <div class="cal-day" id="d<?= $d ?>">
            <div class="cal-dh"><b><?= $d ?></b><span><?= e($MONTHS[$m]) ?></span></div>
            <ul>
              <?php foreach ($BYDAY[$d] as $o): $cls = $KINDS[$o['kind']][1] ?? 'event'; $n = cal_years_this_year($o); ?>
                <li class="<?= e($cls) ?>">
                  <span class="cal-tag"><?= e($KINDS[$o['kind']][0] ?? 'Event') ?></span>
                  <?php if ($o['href']): ?><a href="<?= e($o['href']) ?>"><?= e($o['title']) ?></a>
                  <?php else: ?><b><?= e($o['title']) ?></b><?php endif; ?>
                  <span class="cal-sub"><?= e($o['sub']) ?><?php
                    if ($n && $o['kind'] === 'birthday')    echo ' · turns ' . $n . ' this year';
                    elseif ($n && $o['kind'] === 'anniversary') echo ' · ' . $n . ' years';
                    elseif ($n && $o['kind'] === 'remembrance') echo ' · ' . $n . ' years ago';
                  ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endfor; ?>
      <?php endif; ?>
    </section>

    <aside class="cal-side">
      <h3>Coming up next</h3>
      <?php if (!$UPNEXT): ?>
        <p class="muted">Nothing on the calendar yet.</p>
      <?php else: ?>
        <ul class="cal-up">
          <?php foreach ($UPNEXT as $o): $cls = $KINDS[$o['kind']][1] ?? 'event'; ?>
            <li class="<?= e($cls) ?>">
              <span class="cal-when"><?= e(cal_when($o)) ?></span>
              <?php if ($o['href']): ?><a href="<?= e($o['href']) ?>"><?= e($o['title']) ?></a>
              <?php else: ?><b><?= e($o['title']) ?></b><?php endif; ?>
              <span class="cal-sub"><?= e($KINDS[$o['kind']][0] ?? 'Event') ?> · <?= e(cal_daylabel((int)$o['m'], (int)$o['d'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="cal-hint">Every date here is read from the family tree. If a birthday is missing or wrong,
        open that person&rsquo;s page and correct the date &mdash; the calendar updates itself.</p>
      <a class="btn2" href="news.php#events">Family News &amp; events</a>
    </aside>
  </div>
</div>

<?php legacy_footer();
page_foot();
