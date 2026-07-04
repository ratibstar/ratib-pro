<?php
declare(strict_types=1);

/**
 * Loads project-root app/Accounting for Phase 6 Control Center.
 * Never throws here — only registers autoloader when site app/ exists.
 */
if (class_exists(\App\Accounting\Admin\AccountingControlBootstrap::class, false)) {
    return;
}

$erpRoot = \Rateb\App\Core\Bootstrap::erpRootFromBootstrapFile();
$candidates = [
    dirname($erpRoot) . '/app',
    $erpRoot . '/../app',
];

foreach ($candidates as $candidate) {
    $resolved = realpath($candidate);
    if ($resolved === false) {
        continue;
    }

    $autoloader = $resolved . '/Core/Autoloader.php';
    if (is_file($autoloader)) {
        require_once $autoloader;
        \App\Core\Autoloader::register($resolved);
        if (!defined('RATEB_SITE_APP_ROOT')) {
            define('RATEB_SITE_APP_ROOT', str_replace('\\', '/', $resolved));
        }
    }

    $bootstrap = $resolved . '/Accounting/Admin/AccountingControlBootstrap.php';
    if (is_file($bootstrap)) {
        require_once $bootstrap;
    }

    return;
}
