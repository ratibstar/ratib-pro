<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/../../../includes/tenant-rollout-flags.php';

function trr_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    trr_json(['success' => false, 'message' => 'Unauthorized'], 401);
}
if (
    !hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS)
    && !hasControlPermission('view_control_system_settings')
    && !hasControlPermission(CONTROL_PERM_DASHBOARD)
) {
    trr_json(['success' => false, 'message' => 'Access denied'], 403);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!($ctrl instanceof mysqli)) {
    trr_json(['success' => false, 'message' => 'Control DB unavailable'], 500);
}

$flagKey = strtolower(trim((string) ($_GET['flag_key'] ?? '')));
$tenantId = (int) ($_GET['tenant_id'] ?? 0);
$countryId = (int) ($_GET['country_id'] ?? 0);
if ($flagKey === '') {
    trr_json(['success' => false, 'message' => 'flag_key is required'], 422);
}

$resolved = trf_resolve_effective_flag($ctrl, $flagKey, $tenantId, $countryId);
trr_json([
    'success' => true,
    'resolved' => $resolved,
]);
