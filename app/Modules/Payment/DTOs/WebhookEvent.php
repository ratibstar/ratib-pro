<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/DTOs/WebhookEvent.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/DTOs/WebhookEvent.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

final readonly class WebhookEvent
{
    public const TYPE_CHARGE_CAPTURED = 'charge.captured';

    public const TYPE_CHARGE_FAILED = 'charge.failed';

    public const TYPE_CHARGE_CANCELLED = 'charge.cancelled';

    public function __construct(
        public string $eventType,
        public string $externalId,
        public string $status,
        public ?float $amount,
        public ?string $currencyCode,
        public ?string $customerEmail,
        public ?string $customerName,
        public ?string $description,
        public ?string $metadataValue,
    ) {
    }
}
