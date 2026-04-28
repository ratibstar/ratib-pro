<?php
/**
 * EN: Handles API endpoint/business logic in `api/webauthn/register_finish.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/webauthn/register_finish.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Allow registration from admin panel OR from login page
    // Check if we have registration session data (either from admin or login)
    if (!isset($_SESSION['webauthn_register_user_id']) || !isset($_SESSION['webauthn_register_username'])) {
        echo json_encode(['success' => false, 'message' => 'Registration session expired. Please try again.']);
        exit;
    }
    
    // No admin session required - can be called from login page or admin panel
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['credentialId']) || !isset($data['publicKey'])) {
        echo json_encode(['success' => false, 'message' => 'Missing credential data.']);
        exit;
    }

    $targetUserId = $_SESSION['webauthn_register_user_id'];
    $username = $_SESSION['webauthn_register_username'];
    $credential_id = base64_decode($data['credentialId']);
    $public_key = base64_decode($data['publicKey']);

    // Verify user still exists and is active
    $verifyStmt = $conn->prepare("SELECT user_id, status FROM users WHERE user_id = ? LIMIT 1");
    $verifyStmt->bind_param("i", $targetUserId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    
    if ($verifyResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $userRow = $verifyResult->fetch_assoc();
    if (strcasecmp($userRow['status'], 'active') !== 0) {
        echo json_encode(['success' => false, 'message' => 'User account is not active']);
        exit;
    }
    $verifyStmt->close();

    // Store in webauthn_credentials
    $stmt = $conn->prepare("INSERT INTO webauthn_credentials (user_id, credential_id, public_key) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $targetUserId, $credential_id, $public_key);
    
    if ($stmt->execute()) {
        $credentialId = $stmt->insert_id;
        $stmt->close();
        
        // Also update fingerprint_templates table for compatibility
        // Generate a placeholder since webauthn doesn't store template data
        $placeholderTemplate = base64_encode('webauthn_credential_' . $credentialId);
        $updateTpl = $conn->prepare("
            INSERT INTO fingerprint_templates (user_id, template_data) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE template_data = ?
        ");
        $updateTpl->bind_param("iss", $targetUserId, $placeholderTemplate, $placeholderTemplate);
        $updateTpl->execute();
        $updateTpl->close();
        
        // Clear session variables
        unset($_SESSION['webauthn_register_user_id']);
        unset($_SESSION['webauthn_register_username']);
        unset($_SESSION['webauthn_challenge']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Fingerprint registered successfully for ' . $username,
            'userId' => $targetUserId,
            'username' => $username,
            'credentialId' => $credentialId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to store credential: ' . $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 