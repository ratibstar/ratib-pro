<?php
/**
 * Remove a worker document file (staff): clear DB column, delete file from uploads tree when present.
 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/rateb_uploads_base.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../utils/response.php';

$validDocTypes = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'training_certificate', 'contract_signed', 'insurance', 'exit_permit'];

try {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }

    $raw = file_get_contents('php://input');
    $input = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($input)) {
        $input = $_POST;
    }

    $workerId = isset($input['id']) ? (int) $input['id'] : 0;
    $docType = isset($input['document_type']) ? strtolower(trim((string) $input['document_type'])) : '';

    if ($workerId <= 0 || !in_array($docType, $validDocTypes, true)) {
        sendResponse(['success' => false, 'message' => 'Worker ID and valid document type are required'], 400);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $fetchStmt = $conn->prepare('SELECT * FROM workers WHERE id = ? AND (status IS NULL OR status = \'\' OR status != \'deleted\') LIMIT 1');
    $fetchStmt->execute([$workerId]);
    $oldWorker = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldWorker) {
        sendResponse(['success' => false, 'message' => 'Worker not found'], 404);
    }

    $col = $docType . '_file';
    $statusCol = $docType . '_status';
    $fn = isset($oldWorker[$col]) ? trim((string) $oldWorker[$col]) : '';
    $fn = $fn !== '' ? basename($fn) : '';

    if ($fn !== '' && $fn !== '.' && $fn !== '..') {
        try {
            $baseRoot = rateb_uploads_pick_base_for_worker_document($workerId, $docType);
            $baseDir = realpath(
                $baseRoot . DIRECTORY_SEPARATOR . 'workers' . DIRECTORY_SEPARATOR . $workerId
                    . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $docType
            );
            if ($baseDir !== false) {
                $path = $baseDir . DIRECTORY_SEPARATOR . $fn;
                $real = realpath($path);
                if ($real !== false && is_file($real) && strpos($real, $baseDir) === 0) {
                    @unlink($real);
                }
            }
        } catch (Throwable $e) {
            error_log('worker document delete unlink: ' . $e->getMessage());
        }
    }

    $sql = "UPDATE workers SET `{$col}` = NULL, `{$statusCol}` = 'pending' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$workerId]);

    $fetchStmt2 = $conn->prepare('SELECT * FROM workers WHERE id = ? LIMIT 1');
    $fetchStmt2->execute([$workerId]);
    $updatedWorker = $fetchStmt2->fetch(PDO::FETCH_ASSOC);

    $helperPath = __DIR__ . '/../../core/global-history-helper.php';
    if (file_exists($helperPath) && $oldWorker && $updatedWorker) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldWorker, $updatedWorker);
        }
    }

    sendResponse([
        'success' => true,
        'message' => 'Document removed',
    ]);
} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
