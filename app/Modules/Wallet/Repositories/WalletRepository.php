<?php
/**
 * EN: Handles application behavior in `app/Modules/Wallet/Repositories/WalletRepository.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Wallet/Repositories/WalletRepository.php`.
 */

declare(strict_types=1);

namespace App\Modules\Wallet\Repositories;

use App\Modules\Wallet\Models\Wallet;

final class WalletRepository
{
    public function findById(int $id): ?Wallet
    {
        return Wallet::find($id);
    }

    public function findOrCreateAgencyCommissionWallet(int $agencyId, string $currencyCode): Wallet
    {
        return Wallet::firstOrCreate(
            [
                'agency_id' => $agencyId,
                'holder_type' => Wallet::HOLDER_AGENCY,
                'holder_id' => $agencyId,
                'currency_code' => $currencyCode,
            ],
            [
                'balance' => 0,
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earned' => 0,
                'total_paid' => 0,
            ]
        );
    }

    public function creditCommission(Wallet $wallet, float $amount): void
    {
        $wallet->increment('available_balance', $amount);
        $wallet->increment('total_earned', $amount);
        $wallet->increment('balance', $amount);
    }
}
