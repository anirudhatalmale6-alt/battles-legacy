<?php
/** Admin tree editing — add new people (children / spouses) and link them into
 *  the existing family records. Mirrors the GEDCOM data shape used by data.php. */
require_once __DIR__ . '/db.php';

/* ---------------------------------------------------------------------------
 * Marriage / partnership status. A GEDCOM family record only says two people
 * were joined, never whether they still are — so the tree used to show an
 * ex-husband exactly like a current one. These two columns carry that.
 * ------------------------------------------------------------------------- */
/** status key => [short label for the tree, long label for a profile] */
function te_rel_statuses() {
    return [
      ''          => ['m.',    'Spouse'],
      'married'   => ['m.',    'Spouse'],
      'divorced'  => ['div.',  'Former spouse (divorced)'],
      'separated' => ['sep.',  'Separated'],
      'widowed'   => ['m.',    'Spouse (widowed)'],
      'partner'   => ['ptnr.', 'Partner'],
      'former'    => ['frmr.', 'Former partner'],
    ];
}
function te_rel_ok($s) { return array_key_exists((string)$s, te_rel_statuses()) ? (string)$s : ''; }
function te_rel_short($s) { $m = te_rel_statuses(); return ($m[te_rel_ok($s)])[0]; }
function te_rel_long($s)  { $m = te_rel_statuses(); return ($m[te_rel_ok($s)])[1]; }
/** true for anything that ended — used to label and to draw the link differently */
function te_rel_ended($s) { return in_array(te_rel_ok($s), ['divorced','separated','former'], true); }

/** set the status of one family record (admin only) */
function te_set_rel($fid, $status, $end = '') {
    te_migrate();
    if (!one("SELECT fid FROM families WHERE fid=?", [$fid])) return false;
    q("UPDATE families SET rel_status=?, rel_end=? WHERE fid=?",
      [te_rel_ok($status), substr(trim((string)$end), 0, 80), $fid]);
    return true;
}

/** the family record that joins two people, or null */
function te_couple_fid($pidA, $pidB) {
    foreach (te_json($pidA, 'fams') as $fid) {
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if ($f && ($f['husb'] === $pidB || $f['wife'] === $pidB)) return $fid;
    }
    return null;
}

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
    $name    = te_full_name($f);
    if (trim($f['death_date'] ?? '') !== '') $f['living'] = 0;
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

/* ============================================================
 * Member suggestions — family members propose adds/edits to their
 * CLOSE relatives (self, parents, siblings, spouse, children); every
 * suggestion waits in a queue for the admin (William) to approve.
 * ============================================================ */

function te_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS tree_suggestions (
      id $AI, kind VARCHAR(20) NOT NULL, target_pid VARCHAR(16) DEFAULT '',
      payload TEXT, submitter VARCHAR(160) DEFAULT '', user_id INT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    try { db()->exec("CREATE INDEX idx_ts_status ON tree_suggestions(status)"); } catch (Exception $e) {}
    // link a member account to their own person in the tree
    db_add_column('users', 'pid', "VARCHAR(16) DEFAULT ''");
    // marriage status: a GEDCOM family says two people were joined, never whether they still are
    db_add_column('families', 'rel_status', "VARCHAR(20) DEFAULT ''");
    db_add_column('families', 'rel_end',    "VARCHAR(80) DEFAULT ''");
}

/** the pid a member account is linked to (their own person), or '' */
function te_user_pid($user) {
    if (!$user) return '';
    if (array_key_exists('pid', $user)) return trim($user['pid'] ?? '');
    $r = one("SELECT pid FROM users WHERE id=?", [$user['id']]);
    return $r ? trim($r['pid'] ?? '') : '';
}
function te_set_user_pid($user_id, $pid) { q("UPDATE users SET pid=? WHERE id=?", [$pid, (int)$user_id]); }

/** readable label for a person: "Name (1932)" */
function te_person_label($pid) {
    $p = one("SELECT name,birth_date FROM persons WHERE pid=?", [$pid]);
    if (!$p) return $pid;
    $y = ''; if (preg_match('/\d{4}/', (string)$p['birth_date'], $m)) $y = ' (' . $m[0] . ')';
    return ($p['name'] ?: 'Unknown') . $y;
}

/** pids of $pid's close relatives (self, parents, siblings, spouses, children) */
function te_close_set($pid) {
    $set = [$pid => true];
    if (!$pid) return $set;
    foreach (te_json($pid, 'famc') as $fid) {                 // parents + siblings
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f) continue;
        foreach (['husb','wife'] as $k) if ($f[$k]) $set[$f[$k]] = true;
        foreach (json_decode($f['chil'] ?: '[]', true) ?: [] as $c) $set[$c] = true;
    }
    foreach (te_json($pid, 'fams') as $fid) {                 // spouses + children
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f) continue;
        $sp = ($f['husb'] === $pid) ? $f['wife'] : $f['husb'];
        if ($sp) $set[$sp] = true;
        foreach (json_decode($f['chil'] ?: '[]', true) ?: [] as $c) $set[$c] = true;
    }
    return $set;
}
function te_can_edit($memberPid, $targetPid) {
    if (!$memberPid || !$targetPid) return false;
    return isset(te_close_set($memberPid)[$targetPid]);
}

/** editable vital fields, sanitized from a POST-like array */
function te_clean_fields($src) {
    return [
      'given'      => trim($src['c_given'] ?? ''),
      'surname'    => trim($src['c_surname'] ?? ''),
      'suffix'     => trim($src['c_suffix'] ?? ''),
      'sex'        => in_array(strtoupper($src['c_sex'] ?? ''), ['M','F'], true) ? strtoupper($src['c_sex']) : '',
      'birth_date' => trim($src['c_birth'] ?? ''),
      'birth_place'=> trim($src['c_birthplace'] ?? ''),
      'death_date' => trim($src['c_death'] ?? ''),
      'death_place'=> trim($src['c_deathplace'] ?? ''),
      'living'     => !empty($src['c_living']) ? 1 : 0,
    ];
}

/** "Jr." / "III" — whatever the stored full name carries beyond given + surname.
 *  The tree came from GEDCOM, where the suffix is a third part of the name; it
 *  has no column of its own, so it has to be recovered from the full name or a
 *  save would quietly drop it. */
function te_name_suffix($p) {
    $full = trim((string)($p['name'] ?? ''));
    $base = trim(trim((string)($p['given'] ?? '')) . ' ' . trim((string)($p['surname'] ?? '')));
    if ($base === '' || $full === '' || strcasecmp($full, $base) === 0) return '';
    if (stripos($full, $base) === 0) return trim(substr($full, strlen($base)));
    return '';
}

/** The one place a person's display name is assembled. */
function te_full_name($f) {
    $name = trim(trim($f['given'] ?? '') . ' ' . trim($f['surname'] ?? ''));
    $sfx  = trim($f['suffix'] ?? '');
    if ($sfx !== '') $name = trim($name . ' ' . $sfx);
    return $name !== '' ? $name : 'Unknown';
}

/** apply a vital-fields edit to a person */
function te_update_person($pid, $f) {
    $given = trim($f['given'] ?? ''); $surname = trim($f['surname'] ?? '');
    $name  = te_full_name($f);
    /* A death date and "still living" cannot both be true. Trusting the date
       means a passing recorded here also takes the person out of the private
       living set and off the birthday list, which is what anyone would expect. */
    $living = (int)($f['living'] ?? 0);
    if (trim($f['death_date'] ?? '') !== '') $living = 0;
    q("UPDATE persons SET name=?,given=?,surname=?,sex=?,birth_date=?,birth_place=?,death_date=?,death_place=?,living=? WHERE pid=?",
      [$name,$given,$surname,($f['sex']??''),($f['birth_date']??''),($f['birth_place']??''),
       ($f['death_date']??''),($f['death_place']??''),$living,$pid]);
}

/** add a sibling to $pid (a child of $pid's parents' family). returns new pid or null */
function te_add_sibling($pid, $f) {
    $famc = te_json($pid, 'famc');
    if (!$famc) return null;
    $fid = $famc[0];
    $f['famc'] = [$fid];
    $cpid = te_create_person($f);
    $fam  = one("SELECT chil FROM families WHERE fid=?", [$fid]);
    $chil = json_decode($fam['chil'] ?: '[]', true) ?: []; $chil[] = $cpid;
    q("UPDATE families SET chil=? WHERE fid=?", [json_encode(array_values(array_unique($chil))), $fid]);
    return $cpid;
}

function te_add_suggestion($kind, $target_pid, $fields, $user) {
    q("INSERT INTO tree_suggestions (kind,target_pid,payload,submitter,user_id) VALUES (?,?,?,?,?)",
      [$kind, $target_pid, json_encode($fields), trim($user['name'] ?? '') ?: 'Family member', $user['id'] ?? null]);
}
function te_suggestions($status = 'pending') {
    return all("SELECT * FROM tree_suggestions WHERE status=? ORDER BY created_at DESC, id DESC", [$status]);
}
function te_suggestion($id) { return one("SELECT * FROM tree_suggestions WHERE id=?", [(int)$id]); }
function te_suggestion_count() { $r = one("SELECT COUNT(*) c FROM tree_suggestions WHERE status='pending'"); return $r ? (int)$r['c'] : 0; }
function te_decline_suggestion($id) { q("UPDATE tree_suggestions SET status='declined' WHERE id=?", [(int)$id]); }

/** apply a pending suggestion; returns true on success */
function te_apply_suggestion($id) {
    $s = te_suggestion($id);
    if (!$s || $s['status'] !== 'pending') return false;
    $f = json_decode($s['payload'] ?: '{}', true) ?: [];
    $ok = true;
    if      ($s['kind'] === 'edit')       { if (one("SELECT pid FROM persons WHERE pid=?", [$s['target_pid']])) te_update_person($s['target_pid'], $f); else $ok = false; }
    elseif  ($s['kind'] === 'add_child')  { $ok = (bool) te_add_child($s['target_pid'], $f); }
    elseif  ($s['kind'] === 'add_spouse') { $ok = (bool) te_add_spouse($s['target_pid'], $f); }
    elseif  ($s['kind'] === 'add_sibling'){ $ok = (bool) te_add_sibling($s['target_pid'], $f); }
    else    { $ok = false; }
    if ($ok) q("UPDATE tree_suggestions SET status='applied' WHERE id=?", [(int)$id]);
    return $ok;
}

/** Connect two people who are BOTH already in the tree.
 *  $rel (from $pidA's view): 'spouse' | 'child' (B is A's child) | 'parent' (B is A's parent).
 *  Returns [bool ok, string message]. */
function te_link_existing($pidA, $pidB, $rel) {
    if (!$pidA || !$pidB || $pidA === $pidB) return [false, 'Please choose a different person to connect.'];
    $A = one("SELECT * FROM persons WHERE pid=?", [$pidA]);
    $B = one("SELECT * FROM persons WHERE pid=?", [$pidB]);
    if (!$A || !$B) return [false, 'One of those people is not in the tree.'];

    if ($rel === 'spouse') {
        foreach (te_json($pidA, 'fams') as $fid) {
            $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
            if ($f && ($f['husb'] === $pidB || $f['wife'] === $pidB)) return [false, $A['name'] . ' and ' . $B['name'] . ' are already connected as spouses.'];
        }
        $fid = te_new_fid();
        $aM = strtoupper($A['sex']) === 'M'; $bM = strtoupper($B['sex']) === 'M';
        if ($aM && !$bM)      { $husb = $pidA; $wife = $pidB; }
        elseif ($bM && !$aM)  { $husb = $pidB; $wife = $pidA; }
        else                  { $husb = $pidA; $wife = $pidB; }
        q("INSERT INTO families (fid,husb,wife,marr_date,marr_place,chil) VALUES (?,?,?,?,?,?)", [$fid, $husb, $wife, '', '', '[]']);
        $af = te_json($pidA, 'fams'); $af[] = $fid; te_set_json($pidA, 'fams', $af);
        $bf = te_json($pidB, 'fams'); $bf[] = $fid; te_set_json($pidB, 'fams', $bf);
        return [true, $A['name'] . ' and ' . $B['name'] . ' are now connected as spouses.'];
    }

    if ($rel === 'child' || $rel === 'parent') {
        $parent = $rel === 'child' ? $pidA : $pidB;
        $child  = $rel === 'child' ? $pidB : $pidA;
        foreach (te_json($child, 'famc') as $fid) {
            $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
            if ($f && ($f['husb'] === $parent || $f['wife'] === $parent)) return [false, 'They are already connected as parent and child.'];
        }
        $pfams = te_json($parent, 'fams');
        if ($pfams) { $fid = $pfams[0]; }
        else {
            $fid  = te_new_fid();
            $pRow = one("SELECT sex FROM persons WHERE pid=?", [$parent]);
            if (strtoupper($pRow['sex']) === 'F') { $husb = ''; $wife = $parent; } else { $husb = $parent; $wife = ''; }
            q("INSERT INTO families (fid,husb,wife,marr_date,marr_place,chil) VALUES (?,?,?,?,?,?)", [$fid, $husb, $wife, '', '', '[]']);
            $pf = te_json($parent, 'fams'); $pf[] = $fid; te_set_json($parent, 'fams', $pf);
        }
        $fam  = one("SELECT chil FROM families WHERE fid=?", [$fid]);
        $chil = json_decode($fam['chil'] ?: '[]', true) ?: []; $chil[] = $child;
        q("UPDATE families SET chil=? WHERE fid=?", [json_encode(array_values(array_unique($chil))), $fid]);
        $cf = te_json($child, 'famc'); $cf[] = $fid; te_set_json($child, 'famc', $cf);
        $pn = one("SELECT name FROM persons WHERE pid=?", [$parent]); $cn = one("SELECT name FROM persons WHERE pid=?", [$child]);
        return [true, ($cn['name'] ?? 'They') . ' is now connected as ' . ($pn['name'] ?? 'their parent') . '\'s child.'];
    }
    return [false, 'Please choose how they are related.'];
}

/** remove a family entirely and unlink its fid from every person's fams/famc */
function te_drop_family($fid) {
    if (!$fid) return;
    foreach (all("SELECT pid,fams,famc FROM persons") as $p) {
        foreach (['fams','famc'] as $col) {
            $arr = json_decode($p[$col] ?: '[]', true) ?: [];
            if (in_array($fid, $arr)) q("UPDATE persons SET $col=? WHERE pid=?", [json_encode(array_values(array_diff($arr, [$fid]))), $p['pid']]);
        }
    }
    q("DELETE FROM families WHERE fid=?", [$fid]);
}

/** delete a person entirely: detach from every family, drop now-empty families,
 *  remove their photos, then delete the person. returns [ok, message] */
function te_delete_person($pid) {
    $p = one("SELECT * FROM persons WHERE pid=?", [$pid]);
    if (!$p) return [false, 'That person is not in the tree.'];
    foreach (all("SELECT * FROM families WHERE husb=? OR wife=?", [$pid, $pid]) as $f) {
        $chil = json_decode($f['chil'] ?: '[]', true) ?: [];
        $husb = $f['husb'] === $pid ? '' : $f['husb'];
        $wife = $f['wife'] === $pid ? '' : $f['wife'];
        if ($husb === '' && $wife === '' && !$chil) { te_drop_family($f['fid']); }
        else { q("UPDATE families SET husb=?,wife=? WHERE fid=?", [$husb, $wife, $f['fid']]); }
    }
    // remove from any family's children list
    foreach (all("SELECT fid,husb,wife,chil FROM families") as $f) {
        $chil = json_decode($f['chil'] ?: '[]', true) ?: [];
        if (in_array($pid, $chil)) {
            $chil = array_values(array_diff($chil, [$pid]));
            if (!$chil && $f['husb'] === '' && $f['wife'] === '') te_drop_family($f['fid']);
            else q("UPDATE families SET chil=? WHERE fid=?", [json_encode($chil), $f['fid']]);
        }
    }
    /* Photos. A picture filed under this person may have other people in it —
       a group photograph is stored once and shown on each of their pages. So
       the file is only destroyed when nobody is left in it; otherwise it is
       handed to somebody who is, and stays where it is on disk. */
    require_once __DIR__ . '/photo_people.php';
    pp_migrate();
    foreach (all("SELECT * FROM photos WHERE pid=?", [$pid]) as $ph) {
        pp_untag($ph['id'], $pid);
        $heir = one("SELECT pid FROM photo_people WHERE photo_id=? ORDER BY pid LIMIT 1", [$ph['id']]);
        if ($heir) {
            q("UPDATE photos SET pid=? WHERE id=?", [$heir['pid'], $ph['id']]);
            pp_reseat_primary($heir['pid']);
            continue;
        }
        if (!empty($ph['path'])) { $abs = dirname(__DIR__) . '/public/' . $ph['path']; if (is_file($abs)) @unlink($abs); }
        pp_clear($ph['id']);
        q("DELETE FROM photos WHERE id=?", [$ph['id']]);
    }
    /* Anything they were only tagged in (owned by someone else) simply loses
       the tag — the picture belongs to the other people in it. */
    q("DELETE FROM photo_people WHERE pid=?", [$pid]);
    q("DELETE FROM persons WHERE pid=?", [$pid]);
    return [true, ($p['name'] ?: 'That person') . ' has been removed from the tree.'];
}

/** sever a single relationship between $pid and $other ($type: spouse|child|parent from $pid's view) */
function te_disconnect($pid, $other, $type) {
    if (!$pid || !$other) return [false, 'Nothing to disconnect.'];
    if ($type === 'spouse') {
        foreach (te_json($pid, 'fams') as $fid) {
            $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
            if (!$f || ($f['husb'] !== $other && $f['wife'] !== $other)) continue;
            $chil = json_decode($f['chil'] ?: '[]', true) ?: [];
            if ($chil) { // keep the family (children stay with $pid); just remove the spouse
                $husb = $f['husb'] === $other ? '' : $f['husb'];
                $wife = $f['wife'] === $other ? '' : $f['wife'];
                q("UPDATE families SET husb=?,wife=? WHERE fid=?", [$husb, $wife, $fid]);
                $of = te_json($other, 'fams'); te_set_json($other, 'fams', array_diff($of, [$fid]));
            } else { te_drop_family($fid); }
            return [true, 'Spouse connection removed.'];
        }
        return [false, 'They are not connected as spouses.'];
    }
    // child / parent: figure out which family joins them
    $parent = $type === 'child' ? $pid : $other;
    $child  = $type === 'child' ? $other : $pid;
    foreach (te_json($child, 'famc') as $fid) {
        $f = one("SELECT * FROM families WHERE fid=?", [$fid]);
        if (!$f || ($f['husb'] !== $parent && $f['wife'] !== $parent)) continue;
        $chil = array_values(array_diff(json_decode($f['chil'] ?: '[]', true) ?: [], [$child]));
        q("UPDATE families SET chil=? WHERE fid=?", [json_encode($chil), $fid]);
        te_set_json($child, 'famc', array_diff(te_json($child, 'famc'), [$fid]));
        if (!$chil && $f['husb'] === '' && $f['wife'] === '') te_drop_family($fid);
        return [true, 'Parent/child connection removed.'];
    }
    return [false, 'They are not connected that way.'];
}

/** all people (except $exclude) as [pid,label] for a connect picker, ordered by name */
function te_people_options($exclude = '') {
    $out = [];
    foreach (all("SELECT pid,name,birth_date FROM persons ORDER BY name") as $p) {
        if ($p['pid'] === $exclude) continue;
        $y = ''; if (preg_match('/\d{4}/', (string)$p['birth_date'], $m)) $y = ' (' . $m[0] . ')';
        $out[] = ['pid' => $p['pid'], 'label' => ($p['name'] ?: 'Unknown') . $y];
    }
    return $out;
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
