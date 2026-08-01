<?php
/** Emits window.GED / window.PHOTOS for the tree. Living relatives are redacted unless a family member is logged in. */
require __DIR__ . '/../src/bootstrap.php';
header('Content-Type: application/javascript; charset=utf-8');

$member = logged_in();

$indi = [];
foreach (all("SELECT * FROM persons") as $p) {
    $living = (int)$p['living'] === 1;
    $rec = [
        'id' => $p['pid'], 'name' => $p['name'], 'given' => $p['given'], 'surname' => $p['surname'],
        'sex' => $p['sex'],
        'birth' => ['date' => $p['birth_date'], 'place' => $p['birth_place']],
        'death' => ['date' => $p['death_date'], 'place' => $p['death_place']],
        'burial'=> ['date' => $p['buri_date'],  'place' => $p['buri_place']],
        'occupation' => json_decode($p['occupation'] ?: '[]', true),
        'education'  => json_decode($p['education'] ?: '[]', true),
        'notes'      => json_decode($p['notes'] ?: '[]', true),
        'famc' => json_decode($p['famc'] ?: '[]', true),
        'fams' => json_decode($p['fams'] ?: '[]', true),
        'living' => $living,
    ];
    if ($living && !$member) {
        // Privatize the living for the public preview.
        $first = preg_split('/\s+/', trim($p['given'])) ?: ['Living'];
        $ini = $p['surname'] ? substr($p['surname'], 0, 1) . '.' : '';
        $rec['name'] = trim($first[0] . ' ' . $ini);
        $rec['given'] = $first[0];
        $rec['surname'] = $ini;
        $rec['birth'] = ['date' => '', 'place' => ''];
        $rec['death'] = ['date' => '', 'place' => ''];
        $rec['burial'] = ['date' => '', 'place' => ''];
        $rec['occupation'] = $rec['education'] = $rec['notes'] = [];
    }
    $indi[$p['pid']] = $rec;
}

$fam = [];
foreach (all("SELECT * FROM families") as $f) {
    $fam[$f['fid']] = [
        'id' => $f['fid'], 'husb' => $f['husb'], 'wife' => $f['wife'],
        'chil' => json_decode($f['chil'] ?: '[]', true),
        'marr' => ['date' => $f['marr_date'], 'place' => $f['marr_place']],
    ];
}

// Photo maps — one representative photo per person the viewer is allowed to see.
$photos = [];
$rows = all("SELECT pid, path FROM photos WHERE status='approved' ORDER BY is_primary DESC, id");
foreach ($rows as $r) {
    if (isset($photos[$r['pid']])) continue;  // the chosen main photo (is_primary, else first) wins
    $liv = (int)($indi[$r['pid']]['living'] ?? 0) === 1;
    if ($liv && !$member) continue;          // hide living relatives' photos from the public
    $photos[$r['pid']] = $r['path'];
}

echo "window.GED=" . json_encode(['indi' => $indi, 'fam' => $fam], JSON_UNESCAPED_UNICODE) . ";\n";
echo "window.PHOTOS=" . json_encode($photos, JSON_UNESCAPED_UNICODE) . ";\n";
echo "window.FULL=" . json_encode($photos, JSON_UNESCAPED_UNICODE) . ";\n";
echo "window.IS_MEMBER=" . ($member ? 'true' : 'false') . ";\n";
