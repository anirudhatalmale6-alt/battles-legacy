<?php
/** PDO connection + tiny query helpers. Works with MySQL (production) and SQLite (local testing). */

function config($key = null) {
    static $cfg = null;
    if ($cfg === null) {
        $file = dirname(__DIR__) . '/config.php';
        $cfg = is_file($file) ? require $file : require dirname(__DIR__) . '/config.example.php';
    }
    if ($key === null) return $cfg;
    return $cfg[$key] ?? null;
}

function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $driver = config('db_driver');
    if ($driver === 'sqlite') {
        $path = config('db_sqlite');
        @mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', config('db_host'), config('db_name'));
        $pdo = new PDO($dsn, config('db_user'), config('db_pass'));
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function db_driver() { return config('db_driver'); }

/** Run a query with bound params. */
function q($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function one($sql, $params = []) { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function all($sql, $params = []) { return q($sql, $params)->fetchAll(); }
function insert_id() { return db()->lastInsertId(); }

/** Does $table have $col? Cached per request so migrations cost one cheap query, not a failed ALTER. */
function db_has_column($table, $col, $remember = null) {
    static $cache = [];
    $key = $table . '.' . $col;
    if ($remember !== null) return $cache[$key] = (bool) $remember;
    if (isset($cache[$key])) return $cache[$key];
    $found = false;
    try {
        if (db_driver() === 'sqlite') {
            foreach (all("PRAGMA table_info(" . $table . ")") as $r) {
                if (strcasecmp($r['name'], $col) === 0) { $found = true; break; }
            }
        } else {
            $found = (bool) one("SHOW COLUMNS FROM `$table` LIKE ?", [$col]);
        }
    } catch (\Throwable $e) { $found = false; }
    return $cache[$key] = $found;
}

/** Add a column only if it is missing. Safe to call on every request. */
function db_add_column($table, $col, $def) {
    if (db_has_column($table, $col)) return false;
    try { db()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def"); } catch (\Throwable $e) { return false; }
    db_has_column($table, $col, true);
    return true;
}
