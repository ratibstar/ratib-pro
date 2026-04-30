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

    /** @return list<array<string, mixed>> */
    public function listByWorkflowId(int $workflowId): array
    {
        if ($workflowId <= 0) {
            return [];
        }
        $sql = "SELECT id, event_name, payload, created_at
                FROM events_log
                WHERE JSON_EXTRACT(payload, '$.workflow_id') = :workflow_id
                   OR payload LIKE :like_workflow
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':workflow_id' => $workflowId,
            ':like_workflow' => '%"workflow_id":' . $workflowId . '%',
        ]);
        $rows = $st->fetchAll();
        if (!is_array($rows)) {
            return [];
        }
        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['payload'] ?? '{}'), true);
            $row['payload'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return $rows;
    }
}
