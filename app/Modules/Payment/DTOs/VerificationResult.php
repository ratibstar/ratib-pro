<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/DTOs/VerificationResult.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/DTOs/VerificationResult.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

final readonly class VerificationResult
{
    public function __construct(
        public bool $verified,
        public ?string $externalId,
        public ?float $amount,
        public ?string $currencyCode,
        public ?string $customerEmail,
        public ?string $customerName,
        public ?string $description,
        public ?string $metadataValue,
        public ?string $status,
        public ?string $errorMessage = null,
    ) {
    }

    public static function verified(
        string $externalId,
        float $amount,
        string $currencyCode,
        ?string $customerEmail,
        ?string $customerName,
        ?string $description,
        ?string $metadataValue,
    ): self {
        return new self(
            verified: true,
            externalId: $externalId,
            amount: $amount,
            currencyCode: $currencyCode,
            customerEmail: $customerEmail,
            customerName: $customerName,
            description: $description,
            metadataValue: $metadataValue,
            status: 'CAPTURED',
        );
    }

    public static function failed(?string $externalId = null, ?string $errorMessage = null): self
    {
        return new self(
            verified: false,
            externalId: $externalId,
            amount: null,
            currencyCode: null,
            customerEmail: null,
            customerName: null,
            description: null,
            metadataValue: null,
            status: null,
            errorMessage: $errorMessage,
        );
    }
}
