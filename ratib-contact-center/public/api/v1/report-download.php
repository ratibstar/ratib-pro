<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap-api.php';

use Ratib\ContactCenter\App\Core\Security\AuthContext;

$download = basename((string) ($_GET['download'] ?? ''));
if ($download === '' || !preg_match('/^rcc-[a-z0-9_-]+\.csv$/i', $download)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid download filename'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    AuthContext::requirePermission('rcc.reports.export');
    $path = dirname(__DIR__, 3) . '/storage/exports/' . $download;
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Export file not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $download . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
} catch (\Throwable $e) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
