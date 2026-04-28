<?php
/**
 * EN: Handles application behavior in `fix_perms.php`.
 * AR: يدير سلوك جزء من التطبيق في `fix_perms.php`.
 */
$f = __DIR__ . '/api/settings/get_permissions_groups.php';
$lines = file($f);
$keep = array_merge(array_slice($lines, 0, 217), array_slice($lines, 405));
file_put_contents($f, implode('', $keep));
echo "Done.\n";
