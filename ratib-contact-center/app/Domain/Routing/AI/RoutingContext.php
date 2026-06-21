<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

/**
 * Normalized routing request — built from IVR, queue join, or API context.
 */
final class RoutingContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $callId,
        public readonly ?string $queueCode,
        public readonly string $customerPhone,
        public readonly ?string $ivrInput,
        public readonly ?int $erpCustomerId,
        public readonly string $channelId,
        public readonly ?int $queueId = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['tenant_id'] ?? 0),
            (int) ($data['call_id'] ?? 0),
            isset($data['queue']) ? (string) $data['queue'] : (isset($data['queue_code']) ? (string) $data['queue_code'] : null),
            (string) ($data['customer_phone'] ?? ''),
            isset($data['ivr_input']) ? (string) $data['ivr_input'] : null,
            isset($data['erp_customer_id']) ? (int) $data['erp_customer_id'] : null,
            (string) ($data['channel_id'] ?? ''),
            isset($data['queue_id']) ? (int) $data['queue_id'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'call_id' => $this->callId,
            'queue' => $this->queueCode,
            'queue_code' => $this->queueCode,
            'queue_id' => $this->queueId,
            'customer_phone' => $this->customerPhone,
            'ivr_input' => $this->ivrInput,
            'erp_customer_id' => $this->erpCustomerId,
            'channel_id' => $this->channelId,
        ];
    }
}
