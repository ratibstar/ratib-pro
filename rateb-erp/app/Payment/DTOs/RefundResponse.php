<?php
declare(strict_types=1);

namespace Rateb\App\Payment\DTOs;

final class RefundResponse
{
    /** @param array<string, mixed> $raw */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $refundId,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function success(?string $refundId, array $raw = []): self
    {
        return new self(true, $refundId, null, null, $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function failure(string $errorCode, string $errorMessage, array $raw = []): self
    {
        return new self(false, null, $errorCode, $errorMessage, $raw);
    }
}
