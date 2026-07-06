<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Checkout;

use Rateb\App\Pos\Domain\V2\Payment\PosV2PaymentMethod;
use Rateb\App\Pos\DTO\V2\Payment\PaymentMethodDto;

/** Allowed payment methods for the V2 payment sheet. */
final class CheckoutPaymentMethodCatalog
{
    /** @return list<PaymentMethodDto> */
    public function allowedMethods(): array
    {
        return [
            new PaymentMethodDto('cash', 'Cash', 'cash'),
            new PaymentMethodDto('card', 'Card', 'card'),
            new PaymentMethodDto('bank', 'Bank', 'bank'),
            new PaymentMethodDto('wallet', 'Wallet', 'wallet'),
            new PaymentMethodDto('gift_card', 'Gift card', 'gift-card'),
        ];
    }

    public function labelFor(PosV2PaymentMethod $method): string
    {
        return match ($method) {
            PosV2PaymentMethod::Cash => 'Cash',
            PosV2PaymentMethod::Card => 'Card',
            PosV2PaymentMethod::Bank => 'Bank',
            PosV2PaymentMethod::Wallet => 'Wallet',
            PosV2PaymentMethod::GiftCard => 'Gift card',
        };
    }
}
