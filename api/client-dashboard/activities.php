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
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Activity/ActivityStreamBuilder.php';

rateb_client_dashboard_api_require_access();

$conn = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;

$stream = RATEB_ClientDashboard_ActivityStreamBuilder::buildForHttp($conn);

echo json_encode(
    [
        'ok' => true,
        'generated_at' => gmdate('c'),
        'stream' => $stream,
        'filters_supported' => ['severity', 'source', 'range'],
    ],
    JSON_UNESCAPED_SLASHES
);
