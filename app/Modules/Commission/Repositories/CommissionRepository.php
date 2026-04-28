<?php
/**
 * EN: Handles application behavior in `app/Modules/Commission/Repositories/CommissionRepository.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Commission/Repositories/CommissionRepository.php`.
 */

declare(strict_types=1);

namespace App\Modules\Commission\Repositories;

use App\Modules\Commission\Models\Commission;

final class CommissionRepository
{
    public function create(array $data): Commission
    {
        return Commission::create($data);
    }

    public function existsForTransaction(int $transactionId): bool
    {
        return Commission::where('transaction_id', $transactionId)->exists();
    }

    public function findById(int $id): ?Commission
    {
        return Commission::find($id);
    }
}
