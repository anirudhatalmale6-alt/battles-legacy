<?php
/**
 * Create the database schema. Emits MySQL- or SQLite-appropriate DDL.
 * Run:  php bin/migrate.php
 */
require __DIR__ . '/../src/db.php';

$driver = db_driver();
$AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
$NOW = $driver === 'sqlite' ? "CURRENT_TIMESTAMP" : "CURRENT_TIMESTAMP";
$ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

$tables = [

"users" => "CREATE TABLE IF NOT EXISTS users (
  id $AI,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  phone      VARCHAR(40)  DEFAULT '',
  pass_hash  VARCHAR(255) DEFAULT '',
  role       VARCHAR(20)  NOT NULL DEFAULT 'member',
  status     VARCHAR(20)  NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT $NOW,
  last_login TIMESTAMP NULL
)$ENG",

"invites" => "CREATE TABLE IF NOT EXISTS invites (
  id $AI,
  token      VARCHAR(64) NOT NULL,
  name       VARCHAR(120) DEFAULT '',
  email      VARCHAR(190) DEFAULT '',
  role       VARCHAR(20)  NOT NULL DEFAULT 'member',
  invited_by INT NULL,
  created_at TIMESTAMP DEFAULT $NOW,
  expires_at TIMESTAMP NULL,
  used_at    TIMESTAMP NULL
)$ENG",

"persons" => "CREATE TABLE IF NOT EXISTS persons (
  pid        VARCHAR(16) PRIMARY KEY,
  name       VARCHAR(200) DEFAULT '',
  given      VARCHAR(160) DEFAULT '',
  surname    VARCHAR(120) DEFAULT '',
  sex        VARCHAR(4)   DEFAULT '',
  birth_date VARCHAR(80)  DEFAULT '',
  birth_place VARCHAR(200) DEFAULT '',
  death_date VARCHAR(80)  DEFAULT '',
  death_place VARCHAR(200) DEFAULT '',
  buri_date  VARCHAR(80)  DEFAULT '',
  buri_place VARCHAR(200) DEFAULT '',
  living     INT DEFAULT 0,
  famc       TEXT,
  fams       TEXT,
  occupation TEXT,
  education  TEXT,
  notes      TEXT
)$ENG",

"families" => "CREATE TABLE IF NOT EXISTS families (
  fid        VARCHAR(16) PRIMARY KEY,
  husb       VARCHAR(16) DEFAULT '',
  wife       VARCHAR(16) DEFAULT '',
  marr_date  VARCHAR(80) DEFAULT '',
  marr_place VARCHAR(200) DEFAULT '',
  chil       TEXT
)$ENG",

"photos" => "CREATE TABLE IF NOT EXISTS photos (
  id $AI,
  pid         VARCHAR(16) NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  path        VARCHAR(255) NOT NULL,
  caption     VARCHAR(500) DEFAULT '',
  status      VARCHAR(20)  NOT NULL DEFAULT 'approved',
  source      VARCHAR(20)  NOT NULL DEFAULT 'import',
  uploaded_by INT NULL,
  created_at  TIMESTAMP DEFAULT $NOW
)$ENG",
];

foreach ($tables as $name => $sql) {
    db()->exec($sql);
    echo "ok  table  $name\n";
}

// Helpful indexes (ignore errors if they already exist)
$idx = [
  "CREATE INDEX idx_photos_pid ON photos(pid)",
  "CREATE INDEX idx_photos_status ON photos(status)",
  "CREATE INDEX idx_users_email ON users(email)",
  "CREATE INDEX idx_invites_token ON invites(token)",
  "CREATE INDEX idx_persons_surname ON persons(surname)",
];
foreach ($idx as $s) { try { db()->exec($s); } catch (Exception $e) {} }

echo "Migration complete ($driver).\n";
