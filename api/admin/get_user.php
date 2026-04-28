<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_user.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_user.php`.
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
    
    // Accept both GET and POST for user_id
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    // Get user details
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.email, u.phone, u.status, u.role_id, r.role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 