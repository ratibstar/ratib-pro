<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/events-stream.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/events-stream.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['control_logged_in'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403 Forbidden';
    exit;
}

if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('view_control_agencies')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403 Forbidden';
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!($ctrl instanceof mysqli)) {
    http_response_code(200);
    header('Content-Type: text/event-stream; charset=UTF-8');
    header('Cache-Control: no-cache, no-store');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    echo 'data: {"error":"db_unavailable"}' . "\n\n";
    flush();
    exit;
}

@set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-store');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$lastId = (int) ($_GET['last_id'] ?? 0);
$iter = 0;
$maxIterations = 900;

while ($iter < $maxIterations && connection_aborted() === 0) {
    try {
        $stmt = $ctrl->prepare(
            "SELECT id, event_type, level, tenant_id, user_id, request_id, source, message, metadata, created_at
             FROM system_events
             WHERE id > ?
             ORDER BY id ASC
             LIMIT 50"
        );
        if ($stmt) {
            $stmt->bind_param('i', $lastId);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = $res ? ($res->fetch_all(MYSQLI_ASSOC) ?: []) : [];
            $stmt->close();
            if (empty($rows)) {
                echo ": ping " . time() . "\n\n";
            } else {
                foreach ($rows as $row) {
                    $lastId = max($lastId, (int) ($row['id'] ?? 0));
                    echo 'data: ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                }
            }
        } else {
            echo 'data: {"error":"stream_prepare_failed"}' . "\n\n";
        }
    } catch (Throwable $e) {
        echo 'data: ' . json_encode([
            'error' => 'stream_failed',
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
    flush();
    sleep(2);
    $iter++;
}

