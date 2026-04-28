<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/bulk-update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/bulk-update.php`.
 */
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('agents', 'bulk_update');

require_once '../core/Database.php';
require_once '../core/ApiResponse.php';

try {
    $rawInput = file_get_contents('php://input');
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        throw new Exception('No agents selected');
    }
    
    if (empty($data['action'])) {
        throw new Exception('No action specified');
    }
    
    $db = Database::getInstance();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $result = $db->bulkUpdateAgents($data['ids'], $data['action']);
    
    echo ApiResponse::success($result);
} catch (Exception $e) {
    echo ApiResponse::error($e->getMessage());
} catch (Error $e) {
    echo ApiResponse::error('Fatal error: ' . $e->getMessage());
}