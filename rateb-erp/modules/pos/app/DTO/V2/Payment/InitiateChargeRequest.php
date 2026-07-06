<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

final readonly class InitiateChargeRequest
{
    public function __construct(
        public int $sessionId,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $sessionId = isset($payload['session_id']) ? (int) $payload['session_id'] : 0;

        return new self($sessionId);
    }
}
