<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/delete_user.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/delete_user.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $user_id = $_POST['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);

require_once '../../includes/permission_middleware.php';

// Check if user has permission to access this endpoint
checkApiPermission('users_delete');


        exit;
    }
    
    // Get deleted data for history (before deletion)
    $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $fetchStmt->bind_param("i", $user_id);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $deletedUser = $result->fetch_assoc();
    
    // Prevent deleting the current admin user
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete user permissions
        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Delete biometric data
        $stmt = $conn->prepare("DELETE FROM face_templates WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM fingerprint_templates WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM biometric_credentials WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Delete biometric logs
        $stmt = $conn->prepare("DELETE FROM biometric_logs WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Delete password resets
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Delete WebAuthn credentials
        $stmt = $conn->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Finally delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $deletedUser) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('users', $user_id, 'delete', 'settings', $deletedUser, null);
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'User "' . $deletedUser['username'] . '" deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 