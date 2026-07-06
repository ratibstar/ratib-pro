<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Payment;

/** Supported payment methods in V2 (T12 cash only). */
enum PosV2PaymentMethod: string
{
    case Cash = 'cash';
}
