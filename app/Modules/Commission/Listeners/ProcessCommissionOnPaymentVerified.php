<?php
/**
 * EN: Handles application behavior in `app/Modules/Commission/Listeners/ProcessCommissionOnPaymentVerified.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Commission/Listeners/ProcessCommissionOnPaymentVerified.php`.
 */

declare(strict_types=1);

namespace App\Modules\Commission\Listeners;

use App\Modules\Commission\Services\CommissionService;
use App\Modules\Payment\Events\PaymentVerified;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ProcessCommissionOnPaymentVerified implements ShouldQueue
{
    public function __construct(
        private readonly CommissionService $commissionService
    ) {
    }

    public function handle(PaymentVerified $event): void
    {
        $this->commissionService->handlePaymentVerified($event);
    }
}
