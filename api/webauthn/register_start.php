<?php
/**
 * EN: Handles API endpoint/business logic in `api/webauthn/register_start.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/webauthn/register_start.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Get target user ID (for admin registering for other users, or from username for login registration)
    $data = json_decode(file_get_contents('php://input'), true);
    $targetUserId = isset($data['userId']) ? (int) $data['userId'] : null;
    $username = $data['username'] ?? null;
    
    // Determine if this is login-based registration (username provided without admin session)
    $isLoginRegistration = !$targetUserId && $username
        && (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user());
    
    // If it's login registration, we don't need admin session
    // For admin panel registration, check if admin is logged in (app or control panel)
    if (!$isLoginRegistration && $targetUserId) {
        $isAppAdmin = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
        $isControlAdmin = !empty($_SESSION['control_logged_in']);
        if (!$isAppAdmin && !$isControlAdmin) {
            echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
            exit;
        }
    }
    
    // If userId not provided but username is, look up user by username
    if (!$targetUserId && $username) {
        $stmt = $conn->prepare("SELECT user_id, username, status FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $userRow = $result->fetch_assoc();
        $targetUserId = $userRow['user_id'];
        $username = $userRow['username'];
        
        // Only check status for login registration (when no admin session)
        // Admin can register fingerprint for any user (even inactive)
        if ($isLoginRegistration && strcasecmp($userRow['status'], 'active') !== 0) {
            echo json_encode(['success' => false, 'message' => 'User account is not active']);
            exit;
        }
    } else if ($targetUserId) {
        // Verify the target user exists
        $stmt = $conn->prepare("SELECT user_id, username, status FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $userRow = $result->fetch_assoc();
        
        // Note: Admin can register fingerprint for any user (even inactive)
        // Status check is only for login, not for admin registration
        
        // Use provided username or fall back to database value
        if (empty($username) || strcasecmp($username, $userRow['username']) !== 0) {
            $username = $userRow['username'];
        }
    } else {
        // Fall back to session user (admin registering themselves)
        // Only require admin session if not doing login registration
        if (!$isLoginRegistration) {
            if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
                echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
                exit;
            }
            $targetUserId = $_SESSION['user_id'];
            $username = $_SESSION['username'] ?? 'user';
        } else {
            echo json_encode(['success' => false, 'message' => 'User ID or username required']);
            exit;
        }
    }
    
    // Check if credential already exists
    $checkStmt = $conn->prepare("SELECT id FROM webauthn_credentials WHERE user_id = ?");
    $checkStmt->bind_param("i", $targetUserId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Delete existing credential to allow re-registration
        $deleteStmt = $conn->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?");
        $deleteStmt->bind_param("i", $targetUserId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    $checkStmt->close();
    
    // Generate challenge
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge'] = base64_encode($challenge);
    $_SESSION['webauthn_register_user_id'] = $targetUserId;
    $_SESSION['webauthn_register_username'] = $username;
    
    // Get RP ID from host
    $rpId = $_SERVER['HTTP_HOST'];
    if (strpos($rpId, ':') !== false) {
        $rpId = explode(':', $rpId)[0];
    }
    
    $options = [
        'publicKey' => [
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => 'Ratibprogram',
                'id' => $rpId
            ],
            'user' => [
                'id' => base64_encode(pack('N', $targetUserId)),
                'name' => $username,
                'displayName' => $username,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7], // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 120000, // 2 minutes timeout
            'attestation' => 'none',
            'authenticatorSelection' => [
                'userVerification' => 'preferred' // Prefer fingerprint but allow other methods
                // Removed authenticatorAttachment restriction to be more flexible
            ]
        ]
    ];
    
    echo json_encode($options);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 