<?php
/**
 * Parse a GEDCOM (.ged) file into the persons + families tables.
 * Usage: php bin/import_gedcom.php /path/to/battlesfamily.ged
 * Living vs deceased: has a death date OR born <= 1935 => deceased (shown publicly);
 *                     otherwise flagged living (visible to logged-in family only).
 */
require __DIR__ . '/../src/db.php';

$path = $argv[1] ?? (dirname(__DIR__, 1) . '/battlesfamily.ged');
if (!is_file($path)) { fwrite(STDERR, "GEDCOM not found: $path\n"); exit(1); }

$raw = preg_split('/\r\n|\r|\n/', file_get_contents($path));
$lines = [];
foreach ($raw as $ln) {
    if (trim($ln) === '') continue;
    if (!preg_match('/^(\d+)\s+(@[^@]+@)?\s*([A-Z0-9_]+)?\s?(.*)$/', $ln, $m)) continue;
    $lines[] = [(int)$m[1], $m[2] ?: '', $m[3] ?: '', trim($m[4] ?? '')];
}
$N = count($lines);

function read_event($lines, $start, $N) {
    $L = $lines[$start][0]; $j = $start + 1; $date = $place = '';
    while ($j < $N && $lines[$j][0] > $L) {
        [$lvl, , $tag, $val] = $lines[$j];
        if ($lvl === $L + 1 && $tag === 'DATE') $date = $val;
        elseif ($lvl === $L + 1 && $tag === 'PLAC') $place = $val;
        $j++;
    }
    return [$date, $place, $j];
}
function read_note($lines, $start, $N) {
    $L = $lines[$start][0]; $text = $lines[$start][3]; $j = $start + 1;
    while ($j < $N && $lines[$j][0] > $L) {
        [$lvl, , $tag, $val] = $lines[$j];
        if ($lvl === $L + 1 && $tag === 'CONC') $text .= $val;
        elseif ($lvl === $L + 1 && $tag === 'CONT') $text .= "\n" . $val;
        $j++;
    }
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return [trim($text), $j];
}

$indi = []; $fam = [];
$i = 0;
while ($i < $N) {
    [$level, $xref, $tag, $val] = $lines[$i];
    if ($level === 0 && $xref && $tag === 'INDI') {
        $pid = $xref;
        $r = ['id'=>$pid,'name'=>'','given'=>'','surname'=>'','sex'=>'',
              'birth'=>['','',''],'death'=>['',''],'buri'=>['',''],
              'occupation'=>[],'education'=>[],'notes'=>[],'famc'=>[],'fams'=>[]];
        $i++;
        while ($i < $N && $lines[$i][0] !== 0) {
            [$lvl,,$t,$v] = $lines[$i];
            if ($t==='NAME' && $lvl===1) {
                if (preg_match('/^(.*?)\/(.*?)\/?\s*$/', $v, $gm)) {
                    $r['given']=trim($gm[1]); $r['surname']=trim($gm[2]);
                    $r['name']=trim($r['given'].' '.$r['surname']);
                } else { $r['name']=trim($v); $r['given']=trim($v); }
            } elseif ($t==='SEX' && $lvl===1) { $r['sex']=substr(trim($v),0,1);
            } elseif ($t==='BIRT' && $lvl===1) { [$d,$p,$nx]=read_event($lines,$i,$N); $r['birth']=[$d,$p]; $i=$nx; continue;
            } elseif ($t==='DEAT' && $lvl===1) { [$d,$p,$nx]=read_event($lines,$i,$N); $r['death']=[$d,$p]; $i=$nx; continue;
            } elseif ($t==='BURI' && $lvl===1) { [$d,$p,$nx]=read_event($lines,$i,$N); $r['buri']=[$d,$p]; $i=$nx; continue;
            } elseif ($t==='OCCU' && $lvl===1 && trim($v)!=='') { $r['occupation'][]=trim($v);
            } elseif ($t==='EDUC' && $lvl===1 && trim($v)!=='') { $r['education'][]=trim($v);
            } elseif ($t==='NOTE' && $lvl===1) { [$txt,$nx]=read_note($lines,$i,$N); if($txt)$r['notes'][]=$txt; $i=$nx; continue;
            } elseif ($t==='FAMC' && $lvl===1) { $r['famc'][]=trim($v);
            } elseif ($t==='FAMS' && $lvl===1) { $r['fams'][]=trim($v); }
            $i++;
        }
        if ($r['name']==='') $r['name']='Unknown';
        $indi[$pid]=$r;
    } elseif ($level === 0 && $xref && $tag === 'FAM') {
        $fid=$xref; $r=['id'=>$fid,'husb'=>'','wife'=>'','chil'=>[],'marr'=>['','']];
        $i++;
        while ($i < $N && $lines[$i][0] !== 0) {
            [$lvl,,$t,$v]=$lines[$i];
            if ($t==='HUSB' && $lvl===1) $r['husb']=trim($v);
            elseif ($t==='WIFE' && $lvl===1) $r['wife']=trim($v);
            elseif ($t==='CHIL' && $lvl===1) $r['chil'][]=trim($v);
            elseif ($t==='MARR' && $lvl===1) { [$d,$p,$nx]=read_event($lines,$i,$N); $r['marr']=[$d,$p]; $i=$nx; continue; }
            $i++;
        }
        $fam[$fid]=$r;
    } else { $i++; }
}

function birth_year($d){ return preg_match('/\d{4}/',$d,$m)?(int)$m[0]:null; }

// Write to DB
db()->beginTransaction();
db()->exec("DELETE FROM persons");
db()->exec("DELETE FROM families");

$living=0;
$ins = db()->prepare("INSERT INTO persons
  (pid,name,given,surname,sex,birth_date,birth_place,death_date,death_place,buri_date,buri_place,living,famc,fams,occupation,education,notes)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
foreach ($indi as $pid=>$r) {
    $deceased = trim($r['death'][0]) !== '';
    $by = birth_year($r['birth'][0]);
    $isLiving = (!$deceased) && ($by === null || $by > 1935);
    if ($isLiving) $living++;
    $ins->execute([$pid,$r['name'],$r['given'],$r['surname'],$r['sex'],
        $r['birth'][0],$r['birth'][1],$r['death'][0],$r['death'][1],$r['buri'][0],$r['buri'][1],
        $isLiving?1:0,
        json_encode($r['famc']),json_encode($r['fams']),
        json_encode($r['occupation']),json_encode($r['education']),json_encode($r['notes'])]);
}
$insf = db()->prepare("INSERT INTO families (fid,husb,wife,marr_date,marr_place,chil) VALUES (?,?,?,?,?,?)");
foreach ($fam as $fid=>$r) {
    $insf->execute([$fid,$r['husb'],$r['wife'],$r['marr'][0],$r['marr'][1],json_encode($r['chil'])]);
}
db()->commit();

printf("Imported %d individuals, %d families (%d flagged living/private).\n", count($indi), count($fam), $living);
