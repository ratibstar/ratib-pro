<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Events/PaymentVerified.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Events/PaymentVerified.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentVerified
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Transaction $transaction
    ) {
    }
}
