<?php
declare(strict_types=1);
/** i18n audit — run: php bin/audit-i18n.php */
$root = dirname(__DIR__);
$mainEn = require $root . '/config/lang/en.php';
$mainAr = require $root . '/config/lang/ar.php';
$fieldEn = is_file($root . '/config/field-labels-en.php') ? require $root . '/config/field-labels-en.php' : [];
$mergedEn = array_merge($fieldEn, $mainEn);

$labels = [];
$hardcoded = [];
foreach (['app', 'views'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        $c = file_get_contents($f->getPathname());
        preg_match_all("/'label'\s*=>\s*'([^']+)'/", $c, $m);
        foreach ($m[1] as $k) {
            $labels[$k] = true;
        }
        preg_match_all("/\['name'\s*=>\s*'([^']+)'/", $c, $m2);
        foreach ($m2[1] as $k) {
            $labels[$k] = true;
        }
        if (str_starts_with($rel, 'views/') && preg_match_all('/<th>([A-Za-z][^<]{0,40})<\/th>/', $c, $hm)) {
            foreach ($hm[1] as $h) {
                if (!str_contains($h, '<?php')) {
                    $hardcoded[] = $rel . ': ' . trim($h);
                }
            }
        }
    }
}

$missingEn = [];
$missingAr = [];
foreach (array_keys($labels) as $raw) {
    $key = strtolower(str_replace([' ', '-'], '_', trim($raw)));
    if ($key === '' || $key === '—') {
        continue;
    }
    if (!isset($mergedEn[$key])) {
        $missingEn[] = $raw;
    }
    if (!isset($mainAr[$key]) && !isset((is_file($root . '/config/field-labels-ar.php') ? require $root . '/config/field-labels-ar.php' : [])[$key])) {
        $missingAr[] = $raw;
    }
}
sort($missingEn);
sort($missingAr);
$hardcoded = array_unique($hardcoded);
sort($hardcoded);

echo "=== RATEB ERP i18n audit ===\n";
echo 'Field keys scanned: ' . count($labels) . "\n";
echo 'Missing EN: ' . count($missingEn) . "\n";
foreach (array_slice($missingEn, 0, 40) as $m) {
    echo "  - $m\n";
}
if (count($missingEn) > 40) {
    echo '  ... and ' . (count($missingEn) - 40) . " more\n";
}
echo 'Missing AR: ' . count($missingAr) . "\n";
echo 'Hardcoded <th> in views: ' . count($hardcoded) . "\n";
foreach (array_slice($hardcoded, 0, 20) as $h) {
    echo "  - $h\n";
}
echo count($missingEn) === 0 && count($hardcoded) === 0 ? "\nOK — no critical gaps.\n" : "\nReview items above.\n";
