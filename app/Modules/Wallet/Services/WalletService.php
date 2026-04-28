<?php
/**
 * EN: Handles application behavior in `app/Modules/Wallet/Services/WalletService.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Wallet/Services/WalletService.php`.
 */

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class WalletService
{
    public function __construct(
        private readonly WalletRepository $walletRepository,
        private readonly LedgerService $ledgerService
    ) {
    }

    /**
     * Credit commission to agency wallet. All money passes through Ledger first.
     *
     * @param int    $agencyId     Agency ID
     * @param float  $amount       Commission amount
     * @param string $currencyCode ISO 4217 currency code
     * @param string $referenceType Ledger reference type (e.g. 'commission')
     * @param int    $referenceId  Ledger reference ID (transaction_id for idempotency)
     * @param string|null $description Optional description
     */
    public function creditCommission(
        int $agencyId,
        float $amount,
        string $currencyCode,
        string $referenceType,
        int $referenceId,
        ?string $description = null
    ): Wallet {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $debitAccount = $this->ledgerService->findAccountByCode($agencyId, config('ledger.accounts.commission_receivable', '1100'));
        $creditAccount = $this->ledgerService->findAccountByCode($agencyId, config('ledger.accounts.commission_revenue', '4100'));

        if ($debitAccount === null || $creditAccount === null) {
            $debitCode = config('ledger.accounts.commission_receivable', '1100');
            $creditCode = config('ledger.accounts.commission_revenue', '4100');
            throw new InvalidArgumentException("Ledger accounts for commission ({$debitCode}, {$creditCode}) must exist for this agency.");
        }

        return DB::transaction(function () use ($agencyId, $amount, $currencyCode, $referenceType, $referenceId, $description, $debitAccount, $creditAccount): Wallet {
            $this->ledgerService->recordEntryWithReference(
                $agencyId,
                $debitAccount->id,
                $creditAccount->id,
                $amount,
                $currencyCode,
                $referenceType,
                $referenceId,
                $description ?? "Commission from payment #{$referenceId}"
            );

            $wallet = $this->walletRepository->findOrCreateAgencyCommissionWallet($agencyId, $currencyCode);
            $this->walletRepository->creditCommission($wallet, $amount);

            return $wallet->fresh();
        });
    }

    public function getAgencyCommissionWallet(int $agencyId, string $currencyCode): ?Wallet
    {
        $wallet = \App\Modules\Wallet\Models\Wallet::where('agency_id', $agencyId)
            ->where('holder_type', Wallet::HOLDER_AGENCY)
            ->where('holder_id', $agencyId)
            ->where('currency_code', $currencyCode)
            ->first();

        return $wallet;
    }
}
