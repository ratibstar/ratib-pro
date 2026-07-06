<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartLineDto;

/** Assembles CartResponse from normalized V1 lines. */
final class PosV2CartAssembler
{
    public function __construct(
        private readonly PosV2CartLineMapper $lineMapper = new PosV2CartLineMapper(),
        private readonly PosV2CartTotalsCalculator $totalsCalculator = new PosV2CartTotalsCalculator(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $v1Lines
     */
    public function assemble(PosV2CartScope $scope, array $v1Lines): CartResponse
    {
        $lines = $this->lineMapper->fromV1Lines($v1Lines, $scope->currency);
        $totals = $this->totalsCalculator->calculate($lines, $scope->currency);

        return new CartResponse(
            lines: $lines,
            totals: $totals,
            itemCount: $this->countItems($lines),
        );
    }

    /**
     * @param list<PosV2CartLineDto> $lines
     */
    private function countItems(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            $count += (int) round((float) $line->qty);
        }

        return $count;
    }
}
