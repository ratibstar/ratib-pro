<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/get-documents.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/get-documents.php`.
 */
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('workers', 'documents');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    // Accept both 'id' and 'id' parameters
    $workerId = isset($_GET['id']) ? (int)$_GET['id'] : 
                (isset($_GET['id']) ? (int)$_GET['id'] : null);
    
    if (!$workerId) {
        throw new Exception('Worker ID is required');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get worker documents (SELECT * keeps optional document columns backward-compatible).
    $query = "
        SELECT *
        FROM workers
        WHERE id = ? AND status != 'deleted'
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$workerId]);
    $documents = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documents) {
        throw new Exception('Worker not found');
    }

    // Format response
    $formattedDocs = [];
    $docTypes = ['identity', 'passport', 'contract_signed', 'insurance', 'police', 'medical', 'training_certificate', 'visa', 'exit_permit', 'ticket'];
    
    foreach ($docTypes as $type) {
        $formattedDocs[$type] = [
            'number' => $documents["{$type}_number"] ?? null,
            'file' => $documents["{$type}_file"] ?? null,
            'status' => $documents["{$type}_status"] ?? 'pending',
            'url' => !empty($documents["{$type}_file"]) ? 
                ((defined('BASE_URL') ? BASE_URL : '') . "/uploads/documents/{$type}/{$documents["{$type}_file"]}") : 
                null
        ];
    }

    sendResponse([
        'success' => true,
        'data' => $formattedDocs
    ]);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 