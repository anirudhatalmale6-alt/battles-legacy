<?php
/** Create the database schema. Run: php bin/migrate.php */
require __DIR__ . '/../src/install.php';
$r = install_migrate();
foreach ($r['tables'] as $t) echo "ok  table  $t\n";
echo "Migration complete ({$r['driver']}).\n";
