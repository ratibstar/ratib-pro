<?php
/**
 * EN: Handles application behavior in `app/Modules/Commission/Services/CommissionService.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Commission/Services/CommissionService.php`.
 */

declare(strict_types=1);

namespace App\Modules\Commission\Services;

use App\Modules\Commission\Models\Commission;
use App\Modules\Commission\Repositories\CommissionRepository;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CommissionService
{
    private const REFERENCE_TYPE = 'commission';

    public function __construct(
        private readonly CommissionRepository $commissionRepository,
        private readonly WalletService $walletService
    ) {
    }

    /**
     * Process commission from a verified payment. Agency earns 10%.
     * Only triggered after successful payment verification. No duplicate calculations.
     */
    public function processFromVerifiedPayment(Transaction $transaction): Commission
    {
        if (! $this->isSuccessfulStatus($transaction->status)) {
            throw new InvalidArgumentException('Commission can only be processed for verified or completed payments.');
        }

        $agencyId = $transaction->customer->agency_id ?? null;
        if ($agencyId === null) {
            throw new InvalidArgumentException('Transaction customer must have an agency.');
        }

        if ($this->commissionRepository->existsForTransaction($transaction->id)) {
            throw new InvalidArgumentException('Commission already exists for this transaction. Duplicate calculations are not allowed.');
        }

        $rate = (float) config('commission.agency_rate', 10.00);
        $amount = round((float) $transaction->amount * ($rate / 100), 2);
        $currencyCode = $transaction->currency_code;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Commission amount would be zero. Minimum payment required.');
        }

        return DB::transaction(function () use ($transaction, $agencyId, $amount, $rate, $currencyCode): Commission {
            $this->walletService->creditCommission(
                $agencyId,
                $amount,
                $currencyCode,
                self::REFERENCE_TYPE,
                $transaction->id,
                sprintf('Commission (%s%%) from payment #%d', $rate, $transaction->id)
            );

            $commission = $this->commissionRepository->create([
                'agency_id' => $agencyId,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'rate' => $rate,
                'currency_code' => $currencyCode,
                'status' => Commission::STATUS_PENDING,
            ]);

            return $commission;
        });
    }

    /**
     * Handle PaymentVerified event.
     */
    public function handlePaymentVerified(\App\Modules\Payment\Events\PaymentVerified $event): void
    {
        $this->processFromVerifiedPayment($event->transaction);
    }

    private function isSuccessfulStatus(string $status): bool
    {
        return in_array($status, [Transaction::STATUS_VERIFIED, Transaction::STATUS_COMPLETED], true);
    }
}
