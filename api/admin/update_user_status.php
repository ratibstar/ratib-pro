<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_user_status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_user_status.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Handle both JSON and form data
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['user_id'])) {
            // Form data
            $input = $_POST;
        } else {
            // JSON data
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        }
    }
    
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
    
    $userId = $input['user_id'] ?? null;
    $status = $input['status'] ?? '';
    
    if (!$userId || !in_array($status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID or status']);
        exit;
    }
    
    // Get old data for history
    $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $fetchStmt->bind_param("i", $userId);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $oldUser = $result->fetch_assoc();
    
    if (!$oldUser) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Update user status
    $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("si", $status, $userId);
    
    if ($stmt->execute()) {
        // Get updated user for history
        $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $fetchStmt->bind_param("i", $userId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedUser = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldUser && $updatedUser) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('users', $userId, 'update', 'settings', $oldUser, $updatedUser);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'User status updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 