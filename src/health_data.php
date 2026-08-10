<?php
/** Health section — rotating health tips + upcoming health events.
 *  Idempotent migration; seeds a few samples once so the page looks complete. */
require_once __DIR__ . '/db.php';

function health_migrate() {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS health_tips (
      id $AI, tip VARCHAR(600) NOT NULL, source VARCHAR(160) DEFAULT '',
      sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    db()->exec("CREATE TABLE IF NOT EXISTS health_events (
      id $AI, mon VARCHAR(4) DEFAULT '', day VARCHAR(4) DEFAULT '', title VARCHAR(200) NOT NULL,
      detail VARCHAR(200) DEFAULT '', icon VARCHAR(20) NOT NULL DEFAULT 'walk',
      sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    health_seed();
}

function health_seed() {
    if (!one("SELECT id FROM health_tips LIMIT 1")) {
        $tips = [
          ['Drink more water, move more, stress less, and get enough rest. Your body will thank you.',''],
          ['A short walk after meals helps digestion and steadies your blood sugar.',''],
          ['Take your blood pressure at the same time each day and write it down.',''],
          ['Rest is not laziness — seven to eight hours of sleep protects your heart and your mind.',''],
        ];
        $i=0; foreach ($tips as $t) q("INSERT INTO health_tips (tip,source,sample,sort) VALUES (?,?,1,?)", [$t[0],$t[1],$i++]);
    }
    if (!one("SELECT id FROM health_events LIMIT 1")) {
        $evs = [
          ['MAY','24','Community Walk','Family & friends welcome','walk'],
          ['JUN','14','Health Screening Day','Free screenings','screen'],
          ['JUL','12','Nutrition Workshop','Cooking demo included','food'],
        ];
        $i=0; foreach ($evs as $e) q("INSERT INTO health_events (mon,day,title,detail,icon,sample,sort) VALUES (?,?,?,?,?,1,?)", [$e[0],$e[1],$e[2],$e[3],$e[4],$i++]);
    }
}

function health_tips($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM health_tips $w ORDER BY sort, id");
}
function health_events($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM health_events $w ORDER BY sort, id");
}
function health_event_icons() {
    return ['walk'=>'Walking / activity','screen'=>'Screening / check-up','food'=>'Nutrition / food','heart'=>'Heart health','mind'=>'Mental health','calendar'=>'General event'];
}
