<?php
declare(strict_types=1);

namespace App\Accounting\EventStore;

use App\Accounting\Infrastructure\AccountingConnectionFactory;

final class AccountingEventRepository
{
    public function __construct(
        private readonly ?\PDO $pdo = null,
    ) {
    }

    private function connection(): ?\PDO
    {
        return $this->pdo ?? AccountingConnectionFactory::pdo();
    }

    public function tableExists(): bool
    {
        $pdo = $this->connection();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'accounting_events'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function append(
        string $eventUuid,
        string $sourceSystem,
        string $eventType,
        array $payload,
        ?int $companyId,
        ?int $branchId,
        string $status = 'pending'
    ): ?int {
        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return null;
        }

        $sql = 'INSERT INTO accounting_events
            (event_uuid, source_system, event_type, payload, status, company_id, branch_id, created_at)
            VALUES (:uuid, :source, :type, :payload, :status, :company_id, :branch_id, NOW())';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uuid' => $eventUuid,
            'source' => $sourceSystem,
            'type' => $eventType,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'status' => $status,
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function updateStatus(string $eventUuid, string $status): bool
    {
        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return false;
        }

        $processedAt = in_array($status, ['processed', 'failed'], true) ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare(
            'UPDATE accounting_events SET status = :status, processed_at = :processed_at WHERE event_uuid = :uuid'
        );

        return $stmt->execute([
            'status' => $status,
            'processed_at' => $processedAt,
            'uuid' => $eventUuid,
        ]);
    }

    public function findByUuid(string $eventUuid): ?AccountingEvent
    {
        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM accounting_events WHERE event_uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $eventUuid]);
        $row = $stmt->fetch();

        return is_array($row) ? AccountingEvent::fromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<AccountingEvent>
     */
    public function findByFilters(array $filters): array
    {
        $pdo = $this->connection();
        if ($pdo === null || !$this->tableExists()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['source_system'])) {
            $where[] = 'source_system = :source_system';
            $params['source_system'] = (string) $filters['source_system'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = :event_type';
            $params['event_type'] = (string) $filters['event_type'];
        }
        if (isset($filters['company_id']) && $filters['company_id'] !== '') {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'created_at >= :from_date';
            $params['from_date'] = (string) $filters['from_date'] . ' 00:00:00';
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'created_at <= :to_date';
            $params['to_date'] = (string) $filters['to_date'] . ' 23:59:59';
        }

        $limit = isset($filters['limit']) ? max(1, min(5000, (int) $filters['limit'])) : 1000;
        $sql = 'SELECT * FROM accounting_events WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id ASC LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $events[] = AccountingEvent::fromRow($row);
            }
        }

        return $events;
    }
}
