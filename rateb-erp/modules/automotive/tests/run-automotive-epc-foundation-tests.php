<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$failures = 0;
$check = static function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$sql = (string) file_get_contents($root . '/migrations/291_auto_ref_foundation.sql');
$check(!str_contains($sql, 'company_id'), 'global tables have no company_id');
$check(str_contains($sql, 'rateb_auto_ref_vehicle_brands'), 'vehicle brands table exists');
$check(!str_contains($sql, 'rateb_inventory'), 'migration does not touch inventory');
$check(str_contains($sql, 'uq_auto_ref_org'), 'organization identity is role, name, and market');

$service = (string) file_get_contents($root . '/modules/automotive/app/Services/AutomotiveReferenceService.php');
$check(str_contains($service, 'overlap_rejected'), 'authorization overlap is rejected');
$check(str_contains($service, 'vehicle_manufacturer'), 'vehicle brand requires a vehicle manufacturer');
$check(!str_contains($service, 'INSERT INTO rateb_inventory'), 'service does not create inventory');

$en = (string) file_get_contents($root . '/modules/automotive/config/lang/en.php');
$ar = (string) file_get_contents($root . '/modules/automotive/config/lang/ar.php');
$check(str_contains($en, 'auto_epc_reference') && str_contains($ar, 'auto_epc_reference'), 'both locales define the reference label');

echo $failures === 0 ? "OK\n" : "FAILED {$failures}\n";
exit($failures === 0 ? 0 : 1);
