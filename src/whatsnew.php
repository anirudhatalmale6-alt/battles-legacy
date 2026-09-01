<?php
/** What's new — the newest things anybody has added, photographs first.
 *
 *  The site has never told a member that anything had changed, so there was
 *  never a reason to come back to it. The monthly note is the push; this is the
 *  pull — a page that is different every time you open it, made entirely out of
 *  what the family has actually added.
 *
 *  Same privacy rule as the rest of the site: a signed-out visitor never sees a
 *  living relative. family.php turns the whole page away in that case; here the
 *  page stays and the living people are simply not in it, which is the same
 *  rule applied to a list instead of to one person.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** The newest photographs, with the person each one is pinned to. */
function wn_photos($limit = 24, $member = null) {
    if ($member === null) $member = logged_in();
    $sql = "SELECT ph.id, ph.pid, ph.path, ph.caption, ph.created_at,
                   p.name, p.given, p.surname, p.living
            FROM photos ph
            LEFT JOIN persons p ON p.pid = ph.pid
            WHERE ph.status='approved' AND ph.path <> ''";
    if (!$member) $sql .= " AND (p.living = 0 OR p.living IS NULL)";
    $sql .= " ORDER BY ph.created_at DESC, ph.id DESC LIMIT " . (int)$limit;
    try { return all($sql); } catch (\Throwable $e) { return []; }
}

/** A caption worth showing. The imported filenames are not. */
function wn_caption($row) {
    $cap = trim((string)($row['caption'] ?? ''));
    $who = trim((string)($row['name'] ?? ''));
    if ($who === '') $who = trim(($row['given'] ?? '') . ' ' . ($row['surname'] ?? ''));
    /* A caption that is only the person's name again says nothing twice, and an
       exact-match test is not enough: the imported ones are things like
       "Settie Battles" under "Settie Alma Battles". Word sets, not strings —
       every word already in the name means there is nothing new in it.
       "Horatio field" and "Found in the Tyler album" survive, which is right. */
    if ($cap !== '' && $who !== '') {
        $capW = preg_split('/[^a-z0-9]+/', strtolower($cap), -1, PREG_SPLIT_NO_EMPTY);
        $whoW = preg_split('/[^a-z0-9]+/', strtolower($who), -1, PREG_SPLIT_NO_EMPTY);
        if ($capW && !array_diff($capW, $whoW)) $cap = '';
    }
    return [$who, $cap];
}

/** Everything else that has appeared, newest first, as one flat list.
 *  Each entry is [when, what, who, href] and nothing is invented — an empty
 *  source contributes nothing rather than a line saying it is empty. */
function wn_recent($limit = 20, $member = null) {
    if ($member === null) $member = logged_in();
    $out = [];

    try {
        foreach (all("SELECT s.pid, s.updated_at, s.updated_by_name, p.name, p.living
                      FROM person_stories s LEFT JOIN persons p ON p.pid = s.pid
                      WHERE s.story <> '' ORDER BY s.updated_at DESC LIMIT 12") as $r) {
            if (!$member && (int)$r['living'] === 1) continue;
            if (trim((string)$r['name']) === '') continue;
            $out[] = ['ts' => strtotime((string)$r['updated_at']), 'kind' => 'story',
                      'what' => 'A story was written down for ' . $r['name'],
                      'who'  => trim((string)$r['updated_by_name']),
                      'href' => 'person.php?pid=' . urlencode($r['pid'])];
        }
    } catch (\Throwable $e) {}

    try {
        foreach (all("SELECT id, title, created_at FROM news_posts
                      WHERE status='published' ORDER BY created_at DESC, id DESC LIMIT 8") as $r) {
            $out[] = ['ts' => strtotime((string)$r['created_at']), 'kind' => 'news',
                      'what' => trim((string)$r['title']) !== '' ? $r['title'] : 'Family news',
                      'who'  => '', 'href' => 'news_view.php?id=' . (int)$r['id']];
        }
    } catch (\Throwable $e) {}

    try {
        $KIND = ['question' => 'A question', 'recipe' => 'A recipe',
                 'update' => 'An update', 'healthtip' => 'A health tip'];
        foreach (all("SELECT id, kind, title, author, created_at FROM community_posts
                      WHERE status='published' AND parent_id=0
                        AND kind IN ('question','recipe','update','healthtip')
                      ORDER BY created_at DESC, id DESC LIMIT 8") as $r) {
            $t = trim((string)$r['title']);
            /* A post can be saved with no title at all - one on the live site
               is - so name it by what it is rather than showing a blank. */
            if ($t === '') $t = isset($KIND[$r['kind']]) ? $KIND[$r['kind']] : 'A post';
            $out[] = ['ts' => strtotime((string)$r['created_at']), 'kind' => 'post',
                      'what' => $t, 'who' => trim((string)$r['author']),
                      'href' => 'community_view.php?id=' . (int)$r['id']];
        }
    } catch (\Throwable $e) {}

    if ($member) {
        try {
            foreach (all("SELECT name, created_at FROM users
                          WHERE status='active' AND name <> '' ORDER BY id DESC LIMIT 8") as $r) {
                $out[] = ['ts' => strtotime((string)$r['created_at']), 'kind' => 'member',
                          'what' => $r['name'] . ' joined the site', 'who' => '', 'href' => ''];
            }
        } catch (\Throwable $e) {}
    }

    $out = array_values(array_filter($out, function ($r) { return $r['ts'] > 0; }));
    usort($out, function ($a, $b) { return $b['ts'] - $a['ts']; });
    return array_slice($out, 0, $limit);
}

/** How many photographs went up in the last $days. */
function wn_photo_count($days = 30, $member = null) {
    if ($member === null) $member = logged_in();
    $since = date('Y-m-d H:i:s', time() - $days * 86400);
    $sql = "SELECT COUNT(*) c FROM photos ph LEFT JOIN persons p ON p.pid = ph.pid
            WHERE ph.status='approved' AND ph.created_at >= ?";
    if (!$member) $sql .= " AND (p.living = 0 OR p.living IS NULL)";
    try { $r = one($sql, [$since]); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}

function wn_ago($ts) {
    $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
    if (!$t) return '';
    $d = time() - $t;
    if ($d < 3600)   return 'just now';
    if ($d < 86400)  return floor($d / 3600) . ' hr ago';
    if ($d < 604800) { $n = floor($d / 86400); return $n . ' day' . ($n == 1 ? '' : 's') . ' ago'; }
    return date('j M Y', $t);
}
