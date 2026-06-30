<?php
declare(strict_types=1);

/**
 * Emergency HTTP endpoint — super-admin auth recovery only.
 *
 * GET  + X-Rateb-Migrate-Token → forensic JSON (no changes)
 * POST + X-Rateb-Migrate-Token + X-Rateb-Restore-Confirm: RESTORE-SUPER-ADMINS → restore
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

define('RATEB_ROOT', str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
define('RATEB_ENV_NO_SESSION', true);

require_once RATEB_ROOT . '/app/Core/HealthProbeAuth.php';
if (!\Rateb\App\Core\HealthProbeAuth::verifyRequest()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/bin/SuperAdminRestoreRunner.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$confirm = trim((string) ($_SERVER['HTTP_X_RATEB_RESTORE_CONFIRM'] ?? ''));
$resetPw = trim((string) ($_SERVER['HTTP_X_RATEB_RESTORE_RESET_PASSWORDS'] ?? '1')) !== '0';

try {
    $runner = new SuperAdminRestoreRunner();
    if ($method === 'POST' && $confirm === 'RESTORE-SUPER-ADMINS') {
        $report = $runner->restore($resetPw);
        echo json_encode(['ok' => true, 'report' => $report], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($method === 'GET') {
        $report = $runner->forensic();
        echo json_encode(['ok' => true, 'report' => $report], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'GET forensic or POST with X-Rateb-Restore-Confirm: RESTORE-SUPER-ADMINS',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage() !== '' ? $e->getMessage() : get_class($e),
        'trace' => $e->getFile() . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
}
