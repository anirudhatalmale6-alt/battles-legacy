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

/* ============================================================
 * Member suggestions — family members propose adds/edits to their
 * CLOSE relatives (self, parents, siblings, spouse, children); every
 * suggestion waits in a queue for the admin (William) to approve.
 * ============================================================ */

function te_migrate() {
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
    try { db()->exec("ALTER TABLE users ADD COLUMN pid VARCHAR(16) DEFAULT ''"); } catch (Exception $e) {}
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
      'sex'        => in_array(strtoupper($src['c_sex'] ?? ''), ['M','F'], true) ? strtoupper($src['c_sex']) : '',
      'birth_date' => trim($src['c_birth'] ?? ''),
      'birth_place'=> trim($src['c_birthplace'] ?? ''),
      'death_date' => trim($src['c_death'] ?? ''),
      'death_place'=> trim($src['c_deathplace'] ?? ''),
      'living'     => !empty($src['c_living']) ? 1 : 0,
    ];
}

/** apply a vital-fields edit to a person */
function te_update_person($pid, $f) {
    $given = trim($f['given'] ?? ''); $surname = trim($f['surname'] ?? '');
    $name  = trim($given . ' ' . $surname); if ($name === '') $name = 'Unknown';
    q("UPDATE persons SET name=?,given=?,surname=?,sex=?,birth_date=?,birth_place=?,death_date=?,death_place=?,living=? WHERE pid=?",
      [$name,$given,$surname,($f['sex']??''),($f['birth_date']??''),($f['birth_place']??''),
       ($f['death_date']??''),($f['death_place']??''),(int)($f['living']??0),$pid]);
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
