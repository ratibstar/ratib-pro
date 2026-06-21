<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Events;

/**
 * Immutable normalized real-time event envelope.
 */
final class RealtimeEvent
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        public readonly string $eventUuid,
        public readonly string $type,
        public readonly int $tenantId,
        public readonly string $timestamp,
        public readonly array $payload,
        public readonly ?int $agentId = null,
        public readonly ?int $callId = null,
        public readonly ?int $queueId = null,
        public readonly ?int $ivrSessionId = null
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromNormalized(array $raw): self
    {
        return new self(
            (string) $raw['event_uuid'],
            (string) $raw['type'],
            (int) $raw['tenant_id'],
            (string) $raw['timestamp'],
            is_array($raw['payload'] ?? null) ? $raw['payload'] : [],
            isset($raw['agent_id']) ? (int) $raw['agent_id'] : null,
            isset($raw['call_id']) ? (int) $raw['call_id'] : null,
            isset($raw['queue_id']) ? (int) $raw['queue_id'] : null,
            isset($raw['ivr_session_id']) ? (int) $raw['ivr_session_id'] : null
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'event_uuid' => $this->eventUuid,
            'type' => $this->type,
            'tenant_id' => $this->tenantId,
            'timestamp' => $this->timestamp,
            'payload' => $this->payload,
        ];
        if ($this->agentId !== null) {
            $data['agent_id'] = $this->agentId;
        }
        if ($this->callId !== null) {
            $data['call_id'] = $this->callId;
        }
        if ($this->queueId !== null) {
            $data['queue_id'] = $this->queueId;
        }
        if ($this->ivrSessionId !== null) {
            $data['ivr_session_id'] = $this->ivrSessionId;
        }
        return $data;
    }

    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return $json;
    }
}
