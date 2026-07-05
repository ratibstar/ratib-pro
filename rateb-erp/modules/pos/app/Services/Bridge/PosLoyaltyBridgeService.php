<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

/** Loyalty points earn/redeem bridge. */
final class PosLoyaltyBridgeService
{
    private const POINTS_PER_CURRENCY = 1.0;
    private const CURRENCY_PER_POINT = 0.01;

    public function balance(int $companyId, int $customerId): float
    {
        if ($companyId < 1 || $customerId < 1 || !$this->tableExists('rateb_pos_loyalty_accounts')) {
            return 0.0;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT points_balance FROM rateb_pos_loyalty_accounts
             WHERE company_id = :cid AND customer_id = :cust AND status = :st LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'cust' => $customerId, 'st' => 'active']);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float) $val : 0.0;
    }

    public function pointsToMoney(float $points, ?array $policy = null): float
    {
        $rate = $policy !== null
            ? max(0.0001, (float) ($policy['currency_per_point'] ?? self::CURRENCY_PER_POINT))
            : self::CURRENCY_PER_POINT;
        return round(max(0, $points) * $rate, 2);
    }

    public function moneyToPoints(float $amount, ?array $policy = null): float
    {
        $rate = $policy !== null
            ? max(0.0001, (float) ($policy['points_per_currency'] ?? self::POINTS_PER_CURRENCY))
            : self::POINTS_PER_CURRENCY;
        return round(max(0, $amount) * $rate, 2);
    }

    /** Claw back earned points on return (may partial when balance insufficient). */
    public function clawbackEarnInTransaction(
        int $companyId,
        int $customerId,
        float $points,
        int $returnOrderId,
        int $userId,
        string $notes = 'Return earn reversal'
    ): float {
        if ($points <= 0 || $customerId < 1 || !Database::connection()->inTransaction()) {
            return 0.0;
        }
        $accountId = $this->lockAccount($companyId, $customerId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT points_balance, status FROM rateb_pos_loyalty_accounts WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'active') {
            return 0.0;
        }
        $balance = (float) ($row['points_balance'] ?? 0);
        $actual = round(min($points, max(0, $balance)), 2);
        if ($actual <= 0) {
            return 0.0;
        }
        $newBalance = round($balance - $actual, 2);
        $db->prepare('UPDATE rateb_pos_loyalty_accounts SET points_balance = :b WHERE id = :id')
            ->execute(['b' => $newBalance, 'id' => $accountId]);
        $this->ledger($companyId, $customerId, $returnOrderId, 'earn_reverse', $actual, $newBalance, $userId, $notes);
        return $actual;
    }

    /** Restore redeemed points on return per company policy. */
    public function restoreRedeemInTransaction(
        int $companyId,
        int $customerId,
        float $points,
        int $returnOrderId,
        int $userId,
        string $notes = 'Return redeem restore'
    ): float {
        if ($points <= 0 || $customerId < 1 || !Database::connection()->inTransaction()) {
            return 0.0;
        }
        $accountId = $this->lockAccount($companyId, $customerId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT points_balance, status FROM rateb_pos_loyalty_accounts WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'active') {
            return 0.0;
        }
        $newBalance = round((float) ($row['points_balance'] ?? 0) + $points, 2);
        $db->prepare('UPDATE rateb_pos_loyalty_accounts SET points_balance = :b WHERE id = :id')
            ->execute(['b' => $newBalance, 'id' => $accountId]);
        $this->ledger($companyId, $customerId, $returnOrderId, 'redeem_restore', $points, $newBalance, $userId, $notes);
        return $points;
    }

    public function redeemInTransaction(
        int $companyId,
        int $customerId,
        float $points,
        int $orderId,
        int $userId
    ): float {
        if ($points <= 0 || $customerId < 1 || !Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $accountId = $this->lockAccount($companyId, $customerId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT points_balance, status FROM rateb_pos_loyalty_accounts WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'active') {
            throw new \RuntimeException(__('pos_loyalty_unavailable'));
        }
        $balance = (float) ($row['points_balance'] ?? 0);
        if ($points > $balance + 0.0001) {
            throw new \RuntimeException(__('pos_loyalty_insufficient'));
        }
        $newBalance = round($balance - $points, 2);
        $db->prepare('UPDATE rateb_pos_loyalty_accounts SET points_balance = :b WHERE id = :id')
            ->execute(['b' => $newBalance, 'id' => $accountId]);
        $this->ledger($companyId, $customerId, $orderId, 'redeem', $points, $newBalance, $userId, 'POS redeem');
        return $this->pointsToMoney($points);
    }

    public function earnInTransaction(
        int $companyId,
        int $customerId,
        float $orderTotal,
        int $orderId,
        int $userId
    ): float {
        if ($customerId < 1 || $orderTotal <= 0 || !Database::connection()->inTransaction()) {
            return 0.0;
        }
        $points = $this->moneyToPoints($orderTotal);
        if ($points <= 0) {
            return 0.0;
        }
        $accountId = $this->lockAccount($companyId, $customerId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT points_balance, status FROM rateb_pos_loyalty_accounts WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'active') {
            return 0.0;
        }
        $newBalance = round((float) ($row['points_balance'] ?? 0) + $points, 2);
        $db->prepare('UPDATE rateb_pos_loyalty_accounts SET points_balance = :b WHERE id = :id')
            ->execute(['b' => $newBalance, 'id' => $accountId]);
        $this->ledger($companyId, $customerId, $orderId, 'earn', $points, $newBalance, $userId, 'POS earn');
        return $points;
    }

    private function lockAccount(int $companyId, int $customerId): int
    {
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_pos_loyalty_accounts
             WHERE company_id = :cid AND customer_id = :cust LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['cid' => $companyId, 'cust' => $customerId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        $db->prepare(
            'INSERT INTO rateb_pos_loyalty_accounts (company_id, customer_id, points_balance, status)
             VALUES (:cid, :cust, 0, :st)'
        )->execute(['cid' => $companyId, 'cust' => $customerId, 'st' => 'active']);
        return (int) $db->lastInsertId();
    }

    private function ledger(
        int $companyId,
        int $customerId,
        int $orderId,
        string $type,
        float $points,
        float $balanceAfter,
        int $userId,
        string $notes
    ): void {
        if (!$this->tableExists('rateb_pos_loyalty_ledger')) {
            return;
        }
        Database::connection()->prepare(
            'INSERT INTO rateb_pos_loyalty_ledger
             (company_id, customer_id, order_id, entry_type, points, balance_after, notes, created_by)
             VALUES (:cid, :cust, :oid, :t, :p, :bal, :n, :uid)'
        )->execute([
            'cid' => $companyId,
            'cust' => $customerId,
            'oid' => $orderId,
            't' => $type,
            'p' => $points,
            'bal' => $balanceAfter,
            'n' => $notes,
            'uid' => $userId > 0 ? $userId : null,
        ]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
