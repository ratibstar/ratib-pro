<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosStoreCreditAccount;
use Rateb\App\Pos\Models\PosStoreCreditLedger;

/** Store credit wallet — no GL posting (Phase 6). */
final class PosStoreCreditBridgeService
{
    public function getOrCreateAccount(int $companyId, int $customerId): int
    {
        if ($companyId < 1 || $customerId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_pos_store_credit_accounts
             WHERE company_id = :cid AND customer_id = :cust LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['cid' => $companyId, 'cust' => $customerId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        return (new PosStoreCreditAccount())->create([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'balance' => 0,
            'status' => 'active',
        ]);
    }

    public function creditInTransaction(
        int $companyId,
        int $customerId,
        float $amount,
        int $orderId,
        int $refundId,
        int $userId,
        string $notes = ''
    ): array {
        if ($amount <= 0) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        if (!$db->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $accountId = $this->getOrCreateAccount($companyId, $customerId);
        $stmt = $db->prepare(
            'SELECT balance, status FROM rateb_pos_store_credit_accounts WHERE id = :id AND company_id = :cid FOR UPDATE'
        );
        $stmt->execute(['id' => $accountId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'active') {
            throw new \RuntimeException(__('pos_store_credit_unavailable'));
        }
        $newBalance = round((float) ($row['balance'] ?? 0) + $amount, 2);
        (new PosStoreCreditAccount())->update($accountId, ['balance' => $newBalance]);
        $ledgerId = (new PosStoreCreditLedger())->create([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'order_id' => $orderId,
            'refund_id' => $refundId,
            'entry_type' => 'credit',
            'amount' => round($amount, 2),
            'balance_after' => $newBalance,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $userId > 0 ? $userId : null,
        ]);
        return [
            'account_id' => $accountId,
            'ledger_id' => $ledgerId,
            'balance' => $newBalance,
        ];
    }

    public function balance(int $companyId, int $customerId): float
    {
        if ($companyId < 1 || $customerId < 1) {
            return 0.0;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT balance FROM rateb_pos_store_credit_accounts
             WHERE company_id = :cid AND customer_id = :cust AND status = :st LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'cust' => $customerId, 'st' => 'active']);
        $bal = $stmt->fetchColumn();
        return $bal !== false ? (float) $bal : 0.0;
    }
}
