<?php
/**
 * EN: Handles API endpoint/business logic in `api/webauthn/authenticate_finish_auto.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/webauthn/authenticate_finish_auto.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $credentialId = $input['credentialId'] ?? null;
    
    if (!$credentialId) {
        echo json_encode(['success' => false, 'message' => 'No credential ID provided']);
        exit;
    }
    
    // Find the user associated with this credential
    $credentialIdBin = base64_decode($credentialId);
    
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.role_id 
        FROM webauthn_credentials wc 
        JOIN users u ON wc.user_id = u.user_id 
        WHERE wc.credential_id = ? AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("s", $credentialIdBin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid credential or user not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Set session for successful login
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['logged_in'] = true;
    
    // Update last login
    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $updateStmt->bind_param("i", $user['user_id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Update credential last_used timestamp
    $updateCredStmt = $conn->prepare("UPDATE webauthn_credentials SET last_used = NOW() WHERE credential_id = ?");
    $updateCredStmt->bind_param("s", $credentialIdBin);
    $updateCredStmt->execute();
    $updateCredStmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Fingerprint authentication successful',
        'user' => [
            'user_id' => $user['user_id'],
            'username' => $user['username']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 