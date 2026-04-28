<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/workers/event-worker.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/workers/event-worker.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/EventBus.php';
require_once __DIR__ . '/../core/EventQueue.php';
require_once __DIR__ . '/../core/EventRepository.php';
require_once __DIR__ . '/../core/EventMetricsAggregator.php';
require_once __DIR__ . '/../core/EventAnomalyDetector.php';

@set_time_limit(0);

$sleepMicros = 500000;
$batchSize = (int) (getenv('EVENT_WORKER_BATCH_SIZE') ?: 100);
$batchSize = max(50, min(200, $batchSize));

while (true) {
    try {
        $pdo = getControlDB();
        EventQueue::importFallback($pdo, 500);
        $rows = EventQueue::popBatch($pdo, $batchSize);
        if ($rows === []) {
            usleep($sleepMicros);
            continue;
        }

        $events = [];
        foreach ($rows as $r) {
            $events[] = [
                'event_type' => (string) ($r['event_type'] ?? 'UNKNOWN_EVENT'),
                'level' => (string) ($r['level'] ?? 'info'),
                'tenant_id' => isset($r['tenant_id']) ? (int) $r['tenant_id'] : null,
                'user_id' => isset($r['user_id']) ? (int) $r['user_id'] : null,
                'request_id' => (string) ($r['request_id'] ?? ''),
                'source' => (string) ($r['source'] ?? 'control_center'),
                'message' => (string) ($r['message'] ?? ''),
                'metadata' => isset($r['metadata']) ? (string) $r['metadata'] : null,
            ];
        }

        $pdo->beginTransaction();
        try {
            EventRepository::insertBatch($pdo, $events);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            EventQueue::requeueBatch($pdo, $rows, 5);
            usleep($sleepMicros);
            continue;
        }

        foreach ($events as $ev) {
            $tenantId = isset($ev['tenant_id']) ? (int) $ev['tenant_id'] : null;
            EventMetricsAggregator::record((string) $ev['event_type'], (string) $ev['level'], $tenantId);
            $meta = [];
            if (!empty($ev['metadata'])) {
                $decoded = json_decode((string) $ev['metadata'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            EventAnomalyDetector::evaluateAfterInsert(
                $pdo,
                (string) $ev['event_type'],
                strtolower((string) $ev['level']),
                (string) $ev['message'],
                $meta
            );
        }
    } catch (Throwable $e) {
        error_log('event-worker loop error: ' . $e->getMessage());
        usleep($sleepMicros);
    }
}

