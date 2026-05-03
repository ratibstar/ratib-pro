<?php
/**
 * POST: create / resolve chart-of-accounts row for a partner agency (Ratib Pro GL).
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyController.php';

function partnerAgencyLinkJson(array $payload, int $status = 200): void
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
        partnerAgencyLinkJson(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    enforceApiPermission('partnerships', 'update');
    if (!hasPermission('add_account')) {
        throw new Exception('Add account permission is required to create a ledger link.');
    }
} catch (Exception $e) {
    $code = (int) ($e->getCode() ?: 0);
    $status = ($code >= 400 && $code < 600) ? $code : 403;
    partnerAgencyLinkJson(['success' => false, 'message' => $e->getMessage()], $status);
}

$db = Database::getInstance();
$conn = $db->getConnection();
ratibEnsureGlobalPartnershipsSchema($conn);
$controller = new PartnerAgencyController($conn);

$raw = json_decode((string) file_get_contents('php://input'), true) ?: [];
$pid = (int) ($raw['partner_agency_id'] ?? 0);
if ($pid <= 0) {
    partnerAgencyLinkJson(['success' => false, 'message' => 'partner_agency_id is required'], 400);
}

try {
    $result = $controller->ensureFinancialAccount($pid);
    partnerAgencyLinkJson([
        'success' => true,
        'message' => !empty($result['created'])
            ? 'Ledger account created and linked to this partner.'
            : 'Partner is already linked to the chart of accounts.',
        'data' => $result,
    ]);
} catch (InvalidArgumentException $e) {
    partnerAgencyLinkJson(['success' => false, 'message' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    partnerAgencyLinkJson(['success' => false, 'message' => $e->getMessage()], 503);
} catch (Throwable $e) {
    error_log('partner-agency-account-link: ' . $e->getMessage());
    partnerAgencyLinkJson(['success' => false, 'message' => 'Could not link accounting.'], 500);
}
