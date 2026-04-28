<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Persistent memory endpoint.
 * واجهة قراءة الذاكرة الدائمة.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

coreai_require_auth(true);

/**
 * Return latest project-level history.
 * إرجاع آخر سجل محفوظ على مستوى المشروع.
 */
$history = coreai_read_persistent_history();
echo json_encode(
    ['history' => $history],
    JSON_UNESCAPED_UNICODE
);
