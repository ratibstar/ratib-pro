<?php
/**
 * EN: Handles API endpoint/business logic in `api/profile/update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/profile/update.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $email = $data['email'] ?? $_POST['email'] ?? '';
        $phone = $data['phone'] ?? $_POST['phone'] ?? '';
        
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Valid email is required']);
            exit;
        }
        
        // Check if email is already taken by another user
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $checkStmt->bind_param("si", $email, $_SESSION['user_id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email is already taken by another user']);
            exit;
        }
        
        // Get old data for history
        $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $fetchStmt->bind_param("i", $_SESSION['user_id']);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $oldUser = $result->fetch_assoc();
        
        // Update user profile
        $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("ssi", $email, $phone, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            // Get updated user for history
            $fetchStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $fetchStmt->bind_param("i", $_SESSION['user_id']);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            $updatedUser = $result->fetch_assoc();
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $oldUser && $updatedUser) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    @logGlobalHistory('users', $_SESSION['user_id'], 'update', 'settings', $oldUser, $updatedUser);
                }
            }
            
            // Log activity
            $activityDesc = "Updated profile information";
            $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, module) VALUES (?, 'update', ?, 'profile')");
            $activityStmt->bind_param("is", $_SESSION['user_id'], $activityDesc);
            $activityStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'email' => $email,
                    'phone' => $phone
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        
    } elseif ($action === 'change_password') {
        $currentPassword = $data['current_password'] ?? $_POST['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? $_POST['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? $_POST['confirm_password'] ?? '';
        
        // Validate passwords
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            echo json_encode(['success' => false, 'message' => 'All password fields are required']);
            exit;
        }
        
        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        if (!password_verify($currentPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
        
        if ($updateStmt->execute()) {
            // Log activity
            $activityDesc = "Changed password";
            $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, module) VALUES (?, 'update', ?, 'profile')");
            $activityStmt->bind_param("is", $_SESSION['user_id'], $activityDesc);
            $activityStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to change password']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Profile Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

