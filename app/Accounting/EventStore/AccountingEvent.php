<?php
declare(strict_types=1);

namespace App\Accounting\EventStore;

final class AccountingEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $eventUuid,
        public readonly string $sourceSystem,
        public readonly string $eventType,
        public readonly array $payload,
        public readonly string $status = 'pending',
        public readonly ?int $companyId = null,
        public readonly ?int $branchId = null,
        public readonly ?int $id = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $processedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $payload = $row['payload'] ?? [];
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return new self(
            eventUuid: (string) ($row['event_uuid'] ?? ''),
            sourceSystem: (string) ($row['source_system'] ?? ''),
            eventType: (string) ($row['event_type'] ?? ''),
            payload: $payload,
            status: (string) ($row['status'] ?? 'pending'),
            companyId: isset($row['company_id']) ? (int) $row['company_id'] : null,
            branchId: isset($row['branch_id']) ? (int) $row['branch_id'] : null,
            id: isset($row['id']) ? (int) $row['id'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            processedAt: isset($row['processed_at']) ? (string) $row['processed_at'] : null,
        );
    }
}
