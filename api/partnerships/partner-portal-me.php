<?php
/**
 * Partner portal dashboard payload: agency + deployments + CVs (session scoped).
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
require_once __DIR__ . '/PartnerAgencyController.php';
require_once __DIR__ . '/PartnerAgencyCvsController.php';

function partnerPortalMeJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if (!function_exists('ratib_partner_portal_session_is_valid') || !ratib_partner_portal_session_is_valid()) {
        partnerPortalMeJson(['success' => false, 'message' => 'Partner portal session required'], 401);
    }

    $aid = ratib_partner_portal_agency_id();
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);

    $pa = new PartnerAgencyController($conn);
    try {
        $agency = $pa->show($aid);
    } catch (RuntimeException $e) {
        partnerPortalMeJson(['success' => false, 'message' => 'Agency not found or no longer available.'], 404);
    }

    $cvs = [];
    try {
        $cvsCtl = new PartnerAgencyCvsController($conn);
        $cvs = $cvsCtl->listForAgency($aid);
    } catch (Throwable $cvsErr) {
        error_log('partner-portal-me listForAgency: ' . $cvsErr->getMessage());
    }

    $payload = [
        'success' => true,
        'data' => [
            'agency' => $agency,
            'cvs' => $cvs,
        ],
    ];
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $jsonFlags);
    if ($json === false) {
        partnerPortalMeJson(['success' => false, 'message' => 'Could not encode response'], 500);
    }
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(200);
    echo $json;
    exit;
} catch (Throwable $e) {
    error_log('partner-portal-me: ' . $e->getMessage());
    partnerPortalMeJson(['success' => false, 'message' => 'Internal server error'], 500);
}
