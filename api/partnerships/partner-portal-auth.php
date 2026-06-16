<?php
/**
 * Partner portal login: username + password (username can be agency ID, email, or partner name).
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/rateb_api_session.inc.php';
rateb_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';

function partnerPortalAuthJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        partnerPortalAuthJson(['success' => false, 'message' => 'POST required'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $usernameLogin = trim((string) ($input['username'] ?? ''));
    $passwordLogin = (string) ($input['password'] ?? '');
    // Backward compatibility for older clients still sending `email`.
    if ($usernameLogin === '') {
        $usernameLogin = trim((string) ($input['email'] ?? ''));
    }
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratebEnsureGlobalPartnershipsSchema($conn);

    if ($usernameLogin !== '' && $passwordLogin !== '') {
        $isNumericId = ctype_digit($usernameLogin);
        if ($isNumericId) {
            $stmt = $conn->prepare(
                'SELECT id, portal_password_hash FROM partner_agencies
                 WHERE portal_enabled = 1 AND id = ?
                 LIMIT 1'
            );
            $stmt->execute([(int) $usernameLogin]);
        } else {
            $stmt = $conn->prepare(
                'SELECT id, portal_password_hash FROM partner_agencies
                 WHERE portal_enabled = 1
                   AND (
                        (email IS NOT NULL AND TRIM(email) <> \'\' AND LOWER(TRIM(email)) = LOWER(?))
                        OR LOWER(TRIM(name)) = LOWER(?)
                   )
                 LIMIT 5'
            );
            $stmt->execute([$usernameLogin, $usernameLogin]);
        }
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($matches)) {
            $selected = null;
            foreach ($matches as $candidate) {
                $hash = (string) ($candidate['portal_password_hash'] ?? '');
                if ($hash !== '' && password_verify($passwordLogin, $hash)) {
                    $selected = $candidate;
                    break;
                }
            }
            if ($selected !== null) {
                session_regenerate_id(true);
                if (function_exists('rateb_partner_portal_clear')) {
                    rateb_partner_portal_clear();
                }
                $_SESSION['partner_portal_logged_in'] = true;
                $_SESSION['partner_portal_agency_id'] = (int) ($selected['id'] ?? 0);
                partnerPortalAuthJson([
                    'success' => true,
                    'message' => 'Signed in',
                    'agency_id' => (int) ($selected['id'] ?? 0),
                ]);
            }
        }
        partnerPortalAuthJson(['success' => false, 'message' => 'Invalid username or password'], 401);
    }

    partnerPortalAuthJson(['success' => false, 'message' => 'Send username and password'], 400);
} catch (Throwable $e) {
    partnerPortalAuthJson(['success' => false, 'message' => $e->getMessage()], 500);
}
