<?php
declare(strict_types=1);

/**
 * Find rateb_* function calls in views that have no function definition in the repo.
 * Run: php rateb-erp/tools/audit-undefined-view-helpers.php
 */

$viewRoots = [
    dirname(__DIR__) . '/views/company/bi',
    dirname(__DIR__) . '/views/company/payroll',
    dirname(__DIR__) . '/views/company/qms',
    dirname(__DIR__) . '/views/company/dms',
];
$searchRoots = [
    dirname(__DIR__) . '/config',
    dirname(__DIR__) . '/app',
    dirname(__DIR__) . '/includes',
    dirname(__DIR__) . '/modules',
];

$calls = [];
foreach ($viewRoots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
            continue;
        }
        $src = (string) file_get_contents($file->getPathname());
        if (preg_match_all('/\b(rateb_[a-zA-Z0-9_]+)\s*\(/', $src, $m)) {
            foreach ($m[1] as $fn) {
                $calls[$fn] = ($calls[$fn] ?? 0) + 1;
            }
        }
    }
}

$defined = [];
foreach ($searchRoots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
            continue;
        }
        $src = (string) @file_get_contents($file->getPathname());
        if ($src === '' || !preg_match_all('/function\s+(rateb_[a-zA-Z0-9_]+)\s*\(/', $src, $m)) {
            continue;
        }
        foreach ($m[1] as $fn) {
            $defined[$fn] = true;
        }
    }
}

$missing = [];
foreach ($calls as $fn => $count) {
    if (!isset($defined[$fn])) {
        $missing[$fn] = $count;
    }
}

if (!$missing) {
    echo "OK: all rateb_* helpers used by BI/payroll/QMS/DMS views are defined\n";
    exit(0);
}

ksort($missing);
foreach ($missing as $fn => $count) {
    echo "MISSING\t{$fn}\t({$count} calls)\n";
}
exit(1);
