<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Discount;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2DiscountPortInterface;
use Rateb\App\Pos\Services\V2\Discount\PosV2DiscountAccessValidator;

/** Remove manual discount from a cart line (T11). */
final class RemoveLineDiscountUseCase
{
    public function __construct(
        private readonly PosV2DiscountAccessValidator $access,
        private readonly PosV2DiscountPortInterface $discounts,
    ) {
    }

    public function execute(PosV2RequestContext $context, string $lineId): CartResponse
    {
        $this->access->assertCanApply($context);

        return $this->discounts->removeLineDiscount(
            PosV2CartScope::fromRequestContext($context),
            $lineId,
        );
    }
}
