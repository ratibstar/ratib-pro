<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Discount;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Discount\DiscountRequest;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2DiscountPortInterface;
use Rateb\App\Pos\Services\V2\Discount\DiscountValidator;
use Rateb\App\Pos\Services\V2\Discount\PosV2DiscountAccessValidator;

/** Apply manual discount to a cart line (T11). */
final class ApplyLineDiscountUseCase
{
    public function __construct(
        private readonly PosV2DiscountAccessValidator $access,
        private readonly DiscountValidator $validator,
        private readonly PosV2DiscountPortInterface $discounts,
    ) {
    }

    public function execute(PosV2RequestContext $context, string $lineId, DiscountRequest $request): CartResponse
    {
        $this->access->assertCanApply($context);

        $lines = $this->discounts->readLines();
        $this->validator->assertValid(
            $this->validator->validateLineApply($context, $lines, $lineId, $request),
        );

        return $this->discounts->applyLineDiscount(
            PosV2CartScope::fromRequestContext($context),
            $lineId,
            $request,
        );
    }
}
