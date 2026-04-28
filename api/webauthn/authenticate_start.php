<?php
/**
 * EN: Handles API endpoint/business logic in `api/webauthn/authenticate_start.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/webauthn/authenticate_start.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    
    if (!$username) {
        echo json_encode(['success' => false, 'message' => 'Missing username.']);
        exit;
    }

    // Find user (case-insensitive)
    $stmt = $conn->prepare("SELECT user_id, username, status FROM users WHERE LOWER(username) = LOWER(?)");
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

    // Get credential_id for user
    $stmt2 = $conn->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ? LIMIT 1");
    $stmt2->bind_param("i", $user['user_id']);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    
    if ($res2->num_rows !== 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'No fingerprint credential registered for this user. Please register your fingerprint first in the admin panel.'
        ]);
        exit;
    }
    
    $cred = $res2->fetch_assoc();

    // Generate challenge
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge'] = base64_encode($challenge);
    $_SESSION['webauthn_auth_user_id'] = $user['user_id'];

    // Get RP ID from host
    $rpId = $_SERVER['HTTP_HOST'];
    if (strpos($rpId, ':') !== false) {
        $rpId = explode(':', $rpId)[0];
    }

    $options = [
        'publicKey' => [
            'challenge' => base64_encode($challenge),
            'rpId' => $rpId,
            'allowCredentials' => [[
                'type' => 'public-key',
                'id' => base64_encode($cred['credential_id']),
                'transports' => ['internal']
            ]],
            'timeout' => 60000,
            'userVerification' => 'required'
        ]
    ];

    echo json_encode($options);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 