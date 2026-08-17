<?php
/** The people already in the tree, offered as suggestions when a name is typed.
 *
 *  William asked for this in as many words: "Is it possible for the name to come
 *  when I send an invitation. This way I can have the correct spelling and make
 *  sure the name is a part of the family."
 *
 *  Both halves of that matter. If he types "Dianne Battles" on the invitation
 *  and the tree says "Diane Battles", the site ends up holding two versions of
 *  one person and no way to tell they are the same. And a name that isn't in
 *  the tree at all is worth a second look before a private family website posts
 *  somebody a key to it.
 *
 *  So the list carries more than names: the years, and who each person belongs
 *  to. There are four William Battles in this tree; "son of L.J. Battles and
 *  Susie Johnson" is what tells them apart, and a name alone never would. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** Both an invitation and an account can now say which person in the tree they
 *  belong to. Nothing before today set it, so every existing row keeps an empty
 *  pid and is matched on the name instead. */
function pp_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    db_add_column('invites', 'pid', "VARCHAR(16) NOT NULL DEFAULT ''");
    db_add_column('users',   'pid', "VARCHAR(16) NOT NULL DEFAULT ''");
}

/** Join up the invitations and accounts that were made before any of this
 *  existed, so the ones already waiting say "in the family tree" too.
 *
 *  Only ever fills in a blank, and only when the name can mean one person —
 *  pp_match() returns nothing for the four William Battles, and guessing there
 *  would silently pin somebody's account to the wrong cousin. */
function pp_backfill() {
    static $done = false;
    if ($done) return; $done = true;
    pp_migrate();
    foreach ([['invites', "AND used_at IS NULL"], ['users', '']] as $t) {
        try { $rows = all("SELECT id,name FROM {$t[0]} WHERE (pid IS NULL OR pid='') {$t[1]}"); }
        catch (\Throwable $e) { continue; }
        foreach ($rows as $r) {
            $m = pp_match($r['name']);
            if ($m) { try { q("UPDATE {$t[0]} SET pid=? WHERE id=?", [$m['p'], (int)$r['id']]); } catch (\Throwable $e) {} }
        }
    }
}

function pp_json($v) {
    if (is_array($v)) return $v;
    $d = json_decode((string)$v, true);
    return is_array($d) ? $d : [];
}

/** "son of A and B", or failing that "married to C". Empty when the tree holds
 *  nobody around them — which is itself worth seeing, because a person with no
 *  connections is usually one that was typed in by hand and never joined up. */
function pp_rel($p, $byPid, $fams) {
    $sex  = strtoupper(substr((string)($p['sex'] ?? ''), 0, 1));
    $word = $sex === 'F' ? 'daughter' : ($sex === 'M' ? 'son' : 'child');

    foreach (pp_json($p['famc'] ?? '') as $fid) {
        if (!isset($fams[$fid])) continue;
        $f   = $fams[$fid];
        $dad = isset($byPid[$f['husb']]) ? $byPid[$f['husb']] : '';
        $mom = isset($byPid[$f['wife']]) ? $byPid[$f['wife']] : '';
        if ($dad !== '' && $mom !== '') return $word . ' of ' . $dad . ' and ' . $mom;
        if ($dad !== '' || $mom !== '') return $word . ' of ' . ($dad !== '' ? $dad : $mom);
    }
    foreach (pp_json($p['fams'] ?? '') as $fid) {
        if (!isset($fams[$fid])) continue;
        $f     = $fams[$fid];
        $other = ($f['husb'] === $p['pid']) ? $f['wife'] : $f['husb'];
        if ($other !== '' && isset($byPid[$other])) return 'married to ' . $byPid[$other];
    }
    return '';
}

/** Everyone in the tree, as compact rows for the name box.
 *
 *  Keys are one letter because this whole list is written into the page and
 *  there are three-quarters of a thousand of them.
 *    n name   p pid   y years   r who they belong to
 *    l 1 if living    s 'member' | 'invited' | '' */
function pp_people($fresh = false) {
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    pp_migrate();

    try {
        $rows = all("SELECT pid,name,given,surname,sex,birth_date,death_date,living,famc,fams FROM persons");
        $fams = [];
        foreach (all("SELECT fid,husb,wife FROM families") as $f) $fams[$f['fid']] = $f;
    } catch (\Throwable $e) { return $cache = []; }

    /* names first, so a relationship can be spelled out without a second query */
    $byPid = [];
    foreach ($rows as $p) $byPid[$p['pid']] = trim((string)$p['name']);

    /* Who already has an account or a live invitation. Matched on the tree id
       where we have one and on the name otherwise, because the invitations made
       before today were never linked to anybody. */
    $taken = [];
    try {
        foreach (all("SELECT name,pid FROM users") as $u) {
            if (!empty($u['pid'])) $taken[$u['pid']] = 'member';
            $k = pp_key($u['name']); if ($k !== '') $taken['n:' . $k] = 'member';
        }
    } catch (\Throwable $e) {}
    try {
        foreach (all("SELECT name,pid FROM invites WHERE used_at IS NULL") as $i) {
            if (!empty($i['pid']) && !isset($taken[$i['pid']])) $taken[$i['pid']] = 'invited';
            $k = pp_key($i['name']);
            if ($k !== '' && !isset($taken['n:' . $k])) $taken['n:' . $k] = 'invited';
        }
    } catch (\Throwable $e) {}

    $out = [];
    foreach ($rows as $p) {
        $name = trim((string)$p['name']);
        if ($name === '') continue;
        $k = pp_key($name);
        $s = '';
        if (isset($taken[$p['pid']]))      $s = $taken[$p['pid']];
        elseif (isset($taken['n:' . $k]))  $s = $taken['n:' . $k];
        $out[] = [
            'n' => $name,
            'p' => $p['pid'],
            'y' => lifespan($p),
            'r' => pp_rel($p, $byPid, $fams),
            'l' => empty($p['living']) ? 0 : 1,
            's' => $s,
        ];
    }

    /* Living relatives first — they are the ones anybody is ever invited. */
    usort($out, 'pp_cmp');
    return $cache = $out;
}

function pp_cmp($a, $b) {
    if ($a['l'] !== $b['l']) return $b['l'] - $a['l'];
    return strcasecmp($a['n'], $b['n']);
}

/** A name flattened down to what two spellings of it have in common: lower
 *  case, no accents, no punctuation, single spaces. "D`Vonte Aery" and
 *  "D'Vonte  Aery" both come out as "dvonte aery". */
function pp_key($name) {
    $s = trim((string)$name);
    if ($s === '') return '';
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($t !== false) $s = $t;
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9 ]+/', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

/** The one person this typed name can only mean, or null.
 *
 *  Only ever returns a match when exactly one person answers to it. Two
 *  William Battles and this returns nothing, which is the honest answer —
 *  guessing at that would quietly attach an invitation to the wrong cousin.
 *
 *  Nobody writes out a middle name on an invitation. Of the thirty-eight
 *  invitations already waiting when this was built, thirteen looked like
 *  strangers to an exact comparison and twelve of them were simply the tree's
 *  full name minus its middle: Rodney Battles is Rodney Augustus Battles, and
 *  Sherry Howard is Sherry Kay Howard. So a name that is a subset of exactly
 *  one person's names counts as that person — "exactly one" doing the same
 *  work as before, since Keith Battles could be any of three and therefore
 *  still matches nobody. */
function pp_match($name) {
    $k = pp_key($name);
    if ($k === '') return null;

    $people = pp_people();
    $hits = [];
    foreach ($people as $row) if (pp_key($row['n']) === $k) $hits[] = $row;
    if (count($hits) === 1) return $hits[0];
    if ($hits) return null;                         // more than one exact — refuse

    $want = explode(' ', $k);
    if (count($want) < 2) return null;              // a surname on its own means nothing
    $sur  = $want[count($want) - 1];
    $near = [];
    foreach ($people as $row) {
        $have = explode(' ', pp_key($row['n']));
        /* the surname has to be one of their names, or this is a different
           family altogether and the rest is coincidence */
        if (!in_array($sur, $have, true)) continue;
        if (!array_diff($want, $have)) $near[] = $row;
    }
    return count($near) === 1 ? $near[0] : null;
}

/** Is this a real pid in the tree? Returns the row, or null. */
function pp_person($pid) {
    $pid = trim((string)$pid);
    if ($pid === '') return null;
    foreach (pp_people() as $row) if ($row['p'] === $pid) return $row;
    return null;
}
