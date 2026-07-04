<?php
declare(strict_types=1);

/**
 * Loads project-root app/Accounting for Phase 6 Control Center.
 * site app/ sits beside rateb-erp/ on production (public_html/app, public_html/rateb-erp).
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
    }

    $bootstrap = $resolved . '/Accounting/Admin/AccountingControlBootstrap.php';
    if (is_file($bootstrap)) {
        require_once $bootstrap;

        return;
    }
}

throw new \RuntimeException(
    'Accounting Control Center backend not found. Deploy public_html/app/Accounting/ (project app/Accounting/) beside rateb-erp/.'
);
