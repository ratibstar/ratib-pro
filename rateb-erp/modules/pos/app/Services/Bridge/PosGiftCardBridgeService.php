<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

/** Gift card validation and redemption bridge. */
final class PosGiftCardBridgeService
{
    /** @return array{ok: bool, error?: string, gift_card_id?: int, balance?: float} */
    public function validate(string $code, int $companyId, float $amount = 0): array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || $companyId < 1) {
            return ['ok' => false, 'error' => __('invalid_request')];
        }
        $row = $this->findCard($code, $companyId);
        if ($row === null) {
            return ['ok' => false, 'error' => __('pos_gift_card_invalid')];
        }
        if ((string) ($row['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => __('pos_gift_card_inactive')];
        }
        $expiry = (string) ($row['expires_at'] ?? '');
        if ($expiry !== '' && $expiry < date('Y-m-d')) {
            return ['ok' => false, 'error' => __('pos_gift_card_expired')];
        }
        $balance = (float) ($row['balance'] ?? 0);
        if ($amount > 0 && $balance + 0.0001 < $amount) {
            return ['ok' => false, 'error' => __('pos_gift_card_insufficient')];
        }
        return [
            'ok' => true,
            'gift_card_id' => (int) ($row['id'] ?? 0),
            'balance' => $balance,
        ];
    }

    public function redeemInTransaction(
        string $code,
        float $amount,
        int $orderId,
        int $companyId,
        int $userId
    ): array {
        if ($amount <= 0 || $orderId < 1 || !Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $code = strtoupper(trim($code));
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, balance, status FROM rateb_pos_gift_cards
             WHERE company_id = :cid AND code = :code LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'active') {
            throw new \RuntimeException(__('pos_gift_card_invalid'));
        }
        $balance = (float) ($row['balance'] ?? 0);
        if ($amount > $balance + 0.0001) {
            throw new \RuntimeException(__('pos_gift_card_insufficient'));
        }
        $cardId = (int) ($row['id'] ?? 0);
        $newBalance = round($balance - $amount, 2);
        $status = $newBalance <= 0.0001 ? 'redeemed' : 'active';
        $db->prepare('UPDATE rateb_pos_gift_cards SET balance = :b, status = :st WHERE id = :id')
            ->execute(['b' => max(0, $newBalance), 'st' => $status, 'id' => $cardId]);
        $this->ledger($companyId, $cardId, $orderId, 'redeem', $amount, max(0, $newBalance), $userId);
        return ['gift_card_id' => $cardId, 'balance_after' => max(0, $newBalance)];
    }

    /** Credit gift card balance on return/refund (idempotent per return order handled upstream). */
    public function creditInTransaction(
        string $code,
        float $amount,
        int $returnOrderId,
        int $companyId,
        int $userId
    ): array {
        if ($amount <= 0 || $returnOrderId < 1 || !Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $code = strtoupper(trim($code));
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, balance, status FROM rateb_pos_gift_cards
             WHERE company_id = :cid AND code = :code LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('pos_gift_card_invalid'));
        }
        if (!in_array((string) ($row['status'] ?? ''), ['active', 'redeemed'], true)) {
            throw new \RuntimeException(__('pos_gift_card_inactive'));
        }
        $cardId = (int) ($row['id'] ?? 0);
        $newBalance = round((float) ($row['balance'] ?? 0) + $amount, 2);
        $db->prepare('UPDATE rateb_pos_gift_cards SET balance = :b, status = :st WHERE id = :id')
            ->execute(['b' => $newBalance, 'st' => 'active', 'id' => $cardId]);
        $this->ledger($companyId, $cardId, $returnOrderId, 'refund', $amount, $newBalance, $userId);
        return ['gift_card_id' => $cardId, 'balance_after' => $newBalance, 'amount' => $amount];
    }

    private function ledger(
        int $companyId,
        int $giftCardId,
        int $orderId,
        string $type,
        float $amount,
        float $balanceAfter,
        int $userId
    ): void {
        if (!$this->tableExists('rateb_pos_gift_card_ledger')) {
            return;
        }
        Database::connection()->prepare(
            'INSERT INTO rateb_pos_gift_card_ledger
             (company_id, gift_card_id, order_id, entry_type, amount, balance_after, created_by)
             VALUES (:cid, :gid, :oid, :t, :amt, :bal, :uid)'
        )->execute([
            'cid' => $companyId,
            'gid' => $giftCardId,
            'oid' => $orderId,
            't' => $type,
            'amt' => round($amount, 2),
            'bal' => $balanceAfter,
            'uid' => $userId > 0 ? $userId : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function findCard(string $code, int $companyId): ?array
    {
        if (!$this->tableExists('rateb_pos_gift_cards')) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_gift_cards WHERE company_id = :cid AND code = :code LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
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
