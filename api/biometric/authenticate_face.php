<?php
/**
 * EN: Handles API endpoint/business logic in `api/biometric/authenticate_face.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/biometric/authenticate_face.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $faceImageData = $input['faceImageData'] ?? null;
    $faceTemplate = $input['faceTemplate'] ?? null;
    $username = $input['username'] ?? null;
    
    if (!$faceImageData || !$faceTemplate) {
        echo json_encode(['success' => false, 'message' => 'Face data required']);
        exit;
    }
    
    // Find user by username
    $stmt = $conn->prepare("SELECT user_id, username, role_id FROM users WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['user_id'];
    
    // Get stored face template
    $stmt2 = $conn->prepare("SELECT template_data, confidence_threshold FROM face_templates WHERE user_id = ?");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($result2->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No face template found for this user']);
        exit;
    }
    
    $storedTemplate = $result2->fetch_assoc();
    $storedTemplateData = $storedTemplate['template_data'];
    $confidenceThreshold = $storedTemplate['confidence_threshold'];
    
    // Compare face templates (simplified comparison for demo)
    $similarity = compareFaceTemplates($faceTemplate, $storedTemplateData);
    
    if ($similarity >= $confidenceThreshold) {
        // Log successful authentication
        logBiometricAuth($userId, 'face', 'success', $similarity);
        
        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['logged_in'] = true;
        
        // Update last login
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $updateStmt->bind_param("i", $userId);
        $updateStmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Face authentication successful',
            'confidence' => $similarity,
            'user' => [
                'user_id' => $userId,
                'username' => $user['username']
            ]
        ]);
    } else {
        // Log failed authentication
        logBiometricAuth($userId, 'face', 'failure', $similarity);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Face authentication failed - confidence too low',
            'confidence' => $similarity,
            'threshold' => $confidenceThreshold
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Function to compare face templates (simplified)
function compareFaceTemplates($template1, $template2) {
    // In a real implementation, this would use advanced face recognition algorithms
    // For demo purposes, we'll do a simple comparison
    
    // Decode base64 templates
    $data1 = base64_decode($template1);
    $data2 = base64_decode($template2);
    
    // Simple similarity calculation (very basic)
    $similarity = 0.0;
    
    if (strlen($data1) > 0 && strlen($data2) > 0) {
        // Calculate basic similarity based on data length and content
        $minLength = min(strlen($data1), strlen($data2));
        $maxLength = max(strlen($data1), strlen($data2));
        
        if ($maxLength > 0) {
            $lengthSimilarity = $minLength / $maxLength;
            
            // Calculate content similarity (very simplified)
            $contentSimilarity = 0.0;
            for ($i = 0; $i < $minLength; $i++) {
                if ($data1[$i] === $data2[$i]) {
                    $contentSimilarity += 1.0;
                }
            }
            $contentSimilarity = $contentSimilarity / $minLength;
            
            $similarity = ($lengthSimilarity + $contentSimilarity) / 2.0;
        }
    }
    
    // Add some randomness to simulate real face recognition
    $similarity += (rand(-10, 10) / 100.0);
    $similarity = max(0.0, min(1.0, $similarity));
    
    return $similarity;
}

// Function to log biometric authentication
function logBiometricAuth($userId, $type, $result, $confidence = null) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO biometric_logs (user_id, credential_type, authentication_result, confidence_score, device_info, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt->bind_param("issdsss", $userId, $type, $result, $confidence, $deviceInfo, $ipAddress, $userAgent);
    $stmt->execute();
}
?> 