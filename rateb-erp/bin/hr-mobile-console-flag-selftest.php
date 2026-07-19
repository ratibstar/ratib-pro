<?php
declare(strict_types=1);

/**
 * Offline unit checks for HR Mobile console flag helper (no DB required for default path).
 * Run: php rateb-erp/bin/hr-mobile-console-flag-selftest.php
 */

$root = dirname(__DIR__);
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', str_replace('\\', '/', $root));
}

require_once RATEB_ROOT . '/config/app.php';

$fail = 0;
$assert = static function (bool $cond, string $msg) use (&$fail): void {
    if ($cond) {
        echo "PASS $msg\n";
        return;
    }
    echo "FAIL $msg\n";
    $fail++;
};

// Without SystemSetting row / DB, default must be false (ignore leftover env).
putenv('HR_MOBILE_CONSOLE_ENABLED');
putenv('RATEB_HR_MOBILE_CONSOLE_ENABLED');
rateb_hr_mobile_dev_config_clear_cache();

// Mock: if SystemSetting class exists but DB fails, catch → false
$assert(rateb_hr_mobile_console_flag_enabled() === false, 'default flag is false when unset');

putenv('HR_MOBILE_CONSOLE_ENABLED=true');
rateb_hr_mobile_dev_config_clear_cache();
$assert(rateb_hr_mobile_console_flag_enabled() === true, 'legacy getenv fallback when DB missing');

putenv('HR_MOBILE_CONSOLE_ENABLED');
putenv('RATEB_HR_MOBILE_CONSOLE_ENABLED=1');
rateb_hr_mobile_dev_config_clear_cache();
$assert(rateb_hr_mobile_console_flag_enabled() === true, 'legacy alias RATEB_HR_MOBILE_CONSOLE_ENABLED');

putenv('RATEB_HR_MOBILE_CONSOLE_ENABLED');
rateb_hr_mobile_dev_config_clear_cache();
$assert(rateb_hr_mobile_console_flag_enabled() === false, 'cleared env returns false');

$assert(rateb_hr_mobile_console_permission() === 'settings.manage', 'permission slug unchanged');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
