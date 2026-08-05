<?php
/** Admin tree editing — add new people (children / spouses) and link them into
 *  the existing family records. Mirrors the GEDCOM data shape used by data.php. */
require_once __DIR__ . '/db.php';

/** next unique person id (@I<max+1>@), safe against the imported GEDCOM ids */
function te_new_pid() {
    $max = 0;
    foreach (all("SELECT pid FROM persons") as $r) {
        if (preg_match('/(\d+)/', $r['pid'], $m)) $max = max($max, (int)$m[1]);
    }
    return '@I' . ($max + 1) . '@';
}
function te_new_fid() {
    $max = 0;
    foreach (all("SELECT fid FROM families") as $r) {
        if (preg_match('/(\d+)/', $r['fid'], $m)) $max = max($max, (int)$m[1]);
    }
    return '@F' . ($max + 1) . '@';
}

function te_json($pid, $field) {
    $r = one("SELECT $field FROM persons WHERE pid=?", [$pid]);
    return $r ? (json_decode($r[$field] ?: '[]', true) ?: []) : [];
}
function te_set_json($pid, $field, $arr) {
    q("UPDATE persons SET $field=? WHERE pid=?", [json_encode(array_values(array_unique($arr))), $pid]);
}

/** create a person; $f: given,surname,sex,birth_date,birth_place,death_date,death_place,living,famc[],fams[]. returns pid */
function te_create_person($f) {
    $pid     = te_new_pid();
    $given   = trim($f['given'] ?? '');
    $surname = trim($f['surname'] ?? '');
    $name    = trim($given . ' ' . $surname);
    if ($name === '') $name = 'Unknown';
    q("INSERT INTO persons (pid,name,given,surname,sex,birth_date,birth_place,death_date,death_place,buri_date,buri_place,living,famc,fams,occupation,education,notes)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
      [$pid, $name, $given, $surname, ($f['sex'] ?? ''),
       ($f['birth_date'] ?? ''), ($f['birth_place'] ?? ''),
       ($f['death_date'] ?? ''),  ($f['death_place'] ?? ''), '', '',
       (int)($f['living'] ?? 0),
       json_encode($f['famc'] ?? []), json_encode($f['fams'] ?? []),
       '[]', '[]', '[]']);
    return $pid;
}

/** add a spouse to $pid; creates a family linking them. returns new spouse pid */
function te_add_spouse($pid, $f) {
    $person = one("SELECT * FROM persons WHERE pid=?", [$pid]);
    if (!$person) return null;
    $spid = te_create_person($f);
    $fid  = te_new_fid();
    $pSex = strtoupper($person['sex']);
    $sSex = strtoupper($f['sex'] ?? '');
    if ($pSex === 'F' || $sSex === 'M') { $husb = $spid; $wife = $pid; }
    else                                { $husb = $pid;  $wife = $spid; }
    q("INSERT INTO families (fid,husb,wife,marr_date,marr_place,chil) VALUES (?,?,?,?,?,?)",
      [$fid, $husb, $wife, '', '', '[]']);
    $pf = te_json($pid, 'fams'); $pf[] = $fid; te_set_json($pid, 'fams', $pf);
    $sf = te_json($spid, 'fams'); $sf[] = $fid; te_set_json($spid, 'fams', $sf);
    return $spid;
}

/** add a child under $pid. Uses $pid's spouse-family if any (or $fid), else makes a solo family. returns child pid */
function te_add_child($pid, $f, $fid = '') {
    $person = one("SELECT * FROM persons WHERE pid=?", [$pid]);
    if (!$person) return null;
    $fams = te_json($pid, 'fams');
    if ($fid && in_array($fid, $fams)) {
        // caller chose a specific family
    } elseif ($fams) {
        $fid = $fams[0];
    } else {
        $fid  = te_new_fid();
        $pSex = strtoupper($person['sex']);
        if ($pSex === 'F') { $husb = ''; $wife = $pid; } else { $husb = $pid; $wife = ''; }
        q("INSERT INTO families (fid,husb,wife,marr_date,marr_place,chil) VALUES (?,?,?,?,?,?)",
          [$fid, $husb, $wife, '', '', '[]']);
        $pf = te_json($pid, 'fams'); $pf[] = $fid; te_set_json($pid, 'fams', $pf);
    }
    $f['famc'] = [$fid];
    $cpid = te_create_person($f);
    $fam  = one("SELECT chil FROM families WHERE fid=?", [$fid]);
    $chil = json_decode($fam['chil'] ?: '[]', true) ?: [];
    $chil[] = $cpid;
    q("UPDATE families SET chil=? WHERE fid=?", [json_encode(array_values(array_unique($chil))), $fid]);
    return $cpid;
}

/** the families where $pid is a parent, with a readable spouse label (for the "add child to…" picker) */
function te_parent_families($pid) {
    $out = [];
    foreach (te_json($pid, 'fams') as $fid) {
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f) continue;
        $spid  = ($f['husb'] === $pid) ? $f['wife'] : $f['husb'];
        $label = 'this family';
        if ($spid) { $sp = one("SELECT name FROM persons WHERE pid=?", [$spid]); if ($sp && $sp['name']) $label = 'with ' . $sp['name']; }
        $out[] = ['fid' => $fid, 'label' => $label];
    }
    return $out;
}
