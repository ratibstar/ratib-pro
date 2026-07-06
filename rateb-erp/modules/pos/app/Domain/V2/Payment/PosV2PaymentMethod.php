<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Payment;

/** Supported payment methods (aligned with V1 PosCheckoutService). */
enum PosV2PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Bank = 'bank';
    case Wallet = 'wallet';
    case GiftCard = 'gift_card';

    public static function fromString(string $raw): ?self
    {
        $normalized = strtolower(trim($raw));

        return match ($normalized) {
            'cash' => self::Cash,
            'card' => self::Card,
            'bank' => self::Bank,
            'wallet' => self::Wallet,
            'gift_card', 'gift-card', 'giftcard' => self::GiftCard,
            default => null,
        };
    }

    /** @return list<self> */
    public static function allowedAtRegister(): array
    {
        return [
            self::Cash,
            self::Card,
            self::Bank,
            self::Wallet,
            self::GiftCard,
        ];
    }
}
