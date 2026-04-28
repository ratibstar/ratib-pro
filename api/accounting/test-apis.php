<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/test-apis.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/test-apis.php`.
 */
/**
 * Accounting API Diagnostic - Helps find 500 error causes
 * Access: https://out.ratib.sa/api/accounting/test-apis.php (while logged in)
 * DELETE this file after debugging for security
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$results = [];

// 1. Config
try {
    require_once '../../includes/config.php';
    $results['config'] = ['ok' => true];
} catch (Throwable $e) {
    $results['config'] = ['ok' => false, 'error' => $e->getMessage()];
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

// 2. Database
try {
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn) throw new Exception('No $conn');
    $conn->query("SELECT 1");
    $results['database'] = ['ok' => true];
} catch (Throwable $e) {
    $results['database'] = ['ok' => false, 'error' => $e->getMessage()];
}

// 3. Required files exist
$files = [
    'api-permission-helper' => __DIR__ . '/../core/api-permission-helper.php',
    'date-helper-api-core' => __DIR__ . '/../core/date-helper.php',      // api/core/date-helper.php (preferred)
    'date-helper-accounting-core' => __DIR__ . '/core/date-helper.php',  // api/accounting/core/date-helper.php (fallback)
    'erp-posting-controls' => __DIR__ . '/core/erp-posting-controls.php',
    'invoice-payment-automation' => __DIR__ . '/core/invoice-payment-automation.php',
    'ReceiptPaymentVoucherManager' => __DIR__ . '/core/ReceiptPaymentVoucherManager.php',
];
foreach ($files as $name => $path) {
    $results['file_' . $name] = ['ok' => file_exists($path), 'path' => $path];
}
$results['date_helper_available'] = file_exists($files['date-helper-api-core']) || file_exists($files['date-helper-accounting-core']);

// 4. Session
$results['session'] = [
    'logged_in' => isset($_SESSION['logged_in']) && $_SESSION['logged_in'],
    'user_id' => $_SESSION['user_id'] ?? null
];

// 5. Try direct API fetch (if curl available)
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$apiBase = $base . '/api/accounting';
$results['api_base'] = $apiBase;

$nextStep = 'Check PHP error log: includes/../logs/php-errors.log for 500 details';
if (empty($results['date_helper_available'])) {
    $nextStep = 'Upload date-helper.php to api/core/ OR api/accounting/core/ to fix HTTP 500 on journal, invoices, receipts.';
}
echo json_encode([
    'diagnostic' => $results,
    'next_step' => $nextStep
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
