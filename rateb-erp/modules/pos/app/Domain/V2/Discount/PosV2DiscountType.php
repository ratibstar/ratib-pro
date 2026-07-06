<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Discount;

/** Supported manual discount types (T11). */
enum PosV2DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public static function fromString(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'percent', 'percentage' => self::Percent,
            'fixed', 'amount' => self::Fixed,
            default => null,
        };
    }
}
