<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\ValueObjects;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Warehouse/branch scope for catalog reads (from existing request context). */
final readonly class PosV2CatalogScope
{
    public function __construct(
        public int $companyId,
        public int $warehouseId,
        public int $branchId,
        public int $sessionId,
        public string $currency,
        public bool $rtl,
    ) {
    }

    public static function fromRequestContext(PosV2RequestContext $context): self
    {
        $register = $context->register;

        return new self(
            companyId: $register->companyId,
            warehouseId: $register->warehouseId,
            branchId: $register->branchId,
            sessionId: $register->sessionId,
            currency: $register->currency,
            rtl: $register->rtl,
        );
    }
}
