<?php
/** The family calendar.
 *
 *  Nothing here is typed in by hand — birthdays, wedding anniversaries and
 *  the days we remember someone are read straight out of the family tree, so
 *  the calendar keeps itself up to date every time the tree is edited.
 *  William's own dated events (reunions, picnics) come from news_events.
 *
 *  Privacy follows the same rule as the rest of the site: anything that
 *  involves a living relative is for signed-in family only. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function cal_month_names() {
    return ['', 'January','February','March','April','May','June',
            'July','August','September','October','November','December'];
}

/** GEDCOM-ish date -> ['m'=>1..12,'d'=>1..31|0,'y'=>1900|0,'about'=>bool], or null.
 *  Handles "Sep 17 1994", "Jun 1978", "Aug 02", "ABT 1890", "1947". */
function cal_parse($s) {
    $s = strtoupper(trim((string)$s));
    if ($s === '') return null;
    $about = (bool)preg_match('/\b(ABT|EST|CAL|BEF|AFT|BET)\b/', $s);
    $mons = ['JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,
             'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12];
    $m = 0;
    if (preg_match('/\b(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)/', $s, $mm)) $m = $mons[$mm[1]];
    $y = 0;
    if (preg_match('/\b(1[5-9]\d\d|20\d\d)\b/', $s, $yy)) { $y = (int)$yy[1]; $s = str_replace($yy[1], ' ', $s); }
    $d = 0;
    /* the year is out of the string by now, so any 1-2 digit number left is the day */
    if (preg_match('/\b(\d{1,2})\b/', $s, $dd)) $d = (int)$dd[1];
    if ($d < 1 || $d > 31) $d = 0;
    if (!$m && !$y) return null;
    return ['m' => $m, 'd' => $d, 'y' => $y, 'about' => $about];
}

/** Pretty "September 17" / "September" for a parsed date. */
function cal_daylabel($m, $d) {
    $names = cal_month_names();
    $t = $names[$m] ?? '';
    return $d ? trim($t . ' ' . $d) : $t;
}

/** Everything the calendar knows about, built once per request.
 *  Only occasions with BOTH a month and a day can land on a square. */
function cal_occasions() {
    static $out = null;
    if ($out !== null) return $out;
    $out = [];

    /* --- birthdays and days of remembrance, from the tree --- */
    try {
        $rows = all("SELECT pid,name,given,surname,birth_date,death_date,living FROM persons
                     WHERE (birth_date <> '' OR death_date <> '')");
    } catch (\Throwable $e) { $rows = []; }
    foreach ($rows as $p) {
        $living = (int)$p['living'] === 1;
        $nm = trim($p['name']) !== '' ? trim($p['name']) : trim($p['given'] . ' ' . $p['surname']);
        if ($nm === '') continue;
        $href = 'person.php?pid=' . urlencode($p['pid']);

        $b = cal_parse($p['birth_date']);
        if ($b && $b['m'] && $b['d'] && !$b['about']) {
            /* Someone who has passed does not have a birthday any more — the
               family marks the day they were born instead. */
            $out[] = [
                'm' => $b['m'], 'd' => $b['d'], 'y' => $b['y'],
                'kind'    => $living ? 'birthday' : 'born',
                'title'   => $nm,
                'sub'     => $living
                    ? ($b['y'] ? 'Born ' . $b['y'] : 'Birthday')
                    : ($b['y'] ? 'Born ' . $b['y'] : 'Born on this day'),
                'href'    => $href,
                'private' => $living,
            ];
        }
        $dth = cal_parse($p['death_date']);
        if ($dth && $dth['m'] && $dth['d'] && !$dth['about']) {
            $out[] = [
                'm' => $dth['m'], 'd' => $dth['d'], 'y' => $dth['y'],
                'kind'    => 'remembrance',
                'title'   => $nm,
                'sub'     => $dth['y'] ? 'Passed ' . $dth['y'] : 'In loving memory',
                'href'    => 'tribute.php?pid=' . urlencode($p['pid']),
                'private' => false,          // the Memorial page is public too
            ];
        }
    }

    /* --- wedding anniversaries --- */
    try {
        $fams = all("SELECT fid,husb,wife,marr_date FROM families WHERE marr_date <> ''");
    } catch (\Throwable $e) { $fams = []; }
    if ($fams) {
        $need = [];
        foreach ($fams as $f) { if ($f['husb']) $need[$f['husb']] = 1; if ($f['wife']) $need[$f['wife']] = 1; }
        $people = [];
        foreach (array_chunk(array_keys($need), 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            try {
                foreach (all("SELECT pid,name,given,surname,living FROM persons WHERE pid IN ($in)", $chunk) as $r)
                    $people[$r['pid']] = $r;
            } catch (\Throwable $e) {}
        }
        foreach ($fams as $f) {
            $mk = cal_parse($f['marr_date']);
            if (!$mk || !$mk['m'] || !$mk['d'] || $mk['about']) continue;
            $h = $people[$f['husb']] ?? null; $w = $people[$f['wife']] ?? null;
            $names = [];
            foreach ([$h, $w] as $s) {
                if (!$s) continue;
                $n = trim($s['name']) !== '' ? trim($s['name']) : trim($s['given'] . ' ' . $s['surname']);
                if ($n !== '') $names[] = $n;
            }
            if (!$names) continue;
            $priv = ((int)($h['living'] ?? 0) === 1) || ((int)($w['living'] ?? 0) === 1);
            $out[] = [
                'm' => $mk['m'], 'd' => $mk['d'], 'y' => $mk['y'],
                'kind'    => 'anniversary',
                'title'   => implode(' & ', $names),
                'sub'     => $mk['y'] ? 'Married ' . $mk['y'] : 'Wedding anniversary',
                'href'    => $h ? 'person.php?pid=' . urlencode($h['pid']) : ($w ? 'person.php?pid=' . urlencode($w['pid']) : ''),
                'private' => $priv,
            ];
        }
    }

    /* --- William's own events, from the Family News manage screen --- */
    $mons = ['JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,
             'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12];
    try { $evs = all("SELECT * FROM news_events WHERE status='published' ORDER BY sort, id"); }
    catch (\Throwable $e) { $evs = []; }
    foreach ($evs as $ev) {
        $m = $mons[strtoupper(substr(trim($ev['mon']), 0, 3))] ?? 0;
        $d = (int)preg_replace('/\D/', '', $ev['day']);
        if (!$m || $d < 1 || $d > 31) continue;
        $bits = array_filter([trim($ev['time_label']), trim($ev['place'])]);
        $out[] = [
            'm' => $m, 'd' => $d, 'y' => 0,
            'kind'    => 'event',
            'title'   => $ev['title'],
            'sub'     => $bits ? implode(' · ', $bits) : 'Family event',
            'href'    => 'news.php#events',
            'private' => false,
        ];
    }

    usort($out, function ($a, $b) {
        if ($a['m'] !== $b['m']) return $a['m'] - $b['m'];
        if ($a['d'] !== $b['d']) return $a['d'] - $b['d'];
        return strcmp($a['title'], $b['title']);
    });
    return $out;
}

/** The occasions a given viewer may see. */
function cal_visible($member = null) {
    if ($member === null) $member = logged_in();
    $all = cal_occasions();
    if ($member) return $all;
    return array_values(array_filter($all, function ($o) { return empty($o['private']); }));
}

function cal_kinds() {
    return [
        'birthday'    => ['Birthday',        'bday'],
        'anniversary' => ['Anniversary',     'anniv'],
        'remembrance' => ['Remembering',     'remem'],
        'born'        => ['Born on this day','born'],
        'event'       => ['Family event',    'event'],
    ];
}

/** The next $limit occasions on or after today, rolling into next year. */
function cal_upcoming($limit = 6, $kinds = null, $member = null) {
    $list = cal_visible($member);
    if ($kinds) $list = array_values(array_filter($list, function ($o) use ($kinds) { return in_array($o['kind'], $kinds, true); }));
    $tm = (int)date('n'); $td = (int)date('j');
    $soon = []; $later = [];
    foreach ($list as $o) {
        if ($o['m'] > $tm || ($o['m'] === $tm && $o['d'] >= $td)) $soon[] = $o; else $later[] = $o;
    }
    /* cal_occasions() is already sorted by month/day, so the two halves stay in order */
    return array_slice(array_merge($soon, $later), 0, $limit);
}

/** Occasions in one month, keyed by day number. */
function cal_by_day($month, $member = null) {
    $out = [];
    foreach (cal_visible($member) as $o) {
        if ((int)$o['m'] !== (int)$month) continue;
        $out[(int)$o['d']][] = $o;
    }
    return $out;
}

/** How many years it is this year — "80th birthday", "50 years married". */
function cal_years_this_year($o) {
    if (empty($o['y'])) return 0;
    $n = (int)date('Y') - (int)$o['y'];
    return ($n > 0 && $n < 200) ? $n : 0;
}

/** "in 3 days" / "today" / "tomorrow" for an occasion in the upcoming list. */
function cal_when($o) {
    $tm = (int)date('n'); $td = (int)date('j');
    if ((int)$o['m'] === $tm && (int)$o['d'] === $td) return 'Today';
    $y = (int)date('Y');
    $ts = @mktime(0, 0, 0, (int)$o['m'], (int)$o['d'], $y);
    $today = @mktime(0, 0, 0, $tm, $td, $y);
    if ($ts === false || $today === false) return '';
    if ($ts < $today) $ts = @mktime(0, 0, 0, (int)$o['m'], (int)$o['d'], $y + 1);
    $days = (int)round(($ts - $today) / 86400);
    if ($days === 1)  return 'Tomorrow';
    if ($days <= 30)  return 'in ' . $days . ' days';
    return cal_daylabel((int)$o['m'], (int)$o['d']);
}
