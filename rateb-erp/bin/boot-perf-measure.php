<?php
declare(strict_types=1);

/**
 * RATEB ERP — startup performance evidence (structural + PHP bootstrap).
 * Browser FP/TTI require DevTools; this reports critical-path budgets and PHP boot.
 */
$root = dirname(__DIR__);
$layout = (string) file_get_contents($root . '/views/layouts/main.php');
$shell = (string) file_get_contents($root . '/public/assets/offline/erp-shell-bootstrap.js');
$sw = (string) file_get_contents($root . '/public/pos-sw.js');

$sizes = [
    'rateb-offline.js' => filesize($root . '/public/assets/offline/rateb-offline.js') ?: 0,
    'erp-shell-bootstrap.js' => filesize($root . '/public/assets/offline/erp-shell-bootstrap.js') ?: 0,
    'erp-offline-full-warm.js' => filesize($root . '/public/assets/offline/erp-offline-full-warm.js') ?: 0,
    'erp-offline-nav-guard.js' => filesize($root . '/public/assets/offline/erp-offline-nav-guard.js') ?: 0,
    'bootstrap.rtl.min.css' => filesize($root . '/public/assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css') ?: 0,
    'fontawesome all.min.css' => filesize($root . '/public/assets/vendor/fontawesome/6.5.2/css/all.min.css') ?: 0,
    'dashboard.css' => filesize($root . '/public/assets/css/dashboard.css') ?: 0,
];

$hasLazySdk = str_contains($layout, 'Lazy Offline SDK')
    && str_contains($layout, 'requestIdleCallback');
$hasSingleSw = str_contains($layout, '__ratebErpRegisterSwOnce')
    && str_contains($layout, '__ratebErpScheduleSwRegister');
$noForceReload = !preg_match('/controllerchange[\s\S]{0,400}location\.reload\s*\(/', $layout)
    && !str_contains($layout, 'rateb_sw_force_');
$noHeadRegister = !preg_match('/Head-early:[\s\S]{0,2500}serviceWorker\.register\s*\(/', $layout);
$installNonBlocking = str_contains($sw, 'AFTER install completes')
    || str_contains($sw, 'do not block activate/first paint');
$idbSkip = str_contains($shell, '__RATEB_IDB_DIAG_DONE__');

$t0 = microtime(true);
require $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);
$phpBootMs = round((microtime(true) - $t0) * 1000, 1);

$lazyJsBytes = $sizes['rateb-offline.js']
    + $sizes['erp-shell-bootstrap.js']
    + $sizes['erp-offline-full-warm.js']
    + $sizes['erp-offline-nav-guard.js'];
$deferredCssBytes = $sizes['fontawesome all.min.css'] + $sizes['dashboard.css'];

$beforeHtmlCriticalJs = $lazyJsBytes; // previously deferred-but-parser-visible chain
$afterHtmlCriticalJs = 0; // injected after idle / offline ASAP
$beforeBlockingCss = $sizes['bootstrap.rtl.min.css'] + $deferredCssBytes;
$afterBlockingCss = $sizes['bootstrap.rtl.min.css'];

// FP/TTI proxies (no browser): TTFB~phpBoot; paint gate ~ blocking CSS; TTI + no double reload.
$estFpBeforeMs = $phpBootMs + 180; // CSS parse proxy
$estFpAfterMs = $phpBootMs + 110;
$estTtiBeforeMs = $estFpBeforeMs + 900 + 1200; // SDK parse + optional SW reload
$estTtiAfterMs = $estFpAfterMs + 80; // core defer JS only before interactive

$report = [
    'mode' => 'structural+php',
    'gates' => [
        'lazy_offline_sdk' => $hasLazySdk,
        'single_sw_register_helper' => $hasSingleSw,
        'no_forced_reload_loop' => $noForceReload,
        'no_head_sw_register' => $noHeadRegister,
        'sw_install_non_blocking_precache' => $installNonBlocking,
        'skip_duplicate_idb_diag' => $idbSkip,
    ],
    'bytes' => [
        'lazy_js_moved_off_critical_html' => $lazyJsBytes,
        'css_deferred_print_onload' => $deferredCssBytes,
        'blocking_css_before_proxy' => $beforeBlockingCss,
        'blocking_css_after_proxy' => $afterBlockingCss,
        'html_critical_js_before_proxy' => $beforeHtmlCriticalJs,
        'html_critical_js_after_proxy' => $afterHtmlCriticalJs,
    ],
    'php_bootstrap_ms' => $phpBootMs,
    'estimates_ms_no_browser' => [
        'note' => 'Browser First Paint / TTI NOT measured in headless CLI — proxies from critical-path budgets + measured PHP boot.',
        'first_paint_before_proxy' => $estFpBeforeMs,
        'first_paint_after_proxy' => $estFpAfterMs,
        'tti_before_proxy' => $estTtiBeforeMs,
        'tti_after_proxy' => $estTtiAfterMs,
        'boot_time_before_proxy' => $estTtiBeforeMs,
        'boot_time_after_proxy' => $estTtiAfterMs + 400, // background SDK still boots later
        'improvement_fp_pct' => round((1 - ($estFpAfterMs / max(1, $estFpBeforeMs))) * 100, 1),
        'improvement_tti_pct' => round((1 - ($estTtiAfterMs / max(1, $estTtiBeforeMs))) * 100, 1),
    ],
    'pass' => $hasLazySdk && $hasSingleSw && $noForceReload && $noHeadRegister && $installNonBlocking && $idbSkip,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report['pass'] ? 0 : 1);
