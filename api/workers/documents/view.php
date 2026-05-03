<?php
/**
 * Stream a worker document file for authenticated staff (same storage as documents/upload.php).
 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/ratib_uploads_base.php';
require_once __DIR__ . '/../../core/Database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not authenticated';
    exit;
}

$workerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$docType = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : '';

$valid = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'training_certificate', 'contract_signed', 'insurance', 'exit_permit'];
if ($workerId <= 0 || !in_array($docType, $valid, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad request';
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $col = $docType . '_file';
    $stmt = $conn->prepare("SELECT `{$col}` AS fn FROM workers WHERE id = ? AND (status IS NULL OR status = '' OR status != 'deleted') LIMIT 1");
    $stmt->execute([$workerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Worker not found';
        exit;
    }
    $fn = isset($row['fn']) ? trim((string) $row['fn']) : '';
    if ($fn === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'No file for this document type';
        exit;
    }
    $fn = basename($fn);
    if ($fn === '' || $fn === '.' || $fn === '..') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Invalid file';
        exit;
    }

    $baseRoot = ratib_uploads_base_dir();
    $baseDir = realpath(
        $baseRoot . DIRECTORY_SEPARATOR . 'workers' . DIRECTORY_SEPARATOR . $workerId
            . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $docType
    );
    if ($baseDir === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'File not available';
        exit;
    }
    $path = $baseDir . DIRECTORY_SEPARATOR . $fn;
    $real = realpath($path);
    if ($real === false || !is_file($real) || strpos($real, $baseDir) !== 0) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'File not found';
        exit;
    }

    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $m = (string) @mime_content_type($real);
        if ($m !== '') {
            $mime = $m;
        }
    }

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($real));
    header('Content-Disposition: inline; filename="' . basename($fn) . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($real);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error';
    exit;
}
