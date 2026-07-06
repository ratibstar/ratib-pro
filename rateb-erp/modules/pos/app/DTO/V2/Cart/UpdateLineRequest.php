<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Cart;

use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartInvalidQuantityException;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Quantity;
use InvalidArgumentException;

final readonly class UpdateLineRequest
{
    public function __construct(
        public string $qty,
    ) {
        try {
            new PosV2Quantity($this->qty);
        } catch (InvalidArgumentException $exception) {
            throw new PosV2CartInvalidQuantityException($exception->getMessage());
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['qty']) && !isset($payload['quantity'])) {
            throw new PosV2CartInvalidQuantityException('qty is required.');
        }

        $qty = (string) ($payload['qty'] ?? $payload['quantity']);

        return new self($qty);
    }
}
