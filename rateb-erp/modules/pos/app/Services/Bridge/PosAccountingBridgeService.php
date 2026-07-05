<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Services\AccountingService;

/**
 * POS → GL bridge — all journal posting via ERP AccountingService only.
 * Idempotent source_type + source_id pairs; postings run inside caller DB transaction.
 */
final class PosAccountingBridgeService
{
    private const SALE_REVENUE = 'pos_sale_revenue';
    private const SALE_COGS = 'pos_sale_cogs';
    private const RETURN_REVENUE = 'pos_return_revenue';
    private const RETURN_COGS = 'pos_return_cogs';
    private const EXCHANGE_REVENUE = 'pos_exchange_revenue';
    private const EXCHANGE_COGS = 'pos_exchange_cogs';

    private const POS_SOURCE_TYPES = [
        self::SALE_REVENUE,
        self::SALE_COGS,
        self::RETURN_REVENUE,
        self::RETURN_COGS,
        self::EXCHANGE_REVENUE,
        self::EXCHANGE_COGS,
    ];

    public function __construct(
        private AccountingService $accounting = new AccountingService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosCogsBridgeService $cogs = new PosCogsBridgeService(),
    ) {
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $pricing
     * @param array<int, array<string, mixed>> $payments
     * @param array<int, array<string, mixed>> $orderLines
     */
    public function postSaleInTransaction(
        int $orderId,
        array $order,
        array $pricing,
        array $payments,
        array $orderLines,
        int $companyId,
        int $branchId
    ): void {
        $this->assertInTransaction();
        TenantContext::setCompanyId($companyId);
        $this->accounting->ensureDefaultAccounts($companyId);

        $orderNo = (string) ($order['order_no'] ?? $orderId);
        $entryDate = $this->entryDate($order);

        $this->postRevenueSlice(
            self::SALE_REVENUE,
            $orderId,
            $orderNo,
            $pricing,
            $payments,
            [],
            $companyId,
            $branchId,
            $entryDate,
            'POS sale ' . $orderNo,
            'بيع نقطة بيع ' . $orderNo,
            'sale_revenue'
        );

        $this->postCogsSlice(
            self::SALE_COGS,
            $orderId,
            $orderNo,
            $orderLines,
            $companyId,
            $branchId,
            $entryDate,
            'POS COGS ' . $orderNo,
            'تكلفة بيع نقطة بيع ' . $orderNo,
            'sale_cogs',
            false
        );
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $pricing
     * @param array<int, array<string, mixed>> $refunds
     * @param array<int, array<string, mixed>> $orderLines
     */
    public function postReturnInTransaction(
        int $orderId,
        array $order,
        array $pricing,
        array $refunds,
        array $orderLines,
        int $companyId,
        int $branchId
    ): void {
        $this->assertInTransaction();
        TenantContext::setCompanyId($companyId);
        $this->accounting->ensureDefaultAccounts($companyId);

        $orderNo = (string) ($order['order_no'] ?? $orderId);
        $entryDate = $this->entryDate($order);

        $this->postRevenueSlice(
            self::RETURN_REVENUE,
            $orderId,
            $orderNo,
            $pricing,
            [],
            $refunds,
            $companyId,
            $branchId,
            $entryDate,
            'POS return ' . $orderNo,
            'مرتجع نقطة بيع ' . $orderNo,
            'return_revenue',
            true
        );

        $this->postCogsSlice(
            self::RETURN_COGS,
            $orderId,
            $orderNo,
            $orderLines,
            $companyId,
            $branchId,
            $entryDate,
            'POS return COGS ' . $orderNo,
            'تكلفة مرتجع نقطة بيع ' . $orderNo,
            'return_cogs',
            true
        );
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $salePricing
     * @param array<string, mixed> $returnPricing
     * @param array<int, array<string, mixed>> $payments
     * @param array<int, array<string, mixed>> $refunds
     * @param array<int, array<string, mixed>> $saleLines
     * @param array<int, array<string, mixed>> $returnLines
     */
    public function postExchangeInTransaction(
        int $orderId,
        array $order,
        array $salePricing,
        array $returnPricing,
        array $payments,
        array $refunds,
        array $saleLines,
        array $returnLines,
        int $companyId,
        int $branchId
    ): void {
        $this->assertInTransaction();
        TenantContext::setCompanyId($companyId);
        $this->accounting->ensureDefaultAccounts($companyId);

        $orderNo = (string) ($order['order_no'] ?? $orderId);
        $entryDate = $this->entryDate($order);

        $netTotal = round((float) ($salePricing['total'] ?? 0) - (float) ($returnPricing['total'] ?? 0), 2);

        if (abs($netTotal) > 0.01) {
            $absPricing = [
                'subtotal' => round(abs((float) ($salePricing['subtotal'] ?? 0) - (float) ($returnPricing['subtotal'] ?? 0)), 2),
                'discount_total' => (float) ($salePricing['discount_total'] ?? 0),
                'tax' => round(abs((float) ($salePricing['tax'] ?? 0) - (float) ($returnPricing['tax'] ?? 0)), 2),
                'total' => abs($netTotal),
            ];
            $this->postRevenueSlice(
                self::EXCHANGE_REVENUE,
                $orderId,
                $orderNo,
                $absPricing,
                $netTotal > 0 ? $payments : [],
                $netTotal < 0 ? $refunds : [],
                $companyId,
                $branchId,
                $entryDate,
                'POS exchange ' . $orderNo,
                'استبدال نقطة بيع ' . $orderNo,
                'exchange_revenue',
                $netTotal < 0
            );
        }

        $saleCogs = $this->sumCogs($saleLines, $companyId);
        $returnCogs = $this->sumCogs($returnLines, $companyId);
        $netCogs = round($saleCogs - $returnCogs, 2);
        if ($netCogs > 0.01) {
            $this->postCogsAmount(
                self::EXCHANGE_COGS,
                $orderId,
                $orderNo,
                $netCogs,
                $companyId,
                $branchId,
                $entryDate,
                'POS exchange COGS ' . $orderNo,
                'تكلفة استبدال نقطة بيع ' . $orderNo,
                'exchange_cogs',
                false
            );
        } elseif ($netCogs < -0.01) {
            $this->postCogsAmount(
                self::EXCHANGE_COGS,
                $orderId,
                $orderNo,
                abs($netCogs),
                $companyId,
                $branchId,
                $entryDate,
                'POS exchange COGS reversal ' . $orderNo,
                'عكس تكلفة استبدال نقطة بيع ' . $orderNo,
                'exchange_cogs',
                true
            );
        }
    }

    /** Void all GL postings linked to a POS order (manual reversal). */
    public function voidOrderPostings(int $orderId, int $companyId): int
    {
        if ($orderId < 1 || $companyId < 1 || !$this->tableExists('rateb_pos_gl_postings')) {
            return 0;
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT journal_entry_id, source_type FROM rateb_pos_gl_postings
             WHERE company_id = :cid AND order_id = :oid'
        );
        $stmt->execute(['cid' => $companyId, 'oid' => $orderId]);
        $voided = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $jid = (int) ($row['journal_entry_id'] ?? 0);
            if ($jid < 1) {
                continue;
            }
            if ($this->accounting->voidPostedEntry($jid, $companyId, self::POS_SOURCE_TYPES)) {
                $voided++;
            }
        }
        if ($voided > 0) {
            $this->audit->log('pos_gl_void', 'pos_order', $orderId, [
                'voided_entries' => $voided,
                'company_id' => $companyId,
            ]);
        }
        return $voided;
    }

    /**
     * @param array<string, mixed> $pricing
     * @param array<int, array<string, mixed>> $payments
     * @param array<int, array<string, mixed>> $refunds
     */
    private function postRevenueSlice(
        string $sourceType,
        int $orderId,
        string $orderNo,
        array $pricing,
        array $payments,
        array $refunds,
        int $companyId,
        int $branchId,
        string $entryDate,
        string $description,
        string $descriptionAr,
        string $postingKind,
        bool $isReturn = false
    ): void {
        if ($this->accounting->journalExistsForSource($sourceType, $orderId)) {
            return;
        }

        $total = round(abs((float) ($pricing['total'] ?? 0)), 2);
        $tax = round(abs((float) ($pricing['tax'] ?? 0)), 2);
        $net = round($total - $tax, 2);
        if ($total <= 0.01) {
            return;
        }

        $revenueCode = $isReturn ? '4900' : '4100';
        $revenue = $this->requireAccount($companyId, $revenueCode);
        $vat = $this->accounting->accountIdByCode($companyId, '2200');

        $lines = [];
        if ($isReturn) {
            if ($net > 0) {
                $lines[] = ['account_id' => $revenue, 'debit' => $net, 'credit' => 0, 'memo' => 'Sales return'];
            }
            if ($tax > 0 && $vat) {
                $lines[] = ['account_id' => $vat, 'debit' => $tax, 'credit' => 0, 'memo' => 'VAT reversal'];
            }
            $creditLines = $this->buildPaymentLines($companyId, $refunds, 'credit', $total);
            if ($creditLines === []) {
                $cash = $this->requireAccount($companyId, '1100');
                $creditLines[] = ['account_id' => $cash, 'debit' => 0, 'credit' => $total, 'memo' => 'Refund'];
            }
            $lines = array_merge($lines, $creditLines);
        } else {
            $debitLines = $this->buildPaymentLines($companyId, $payments, 'debit', $total);
            if ($debitLines === []) {
                $cash = $this->requireAccount($companyId, '1100');
                $debitLines[] = ['account_id' => $cash, 'debit' => $total, 'credit' => 0, 'memo' => 'POS payment'];
            }
            $lines = array_merge($lines, $debitLines);
            if ($net > 0) {
                $lines[] = ['account_id' => $revenue, 'debit' => 0, 'credit' => $net, 'memo' => 'Sales revenue'];
            }
            if ($tax > 0 && $vat) {
                $lines[] = ['account_id' => $vat, 'debit' => 0, 'credit' => $tax, 'memo' => 'Output VAT'];
            } elseif ($tax <= 0 && $net > 0) {
                // all revenue if no VAT account
            }
        }

        $this->normalizeRounding($lines, $total, $isReturn);
        $entryId = $this->createEntry(
            $companyId,
            $branchId,
            $sourceType,
            $orderId,
            $lines,
            $description,
            $descriptionAr,
            $entryDate
        );
        $this->persistPosting($companyId, $branchId, $orderId, $postingKind, $entryId, $sourceType, $orderId);
        $this->audit->log('pos_gl_revenue', 'pos_order', $orderId, [
            'source_type' => $sourceType,
            'journal_entry_id' => $entryId,
            'total' => $total,
            'tax' => $tax,
            'is_return' => $isReturn,
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $orderLines
     */
    private function postCogsSlice(
        string $sourceType,
        int $orderId,
        string $orderNo,
        array $orderLines,
        int $companyId,
        int $branchId,
        string $entryDate,
        string $description,
        string $descriptionAr,
        string $postingKind,
        bool $reverse
    ): void {
        if ($this->accounting->journalExistsForSource($sourceType, $orderId)) {
            return;
        }
        $amount = $this->sumCogs($orderLines, $companyId);
        if ($amount <= 0.01) {
            return;
        }
        $this->postCogsAmount(
            $sourceType,
            $orderId,
            $orderNo,
            $amount,
            $companyId,
            $branchId,
            $entryDate,
            $description,
            $descriptionAr,
            $postingKind,
            $reverse
        );
    }

    private function postCogsAmount(
        string $sourceType,
        int $orderId,
        string $orderNo,
        float $amount,
        int $companyId,
        int $branchId,
        string $entryDate,
        string $description,
        string $descriptionAr,
        string $postingKind,
        bool $reverse
    ): void {
        if ($this->accounting->journalExistsForSource($sourceType, $orderId)) {
            return;
        }
        $amount = round($amount, 2);
        if ($amount <= 0.01) {
            return;
        }

        $inventory = $this->requireAccount($companyId, '1300');
        $cogs = $this->requireAccount($companyId, '5200');

        if ($reverse) {
            $lines = [
                ['account_id' => $inventory, 'debit' => $amount, 'credit' => 0, 'memo' => 'Inventory restore'],
                ['account_id' => $cogs, 'debit' => 0, 'credit' => $amount, 'memo' => 'COGS reversal'],
            ];
        } else {
            $lines = [
                ['account_id' => $cogs, 'debit' => $amount, 'credit' => 0, 'memo' => 'COGS'],
                ['account_id' => $inventory, 'debit' => 0, 'credit' => $amount, 'memo' => 'Inventory issue'],
            ];
        }

        $entryId = $this->createEntry(
            $companyId,
            $branchId,
            $sourceType,
            $orderId,
            $lines,
            $description,
            $descriptionAr,
            $entryDate
        );
        $this->persistPosting($companyId, $branchId, $orderId, $postingKind, $entryId, $sourceType, $orderId);
        $this->audit->log('pos_gl_cogs', 'pos_order', $orderId, [
            'source_type' => $sourceType,
            'journal_entry_id' => $entryId,
            'amount' => $amount,
            'reverse' => $reverse,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'cost_basis' => 'batch_fefo',
        ]);
    }

    /**
     * @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines
     */
    private function createEntry(
        int $companyId,
        int $branchId,
        string $sourceType,
        int $sourceId,
        array $lines,
        string $description,
        string $descriptionAr,
        string $entryDate
    ): int {
        $entryId = $this->accounting->createPostedEntry(
            $companyId,
            $sourceType,
            $sourceId,
            $lines,
            $description,
            $descriptionAr,
            $entryDate
        );
        if ($entryId === null || $entryId < 1) {
            throw new \RuntimeException(__('pos_gl_post_failed'));
        }
        $this->assignBranch($entryId, $branchId);
        return $entryId;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{account_id:int,debit:float,credit:float,memo?:string}>
     */
    private function buildPaymentLines(int $companyId, array $rows, string $side, float $expectedTotal): array
    {
        $lines = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $method = strtolower(trim((string) ($row['payment_method'] ?? $row['method'] ?? $row['refund_method'] ?? 'cash')));
            $accountId = $this->paymentAccountId($companyId, $method);
            if ($side === 'debit') {
                $lines[] = ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0, 'memo' => ucfirst($method)];
            } else {
                $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => $amount, 'memo' => ucfirst($method) . ' refund'];
            }
        }
        if ($lines !== [] && $expectedTotal > 0) {
            $sum = 0.0;
            foreach ($lines as $line) {
                $sum += $side === 'debit' ? (float) $line['debit'] : (float) $line['credit'];
            }
            $diff = round($expectedTotal - $sum, 2);
            if (abs($diff) >= 0.01 && $lines !== []) {
                $idx = count($lines) - 1;
                if ($side === 'debit') {
                    $lines[$idx]['debit'] = round((float) $lines[$idx]['debit'] + $diff, 2);
                } else {
                    $lines[$idx]['credit'] = round((float) $lines[$idx]['credit'] + $diff, 2);
                }
            }
        }
        return $lines;
    }

    private function paymentAccountId(int $companyId, string $method): int
    {
        return match ($method) {
            'card', 'bank' => $this->requireAccount($companyId, '1150'),
            'wallet', 'store_credit' => $this->requireAccount($companyId, '2110'),
            'gift_card' => $this->requireAccount($companyId, '2110'),
            default => $this->requireAccount($companyId, '1100'),
        };
    }

    private function requireAccount(int $companyId, string $code): int
    {
        $id = $this->accounting->accountIdByCode($companyId, $code);
        if ($id === null || $id < 1) {
            throw new \RuntimeException(__('pos_gl_account_missing') . ' (' . $code . ')');
        }
        return $id;
    }

    /**
     * @param array<int, array<string, mixed>> $orderLines
     */
    private function sumCogs(array $orderLines, int $companyId): float
    {
        return $this->cogs->sumForOrderLines($orderLines, $companyId);
    }

    /**
     * @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines
     */
    private function normalizeRounding(array &$lines, float $targetTotal, bool $isReturn): void
    {
        $dr = 0.0;
        $cr = 0.0;
        foreach ($lines as $line) {
            $dr += (float) $line['debit'];
            $cr += (float) $line['credit'];
        }
        $diff = round($dr - $cr, 2);
        if (abs($diff) < 0.01 || $lines === []) {
            return;
        }
        $idx = count($lines) - 1;
        if ($isReturn) {
            if ($diff > 0) {
                $lines[$idx]['credit'] = round((float) $lines[$idx]['credit'] + $diff, 2);
            } else {
                $lines[$idx]['debit'] = round((float) $lines[$idx]['debit'] + abs($diff), 2);
            }
        } else {
            if ($diff > 0) {
                $lines[$idx]['credit'] = round((float) $lines[$idx]['credit'] + $diff, 2);
            } else {
                $lines[$idx]['debit'] = round((float) $lines[$idx]['debit'] + abs($diff), 2);
            }
        }
        unset($targetTotal);
    }

    private function persistPosting(
        int $companyId,
        int $branchId,
        int $orderId,
        string $postingKind,
        int $journalEntryId,
        string $sourceType,
        int $sourceId
    ): void {
        if (!$this->tableExists('rateb_pos_gl_postings')) {
            return;
        }
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_pos_gl_postings
             (company_id, branch_id, order_id, posting_kind, journal_entry_id, source_type, source_id)
             VALUES (:cid, :bid, :oid, :kind, :jid, :st, :sid)'
        )->execute([
            'cid' => $companyId,
            'bid' => $branchId > 0 ? $branchId : null,
            'oid' => $orderId,
            'kind' => $postingKind,
            'jid' => $journalEntryId,
            'st' => $sourceType,
            'sid' => $sourceId,
        ]);
    }

    private function assignBranch(int $entryId, int $branchId): void
    {
        if ($branchId < 1 || !$this->tableColumnExists('rateb_journal_entries', 'branch_id')) {
            return;
        }
        (new JournalEntry())->update($entryId, ['branch_id' => $branchId]);
    }

    /** @param array<string, mixed> $order */
    private function entryDate(array $order): string
    {
        $dt = (string) ($order['completed_at'] ?? $order['created_at'] ?? '');
        if ($dt !== '') {
            return date('Y-m-d', strtotime($dt));
        }
        return date('Y-m-d');
    }

    private function assertInTransaction(): void
    {
        if (!Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('pos_gl_requires_transaction'));
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        return Database::liveTableHasColumn($table, $column);
    }
}
