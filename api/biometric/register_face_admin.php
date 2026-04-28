<?php
/**
 * EN: Handles API endpoint/business logic in `api/biometric/register_face_admin.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/biometric/register_face_admin.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Handle FormData input
    $userId = $_POST['userId'] ?? null;
    $username = $_POST['username'] ?? null;
    $faceImageData = $_POST['faceImageData'] ?? null;
    $faceTemplate = $_POST['faceTemplate'] ?? null;
    
    // Check if admin is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }
    
    if (!$userId || !$username || !$faceImageData || !$faceTemplate) {
        echo json_encode(['success' => false, 'message' => 'All face data required']);
        exit;
    }
    
    // Verify the user exists
    $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE user_id = ? AND username = ? AND status = 'active'");
    $stmt->bind_param("is", $userId, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found or inactive']);
        exit;
    }
    
    // Store face template in database
    $stmt = $conn->prepare("
        INSERT INTO face_templates (user_id, template_data, template_version, confidence_threshold) 
        VALUES (?, ?, '1.0', 0.80)
        ON DUPLICATE KEY UPDATE 
        template_data = VALUES(template_data),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param("is", $userId, $faceTemplate);
    
    if ($stmt->execute()) {
        // Also store in biometric_credentials table
        $credentialData = json_encode([
            'faceImageData' => $faceImageData,
            'faceTemplate' => $faceTemplate,
            'deviceInfo' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'registrationTime' => date('Y-m-d H:i:s'),
            'registeredBy' => $_SESSION['user_id']
        ]);
        
        $stmt2 = $conn->prepare("
            INSERT INTO biometric_credentials (user_id, credential_type, credential_data, device_id) 
            VALUES (?, 'face', ?, ?)
            ON DUPLICATE KEY UPDATE 
            credential_data = VALUES(credential_data),
            last_used = CURRENT_TIMESTAMP
        ");
        
        $deviceId = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt2->bind_param("iss", $userId, $credentialData, $deviceId);
        $stmt2->execute();
        
        // Log the registration
        $logStmt = $conn->prepare("
            INSERT INTO biometric_logs (user_id, credential_type, authentication_result, confidence_score, device_info, ip_address, user_agent) 
            VALUES (?, 'face', 'success', 1.0, ?, ?, ?)
        ");
        
        $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt->bind_param("isss", $userId, $deviceInfo, $ipAddress, $userAgent);
        $logStmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Face biometric registered successfully for ' . $username,
            'templateId' => $conn->insert_id,
            'userId' => $userId,
            'username' => $username
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to store face template']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 