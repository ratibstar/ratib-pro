<?php
/**
 * EN: Handles API endpoint/business logic in `api/biometric/register_face.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/biometric/register_face.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? null;
    $faceImageData = $input['faceImageData'] ?? null;
    $faceTemplate = $input['faceTemplate'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }
    
    if (!$faceImageData || !$faceTemplate) {
        echo json_encode(['success' => false, 'message' => 'Face data required']);
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
            'registrationTime' => date('Y-m-d H:i:s')
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
        
        echo json_encode([
            'success' => true, 
            'message' => 'Face biometric registered successfully',
            'templateId' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to store face template']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 