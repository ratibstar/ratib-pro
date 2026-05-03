<?php
/**
 * GET: partner agency account statement from main journal / chart (Ratib Pro GL) — staff session.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyController.php';
require_once __DIR__ . '/partner-agency-account-statement-lib.php';

function partnerAgencyStmtJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    enforceApiPermission('partnerships', 'get');
    if (!hasPermission('view_chart_accounts')) {
        throw new Exception('Chart of accounts permission is required.');
    }
} catch (Exception $e) {
    partnerAgencyStmtJson(['success' => false, 'message' => $e->getMessage()], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();
ratibEnsureGlobalPartnershipsSchema($conn);
$controller = new PartnerAgencyController($conn);

$pid = (int) ($_GET['partner_agency_id'] ?? 0);
if ($pid <= 0) {
    partnerAgencyStmtJson(['success' => false, 'message' => 'partner_agency_id is required'], 400);
}

$linkedId = $controller->resolveLinkedFinancialAccountId($pid);
if ($linkedId === null) {
    partnerAgencyStmtJson([
        'success' => true,
        'linked' => false,
        'message' => 'Account statement and billing history will appear here when linked to accounting.',
    ]);
}

[$start, $end] = partnerAgencyStmtNormalizeDates(
    isset($_GET['start_date']) ? (string) $_GET['start_date'] : null,
    isset($_GET['end_date']) ? (string) $_GET['end_date'] : null
);

try {
    $body = partnerAgencyStmtBuildForAccount($conn, $linkedId, $start, $end, $pid);
    partnerAgencyStmtJson(array_merge(['success' => true, 'linked' => true], $body));
} catch (Throwable $e) {
    error_log('partner-agency-account-statement: ' . $e->getMessage());
    $code = $e instanceof RuntimeException ? 404 : 500;
    partnerAgencyStmtJson(['success' => false, 'message' => 'Could not load account statement.'], $code);
}
