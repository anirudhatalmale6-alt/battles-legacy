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
