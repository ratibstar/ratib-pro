<?php
/**
 * Partner portal login: magic token or agency id + password.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
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
    $emailLogin = trim((string) ($input['email'] ?? ''));
    $passwordLogin = (string) ($input['password'] ?? '');
    $token = trim((string) ($input['token'] ?? ''));
    $agencyId = (int) ($input['agency_id'] ?? 0);
    $password = (string) ($input['password'] ?? '');

    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);

    if ($emailLogin !== '' && $passwordLogin !== '') {
        $stmt = $conn->prepare(
            'SELECT id, portal_password_hash FROM partner_agencies
             WHERE portal_enabled = 1
               AND email IS NOT NULL AND TRIM(email) <> \'\'
               AND LOWER(TRIM(email)) = LOWER(?)
             LIMIT 3'
        );
        $stmt->execute([$emailLogin]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) === 1) {
            $hash = (string) ($matches[0]['portal_password_hash'] ?? '');
            if ($hash !== '' && password_verify($passwordLogin, $hash)) {
                session_regenerate_id(true);
                if (function_exists('ratib_partner_portal_clear')) {
                    ratib_partner_portal_clear();
                }
                $_SESSION['partner_portal_logged_in'] = true;
                $_SESSION['partner_portal_agency_id'] = (int) $matches[0]['id'];
                partnerPortalAuthJson([
                    'success' => true,
                    'message' => 'Signed in',
                    'agency_id' => (int) $matches[0]['id'],
                ]);
            }
        }
        partnerPortalAuthJson(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    if ($token !== '') {
        $stmt = $conn->prepare(
            'SELECT id FROM partner_agencies
             WHERE portal_enabled = 1
               AND portal_access_token IS NOT NULL
               AND portal_access_token <> \'\'
               AND portal_access_token = ?
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            session_regenerate_id(true);
            if (function_exists('ratib_partner_portal_clear')) {
                ratib_partner_portal_clear();
            }
            $_SESSION['partner_portal_logged_in'] = true;
            $_SESSION['partner_portal_agency_id'] = (int) $row['id'];
            partnerPortalAuthJson([
                'success' => true,
                'message' => 'Signed in',
                'agency_id' => (int) $row['id'],
            ]);
        }
        partnerPortalAuthJson(['success' => false, 'message' => 'Invalid or expired access link. Ask your office for a new link.'], 401);
    }

    if ($agencyId > 0 && $password !== '') {
        $stmt = $conn->prepare(
            'SELECT id, portal_password_hash FROM partner_agencies WHERE id = ? AND portal_enabled = 1 LIMIT 1'
        );
        $stmt->execute([$agencyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $hash = (string) ($row['portal_password_hash'] ?? '');
        if ($row && $hash !== '' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            if (function_exists('ratib_partner_portal_clear')) {
                ratib_partner_portal_clear();
            }
            $_SESSION['partner_portal_logged_in'] = true;
            $_SESSION['partner_portal_agency_id'] = (int) $row['id'];
            partnerPortalAuthJson([
                'success' => true,
                'message' => 'Signed in',
                'agency_id' => (int) $row['id'],
            ]);
        }
        partnerPortalAuthJson(['success' => false, 'message' => 'Invalid agency ID or password'], 401);
    }

    partnerPortalAuthJson(['success' => false, 'message' => 'Send email and password, token, or agency_id and password'], 400);
} catch (Throwable $e) {
    partnerPortalAuthJson(['success' => false, 'message' => $e->getMessage()], 500);
}
