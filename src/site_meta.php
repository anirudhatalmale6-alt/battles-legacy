<?php
/** Small key/value store for bits of page text William edits himself —
 *  the Faith Corner verse, the scripture band, the featured cards on the
 *  home page. Anything that used to be typed into a template lives here. */
require_once __DIR__ . '/db.php';

function sm_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $ENG = db_driver() === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS site_meta (
          k VARCHAR(48) NOT NULL PRIMARY KEY, v TEXT,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )$ENG");
    } catch (\Throwable $e) { /* a missing setting must never take a page down */ }
}

/** The whole table, read once per request — these are used a dozen at a time.
 *  Returned by reference so a write updates the same array a read sees. */
function &sm_all() {
    static $cache = null;
    if ($cache === null) {
        sm_migrate();
        $cache = [];
        try { foreach (all("SELECT k,v FROM site_meta") as $r) $cache[$r['k']] = (string)$r['v']; }
        catch (\Throwable $e) {}
    }
    return $cache;
}

/** The stored value, or $default when it has never been set.
 *  An empty string counts as "set" — William may want a blank line. */
function sm($k, $default = '') {
    $a = &sm_all();
    return array_key_exists($k, $a) ? $a[$k] : $default;
}

function sm_set($k, $v) {
    sm_migrate();
    $k = (string)$k; $v = (string)$v;
    try {
        if (one("SELECT k FROM site_meta WHERE k=?", [$k])) q("UPDATE site_meta SET v=? WHERE k=?", [$v, $k]);
        else                                                q("INSERT INTO site_meta (k,v) VALUES (?,?)", [$k, $v]);
    } catch (\Throwable $e) { return; }
    $a = &sm_all(); $a[$k] = $v;
}

/** Reset a key back to whatever the page's built-in default is. */
function sm_clear($k) {
    sm_migrate();
    $k = (string)$k;
    try { q("DELETE FROM site_meta WHERE k=?", [$k]); } catch (\Throwable $e) {}
    $a = &sm_all(); unset($a[$k]);
}
