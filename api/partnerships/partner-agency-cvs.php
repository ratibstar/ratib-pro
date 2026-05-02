<?php
/**
 * List / upload / delete partner agency CVs. Staff: all methods with permissions.
 * Partners (portal session): GET/POST/DELETE for their agency only.
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
        if (function_exists('ratib_partner_portal_session_is_valid') && ratib_partner_portal_session_is_valid()) {
            $agencyId = ratib_partner_portal_agency_id();
            if ($agencyId <= 0) {
                throw new InvalidArgumentException('Partner portal session required');
            }
            $title = trim((string) ($_POST['title'] ?? ''));
            if (!isset($_FILES['file'])) {
                throw new InvalidArgumentException('file is required');
            }
            $created = $cvs->create($agencyId, $title, $_FILES['file']);
            partnerAgencyCvsJson(['success' => true, 'message' => 'Document uploaded', 'data' => $created], 201);
        }

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
        $cvId = (int) ($_GET['id'] ?? 0);
        if ($cvId <= 0) {
            throw new InvalidArgumentException('id is required');
        }
        if (function_exists('ratib_partner_portal_session_is_valid') && ratib_partner_portal_session_is_valid()) {
            $agencyId = ratib_partner_portal_agency_id();
            if ($agencyId <= 0) {
                throw new InvalidArgumentException('Partner portal session required');
            }
            $cvs->delete($cvId, $agencyId);
            partnerAgencyCvsJson(['success' => true, 'message' => 'Document removed']);
        }

        enforceApiPermission('partnerships', 'delete');
        $agencyId = (int) ($_GET['partner_agency_id'] ?? 0);
        if ($agencyId <= 0) {
            throw new InvalidArgumentException('partner_agency_id is required');
        }
        $cvs->delete($cvId, $agencyId);
        partnerAgencyCvsJson(['success' => true, 'message' => 'Document removed']);
    }

    partnerAgencyCvsJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    partnerAgencyCvsJson(['success' => false, 'message' => $e->getMessage()], 500);
}
