<?php
declare(strict_types=1);

/**
 * Probe: confirm Super Admin module-gate fix is on disk (no secrets).
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$mw = $root . '/app/Core/Middleware/Middleware.php';
$pls = $root . '/app/services/PlanLimitService.php';
$sw = __DIR__ . '/pos-sw.js';

foreach ([
    'middleware' => $mw,
    'plan_limit' => $pls,
    'pos_sw' => $sw,
] as $label => $path) {
    if (!is_file($path)) {
        echo $label . '=missing' . PHP_EOL;
        continue;
    }
    $raw = (string) file_get_contents($path);
    echo $label . '_bytes=' . strlen($raw) . PHP_EOL;
    echo $label . '_mtime=' . date('c', (int) filemtime($path)) . PHP_EOL;
    if ($label === 'middleware') {
        echo 'middleware_has_full_erp_open=' . (str_contains($raw, 'Super Admin: full ERP open') ? 'yes' : 'no') . PHP_EOL;
        echo 'middleware_has_isSuperAdminSession=' . (str_contains($raw, 'isSuperAdminSession') ? 'yes' : 'no') . PHP_EOL;
        echo 'middleware_has_company_edit_redirect=' . (str_contains($raw, "companies/' . \$companyId . '/edit") ? 'yes' : 'no') . PHP_EOL;
    }
    if ($label === 'plan_limit') {
        echo 'plan_limit_sa_bypass=' . (str_contains($raw, 'full ERP entitlement') ? 'yes' : 'no') . PHP_EOL;
    }
    if ($label === 'pos_sw') {
        echo 'pos_sw_build=' . (preg_match("/SW_BUILD_ID = '([^']+)'/", $raw, $m) ? $m[1] : 'unknown') . PHP_EOL;
        echo 'pos_sw_no_company_edit_bounce=' . (str_contains($raw, 'Never bounce ops module denials') ? 'yes' : 'no') . PHP_EOL;
    }
}

if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    echo 'opcache_enabled=' . (!empty($st['opcache_enabled']) ? 'yes' : 'no') . PHP_EOL;
}

echo 'probe_ok' . PHP_EOL;
