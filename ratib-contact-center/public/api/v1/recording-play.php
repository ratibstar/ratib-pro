<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap-api.php';

use Ratib\ContactCenter\App\Core\Security\AuthContext;

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'recording id required'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    AuthContext::requirePermission('rcc.recordings.play');
    $tenantId = AuthContext::tenantId();
    $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->prepare(
        'SELECT file_path, mime_type, file_name FROM rcc_recordings WHERE tenant_id = :tid AND id = :id LIMIT 1'
    );
    $stmt->execute(['tid' => $tenantId, 'id' => $id]);
    $row = $stmt->fetch();
    if ($row === false || !is_file((string) $row['file_path'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Recording not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $path = (string) $row['file_path'];
    $mime = (string) ($row['mime_type'] ?? 'audio/wav');
    $download = isset($_GET['download']);
    if ($download) {
        AuthContext::requirePermission('rcc.recordings.download');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    if ($download) {
        header('Content-Disposition: attachment; filename="' . basename((string) $row['file_name']) . '"');
    } else {
        header('Content-Disposition: inline; filename="' . basename((string) $row['file_name']) . '"');
    }
    readfile($path);
    exit;
} catch (\Throwable $e) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
