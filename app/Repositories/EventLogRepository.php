<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\BaseModel;

final class EventLogRepository extends BaseModel
{
    /** @param array<string, mixed> $payload */
    public function create(string $eventName, array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO events_log (event_name, payload, created_at) VALUES (:event_name, :payload, NOW())'
        );
        $stmt->execute([
            ':event_name' => $eventName,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function __call(string $name, array $arguments): mixed
    {
        throw new \BadMethodCallException("EventLogRepository is write-only. Unsupported method: {$name}");
    }
}
