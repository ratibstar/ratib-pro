<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;

final class RealtimeEventRepository
{
    public function persist(RealtimeEvent $event): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_realtime_events (event_uuid, tenant_id, event_type, payload, created_at)
             VALUES (:uuid, :tid, :type, :payload, :ts)'
        );
        $stmt->execute([
            'uuid' => $event->eventUuid,
            'tid' => $event->tenantId,
            'type' => $event->type,
            'payload' => json_encode($event->payload, JSON_UNESCAPED_UNICODE),
            'ts' => $event->timestamp,
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
