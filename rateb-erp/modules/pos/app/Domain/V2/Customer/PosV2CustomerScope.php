<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Customer;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Company scope for customer reads (from existing request context, T10). */
final readonly class PosV2CustomerScope
{
    public function __construct(
        public int $companyId,
    ) {
    }

    public static function fromRequestContext(PosV2RequestContext $context): self
    {
        return new self(companyId: $context->register->companyId);
    }
}
