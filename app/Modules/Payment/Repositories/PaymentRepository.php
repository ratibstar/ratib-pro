<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Repositories/PaymentRepository.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Repositories/PaymentRepository.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Repositories;

use App\Modules\Payment\Models\Transaction;

final class PaymentRepository
{
    public function findTransactionByExternalReference(string $externalReference): ?Transaction
    {
        return Transaction::where('external_reference', $externalReference)->first();
    }

    public function createTransaction(array $data): Transaction
    {
        return Transaction::create($data);
    }

    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        $transaction->update($data);

        return $transaction->fresh();
    }
}
