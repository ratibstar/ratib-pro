<?php
/**
 * EN: Handles API endpoint/business logic in `api/biometric/authenticate_fingerprint.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/biometric/authenticate_fingerprint.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $fingerprintData = $input['fingerprintData'] ?? null;
    $username = $input['username'] ?? null;
    
    if (!$fingerprintData || !$username) {
        echo json_encode(['success' => false, 'message' => 'Fingerprint data and username required']);
        exit;
    }
    
    // Find user by username (case-insensitive)
    $stmt = $conn->prepare("SELECT user_id, username, role_id FROM users WHERE LOWER(username) = LOWER(?) AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found or account is inactive']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['user_id'];
    $actualUsername = $user['username'];
    
    // Get stored fingerprint template
    $stmt2 = $conn->prepare("SELECT template_data FROM fingerprint_templates WHERE user_id = ? AND template_data IS NOT NULL AND template_data <> ''");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($result2->num_rows === 0) {
        // Check if user exists in table but has empty template
        $checkStmt = $conn->prepare("SELECT id FROM fingerprint_templates WHERE user_id = ?");
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Fingerprint template exists but is empty. Please re-register your fingerprint in the admin panel.'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'No fingerprint registered for user "' . $actualUsername . '". Please register your fingerprint in the admin panel first.'
            ]);
        }
        exit;
    }
    
    $storedTemplate = $result2->fetch_assoc();
    $storedTemplateData = $storedTemplate['template_data'];
    
    // Compare fingerprint templates
    $similarity = compareFingerprintTemplates($fingerprintData, $storedTemplateData);
    $confidenceThreshold = 0.75; // 75% confidence threshold
    
    if ($similarity >= $confidenceThreshold) {
        // Log successful authentication
        logBiometricAuth($userId, 'fingerprint', 'success', $similarity);
        
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
            'message' => 'Fingerprint authentication successful',
            'confidence' => $similarity,
            'user' => [
                'user_id' => $userId,
                'username' => $user['username']
            ]
        ]);
    } else {
        // Log failed authentication
        logBiometricAuth($userId, 'fingerprint', 'failure', $similarity);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Fingerprint authentication failed - confidence too low',
            'confidence' => $similarity,
            'threshold' => $confidenceThreshold
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Function to compare fingerprint templates
function compareFingerprintTemplates($template1, $template2) {
    // In a real implementation, this would use advanced fingerprint recognition algorithms
    // For demo purposes, we'll do a simple comparison
    
    // Decode base64 templates
    $data1 = base64_decode($template1);
    $data2 = base64_decode($template2);
    
    // Simple similarity calculation
    $similarity = 0.0;
    
    if (strlen($data1) > 0 && strlen($data2) > 0) {
        // Calculate basic similarity based on data length and content
        $minLength = min(strlen($data1), strlen($data2));
        $maxLength = max(strlen($data1), strlen($data2));
        
        if ($maxLength > 0) {
            $lengthSimilarity = $minLength / $maxLength;
            
            // Calculate content similarity
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
    
    // Add some randomness to simulate real fingerprint recognition
    $similarity += (rand(-5, 5) / 100.0);
    $similarity = max(0.0, min(1.0, $similarity));
    
    return $similarity;
}

// Function to log biometric authentication (flexible columns)
function logBiometricAuth($userId, $type, $result, $confidence = null) {
    global $conn;
    
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'biometric_logs'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return; // Table doesn't exist, skip logging
    }
    
    try {
        // Get actual columns from the table
        $columnsResult = $conn->query("SHOW COLUMNS FROM biometric_logs");
        $actualColumns = [];
        if ($columnsResult) {
            while ($row = $columnsResult->fetch_assoc()) {
                $actualColumns[] = strtolower($row['Field']);
            }
            $columnsResult->free();
        }
        
        // Build insert with only columns that exist
        $insertColumns = [];
        $insertValues = [];
        $insertTypes = '';
        
        if (in_array('user_id', $actualColumns)) {
            $insertColumns[] = 'user_id';
            $insertValues[] = $userId;
            $insertTypes .= 'i';
        }
        
        // Check for credential_type or biometric_type
        if (in_array('credential_type', $actualColumns)) {
            $insertColumns[] = 'credential_type';
            $insertValues[] = $type;
            $insertTypes .= 's';
        } elseif (in_array('biometric_type', $actualColumns)) {
            $insertColumns[] = 'biometric_type';
            $insertValues[] = $type;
            $insertTypes .= 's';
        }
        
        // Check for authentication_result or success
        if (in_array('authentication_result', $actualColumns)) {
            $insertColumns[] = 'authentication_result';
            $insertValues[] = $result;
            $insertTypes .= 's';
        } elseif (in_array('success', $actualColumns)) {
            $insertColumns[] = 'success';
            $insertValues[] = ($result === 'success') ? 1 : 0;
            $insertTypes .= 'i';
        }
        
        if ($confidence !== null && in_array('confidence_score', $actualColumns)) {
            $insertColumns[] = 'confidence_score';
            $insertValues[] = $confidence;
            $insertTypes .= 'd';
        }
        
        if (in_array('device_info', $actualColumns)) {
            $insertColumns[] = 'device_info';
            $insertValues[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $insertTypes .= 's';
        }
        
        if (in_array('ip_address', $actualColumns)) {
            $insertColumns[] = 'ip_address';
            $insertValues[] = $_SERVER['REMOTE_ADDR'] ?? '';
            $insertTypes .= 's';
        }
        
        if (in_array('user_agent', $actualColumns)) {
            $insertColumns[] = 'user_agent';
            $insertValues[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $insertTypes .= 's';
        }
        
        // Only insert if we have at least one column
        if (!empty($insertColumns)) {
            $columnList = '`' . implode('`, `', $insertColumns) . '`';
            $placeholders = str_repeat('?,', count($insertColumns) - 1) . '?';
            $insertSql = "INSERT INTO biometric_logs ($columnList) VALUES ($placeholders)";
            
            $logStmt = $conn->prepare($insertSql);
            if ($logStmt) {
                $logStmt->bind_param($insertTypes, ...$insertValues);
                $logStmt->execute();
                $logStmt->close();
            }
        }
    } catch (Exception $e) {
        // Ignore logging errors - authentication should still work
        error_log("Note: Could not log biometric authentication: " . $e->getMessage());
    }
} 