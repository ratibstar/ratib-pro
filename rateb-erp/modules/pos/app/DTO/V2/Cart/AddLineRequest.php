<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Cart;

use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartInvalidQuantityException;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Quantity;
use InvalidArgumentException;

final readonly class AddLineRequest
{
    public function __construct(
        public int $productId,
        public string $qty,
    ) {
        if ($this->productId < 1) {
            throw new InvalidArgumentException('product_id is required.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $productId = (int) ($payload['product_id'] ?? 0);
        $qty = (string) ($payload['qty'] ?? $payload['quantity'] ?? '1');

        $request = new self($productId, $qty);

        try {
            new PosV2Quantity($request->qty);
        } catch (InvalidArgumentException $exception) {
            throw new PosV2CartInvalidQuantityException($exception->getMessage());
        }

        return $request;
    }
}
