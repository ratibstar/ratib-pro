<?php
declare(strict_types=1);

require dirname(__DIR__) . '/admin/lib.php';

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

$_GET = ['range' => '30'];
$range = admin_finance_range();
$check($range['key'] === '30' && $range['start'] !== '' && $range['end'] !== '', 'default finance range is 30 days');

$_GET = ['range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31'];
$custom = admin_finance_range();
$check($custom['key'] === 'custom' && $custom['from'] === '2026-01-01', 'custom finance range parses');

$_GET = ['range' => 'custom', 'from' => '2026-02-01', 'to' => '2026-01-01'];
$bad = admin_finance_range();
$check($bad['key'] === '30', 'invalid custom range falls back to 30 days');

$check(admin_csv_cell('normal') === 'normal', 'csv plain cell unchanged');
$check(admin_csv_cell('=1+1') === "'=1+1", 'csv formula injection prefixed');
$check(admin_csv_cell(' =1+1') === "' =1+1", 'csv leading space before equals');
$check(admin_csv_cell('   =1+1') === "'   =1+1", 'csv multiple spaces before equals');
$check(admin_csv_cell("\t=1+1") === "'\t=1+1", 'csv tab before equals');
$check(admin_csv_cell("\r=1+1") === "\"'\r=1+1\"", 'csv cr before equals');
$check(admin_csv_cell("\n=1+1") === "\"'\n=1+1\"", 'csv lf before equals');
$check(admin_csv_formula_risk("\r=1+1") && admin_csv_formula_risk("\n=1+1"), 'csv formula risk after leading cr or lf');
$check(admin_csv_cell("  +cmd") === "'  +cmd", 'csv spaces before plus');
$check(admin_csv_cell("\x0B@SUM(A1)") === "'\x0B@SUM(A1)", 'csv vtab before at');
$check(admin_csv_cell('-99.00') === '-99.00', 'csv negative amount unchanged');
$check(admin_csv_cell("  -99.00") === '  -99.00', 'csv spaced negative amount unchanged');
$check(admin_csv_cell('1234.56') === '1234.56', 'csv positive amount unchanged');
$check(admin_csv_cell('اختبار') === 'اختبار', 'csv Arabic text unchanged');
$check(admin_csv_cell('say "hi"') === '"say ""hi"""', 'csv quotes escaped');

$row = admin_csv_row(['a', 'b']);
$check(str_ends_with($row, "\r\n") && str_contains($row, 'a,b'), 'csv row format');

exit($failures > 0 ? 1 : 0);
