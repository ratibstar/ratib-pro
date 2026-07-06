<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Discount\DiscountRequest;

/** Discount persistence port (V1 session fields, T11). */
interface PosV2DiscountPortInterface
{
    public function applyLineDiscount(PosV2CartScope $scope, string $lineId, DiscountRequest $request): CartResponse;

    public function removeLineDiscount(PosV2CartScope $scope, string $lineId): CartResponse;

    public function applyCartDiscount(PosV2CartScope $scope, DiscountRequest $request): CartResponse;

    public function removeCartDiscount(PosV2CartScope $scope): CartResponse;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readLines(): array;

    /** @return array<string, mixed> */
    public function readCartDiscount(): array;
}
