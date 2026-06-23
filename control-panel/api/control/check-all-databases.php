<?php
/**
 * Check all Rateb databases (ERP, CP, RCC, Pro). Requires Control Panel admin session.
 */
declare(strict_types=1);

if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/control/check-all-databases-lib.php';

    if (empty($_SESSION['control_logged_in'])) {
        http_response_code(403);
        exit("Login to Control Panel first.\n");
    }

    [$report, $allPass] = control_check_all_databases_run();
    echo $report;
    if (!$allPass) {
        echo "\n(Some checks failed — see [!!] above. This is not a server error.)\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'FATAL: ', $e->getMessage(), "\n";
    echo $e->getFile(), ':', (string) $e->getLine(), "\n";
}
