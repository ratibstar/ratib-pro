<?php
/**
 * EN: Handles API endpoint/business logic in `api/ratib-payment-trace.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/ratib-payment-trace.php`.
 */

declare(strict_types=1);

/**
 * Read-only diagnostic: env bootstrap → main DB → ngenius table ensure.
 * Open in browser: /api/ratib-payment-trace.php (no secrets in response).
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';
$isAppAdmin = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()
    && (int) ($_SESSION['role_id'] ?? 0) === 1;
$isControlLogged = !empty($_SESSION['control_logged_in']);
if (!$isAppAdmin && !$isControlLogged) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied'], JSON_UNESCAPED_SLASHES);
    exit;
}

$out = [
    'trace_version' => '2026-04-11a',
    'php' => PHP_VERSION,
];

require_once __DIR__ . '/../includes/payment_api_throwable_polyfill.php';

if (!defined('RATIB_ENV_NO_SESSION')) {
    define('RATIB_ENV_NO_SESSION', true);
}

try {
    require_once __DIR__ . '/../includes/payment_api_bootstrap.php';
    $out['bootstrap'] = 'ok';
    $out['db_configured'] = defined('DB_NAME') && (string) DB_NAME !== ''
        && defined('DB_HOST') && defined('DB_USER');
} catch (Throwable $e) {
    $out['bootstrap'] = 'fail';
    $out['bootstrap_error'] = $e->getMessage();
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
}

if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
    $out['pdo_mysql'] = 'missing';
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = null;
try {
    $dbName = defined('DB_NAME') ? (string) DB_NAME : '';
    if ($dbName === '') {
        throw new RuntimeException('DB_NAME empty');
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        defined('DB_PORT') ? (int) DB_PORT : 3306,
        $dbName
    );
    $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    $pdo->query('SELECT 1');
    $out['main_db_connect'] = 'ok';
} catch (Throwable $e) {
    $out['main_db_connect'] = 'fail';
    $out['main_db_error'] = $e->getMessage();
}

if ($pdo instanceof PDO) {
    try {
        require_once __DIR__ . '/../includes/payment_orders_schema.php';
        payment_ensure_ngenius_tables($pdo);
        $out['payment_ensure_ngenius_tables'] = 'ok';
    } catch (Throwable $e) {
        $out['payment_ensure_ngenius_tables'] = 'fail';
        $out['schema_error'] = $e->getMessage();
    }
}

$apiDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api'));
$out['suggested_urls'] = [
    'create_order_ping' => $apiDir . '/create-order.php?ping=1',
    'this_trace' => $apiDir . '/ratib-payment-trace.php',
];

echo json_encode($out, JSON_UNESCAPED_SLASHES);
