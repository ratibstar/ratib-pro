<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/delete.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/delete.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('agents', 'delete');

try {
    require_once __DIR__ . '/../core/Database.php';
    require_once __DIR__ . '/../core/ApiResponse.php';
    
    // Get the ID from the request
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Agent ID is required');
    }
    
    // Get database instance
    $db = Database::getInstance();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if agent exists
    $agent = $db->getAgentById($id);
    if (!$agent) {
        throw new Exception('Agent not found');
    }
    
    // Delete the agent
    $result = $db->deleteAgent($id);
    if (!$result) {
        throw new Exception('Failed to delete agent');
    }
    
    // Return success response
    ob_clean();
    echo ApiResponse::success($agent, 'Agent deleted successfully');
    exit;
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo ApiResponse::error($e->getMessage());
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error("An unexpected error occurred");
    exit;
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo ApiResponse::error("An unexpected error occurred");
    exit;
} 