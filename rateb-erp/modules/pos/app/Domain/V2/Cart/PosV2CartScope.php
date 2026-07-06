<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Cart;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Session/register scope for cart persistence (T09). */
final readonly class PosV2CartScope
{
    public function __construct(
        public int $companyId,
        public int $branchId,
        public int $warehouseId,
        public int $sessionId,
        public string $currency,
    ) {
    }

    public static function fromRequestContext(PosV2RequestContext $context): self
    {
        $register = $context->register;

        return new self(
            companyId: $register->companyId,
            branchId: $register->branchId,
            warehouseId: $register->warehouseId,
            sessionId: $register->sessionId,
            currency: $register->currency,
        );
    }
}
