<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * CoreAI AI analytics endpoint.
 * واجهة تحليلات الذكاء الاصطناعي في CoreAI.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

coreai_require_auth(true);
$analytics = coreai_get_ai_analytics_summary();

echo json_encode(
    [
        'ok' => true,
        'analytics' => $analytics,
    ],
    JSON_UNESCAPED_UNICODE
);
