<?php
/**
 * Partner portal — documents & CVs only (JSON). Lighter than partner-portal-me.php (no deployments).
 */
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../core/rateb_api_session.inc.php';
rateb_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyController.php';
require_once __DIR__ . '/PartnerAgencyCvsController.php';
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

/**
 * @param array<string, mixed> $payload
 */
function partnerPortalDocumentsJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if (!function_exists('rateb_partner_portal_session_is_valid') || !rateb_partner_portal_session_is_valid()) {
        partnerPortalDocumentsJson(['success' => false, 'message' => 'Partner portal session required'], 401);
    }

    $aid = rateb_partner_portal_agency_id();
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratebEnsureGlobalPartnershipsSchema($conn);

    $pa = new PartnerAgencyController($conn);
    try {
        $agency = $pa->portalSummary($aid);
    } catch (InvalidArgumentException | RuntimeException $e) {
        partnerPortalDocumentsJson(['success' => false, 'message' => 'Agency not found or no longer available.'], 404);
    }

    $cvs = [];
    try {
        $cvsCtl = new PartnerAgencyCvsController($conn);
        $cvs = $cvsCtl->listForAgency($aid);
    } catch (Throwable $cvsErr) {
        error_log('partner-portal-documents listForAgency: ' . $cvsErr->getMessage());
    }

    $sharedWorkerDocs = [];
    try {
        $shCtl = new PartnerAgencyWorkerDocSharesController($conn);
        $sharedWorkerDocs = $shCtl->listSharesWithDetails($aid);
    } catch (Throwable $shErr) {
        error_log('partner-portal-documents worker shares: ' . $shErr->getMessage());
    }

    $payload = [
        'success' => true,
        'data' => [
            'agency' => $agency,
            'cvs' => $cvs,
            'shared_worker_documents' => $sharedWorkerDocs,
        ],
    ];
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $jsonFlags);
    if ($json === false) {
        partnerPortalDocumentsJson(['success' => false, 'message' => 'Could not encode response'], 500);
    }
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(200);
    echo $json;
    exit;
} catch (Throwable $e) {
    error_log('partner-portal-documents: ' . $e->getMessage());
    partnerPortalDocumentsJson(['success' => false, 'message' => 'Internal server error'], 500);
}
