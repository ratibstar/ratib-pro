<?php
/**
 * EN: Handles API endpoint/business logic in `api/webauthn/authenticate_start_auto.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/webauthn/authenticate_start_auto.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Find ALL registered credentials (for auto-authentication)
    $stmt = $conn->prepare("
        SELECT wc.credential_id, u.user_id, u.username 
        FROM webauthn_credentials wc 
        JOIN users u ON wc.user_id = u.user_id 
        WHERE u.status = 'active'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No fingerprint registered. Please register in admin panel first.']);
        exit;
    }
    
    // Build list of all credentials
    $allowCredentials = [];
    while ($row = $result->fetch_assoc()) {
        $allowCredentials[] = [
            'type' => 'public-key',
            'id' => base64_encode($row['credential_id']),
            'transports' => ['internal']
        ];
    }
    
    // Generate challenge (don't set session until authentication succeeds)
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge'] = base64_encode($challenge);
    
    // Get RP ID from host
    $rpId = $_SERVER['HTTP_HOST'];
    if (strpos($rpId, ':') !== false) {
        $rpId = explode(':', $rpId)[0];
    }
    
    // Create authentication options with all credentials
    $options = [
        'publicKey' => [
            'challenge' => base64_encode($challenge),
            'timeout' => 60000,
            'userVerification' => 'required',
            'rpId' => $rpId,
            'allowCredentials' => $allowCredentials
        ]
    ];
    
    echo json_encode($options);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 