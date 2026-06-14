<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$labels = require $root . '/config/permission-labels-ar.php';
$coa = require $root . '/config/coa-labels-ar.php';

$hex = static function (string $text): string {
    return strtoupper(bin2hex($text));
};

$lines = [
    '-- RATEB ERP — repair Arabic permissions + COA + warehouse (UNHEX; deploy-safe)',
    'SET NAMES utf8mb4;',
    'SET CHARACTER SET utf8mb4;',
    '',
];

foreach ($labels as $slug => $pair) {
    $nameHex = $hex($pair[0]);
    $descHex = $hex($pair[1]);
    $slugEsc = str_replace("'", "''", $slug);
    $lines[] = "UPDATE rateb_permissions SET name_ar = CONVERT(UNHEX('{$nameHex}') USING utf8mb4), description_ar = CONVERT(UNHEX('{$descHex}') USING utf8mb4) WHERE slug = '{$slugEsc}';";
}

$lines[] = '';
foreach ($coa as $code => $pair) {
    $nameHex = $hex($pair[1]);
    $enHex = $hex($pair[0]);
    $lines[] = "UPDATE rateb_chart_of_accounts SET name = CONVERT(UNHEX('{$enHex}') USING utf8mb4), name_ar = CONVERT(UNHEX('{$nameHex}') USING utf8mb4) WHERE code = '{$code}';";
}

$whHex = $hex('المستودع الرئيسي');
$locHex = $hex('الرياض');
$lines[] = '';
$lines[] = "UPDATE rateb_warehouses SET name = CONVERT(UNHEX('{$whHex}') USING utf8mb4), location = CONVERT(UNHEX('{$locHex}') USING utf8mb4) WHERE code = 'WH-MAIN';";
$lines[] = "UPDATE rateb_warehouses SET name = CONVERT(UNHEX('{$whHex}') USING utf8mb4) WHERE name LIKE '%?%';";

$out = $root . '/migrations/054_permissions_coa_arabic_unhex.sql';
file_put_contents($out, implode("\n", $lines) . "\n");
echo "Wrote {$out} (" . count($labels) . " permissions, " . count($coa) . " COA codes)\n";
