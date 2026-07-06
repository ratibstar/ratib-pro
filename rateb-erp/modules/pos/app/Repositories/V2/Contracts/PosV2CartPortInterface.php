<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;

/** Cart persistence port (session-scoped, T09). */
interface PosV2CartPortInterface
{
    public function load(PosV2CartScope $scope): CartResponse;

    public function addLine(PosV2CartScope $scope, int $productId, string $qty): CartResponse;

    public function updateLine(PosV2CartScope $scope, string $lineId, string $qty): CartResponse;

    public function removeLine(PosV2CartScope $scope, string $lineId): CartResponse;

    public function clear(PosV2CartScope $scope): CartResponse;
}
