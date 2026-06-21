<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR;

use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrNodeType;

final class IvrNode
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly int $id,
        public readonly int $flowId,
        public readonly IvrNodeType $type,
        public readonly array $payload,
        public readonly ?int $nextNodeId,
        public readonly ?int $fallbackNodeId,
        public readonly int $maxRetries,
        public readonly int $timeoutSeconds,
        public readonly int $sortOrder = 0
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $payload = $row['payload'] ?? '{}';
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return new self(
            (int) $row['id'],
            (int) $row['flow_id'],
            IvrNodeType::from((string) $row['type']),
            $payload,
            isset($row['next_node_id']) && $row['next_node_id'] !== null ? (int) $row['next_node_id'] : null,
            isset($row['fallback_node_id']) && $row['fallback_node_id'] !== null ? (int) $row['fallback_node_id'] : null,
            (int) ($row['max_retries'] ?? 3),
            (int) ($row['timeout_seconds'] ?? 10),
            (int) ($row['sort_order'] ?? 0)
        );
    }

    public function localizedMessage(string $locale): string
    {
        $key = $locale === 'ar' ? 'message_ar' : 'message_en';
        if (!empty($this->payload[$key])) {
            return (string) $this->payload[$key];
        }
        return (string) ($this->payload['message'] ?? '');
    }
}
