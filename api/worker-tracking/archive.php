<?php
declare(strict_types=1);

define('SYSTEM_ENDPOINT', true);
define('TENANT_REQUIRED', false);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ensure-worker-tracking-schema.php';
require_once __DIR__ . '/../../admin/core/EventBus.php';

header('Content-Type: application/json; charset=UTF-8');

function archive_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        archive_json(['success' => false, 'message' => 'POST required'], 405);
    }
    $token = trim((string) ($_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? ''));
    $required = trim((string) getenv('TRACKING_ARCHIVE_TOKEN'));
    if ($required !== '' && !hash_equals($required, $token)) {
        archive_json(['success' => false, 'message' => 'Forbidden'], 403);
    }

    $days = isset($_GET['days']) ? (int) $_GET['days'] : (int) (getenv('TRACKING_ARCHIVE_AFTER_DAYS') ?: 7);
    $days = max(1, min(365, $days));
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10000;
    $limit = max(100, min(50000, $limit));

    $pdo = getControlDB();
    ratibEnsureWorkerTrackingSchema($pdo);
    $pdo->beginTransaction();
    try {
        $copy = $pdo->prepare(
            "INSERT INTO worker_locations_archive
             (worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at, created_at)
             SELECT worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at, created_at
             FROM worker_locations
             WHERE recorded_at < DATE_SUB(NOW(), INTERVAL :d DAY)
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        $copy->bindValue(':d', $days, PDO::PARAM_INT);
        $copy->execute();
        $copied = (int) $copy->rowCount();

        $del = $pdo->prepare(
            "DELETE FROM worker_locations
             WHERE recorded_at < DATE_SUB(NOW(), INTERVAL :d DAY)
             LIMIT {$limit}"
        );
        $del->bindValue(':d', $days, PDO::PARAM_INT);
        $del->execute();
        $deleted = (int) $del->rowCount();
        $pdo->commit();

        emitEvent('WORKER_TRACKING_ARCHIVE', 'info', 'Tracking archive rotation executed', [
            'source' => 'worker_tracking',
            'days' => $days,
            'copied' => $copied,
            'deleted' => $deleted,
            'request_id' => getRequestId(),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    archive_json(['success' => true, 'data' => ['days' => $days, 'copied' => $copied ?? 0, 'deleted' => $deleted ?? 0]]);
} catch (Throwable $e) {
    archive_json(['success' => false, 'message' => $e->getMessage()], 500);
}
