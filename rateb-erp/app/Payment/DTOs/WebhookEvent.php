<?php
declare(strict_types=1);

namespace Rateb\App\Payment\DTOs;

final class WebhookEvent
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $externalId,
        public readonly string $status,
        public readonly ?float $amount,
        public readonly ?string $currency,
        public readonly array $raw = [],
    ) {
    }
}
