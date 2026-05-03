<?php
/**
 * Staff JSON — same payload shape as partner-portal-documents.php for a chosen agency (no partner portal session).
 */
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyController.php';
require_once __DIR__ . '/PartnerAgencyCvsController.php';
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

/**
 * @param array<string, mixed> $payload
 */
function partnerDocumentsStaffJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    try {
        enforceApiPermission('partnerships', 'view');
    } catch (Exception $permEx) {
        $msg = $permEx->getMessage();
        $code = (stripos($msg, 'Authentication required') !== false || stripos($msg, 'log in') !== false) ? 401 : 403;
        partnerDocumentsStaffJson(['success' => false, 'message' => $msg], $code);
    }

    $aid = (int) ($_GET['partner_agency_id'] ?? 0);
    if ($aid <= 0) {
        partnerDocumentsStaffJson(['success' => false, 'message' => 'partner_agency_id is required'], 400);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);

    $pa = new PartnerAgencyController($conn);
    try {
        $agency = $pa->portalSummary($aid);
    } catch (RuntimeException $e) {
        partnerDocumentsStaffJson(['success' => false, 'message' => 'Agency not found or no longer available.'], 404);
    }

    $cvs = [];
    try {
        $cvsCtl = new PartnerAgencyCvsController($conn);
        $cvs = $cvsCtl->listForAgency($aid);
    } catch (Throwable $cvsErr) {
        error_log('partner-documents-staff listForAgency: ' . $cvsErr->getMessage());
    }

    $sharedWorkerDocs = [];
    try {
        $shCtl = new PartnerAgencyWorkerDocSharesController($conn);
        $sharedWorkerDocs = $shCtl->listSharesWithDetails($aid);
    } catch (Throwable $shErr) {
        error_log('partner-documents-staff worker shares: ' . $shErr->getMessage());
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
        partnerDocumentsStaffJson(['success' => false, 'message' => 'Could not encode response'], 500);
    }
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(200);
    echo $json;
    exit;
} catch (Throwable $e) {
    error_log('partner-documents-staff: ' . $e->getMessage());
    partnerDocumentsStaffJson(['success' => false, 'message' => 'Internal server error'], 500);
}
