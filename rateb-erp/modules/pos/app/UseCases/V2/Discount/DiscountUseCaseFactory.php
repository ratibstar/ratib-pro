<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Discount;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Services\V2\Discount\DiscountValidator;
use Rateb\App\Pos\Services\V2\Discount\PosV2DiscountAccessValidator;

/** Wires discount use cases from the shared composition root (T11). */
final class DiscountUseCaseFactory
{
    public function createApplyLine(): ApplyLineDiscountUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new ApplyLineDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            new DiscountValidator(settings: $root->repositories->posSettingsRepository),
            $root->repositories->discounts,
        );
    }

    public function createRemoveLine(): RemoveLineDiscountUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new RemoveLineDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            $root->repositories->discounts,
        );
    }

    public function createApplyCart(): ApplyCartDiscountUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new ApplyCartDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            new DiscountValidator(settings: $root->repositories->posSettingsRepository),
            $root->repositories->discounts,
        );
    }

    public function createRemoveCart(): RemoveCartDiscountUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new RemoveCartDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            $root->repositories->discounts,
        );
    }
}
