<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosShift;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Support\PosDocumentCodes;
use Rateb\App\Pos\Support\PosFkValidator;
use Rateb\App\Pos\Support\PosBranchScope;

/** X/Z shift reports, drawer reconciliation, summaries, snapshots. */
final class PosReportService
{
    public function __construct(
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /** @return array<string, mixed> */
    public function buildXReport(int $shiftId, int $companyId): array
    {
        return $this->buildShiftReport($shiftId, $companyId, 'x', false);
    }

    /**
     * Build Z report, persist snapshot, update shift markers.
     *
     * @return array<string, mixed>
     */
    public function buildAndPersistZReport(int $shiftId, int $companyId, int $userId): array
    {
        $report = $this->buildShiftReport($shiftId, $companyId, 'z', true);
        $snapshotId = $this->persistSnapshot($report, $companyId, $userId);
        $report['snapshot_id'] = $snapshotId;
        if ($this->tableExists('rateb_pos_shifts')) {
            (new PosShift())->update($shiftId, [
                'last_z_report_id' => $snapshotId,
                'last_x_report_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->audit->log('pos_z_report', 'pos_shift', $shiftId, [
            'report_no' => $report['report_no'] ?? '',
            'snapshot_id' => $snapshotId,
            'company_id' => $companyId,
        ]);
        return $report;
    }

    /** @return array<string, mixed> */
    public function buildShiftReport(int $shiftId, int $companyId, string $type, bool $finalize): array
    {
        if ($shiftId < 1 || $companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        TenantContext::setCompanyId($companyId);
        $shift = PosFkValidator::assertShift($shiftId, $companyId);
        $branchId = (int) ($shift['branch_id'] ?? 0);
        $terminalId = (int) ($shift['terminal_id'] ?? 0);
        $openedAt = (string) ($shift['opened_at'] ?? '');
        $closedAt = (string) ($shift['closed_at'] ?? '');

        $db = Database::connection();
        $drawer = $this->loadDrawer($shiftId, $companyId);
        $drawerEvents = $drawer !== null ? $this->loadDrawerEvents((int) ($drawer['id'] ?? 0), $companyId) : [];

        $sales = $this->aggregateOrders($shiftId, $companyId, 'sale');
        $returns = $this->aggregateOrders($shiftId, $companyId, 'return');
        $exchanges = $this->aggregateOrders($shiftId, $companyId, 'exchange');
        $payments = $this->aggregatePayments($shiftId, $companyId);
        $refunds = $this->aggregateRefunds($shiftId, $companyId);
        $rewardReversals = $this->aggregateRewardReversals($shiftId, $companyId);

        $openingFloat = (float) ($shift['opening_float'] ?? 0);
        $expectedCash = (float) ($drawer['expected_balance'] ?? $shift['expected_cash'] ?? $openingFloat);
        $countedCash = (float) ($drawer['counted_balance'] ?? $shift['closing_float'] ?? 0);
        $cashVariance = round($countedCash - $expectedCash, 2);

        $reportNo = $this->nextReportNo($companyId, $type, (string) ($shift['shift_no'] ?? ''));

        return [
            'report_type' => $type,
            'report_no' => $reportNo,
            'generated_at' => date('Y-m-d H:i:s'),
            'finalize' => $finalize,
            'shift' => [
                'id' => $shiftId,
                'shift_no' => $shift['shift_no'] ?? '',
                'status' => $shift['status'] ?? '',
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'user_id' => (int) ($shift['user_id'] ?? 0),
                'opened_at' => $openedAt,
                'closed_at' => $closedAt !== '' ? $closedAt : null,
                'opening_float' => $openingFloat,
                'closing_float' => (float) ($shift['closing_float'] ?? 0),
                'expected_cash' => $expectedCash,
                'variance' => (float) ($shift['variance'] ?? $cashVariance),
            ],
            'drawer_reconciliation' => [
                'drawer_id' => $drawer !== null ? (int) ($drawer['id'] ?? 0) : null,
                'status' => $drawer['status'] ?? null,
                'opening_float' => $openingFloat,
                'expected_balance' => $expectedCash,
                'counted_balance' => $countedCash > 0 ? $countedCash : null,
                'variance' => $cashVariance,
                'events' => $drawerEvents,
            ],
            'sales_summary' => $sales,
            'return_summary' => $returns,
            'exchange_summary' => $exchanges,
            'payment_summary' => $payments,
            'refund_summary' => $refunds,
            'reward_reversal_summary' => $rewardReversals,
            'totals' => [
                'gross_sales' => (float) ($sales['total'] ?? 0),
                'returns' => (float) ($returns['total'] ?? 0),
                'exchanges' => (float) ($exchanges['total'] ?? 0),
                'net_sales' => round(
                    (float) ($sales['total'] ?? 0)
                    - (float) ($returns['total'] ?? 0)
                    + (float) ($exchanges['total'] ?? 0),
                    2
                ),
                'tax_collected' => round(
                    (float) ($sales['tax'] ?? 0)
                    - (float) ($returns['tax'] ?? 0)
                    + (float) ($exchanges['tax'] ?? 0),
                    2
                ),
                'discounts' => (float) ($sales['discount_total'] ?? 0),
            ],
            'audit' => [
                'shift_id' => $shiftId,
                'company_id' => $companyId,
                'report_kind' => strtoupper($type) . '_REPORT',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function findSnapshot(int $snapshotId, int $companyId): ?array
    {
        if ($snapshotId < 1 || !$this->tableExists('rateb_pos_report_snapshots')) {
            return null;
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_report_snapshots WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $snapshotId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        try {
            PosBranchScope::assertSnapshotReadable($row);
        } catch (\Throwable $e) {
            return null;
        }
        $json = $row['snapshot_json'] ?? null;
        $row['snapshot'] = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function listSnapshots(int $companyId, ?int $shiftId = null, int $limit = 50): array
    {
        if (!$this->tableExists('rateb_pos_report_snapshots')) {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        [$branchSql, $branchParams] = PosBranchScope::readFilterSql('', 'branch_id');
        if ($shiftId !== null && $shiftId > 0) {
            $stmt = $db->prepare(
                'SELECT id, report_type, report_no, shift_id, created_at FROM rateb_pos_report_snapshots
                 WHERE company_id = :cid AND shift_id = :sid' . $branchSql . ' ORDER BY id DESC LIMIT ' . (int) $limit
            );
            $stmt->execute(array_merge(['cid' => $companyId, 'sid' => $shiftId], $branchParams));
        } else {
            $stmt = $db->prepare(
                'SELECT id, report_type, report_no, shift_id, created_at FROM rateb_pos_report_snapshots
                 WHERE company_id = :cid' . $branchSql . ' ORDER BY id DESC LIMIT ' . (int) $limit
            );
            $stmt->execute(array_merge(['cid' => $companyId], $branchParams));
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $report */
    private function persistSnapshot(array $report, int $companyId, int $userId): int
    {
        if (!$this->tableExists('rateb_pos_report_snapshots')) {
            return 0;
        }
        $db = Database::connection();
        $shift = $report['shift'] ?? [];
        $db->prepare(
            'INSERT INTO rateb_pos_report_snapshots
             (company_id, branch_id, terminal_id, shift_id, report_type, report_no, snapshot_json, created_by)
             VALUES (:cid, :bid, :tid, :sid, :rt, :rno, :json, :uid)'
        )->execute([
            'cid' => $companyId,
            'bid' => !empty($shift['branch_id']) ? (int) $shift['branch_id'] : null,
            'tid' => !empty($shift['terminal_id']) ? (int) $shift['terminal_id'] : null,
            'sid' => !empty($shift['id']) ? (int) $shift['id'] : null,
            'rt' => (string) ($report['report_type'] ?? 'z'),
            'rno' => (string) ($report['report_no'] ?? ''),
            'json' => json_encode($report, JSON_UNESCAPED_UNICODE),
            'uid' => $userId > 0 ? $userId : null,
        ]);
        return (int) $db->lastInsertId();
    }

    private function nextReportNo(int $companyId, string $type, string $shiftNo): string
    {
        $prefix = $type === 'z' ? PosDocumentCodes::Z_REPORT : PosDocumentCodes::X_REPORT;
        $base = $prefix . preg_replace('/[^A-Z0-9-]/i', '', $shiftNo);
        $seq = 1;
        if ($this->tableExists('rateb_pos_report_snapshots')) {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM rateb_pos_report_snapshots
                 WHERE company_id = :cid AND report_type = :rt AND shift_id IS NOT NULL'
            );
            $stmt->execute(['cid' => $companyId, 'rt' => $type]);
            $seq = (int) $stmt->fetchColumn() + 1;
        }
        return $base . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed>|null */
    private function loadDrawer(int $shiftId, int $companyId): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_cash_drawers WHERE shift_id = :sid AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadDrawerEvents(int $drawerId, int $companyId): array
    {
        if ($drawerId < 1) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT event_type, amount, notes, user_id, created_at FROM rateb_pos_cash_drawer_events
             WHERE drawer_id = :did AND company_id = :cid ORDER BY id'
        );
        $stmt->execute(['did' => $drawerId, 'cid' => $companyId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed> */
    private function aggregateOrders(int $shiftId, int $companyId, string $orderType): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS order_count,
                    COALESCE(SUM(subtotal), 0) AS subtotal,
                    COALESCE(SUM(discount_total), 0) AS discount_total,
                    COALESCE(SUM(tax), 0) AS tax,
                    COALESCE(SUM(total), 0) AS total
             FROM rateb_pos_orders
             WHERE shift_id = :sid AND company_id = :cid
               AND order_type = :ot AND status = :st'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId, 'ot' => $orderType, 'st' => 'completed']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return [
            'order_type' => $orderType,
            'count' => (int) ($row['order_count'] ?? 0),
            'subtotal' => round((float) ($row['subtotal'] ?? 0), 2),
            'discount_total' => round((float) ($row['discount_total'] ?? 0), 2),
            'tax' => round((float) ($row['tax'] ?? 0), 2),
            'total' => round((float) ($row['total'] ?? 0), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function aggregatePayments(int $shiftId, int $companyId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT p.payment_method, COUNT(*) AS tx_count, COALESCE(SUM(p.amount), 0) AS total
             FROM rateb_pos_payments p
             INNER JOIN rateb_pos_orders o ON o.id = p.order_id
             WHERE o.shift_id = :sid AND o.company_id = :cid
               AND o.status = :st AND o.order_type IN (\'sale\', \'exchange\')
             GROUP BY p.payment_method
             ORDER BY p.payment_method'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId, 'st' => 'completed']);
        $byMethod = [];
        $grand = 0.0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $method = (string) ($row['payment_method'] ?? 'cash');
            $total = round((float) ($row['total'] ?? 0), 2);
            $byMethod[$method] = [
                'count' => (int) ($row['tx_count'] ?? 0),
                'total' => $total,
            ];
            $grand += $total;
        }
        return ['by_method' => $byMethod, 'total' => round($grand, 2)];
    }

    /** @return array<string, mixed> */
    private function aggregateRefunds(int $shiftId, int $companyId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT r.refund_method, COUNT(*) AS tx_count, COALESCE(SUM(r.amount), 0) AS total
             FROM rateb_pos_refunds r
             INNER JOIN rateb_pos_orders o ON o.id = r.order_id
             WHERE o.shift_id = :sid AND o.company_id = :cid
               AND o.status = :st AND r.status = :rs
             GROUP BY r.refund_method
             ORDER BY r.refund_method'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId, 'st' => 'completed', 'rs' => 'completed']);
        $byMethod = [];
        $grand = 0.0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $method = (string) ($row['refund_method'] ?? 'cash');
            $total = round((float) ($row['total'] ?? 0), 2);
            $byMethod[$method] = [
                'count' => (int) ($row['tx_count'] ?? 0),
                'total' => $total,
            ];
            $grand += $total;
        }
        return ['by_method' => $byMethod, 'total' => round($grand, 2)];
    }

    /** @return array<string, mixed> */
    private function aggregateRewardReversals(int $shiftId, int $companyId): array
    {
        if (!$this->tableExists('rateb_pos_reward_reversals')) {
            return ['count' => 0, 'by_kind' => []];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT rr.reversal_kind, COUNT(*) AS tx_count,
                    COALESCE(SUM(rr.points), 0) AS points,
                    COALESCE(SUM(rr.amount), 0) AS amount
             FROM rateb_pos_reward_reversals rr
             INNER JOIN rateb_pos_orders o ON o.id = rr.return_order_id
             WHERE o.shift_id = :sid AND rr.company_id = :cid
               AND rr.reversal_kind <> :bundle
             GROUP BY rr.reversal_kind'
        );
        $stmt->execute(['sid' => $shiftId, 'cid' => $companyId, 'bundle' => 'bundle']);
        $byKind = [];
        $count = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $kind = (string) ($row['reversal_kind'] ?? '');
            $byKind[$kind] = [
                'count' => (int) ($row['tx_count'] ?? 0),
                'points' => round((float) ($row['points'] ?? 0), 2),
                'amount' => round((float) ($row['amount'] ?? 0), 2),
            ];
            $count += (int) ($row['tx_count'] ?? 0);
        }
        return ['count' => $count, 'by_kind' => $byKind];
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
