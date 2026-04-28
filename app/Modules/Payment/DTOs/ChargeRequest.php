<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/DTOs/ChargeRequest.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/DTOs/ChargeRequest.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

final readonly class ChargeRequest
{
    public function __construct(
        public float $amount,
        public string $currencyCode,
        public string $customerEmail,
        public string $customerName,
        public ?string $customerPhone,
        public string $description,
        public string $successRedirectUrl,
        public ?string $metadataKey = null,
        public ?string $metadataValue = null,
    ) {
    }

    public function toArray(): array
    {
        $metadata = [];
        if ($this->metadataKey !== null && $this->metadataValue !== null) {
            $metadata[$this->metadataKey] = $this->metadataValue;
        }

        return [
            'amount' => $this->amount,
            'currency' => $this->currencyCode,
            'customer_email' => $this->customerEmail,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'description' => $this->description,
            'success_redirect_url' => $this->successRedirectUrl,
            'metadata' => $metadata,
        ];
    }
}
