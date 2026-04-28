<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/update.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('agents', 'update');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

try {
    if (empty($_GET['id'])) {
        throw new Exception("Agent ID is required");
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['full_name', 'email', 'phone'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("$field is required");
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }
    
    $db = Database::getInstance();
    $agent = $db->updateAgent($_GET['id'], $data);
    
    ob_clean();
    echo ApiResponse::success($agent, 'Agent updated successfully');
    exit;
} catch (Exception $e) {
    ob_clean();
    echo ApiResponse::error($e->getMessage());
    exit;
} catch (Throwable $e) {
    ob_clean();
    echo ApiResponse::error('Fatal error: ' . $e->getMessage());
    exit;
}