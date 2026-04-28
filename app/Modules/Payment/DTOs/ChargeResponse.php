<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/DTOs/ChargeResponse.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/DTOs/ChargeResponse.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

final readonly class ChargeResponse
{
    public function __construct(
        public bool $success,
        public ?string $paymentUrl,
        public ?string $externalId,
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(string $paymentUrl, string $externalId): self
    {
        return new self(true, $paymentUrl, $externalId);
    }

    public static function failed(string $errorMessage): self
    {
        return new self(false, null, null, $errorMessage);
    }
}
