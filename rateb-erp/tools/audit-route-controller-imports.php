<?php
declare(strict_types=1);

/**
 * Audit route files for Controller::class references missing a matching use import.
 * Run: php rateb-erp/tools/audit-route-controller-imports.php
 */

$root = dirname(__DIR__) . '/routes';
$issues = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

foreach ($rii as $file) {
    if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
        continue;
    }
    $path = $file->getPathname();
    $src = (string) file_get_contents($path);
    if (!preg_match_all('/\[([A-Za-z_][A-Za-z0-9_]*)::class\s*,/', $src, $m)) {
        continue;
    }
    $uses = [];
    if (preg_match_all('/^use\s+([^;]+);/m', $src, $um)) {
        foreach ($um[1] as $u) {
            $u = trim($u);
            if (preg_match('/\\\\([A-Za-z_][A-Za-z0-9_]*)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/', $u, $am)) {
                $uses[$am[2]] = true;
            } elseif (preg_match('/\\\\([A-Za-z_][A-Za-z0-9_]*)$/', $u, $sm)) {
                $uses[$sm[1]] = true;
            } elseif (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)$/', $u, $dm)) {
                $uses[$dm[1]] = true;
            }
        }
    }
    $missing = [];
    foreach (array_unique($m[1]) as $name) {
        if (!isset($uses[$name])) {
            $missing[] = $name;
        }
    }
    if ($missing) {
        $issues[str_replace('\\', '/', $path)] = $missing;
    }
}

if (!$issues) {
    echo "OK: no missing controller imports in routes/\n";
    exit(0);
}

foreach ($issues as $path => $miss) {
    echo $path . "\n";
    echo '  MISSING (' . count($miss) . '): ' . implode(', ', $miss) . "\n";
}
echo 'FILES_WITH_ISSUES=' . count($issues) . "\n";
exit(1);
