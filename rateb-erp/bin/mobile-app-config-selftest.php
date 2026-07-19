<?php
declare(strict_types=1);

/**
 * Offline unit checks for MobileAppConfigService (no DB).
 * Run: php rateb-erp/bin/mobile-app-config-selftest.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/services/MobileAppConfigService.php';

use Rateb\App\Services\MobileAppConfigService;

$fail = 0;
$assert = static function (bool $cond, string $msg) use (&$fail): void {
    if ($cond) {
        echo "PASS $msg\n";
        return;
    }
    echo "FAIL $msg\n";
    $fail++;
};

$svc = new MobileAppConfigService();

$defaults = MobileAppConfigService::defaultFeatures();
$assert($defaults['attendance'] === true, 'default attendance on');
$assert($defaults['payroll'] === false, 'default payroll off');

$norm = $svc->normalizeFeatures(['attendance' => '0', 'payroll' => '1', 'bogus' => true]);
$assert($norm['attendance'] === false, 'normalize attendance off');
$assert($norm['payroll'] === true, 'normalize payroll on');
$assert(!array_key_exists('bogus', $norm), 'unknown feature keys dropped');

$json = $svc->encodeFeatures(['leave' => false]);
$decoded = $svc->decodeFeatures($json);
$assert($decoded['leave'] === false, 'round-trip leave false');
$assert($decoded['notifications'] === true, 'round-trip keeps default notifications');

// Tenant isolation contract: API rejects client company_id (controller-level).
$controllerFile = $root . '/app/controllers/Api/MobileConfigController.php';
$src = (string) file_get_contents($controllerFile);
$assert(str_contains($src, "isset(\$_GET['company_id'])"), 'API rejects GET company_id');
$assert(str_contains($src, 'TenantContext::companyId()'), 'API uses TenantContext company');

$assert(is_file($root . '/migrations/204_mobile_app_configs.sql'), 'migration 204 present');
$mig = (string) file_get_contents($root . '/migrations/204_mobile_app_configs.sql');
$assert(str_contains($mig, 'rateb_mobile_app_configs'), 'table name rateb_mobile_app_configs');
$assert(str_contains($mig, 'mobile_apps.view'), 'permission mobile_apps.view');
$assert(str_contains($mig, 'mobile_apps.manage'), 'permission mobile_apps.manage');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
