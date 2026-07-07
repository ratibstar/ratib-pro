<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosCashDrawer;
use Rateb\App\Pos\Models\PosCashDrawerEvent;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Support\PosFkValidator;

final class PosCashDrawerService
{
    public function __construct(
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 100, int $offset = 0): array
    {
        if ($companyId < 1) {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        return (new PosCashDrawer())->all($limit, $offset);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        if ($id < 1) {
            return null;
        }
        try {
            return PosFkValidator::assertDrawer($id, $companyId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function eventsForDrawer(int $drawerId, int $companyId): array
    {
        PosFkValidator::assertDrawer($drawerId, $companyId);
        return (new PosCashDrawerEvent())->query(
            'SELECT * FROM rateb_pos_cash_drawer_events WHERE drawer_id = :did AND company_id = :cid ORDER BY id DESC LIMIT 200',
            ['did' => $drawerId, 'cid' => $companyId]
        );
    }

    public function openForShift(
        int $companyId,
        int $branchId,
        int $terminalId,
        int $shiftId,
        int $userId,
        float $openingFloat
    ): int {
        $amount = max(0, round($openingFloat, 2));
        $drawerId = (new PosCashDrawer())->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'terminal_id' => $terminalId,
            'shift_id' => $shiftId,
            'status' => 'open',
            'expected_balance' => $amount,
            'opened_at' => date('Y-m-d H:i:s'),
        ]);
        $this->recordEvent($drawerId, $companyId, $branchId, $shiftId, $userId, 'open', $amount, __('pos_drawer_opened'));
        return $drawerId;
    }

    /** @return array<string, mixed> */
    public function closeForShift(int $shiftId, int $companyId, int $userId, float $countedBalance): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_cash_drawers WHERE shift_id = :sid AND company_id = :cid AND status = :st LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId, 'st' => 'open']);
        $drawer = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$drawer) {
            throw new \RuntimeException(__('pos_drawer_not_found'));
        }

        $drawerId = (int) $drawer['id'];
        $expected = (float) ($drawer['expected_balance'] ?? 0);
        $counted = round($countedBalance, 2);
        $variance = round($counted - $expected, 2);

        (new PosCashDrawer())->update($drawerId, [
            'status' => 'closed',
            'counted_balance' => $counted,
            'variance' => $variance,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->recordEvent(
            $drawerId,
            $companyId,
            (int) ($drawer['branch_id'] ?? 0),
            $shiftId,
            $userId,
            'close',
            $counted,
            __('pos_drawer_closed')
        );

        return array_merge($drawer, [
            'counted_balance' => $counted,
            'variance' => $variance,
            'expected_balance' => $expected,
        ]);
    }

    /** Apply signed cash delta to open drawer — must run inside caller transaction. */
    public function applyCashDeltaInTransaction(int $shiftId, int $companyId, float $delta): void
    {
        if (abs($delta) < 0.0001 || $shiftId < 1 || $companyId < 1) {
            return;
        }
        if (!Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('pos_drawer_requires_transaction'));
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, expected_balance FROM rateb_pos_cash_drawers
             WHERE shift_id = :sid AND company_id = :cid AND status = :st LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId, 'st' => 'open']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $newExpected = round((float) ($row['expected_balance'] ?? 0) + $delta, 2);
        $db->prepare('UPDATE rateb_pos_cash_drawers SET expected_balance = :b WHERE id = :id')
            ->execute(['b' => $newExpected, 'id' => (int) ($row['id'] ?? 0)]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function sumCashAmount(array $rows, string $methodKey = 'payment_method', string $amountKey = 'amount'): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $method = strtolower(trim((string) ($row[$methodKey] ?? $row['method'] ?? '')));
            if ($method === 'cash') {
                $sum += (float) ($row[$amountKey] ?? 0);
            }
        }
        return round($sum, 2);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function sumCashRefunds(array $rows): float
    {
        return self::sumCashAmount($rows, 'refund_method', 'amount');
    }

    /** @return array<string, mixed>|null */
    public function findOpenByShift(int $shiftId, int $companyId): ?array
    {
        if ($shiftId < 1 || $companyId < 1) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_cash_drawers
             WHERE shift_id = :sid AND company_id = :cid AND status = :st LIMIT 1'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId, 'st' => 'open']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function recordManualEvent(
        int $drawerId,
        int $companyId,
        int $userId,
        string $eventType,
        float $amount,
        string $notes = ''
    ): int {
        $drawer = PosFkValidator::assertDrawer($drawerId, $companyId);
        if (($drawer['status'] ?? '') !== 'open') {
            throw new \RuntimeException(__('pos_drawer_not_open'));
        }
        if (!in_array($eventType, ['pay_in', 'pay_out', 'no_sale'], true)) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $signed = in_array($eventType, ['pay_out'], true) ? -abs($amount) : abs($amount);
            $newExpected = round((float) ($drawer['expected_balance'] ?? 0) + $signed, 2);
            if ($newExpected < 0) {
                throw new \RuntimeException(__('pos_drawer_insufficient'));
            }

            (new PosCashDrawer())->update($drawerId, ['expected_balance' => $newExpected]);
            $eventId = $this->recordEvent(
                $drawerId,
                $companyId,
                (int) ($drawer['branch_id'] ?? 0),
                (int) ($drawer['shift_id'] ?? 0) ?: null,
                $userId,
                $eventType,
                abs($amount),
                $notes
            );

            $this->audit->log('pos_drawer_event', 'pos_cash_drawer', $drawerId, [
                'event_type' => $eventType,
                'amount' => $amount,
            ]);
            $db->commit();
            return $eventId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function recordEvent(
        int $drawerId,
        int $companyId,
        int $branchId,
        ?int $shiftId,
        int $userId,
        string $eventType,
        float $amount,
        string $notes
    ): int {
        return (new PosCashDrawerEvent())->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'drawer_id' => $drawerId,
            'shift_id' => ($shiftId !== null && $shiftId > 0) ? $shiftId : null,
            'event_type' => $eventType,
            'amount' => round($amount, 2),
            'notes' => $notes !== '' ? $notes : null,
            'user_id' => $userId > 0 ? $userId : SessionManager::get('rateb_user_id'),
        ]);
    }
}
