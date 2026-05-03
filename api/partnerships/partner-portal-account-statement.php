<?php
/**
 * GET: same GL statement as staff, scoped to logged-in partner portal session (read-only).
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
require_once __DIR__ . '/partner-agency-account-statement-lib.php';

function partnerPortalStmtJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!function_exists('ratib_partner_portal_session_is_valid') || !ratib_partner_portal_session_is_valid()) {
    partnerPortalStmtJson(['success' => false, 'message' => 'Partner portal session required'], 401);
}

$pid = (int) ratib_partner_portal_agency_id();
if ($pid <= 0) {
    partnerPortalStmtJson(['success' => false, 'message' => 'Invalid session'], 401);
}

$db = Database::getInstance();
$conn = $db->getConnection();
ratibEnsureGlobalPartnershipsSchema($conn);
$controller = new PartnerAgencyController($conn);

$linkedId = $controller->resolveLinkedFinancialAccountId($pid);
if ($linkedId === null) {
    partnerPortalStmtJson([
        'success' => true,
        'linked' => false,
        'message' => 'Your ledger is not connected yet. When your office links this agency to accounting, your statement will appear here.',
    ]);
}

[$start, $end] = partnerAgencyStmtNormalizeDates(
    isset($_GET['start_date']) ? (string) $_GET['start_date'] : null,
    isset($_GET['end_date']) ? (string) $_GET['end_date'] : null
);

try {
    $body = partnerAgencyStmtBuildForAccount($conn, $linkedId, $start, $end);
    partnerPortalStmtJson(array_merge(['success' => true, 'linked' => true], $body));
} catch (Throwable $e) {
    error_log('partner-portal-account-statement: ' . $e->getMessage());
    partnerPortalStmtJson(['success' => false, 'message' => 'Could not load account statement.'], 500);
}
