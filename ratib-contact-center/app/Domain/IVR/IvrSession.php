<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR;

use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrSessionStatus;

final class IvrSession
{
    /** @param array<string, mixed> $state */
    public function __construct(
        public readonly int $id,
        public readonly int $callId,
        public readonly ?string $callUuid,
        public readonly int $tenantId,
        public readonly int $flowId,
        public ?int $currentNodeId,
        public array $state,
        public IvrSessionStatus $status,
        public readonly ?string $channelId,
        public readonly string $locale,
        public int $retryCount,
        public readonly string $startedAt,
        public ?string $updatedAt = null,
        public ?string $completedAt = null
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $state = $row['state'] ?? '{}';
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : [];
        }

        return new self(
            (int) $row['id'],
            (int) $row['call_id'],
            isset($row['call_uuid']) ? (string) $row['call_uuid'] : null,
            (int) $row['tenant_id'],
            (int) $row['flow_id'],
            isset($row['current_node_id']) && $row['current_node_id'] !== null ? (int) $row['current_node_id'] : null,
            $state,
            IvrSessionStatus::from((string) $row['status']),
            isset($row['channel_id']) ? (string) $row['channel_id'] : null,
            (string) ($row['locale'] ?? 'ar'),
            (int) ($row['retry_count'] ?? 0),
            (string) $row['started_at'],
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            isset($row['completed_at']) ? (string) $row['completed_at'] : null
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            IvrSessionStatus::Completed,
            IvrSessionStatus::Failed,
            IvrSessionStatus::Timeout,
        ], true);
    }

    public function lastInput(): ?string
    {
        $input = $this->state['last_input'] ?? null;
        return $input !== null ? (string) $input : null;
    }
}
