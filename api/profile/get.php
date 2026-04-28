<?php
/**
 * EN: Handles API endpoint/business logic in `api/profile/get.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/profile/get.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = intval($_SESSION['user_id']);
    
    // Get full user data including fingerprint status, country, city
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            CASE WHEN (fp.latest_template_id IS NOT NULL OR wc.credential_id IS NOT NULL) THEN 1 ELSE 0 END AS has_fingerprint,
            CASE WHEN (fp.latest_template_id IS NOT NULL OR wc.credential_id IS NOT NULL) THEN 'Registered' ELSE 'Not Registered' END AS fingerprint_status,
            r.role_name, r.description as role_description
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        LEFT JOIN (
            SELECT user_id, MAX(id) AS latest_template_id
            FROM fingerprint_templates
            WHERE template_data IS NOT NULL AND template_data <> ''
            GROUP BY user_id
        ) fp ON u.user_id = fp.user_id
        LEFT JOIN (
            SELECT user_id, credential_id
            FROM webauthn_credentials
            GROUP BY user_id
        ) wc ON u.user_id = wc.user_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $userProfile = [
            'user_id' => $userData['user_id'],
            'username' => $userData['username'],
            'email' => $userData['email'] ?? '',
            'phone' => $userData['phone'] ?? '',
            'country_id' => $userData['country_id'] ?? null,
            'city' => $userData['city'] ?? '',
            'has_fingerprint' => $userData['has_fingerprint'] ?? 0,
            'fingerprint_status' => $userData['fingerprint_status'] ?? 'Not Registered',
            'role' => $userData['role_name'] ?? ucfirst($_SESSION['role'] ?? 'User'),
            'role_id' => $userData['role_id'] ?? null,
            'status' => $userData['status'] ?? 'active',
            'last_login' => $userData['last_login'] ? date('M d, Y H:i', strtotime($userData['last_login'])) : 'Never',
            'created_at' => $userData['created_at'] ? date('M d, Y', strtotime($userData['created_at'])) : '',
            'role_description' => $userData['role_description'] ?? ''
        ];
        
        // Get country name if country_id exists
        if ($userProfile['country_id']) {
            try {
                $countryStmt = $conn->prepare("SELECT country_name FROM recruitment_countries WHERE id = ?");
                $countryStmt->bind_param("i", $userProfile['country_id']);
                $countryStmt->execute();
                $countryResult = $countryStmt->get_result();
                if ($countryResult->num_rows > 0) {
                    $countryData = $countryResult->fetch_assoc();
                    $userProfile['country_name'] = $countryData['country_name'];
                }
            } catch (Exception $e) {
                // Country lookup failed
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $userProfile
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User profile not found'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching profile: ' . $e->getMessage()
    ]);
}
?>
