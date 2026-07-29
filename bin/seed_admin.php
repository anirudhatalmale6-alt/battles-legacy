<?php
/** Create/promote the first Admin. Usage: php bin/seed_admin.php "Name" email password */
require __DIR__ . '/../src/install.php';
$r = install_admin($argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? '');
if (!$r['ok']) { fwrite(STDERR, $r['error'] . "\n"); exit(1); }
echo "Admin ready: {$r['email']}\n";
