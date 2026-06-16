<?php
/**
 * Partner portal: update own contact / address fields (session scoped).
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
require_once __DIR__ . '/PartnerAgencyController.php';

function ppProfileJson(array $payload, int $status = 200): void
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
        ppProfileJson(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!function_exists('rateb_partner_portal_session_is_valid') || !rateb_partner_portal_session_is_valid()) {
        ppProfileJson(['success' => false, 'message' => 'Partner portal session required'], 401);
    }

    $aid = function_exists('rateb_partner_portal_agency_id') ? (int) rateb_partner_portal_agency_id() : 0;
    if ($aid <= 0) {
        ppProfileJson(['success' => false, 'message' => 'Invalid session'], 401);
    }

    $raw = (string) file_get_contents('php://input');
    $input = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($input)) {
        $input = [];
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratebEnsureGlobalPartnershipsSchema($conn);

    $ctl = new PartnerAgencyController($conn);
    $updated = $ctl->updatePartnerPortalProfile($aid, $input);
    $full = $ctl->show($aid);

    ppProfileJson(['success' => true, 'message' => 'Profile updated', 'data' => ['agency' => $full]]);
} catch (InvalidArgumentException $e) {
    ppProfileJson(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('partner-portal-profile-update: ' . $e->getMessage());
    ppProfileJson(['success' => false, 'message' => 'Could not save'], 500);
}
