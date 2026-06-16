<?php
/**
 * List / upload / delete partner agency CVs. Staff: GET/POST/DELETE with permissions.
 * Partners (portal session): GET only (download via partner-agency-cv-download.php).
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
    ratebEnsureGlobalPartnershipsSchema($conn);
    $cvs = new PartnerAgencyCvsController($conn);

    if ($method === 'GET') {
        $agencyId = (int) ($_GET['partner_agency_id'] ?? 0);
        if (function_exists('rateb_partner_portal_session_is_valid') && rateb_partner_portal_session_is_valid()) {
            $agencyId = rateb_partner_portal_agency_id();
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
        if ($cvId <= 0) {
            throw new InvalidArgumentException('id is required');
        }
        if ($agencyId <= 0) {
            throw new InvalidArgumentException('partner_agency_id is required');
        }
        $cvs->delete($cvId, $agencyId);
        partnerAgencyCvsJson(['success' => true, 'message' => 'Document removed']);
    }

    if ($method === 'PATCH') {
        enforceApiPermission('partnerships', 'update');
        $raw = (string) file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($json)) {
            $json = [];
        }
        $cvId = (int) ($json['id'] ?? 0);
        $agencyId = (int) ($json['partner_agency_id'] ?? 0);
        if ($cvId <= 0 || $agencyId <= 0) {
            throw new InvalidArgumentException('id and partner_agency_id are required');
        }
        $displayStatus = array_key_exists('display_status', $json) ? $json['display_status'] : false;
        if ($displayStatus === false) {
            throw new InvalidArgumentException('display_status is required (use null or empty for automatic)');
        }
        $cvs->updateDisplayStatus($cvId, $agencyId, $displayStatus);
        partnerAgencyCvsJson(['success' => true, 'message' => 'Status updated']);
    }

    partnerAgencyCvsJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (InvalidArgumentException $e) {
    partnerAgencyCvsJson(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    partnerAgencyCvsJson(['success' => false, 'message' => $e->getMessage()], 500);
}
