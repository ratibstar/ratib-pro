<?php
declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/bootstrap.php';

ratib_client_dashboard_api_require_access();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false,"message":"method_not_allowed"}';
    exit;
}

$raw = file_get_contents('php://input');
$data = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
$verb = strtolower(trim((string) ($data['action'] ?? $_POST['action'] ?? '')));
$targetId = isset($data['target_id']) ? trim((string) $data['target_id']) : trim((string) ($_POST['target_id'] ?? ''));

$accepted = ['renew', 'suspend', 'restart', 'cancel', 'upgrade', 'retry_payment', 'open_ticket'];
if ($verb === '' || !in_array($verb, $accepted, true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'unsupported_action',
        'accepted' => $accepted,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Actions/ActionDispatcher.php';

$out = Ratib_ClientDashboard_Action_Dispatcher::dispatch($verb, $targetId, $data);
echo json_encode(
    array_merge(
        [
            'action' => $verb,
            'target_id' => $targetId,
        ],
        $out
    ),
    JSON_UNESCAPED_SLASHES
);
