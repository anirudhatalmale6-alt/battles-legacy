<?php
/** Import/refresh the tree from a GEDCOM. Usage: php bin/import_gedcom.php FILE.ged */
require __DIR__ . '/../src/install.php';
$path = $argv[1] ?? (dirname(__DIR__, 1) . '/battlesfamily.ged');
$r = install_gedcom($path);
if (!$r['ok']) { fwrite(STDERR, $r['error'] . "\n"); exit(1); }
printf("Imported %d individuals, %d families (%d flagged living/private).\n", $r['individuals'], $r['families'], $r['living']);
