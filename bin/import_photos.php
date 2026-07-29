<?php
/** Auto-pin a folder of photos to people by filename. Usage: php bin/import_photos.php DIR [--dry] */
require __DIR__ . '/../src/install.php';
$srcDir = $argv[1] ?? dirname(__DIR__, 1);
$dry = in_array('--dry', $argv, true);
$r = install_photos($srcDir, $dry);
if (!$r['ok']) { fwrite(STDERR, $r['error'] . "\n"); exit(1); }
$s = $r['stats'];
printf("Matched %d  ·  copied %d  ·  already-present %d  ·  UNMATCHED %d%s\n",
    $s['matched'], $s['copied'], $s['skipped'], $s['unmatched'], $dry ? '  (dry run)' : '');
if ($r['unmatched']) { echo "\nNeeds a name from the family (couldn't match automatically):\n"; foreach ($r['unmatched'] as $u) echo "  - $u\n"; }
