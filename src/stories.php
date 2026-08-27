<?php
/**
 * A person's life story - the part of a family record a GEDCOM cannot hold.
 *
 * Deliberately its own table rather than a column on `persons`: install_gedcom()
 * begins with DELETE FROM persons, so anything the family wrote would be thrown
 * away by the next re-import of the tree. Keyed by pid, it survives that.
 */

function st_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $ENG = db_driver() === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS person_stories (
      pid VARCHAR(16) NOT NULL PRIMARY KEY,
      story TEXT,
      updated_by INT NULL,
      updated_by_name VARCHAR(160) DEFAULT '',
      updated_at TIMESTAMP NULL
    )$ENG");
}

/** the row, or null */
function st_get($pid) {
    st_migrate();
    if ($pid === '' || $pid === null) return null;
    try { $r = one("SELECT * FROM person_stories WHERE pid=?", [$pid]); }
    catch (\Throwable $e) { return null; }
    return $r ?: null;
}

/** just the text, '' when there is none */
function st_text($pid) { $r = st_get($pid); return $r ? (string)$r['story'] : ''; }

/** how many people have a story - for the nudge on the person page */
function st_count() {
    st_migrate();
    try { $r = one("SELECT COUNT(*) c FROM person_stories WHERE story <> ''"); return $r ? (int)$r['c'] : 0; }
    catch (\Throwable $e) { return 0; }
}

/** replace a person's story. Empty text removes the row rather than leaving a blank one. */
function st_set($pid, $text, $user = null) {
    st_migrate();
    $pid  = trim((string)$pid);
    $text = trim((string)$text);
    if ($pid === '') return false;
    if (!one("SELECT pid FROM persons WHERE pid=?", [$pid])) return false;
    if ($text === '') { q("DELETE FROM person_stories WHERE pid=?", [$pid]); return true; }
    $uid  = $user && isset($user['id'])   ? (int)$user['id'] : null;
    $name = $user && isset($user['name']) ? mb_substr((string)$user['name'], 0, 160) : '';
    $now  = date('Y-m-d H:i:s');
    if (one("SELECT pid FROM person_stories WHERE pid=?", [$pid])) {
        q("UPDATE person_stories SET story=?, updated_by=?, updated_by_name=?, updated_at=? WHERE pid=?",
          [$text, $uid, $name, $now, $pid]);
    } else {
        q("INSERT INTO person_stories (pid,story,updated_by,updated_by_name,updated_at) VALUES (?,?,?,?,?)",
          [$pid, $text, $uid, $name, $now]);
    }
    return true;
}

/**
 * Add a memory to the end of what is already there, signed by whoever sent it.
 * This is what runs when William approves a memory a family member submitted -
 * a life is not one person's paragraph, and the second cousin who remembers the
 * fishing trips should not overwrite the first one's account of the funeral.
 */
function st_append($pid, $text, $whoName, $user = null) {
    st_migrate();
    $text = trim((string)$text);
    if ($text === '') return false;
    $whoName = trim((string)$whoName);
    $block = $text . ($whoName !== '' ? "\n\n— " . $whoName : '');
    $cur = st_text($pid);
    return st_set($pid, $cur === '' ? $block : $cur . "\n\n" . $block, $user);
}
