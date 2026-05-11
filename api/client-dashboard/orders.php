<?php
declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo '{"ok":false,"message":"method_not_allowed"}';
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/bootstrap.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Data/FallbackPayloads.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Observability/ObservabilityHub.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Adapters/AdapterContext.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Adapters/OrdersAdapter.php';

ratib_client_dashboard_api_require_access();

$q = isset($_GET['q']) ? strtolower(trim((string) $_GET['q'])) : '';
$status = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
$pay = isset($_GET['payment_status']) ? strtolower(trim((string) $_GET['payment_status'])) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per = isset($_GET['per_page']) ? min(50, max(5, (int) $_GET['per_page'])) : 8;

$conn = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;
$obs = new Ratib_ClientDashboard_ObservabilityHub();
$ctx = Ratib_ClientDashboard_AdapterContext::fromSession($conn, $obs);
$rows = (new Ratib_ClientDashboard_OrdersAdapter())->fetchNormalized($ctx);

$filtered = array_values(array_filter($rows, static function ($r) use ($q, $status, $pay): bool {
    if ($status !== '' && ($r['status'] ?? '') !== $status) {
        return false;
    }
    if ($pay !== '' && ($r['payment_status'] ?? '') !== $pay) {
        return false;
    }
    if ($q !== '') {
        $blob = strtolower(($r['id'] ?? '') . ' ' . ($r['product'] ?? ''));
        if (strpos($blob, $q) === false) {
            return false;
        }
    }
    return true;
}));

$total = count($filtered);
$offset = ($page - 1) * $per;
$slice = array_slice($filtered, $offset, $per);

$payload = [
    'ok' => true,
    'generated_at' => gmdate('c'),
    'source' => $ctx->obs->snapshotSlice()['degraded_flags'] ? 'mixed' : 'adapter',
    'filters' => [
        'q' => $q,
        'status' => $status,
        'payment_status' => $pay,
    ],
    'pagination' => [
        'page' => $page,
        'per_page' => $per,
        'total' => $total,
        'total_pages' => $per > 0 ? (int) ceil($total / $per) : 0,
    ],
    'rows' => $slice,
];

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
