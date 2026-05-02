<?php
/**
 * List / upload / delete partner agency CVs (staff). Partners: GET list only for own agency.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyCvsController.php';

function partnerAgencyCvsJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    $cvs = new PartnerAgencyCvsController($conn);

    if ($method === 'GET') {
        $agencyId = (int) ($_GET['partner_agency_id'] ?? 0);
        if (function_exists('ratib_partner_portal_session_is_valid') && ratib_partner_portal_session_is_valid()) {
            $agencyId = ratib_partner_portal_agency_id();
        } else {
            enforceApiPermission('partnerships', 'view');
            if ($agencyId <= 0) {
                throw new InvalidArgumentException('partner_agency_id is required');
            }
        }
        partnerAgencyCvsJson(['success' => true, 'data' => $cvs->listForAgency($agencyId)]);
    }

    if ($method === 'POST') {
        enforceApiPermission('partnerships', 'update');
        $agencyId = (int) ($_POST['partner_agency_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($agencyId <= 0) {
            throw new InvalidArgumentException('partner_agency_id is required');
        }
        if (!isset($_FILES['file'])) {
            throw new InvalidArgumentException('file is required');
        }
        $created = $cvs->create($agencyId, $title, $_FILES['file']);
        partnerAgencyCvsJson(['success' => true, 'message' => 'Document uploaded', 'data' => $created], 201);
    }

    if ($method === 'DELETE') {
        enforceApiPermission('partnerships', 'delete');
        $cvId = (int) ($_GET['id'] ?? 0);
        $agencyId = (int) ($_GET['partner_agency_id'] ?? 0);
        if ($cvId <= 0 || $agencyId <= 0) {
            throw new InvalidArgumentException('id and partner_agency_id are required');
        }
        $cvs->delete($cvId, $agencyId);
        partnerAgencyCvsJson(['success' => true, 'message' => 'Document removed']);
    }

    partnerAgencyCvsJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    partnerAgencyCvsJson(['success' => false, 'message' => $e->getMessage()], 500);
}
