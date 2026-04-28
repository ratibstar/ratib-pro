<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_user_permissions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_user_permissions.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Check if admin is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }
    
    // Check if user has admin role
    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
        exit;
    }
    
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    // Get user permissions
    $stmt = $conn->prepare("
        SELECT module_name, can_view, can_add, can_edit, can_delete
        FROM user_permissions
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissions
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 