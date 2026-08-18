<?php
/** Who is in a photograph.
 *
 *  Until now a picture belonged to exactly one person: photos.pid, and the file
 *  itself was copied into that person's folder. That works for a portrait and
 *  falls apart for a group — a picture of the Holmes boys could only ever live
 *  on one brother's page.
 *
 *  So the link between a picture and a person moves out into its own table. One
 *  file, one row in photos, and as many rows in photo_people as there are faces
 *  in it. The photo shows up on every one of their profiles, and it is still
 *  only stored once.
 *
 *  photos.pid stays as it was and means "whose folder the file sits in" — the
 *  owner. It is not the whole answer to "who is in this", but it is the answer
 *  to "where does this file live", which is a different question and still
 *  needs one. */
require_once __DIR__ . '/db.php';

/** Create the table, and on the very first run give every picture already on
 *  the site a tag for the person it was filed under, so nothing disappears. */
function pp_migrate() {
    static $done = null;
    if ($done !== null) return $done;

    try { all("SELECT photo_id FROM photo_people LIMIT 1"); return $done = true; }
    catch (\Throwable $e) { /* not there yet — make it */ }

    $driver = db_driver();
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS photo_people (
          photo_id INT NOT NULL, pid VARCHAR(16) NOT NULL, is_primary INT NOT NULL DEFAULT 0,
          PRIMARY KEY (photo_id, pid)
        )$ENG");
        try { db()->exec("CREATE INDEX idx_pp_pid ON photo_people(pid)"); } catch (\Throwable $e) {}

        /* The backfill runs exactly once, on the request that creates the table.
           It must not run again: somebody removing a face from a photograph
           later would find it put straight back. */
        $isPrimary = db_has_column('photos', 'is_primary') ? 'is_primary' : '0';
        db()->exec("INSERT INTO photo_people (photo_id, pid, is_primary)
                    SELECT id, pid, $isPrimary FROM photos WHERE pid <> ''");
    } catch (\Throwable $e) { return $done = false; }
    return $done = true;
}

/** Put a person in a photograph. Silently does nothing if they are already in it. */
function pp_tag($photoId, $pid, $isPrimary = 0) {
    $photoId = (int)$photoId; $pid = trim((string)$pid);
    if (!$photoId || $pid === '') return false;
    if (!pp_migrate()) return false;
    if (one("SELECT pid FROM photo_people WHERE photo_id=? AND pid=?", [$photoId, $pid])) return false;
    try { q("INSERT INTO photo_people (photo_id,pid,is_primary) VALUES (?,?,?)", [$photoId, $pid, $isPrimary ? 1 : 0]); }
    catch (\Throwable $e) { return false; }
    return true;
}

/** Take a person out of a photograph — the picture and everyone else stay. */
function pp_untag($photoId, $pid) {
    if (!pp_migrate()) return false;
    q("DELETE FROM photo_people WHERE photo_id=? AND pid=?", [(int)$photoId, (string)$pid]);
    return true;
}

/** Everyone in one photograph, in the order their names read. */
function pp_people($photoId) {
    if (!pp_migrate()) return [];
    return all("SELECT t.pid, t.is_primary, COALESCE(x.name,'') AS name
                FROM photo_people t LEFT JOIN persons x ON x.pid = t.pid
                WHERE t.photo_id = ? ORDER BY x.name", [(int)$photoId]);
}

/** How many people are in it. Used before deleting anything. */
function pp_count($photoId) {
    if (!pp_migrate()) return 0;
    $r = one("SELECT COUNT(*) c FROM photo_people WHERE photo_id=?", [(int)$photoId]);
    return (int)($r['c'] ?? 0);
}

/** The approved photographs one person appears in — their own and any group
 *  picture somebody has put them in. Main photo first. */
function pp_photos($pid) {
    if (!pp_migrate()) return null;          // null = caller should fall back
    return all("SELECT ph.*, t.is_primary AS pinned
                FROM photo_people t JOIN photos ph ON ph.id = t.photo_id
                WHERE t.pid = ? AND ph.status = 'approved'
                ORDER BY t.is_primary DESC, ph.id", [(string)$pid]);
}

/** Choose which of the pictures a person is in represents them in the tree.
 *  It is set per person, not per photograph: the same group photo can be one
 *  brother's main picture without becoming everybody's. */
function pp_set_primary($pid, $photoId) {
    if (!pp_migrate()) return false;
    if (!one("SELECT pid FROM photo_people WHERE photo_id=? AND pid=?", [(int)$photoId, (string)$pid])) return false;
    q("UPDATE photo_people SET is_primary=0 WHERE pid=?", [(string)$pid]);
    q("UPDATE photo_people SET is_primary=1 WHERE pid=? AND photo_id=?", [(string)$pid, (int)$photoId]);
    return true;
}

/** After a photograph is removed from someone, make sure they still have a
 *  main picture if they have any pictures left. */
function pp_reseat_primary($pid) {
    if (!pp_migrate()) return;
    if (one("SELECT photo_id FROM photo_people WHERE pid=? AND is_primary=1", [(string)$pid])) return;
    $next = one("SELECT t.photo_id FROM photo_people t JOIN photos ph ON ph.id=t.photo_id
                 WHERE t.pid=? AND ph.status='approved' ORDER BY ph.id LIMIT 1", [(string)$pid]);
    if ($next) q("UPDATE photo_people SET is_primary=1 WHERE pid=? AND photo_id=?", [(string)$pid, (int)$next['photo_id']]);
}

/** Hand photographs from one person to another.
 *
 *  The case this exists for: a father and a son with the same name. The
 *  importer reads "Lafane Battles.jpg" and files it under whichever Lafane it
 *  matched, and half of them are the other one. Putting that right one picture
 *  at a time meant adding the son on one page, then finding the same picture
 *  again and taking the father off it — two steps in two places, and the
 *  control for the second step is an "x" that reads like "delete".
 *
 *  $keep leaves the original person in the picture as well, which is the right
 *  answer for a group photograph: both of them really are in it.
 *
 *  WHAT DELIBERATELY DOES NOT MOVE is photos.pid. That column is not "whose
 *  page this shows on" — photo_people answers that. It is where the file sits
 *  on disk and, just as importantly, the identity the photo importer dedupes
 *  on: it skips a file when it already has that person's copy of that content.
 *  Repoint it at the son and the next "Import photos" run finds no father's
 *  copy on record, imports the file again, and puts it straight back on the
 *  father's page — the move would quietly undo itself. So ownership stays put
 *  and only who is in the picture changes. te_delete_person() hands ownership
 *  on to a remaining tagged person if the owner is ever removed, so nothing is
 *  left stranded.
 *
 *  Returns ['moved'=>int,'already'=>int,'skipped'=>int]. */
function pp_move($photoIds, $from, $to, $keep = false) {
    $out = ['moved' => 0, 'already' => 0, 'skipped' => 0];
    $from = trim((string)$from); $to = trim((string)$to);
    if (!pp_migrate() || $from === '' || $to === '' || $from === $to) return $out;
    if (!one("SELECT pid FROM persons WHERE pid=?", [$to])) return $out;

    foreach ((array)$photoIds as $id) {
        $id = (int)$id;
        if (!$id) continue;
        /* Only pictures this person is actually in. Without this the form could
           be pointed at any photo id on the site. */
        if (!one("SELECT pid FROM photo_people WHERE photo_id=? AND pid=?", [$id, $from])) { $out['skipped']++; continue; }
        $added = pp_tag($id, $to);
        if (!$keep) pp_untag($id, $from);
        /* Nothing changed only when they were already in it AND the original
           person is staying — anything else is a real move. */
        if ($added || !$keep) $out['moved']++; else $out['already']++;
    }
    if ($out['moved']) { pp_reseat_primary($from); pp_reseat_primary($to); }
    return $out;
}

/** Drop every tag for a photograph (it is being deleted outright). */
function pp_clear($photoId) {
    if (!pp_migrate()) return;
    q("DELETE FROM photo_people WHERE photo_id=?", [(int)$photoId]);
}

/* ---------------------------------------------------------------------------
 * Guessing who is in a picture from its file name.
 *
 * The importer already tried an exact match on every one of these and failed —
 * that is why they are still sitting unplaced. So there is no point running the
 * same test again. What is left is the partial evidence: "Holmes boys.jpg"
 * knows a surname, "Aunt Ruth 2.jpg" knows a first name. That is not enough to
 * place a picture automatically, and it is plenty to put the six likeliest
 * people at the top of a list of two hundred.
 * ------------------------------------------------------------------------- */

function pp_norm($s) {
    $s = strtolower(trim((string)$s));
    $s = str_replace(['’', "'", '`'], '', $s);
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/** Words that appear in family photo file names and name nobody. */
function pp_stopwords() {
    return array_fill_keys([
        'the','and','a','of','at','in','on','with','to','my','our','his','her','their',
        'jpg','jpeg','png','gif','webp','img','image','photo','photos','picture','pictures','scan','copy','new','old',
        'boys','girls','kids','children','child','family','families','reunion','wedding','funeral','church','home','house',
        'aunt','uncle','cousin','cousins','grandma','grandmother','grandpa','grandfather','granny','mom','mother','dad',
        'father','mama','papa','sister','sisters','brother','brothers','baby','group','together','left','right','front',
        'back','row','circa','abt','about','unknown','misc','untitled','dsc','dscn','pic',
    ], true);
}

/** Rank the people most likely to be in a picture called $name.
 *  Returns pids, best first, at most $limit of them. */
function pp_suggest($name, $people, $limit = 6) {
    $tokens = [];
    foreach (explode(' ', pp_norm($name)) as $t) {
        if ($t === '' || strlen($t) < 3) continue;    // "jr", "II", stray initials
        if (is_numeric($t)) continue;                 // "Holmes 3" — a copy number, not a person
        $tokens[$t] = true;
    }
    /* "Holmes boys" and "Holmes girls" are different photographs of different
       people, and on the evidence of the surname alone they would get the same
       list. The word that makes them different is one I throw away a line
       later, so read it first. */
    $want = '';
    foreach ($tokens as $t => $_) {
        if (in_array($t, ['boys','men','brothers','sons','uncles','grandsons','nephews'], true)) { $want = 'M'; break; }
        if (in_array($t, ['girls','women','sisters','daughters','aunts','granddaughters','nieces','ladies'], true)) { $want = 'F'; break; }
    }

    $stop = pp_stopwords();
    /* A word is only worth scoring if it is not furniture. But if the file name
       is nothing BUT furniture we would rather show the general list than
       nothing, so an empty result here is a real answer. */
    $useful = array_diff_key($tokens, $stop);
    if (!$useful) return [];

    /* How many people carry each name-word. A word almost nobody has is strong
       evidence; one that sixty people share is barely evidence at all, and that
       is true whether it turns up as a surname or a middle name. "Battles" is
       both, all over this tree, so it has to be judged on how common it is
       rather than on which column it sits in. */
    $common = [];
    $tok = [];
    foreach ($people as $p) {
        $words = array_fill_keys(array_filter(explode(' ',
            pp_norm(($p['given'] !== '' ? $p['given'] : $p['name']) . ' ' . $p['surname']))), true);
        $tok[$p['pid']] = $words;
        foreach ($words as $w => $_) $common[$w] = isset($common[$w]) ? $common[$w] + 1 : 1;
    }

    $scores = [];
    foreach ($people as $p) {
        /* Somebody with no sex recorded is never ruled out — a blank field means
           nobody has filled it in, not that they are neither. */
        if ($want !== '' && isset($p['sex']) && $p['sex'] !== '' && strtoupper(substr($p['sex'], 0, 1)) !== $want) continue;
        $score = 0;
        foreach ($useful as $t => $_) {
            if (!isset($tok[$p['pid']][$t])) continue;
            $score += (isset($common[$t]) && $common[$t] >= 5) ? 1 : 3;
        }
        if ($score > 0) $scores[$p['pid']] = $score;
    }
    if (!$scores) return [];
    /* Ties keep the order the people came in, which is alphabetical, so the
       list is at least predictable when the evidence is weak. */
    arsort($scores);
    $best = max($scores);
    /* A hit on a name sixty people share is noise next to a hit on a rare one.
       Only fall back to the common-name matches when nothing scored better. */
    $out = [];
    foreach ($scores as $pid => $s) {
        if ($best >= 3 && $s < 3) continue;
        $out[] = $pid;
        if (count($out) >= $limit) break;
    }
    return $out;
}
