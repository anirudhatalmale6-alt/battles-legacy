<?php
/**
 * The Battles Legacy — configuration.
 * Copy this file to config.php and fill in your real values.
 * config.php is git-ignored so your database password is never committed.
 */
return [
    // ---- Database ----
    // For your cPanel hosting use 'mysql'. 'sqlite' is only for local testing.
    'db_driver'   => 'mysql',
    'db_host'     => 'localhost',
    'db_name'     => 'battles_legacy',
    'db_user'     => 'battles_user',
    'db_pass'     => 'CHANGE_ME',
    // Used only when db_driver = sqlite (local testing):
    'db_sqlite'   => __DIR__ . '/data/battles.sqlite',

    // ---- Site ----
    'site_name'   => 'The Battles Legacy',
    'base_url'    => '',          // e.g. 'https://thebattlesfamily.com' (leave blank to auto-detect)
    'root_person' => '@I294@',    // Richmond Battles — the tree's default centre

    // Where imported / uploaded photos are stored, relative to /public
    'photos_dir'  => 'assets/photos',

    // ---- Notifications (optional, wired later) ----
    'twilio_sid'  => '',
    'twilio_token'=> '',
    'twilio_from' => '',
    /* Not noreply@ — see the note in src/mailer.php. Only used from the command
       line, where there is no request host to read the domain from. */
    'mail_from'   => 'family@thebattlesfamily.com',
];
