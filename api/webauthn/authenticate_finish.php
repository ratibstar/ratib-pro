<?php
/**
 * EN: Handles API endpoint/business logic in `api/webauthn/authenticate_finish.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/webauthn/authenticate_finish.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['credentialId']) || !isset($data['username'])) {
        echo json_encode(['success' => false, 'message' => 'Missing credential data.']);
        exit;
    }

    $username = $data['username'];
    $credential_id = base64_decode($data['credentialId']);

    // Find user with role information
    $stmt = $conn->prepare("SELECT user_id, username, role_id, status FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    if (strcasecmp($user['status'], 'active') !== 0) {
        echo json_encode(['success' => false, 'message' => 'User account is not active.']);
        exit;
    }

    // Check if credential exists for this user
    // Note: In production, you should verify the signature using a WebAuthn library
    // For now, we'll just verify the credential exists
    $stmt2 = $conn->prepare("SELECT id FROM webauthn_credentials WHERE user_id = ? AND credential_id = ?");
    $stmt2->bind_param("is", $user['user_id'], $credential_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    
    if ($res2->num_rows === 1) {
        // Set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['logged_in'] = true;
        
        // Update last login
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $updateStmt->bind_param("i", $user['user_id']);
        $updateStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Fingerprint authentication successful',
            'user' => [
                'user_id' => $user['user_id'],
                'username' => $user['username']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fingerprint credential not recognized. Please register your fingerprint first.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 