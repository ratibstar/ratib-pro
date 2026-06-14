<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\JournalEntry;
use PDO;

final class AccountingService
{
    /** @var array<string, array{code:string,name:string,name_ar:string,type:string}> */
    private const DEFAULT_ACCOUNTS = [
        'cash' => ['code' => '1100', 'name' => 'Cash', 'name_ar' => 'النقدية', 'type' => 'asset'],
        'ar' => ['code' => '1200', 'name' => 'Accounts Receivable', 'name_ar' => 'ذمم مدينة', 'type' => 'asset'],
        'ap' => ['code' => '2100', 'name' => 'Accounts Payable', 'name_ar' => 'ذمم دائنة', 'type' => 'liability'],
        'vat' => ['code' => '2200', 'name' => 'VAT Payable', 'name_ar' => 'ضريبة مستحقة', 'type' => 'liability'],
        'revenue' => ['code' => '4100', 'name' => 'Revenue', 'name_ar' => 'الإيرادات', 'type' => 'revenue'],
        'procurement' => ['code' => '5100', 'name' => 'Procurement Expense', 'name_ar' => 'مصروفات المشتريات', 'type' => 'expense'],
        'inventory' => ['code' => '1300', 'name' => 'Inventory', 'name_ar' => 'المخزون', 'type' => 'asset'],
        'vat_input' => ['code' => '1210', 'name' => 'VAT Recoverable', 'name_ar' => 'ضريبة قابلة للاسترداد', 'type' => 'asset'],
        'cogs' => ['code' => '5200', 'name' => 'Cost of Goods Sold', 'name_ar' => 'تكلفة البضاعة المباعة', 'type' => 'expense'],
    ];

    public function normalizeCompanyId($companyId): ?int
    {
        if ($companyId === null || $companyId === '') {
            return null;
        }
        $id = (int) $companyId;
        return $id > 0 ? $id : null;
    }

    public function ensureDefaultAccounts(?int $companyId): void
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null || $companyId < 1) {
            return;
        }
        $coa = new ChartOfAccount();
        foreach (self::DEFAULT_ACCOUNTS as $def) {
            $exists = $coa->queryOne(
                'SELECT id FROM rateb_chart_of_accounts WHERE company_id <=> :cid AND code = :code LIMIT 1',
                ['cid' => $companyId, 'code' => $def['code']]
            );
            if ($exists) {
                continue;
            }
            $coa->create([
                'company_id' => $companyId,
                'code' => $def['code'],
                'name' => $def['name'],
                'name_ar' => $def['name_ar'],
                'account_type' => $def['type'],
                'is_active' => 1,
            ]);
        }
    }

    public function accountIdByCode(?int $companyId, string $code): ?int
    {
        $row = (new ChartOfAccount())->queryOne(
            'SELECT id FROM rateb_chart_of_accounts WHERE company_id <=> :cid AND code = :code LIMIT 1',
            ['cid' => $companyId, 'code' => $code]
        );
        return $row ? (int) $row['id'] : null;
    }

    public function syncFromSources(?int $companyId): int
    {
        $this->ensureDefaultAccounts($companyId);
        $count = 0;
        $pdo = Database::connection();

        $invoiceSql = 'SELECT * FROM rateb_invoices WHERE status = :st';
        $params = ['st' => 'paid'];
        if ($companyId !== null) {
            $invoiceSql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $pdo->prepare($invoiceSql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $invoice) {
            if ($this->postInvoice((array) $invoice)) {
                $count++;
            }
        }

        $paySql = "SELECT * FROM rateb_payments WHERE status = 'completed'";
        if ($companyId !== null) {
            $paySql .= ' AND company_id = ' . (int) $companyId;
        }
        foreach ($pdo->query($paySql)->fetchAll() as $payment) {
            if ($this->postPayment((array) $payment)) {
                $count++;
            }
        }

        $poSql = "SELECT * FROM rateb_purchase_orders WHERE status IN ('received','confirmed')";
        if ($companyId !== null) {
            $poSql .= ' AND company_id = ' . (int) $companyId;
        }
        foreach ($pdo->query($poSql)->fetchAll() as $po) {
            if ($this->postPurchaseOrder((array) $po)) {
                $count++;
            }
        }

        return $count;
    }

    public function postInvoice(array $invoice): bool
    {
        $companyId = $this->normalizeCompanyId($invoice['company_id'] ?? null);
        if ($this->entryExists('invoice', (int) $invoice['id'])) {
            return false;
        }

        $total = (float) ($invoice['total_amount'] ?? 0);
        $tax = (float) ($invoice['tax_amount'] ?? 0);
        $net = $total - $tax;
        if ($total <= 0) {
            return false;
        }

        $cash = $this->accountIdByCode($companyId, '1100');
        $revenue = $this->accountIdByCode($companyId, '4100');
        $vat = $this->accountIdByCode($companyId, '2200');
        if (!$cash || !$revenue) {
            return false;
        }

        $lines = [
            ['account_id' => $cash, 'debit' => $total, 'credit' => 0, 'memo' => 'Invoice ' . ($invoice['invoice_no'] ?? '')],
            ['account_id' => $revenue, 'debit' => 0, 'credit' => $net, 'memo' => 'Revenue'],
        ];
        if ($tax > 0 && $vat) {
            $lines[] = ['account_id' => $vat, 'debit' => 0, 'credit' => $tax, 'memo' => 'VAT'];
        } else {
            $lines[1]['credit'] = $total;
        }

        return $this->createPostedEntry($companyId, 'invoice', (int) $invoice['id'], $lines,
            'Invoice ' . ($invoice['invoice_no'] ?? ''),
            'فاتورة ' . ($invoice['invoice_no'] ?? ''),
            (string) ($invoice['issued_at'] ?? date('Y-m-d'))
        ) !== null;
    }

    public function postPayment(array $payment): bool
    {
        $companyId = $this->normalizeCompanyId($payment['company_id'] ?? null);
        if ($this->entryExists('payment', (int) $payment['id'])) {
            return false;
        }

        $amount = (float) ($payment['amount'] ?? 0);
        if ($amount <= 0) {
            return false;
        }

        $cash = $this->accountIdByCode($companyId, '1100');
        $revenue = $this->accountIdByCode($companyId, '4100');
        if (!$cash || !$revenue) {
            return false;
        }

        return $this->createPostedEntry($companyId, 'payment', (int) $payment['id'], [
            ['account_id' => $cash, 'debit' => $amount, 'credit' => 0, 'memo' => 'Payment'],
            ['account_id' => $revenue, 'debit' => 0, 'credit' => $amount, 'memo' => 'Payment revenue'],
        ], 'Payment ' . ($payment['reference_no'] ?? $payment['id']),
            'دفعة ' . ($payment['reference_no'] ?? $payment['id']),
            date('Y-m-d', strtotime((string) ($payment['paid_at'] ?? $payment['created_at'] ?? 'now')))
        ) !== null;
    }

    public function autoPostPurchaseOrder(int $purchaseOrderId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_purchase_orders WHERE id = :id LIMIT 1',
            ['id' => $purchaseOrderId]
        );
        if (!$row) {
            return false;
        }
        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, ['received', 'confirmed'], true)) {
            return false;
        }
        return $this->postPurchaseOrder((array) $row);
    }

    public function postPurchaseOrder(array $po): bool
    {
        $companyId = (int) ($po['company_id'] ?? 0);
        if ($this->entryExists('purchase_order', (int) $po['id'])) {
            return false;
        }

        $total = (float) ($po['total_amount'] ?? 0);
        $tax = (float) ($po['tax_amount'] ?? 0);
        $net = max(0, $total - $tax);
        if ($total <= 0) {
            return false;
        }

        $status = (string) ($po['status'] ?? '');
        $debitCode = $status === 'received' ? '1300' : '5100';
        $debitAccount = $this->accountIdByCode($companyId, $debitCode);
        $ap = $this->accountIdByCode($companyId, '2100');
        $vatInput = $this->accountIdByCode($companyId, '1210');
        if (!$debitAccount || !$ap) {
            return false;
        }

        $debitMemo = $status === 'received' ? 'Inventory' : 'Procurement expense';
        $lines = [
            ['account_id' => $debitAccount, 'debit' => $net > 0 ? $net : $total, 'credit' => 0, 'memo' => $debitMemo . ' PO ' . ($po['order_no'] ?? '')],
        ];
        if ($tax > 0 && $vatInput) {
            $lines[] = ['account_id' => $vatInput, 'debit' => $tax, 'credit' => 0, 'memo' => 'Input VAT'];
        }
        $lines[] = ['account_id' => $ap, 'debit' => 0, 'credit' => $total, 'memo' => 'AP'];

        return $this->createPostedEntry($companyId, 'purchase_order', (int) $po['id'], $lines,
            'Purchase order ' . ($po['order_no'] ?? ''),
            'أمر شراء ' . ($po['order_no'] ?? ''),
            (string) ($po['order_date'] ?? date('Y-m-d'))
        ) !== null;
    }

    /** @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines */
    public function createPostedEntry(
        ?int $companyId,
        string $sourceType,
        ?int $sourceId,
        array $lines,
        string $description,
        string $descriptionAr,
        string $entryDate
    ): ?int {
        if (!$this->isBalanced($lines)) {
            return null;
        }
        if (!$this->isPeriodOpen($companyId, $entryDate)) {
            return null;
        }

        $entryModel = new JournalEntry();
        $entryNo = $this->nextEntryNo($companyId);
        $companyId = $this->normalizeCompanyId($companyId);
        $entryId = $entryModel->create([
            'company_id' => $companyId,
            'entry_no' => $entryNo,
            'entry_date' => $entryDate,
            'description' => $description,
            'description_ar' => $descriptionAr,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'posted',
            'posted_at' => date('Y-m-d H:i:s'),
        ]);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, cost_center_id, debit, credit, memo) VALUES (:eid, :aid, :cc, :dr, :cr, :memo)'
        );
        foreach ($lines as $line) {
            $cc = isset($line['cost_center_id']) && (int) $line['cost_center_id'] > 0 ? (int) $line['cost_center_id'] : null;
            $stmt->execute([
                'eid' => $entryId,
                'aid' => (int) $line['account_id'],
                'cc' => $cc,
                'dr' => $line['debit'],
                'cr' => $line['credit'],
                'memo' => $line['memo'] ?? null,
            ]);
        }

        return (int) $entryId;
    }

    public function autoPostStockMovement(int $movementId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT m.*, i.item_name, i.unit_cost, i.company_id
             FROM rateb_stock_movements m
             JOIN rateb_inventory i ON i.id = m.inventory_id
             WHERE m.id = :id LIMIT 1',
            ['id' => $movementId]
        );
        if (!$row) {
            return false;
        }
        return $this->postStockMovement((array) $row);
    }

    public function postStockMovement(array $movement): bool
    {
        $companyId = (int) ($movement['company_id'] ?? 0);
        $type = (string) ($movement['movement_type'] ?? '');
        if (!in_array($type, ['in', 'out'], true)) {
            return false;
        }
        if ($this->entryExists('stock_movement', (int) $movement['id'])) {
            return false;
        }

        $qty = (float) ($movement['quantity'] ?? 0);
        $unitCost = (float) ($movement['unit_cost'] ?? 0);
        $amount = round($qty * $unitCost, 2);
        if ($amount <= 0) {
            return false;
        }

        $inventory = $this->accountIdByCode($companyId, '1300');
        $ap = $this->accountIdByCode($companyId, '2100');
        $cogs = $this->accountIdByCode($companyId, '5200');
        if (!$inventory) {
            return false;
        }

        $itemName = (string) ($movement['item_name'] ?? 'Item');
        if ($type === 'in') {
            if (!$ap) {
                return false;
            }
            $lines = [
                ['account_id' => $inventory, 'debit' => $amount, 'credit' => 0, 'memo' => 'Stock in ' . $itemName],
                ['account_id' => $ap, 'debit' => 0, 'credit' => $amount, 'memo' => 'GRN'],
            ];
            $desc = 'Stock receipt ' . $itemName;
            $descAr = 'استلام مخزون ' . $itemName;
        } else {
            $expense = $cogs ?: $this->accountIdByCode($companyId, '5100');
            if (!$expense) {
                return false;
            }
            $lines = [
                ['account_id' => $expense, 'debit' => $amount, 'credit' => 0, 'memo' => 'Stock out ' . $itemName],
                ['account_id' => $inventory, 'debit' => 0, 'credit' => $amount, 'memo' => 'Issue'],
            ];
            $desc = 'Stock issue ' . $itemName;
            $descAr = 'صرف مخزون ' . $itemName;
        }

        return $this->createPostedEntry(
            $companyId,
            'stock_movement',
            (int) $movement['id'],
            $lines,
            $desc,
            $descAr,
            date('Y-m-d', strtotime((string) ($movement['created_at'] ?? 'now')))
        ) !== null;
    }

    /** @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines */
    public function createManualDraft(
        ?int $companyId,
        string $entryDate,
        string $description,
        string $descriptionAr,
        array $lines,
        ?int $createdBy = null
    ): int {
        if (!$this->isBalanced($lines)) {
            throw new \InvalidArgumentException('Journal entry is not balanced.');
        }
        $this->ensureDefaultAccounts($companyId);
        $companyId = $this->normalizeCompanyId($companyId);
        $entryModel = new JournalEntry();
        $entryId = $entryModel->create([
            'company_id' => $companyId,
            'entry_no' => $this->nextEntryNo($companyId),
            'entry_date' => $entryDate,
            'description' => $description,
            'description_ar' => $descriptionAr,
            'source_type' => 'manual',
            'source_id' => null,
            'status' => 'draft',
            'created_by' => $createdBy,
            'posted_at' => null,
        ]);
        $this->replaceJournalLines($entryId, $lines);
        return $entryId;
    }

    /** @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines */
    public function updateManualDraft(
        int $entryId,
        ?int $companyId,
        string $entryDate,
        string $description,
        string $descriptionAr,
        array $lines
    ): bool {
        if (!$this->isBalanced($lines)) {
            throw new \InvalidArgumentException('Journal entry is not balanced.');
        }
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry || ($entry['source_type'] ?? '') !== 'manual' || ($entry['status'] ?? '') !== 'draft') {
            return false;
        }
        (new JournalEntry())->update($entryId, [
            'entry_date' => $entryDate,
            'description' => $description,
            'description_ar' => $descriptionAr,
        ]);
        $this->replaceJournalLines($entryId, $lines);
        return true;
    }

    public function postDraftEntry(int $entryId, ?int $companyId): bool
    {
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry || ($entry['status'] ?? '') !== 'draft') {
            return false;
        }
        $lines = $this->loadEntryLines($entryId);
        if (!$this->isBalanced($lines)) {
            return false;
        }
        if (!$this->isPeriodOpen($companyId, (string) ($entry['entry_date'] ?? date('Y-m-d')))) {
            return false;
        }
        (new JournalEntry())->update($entryId, [
            'status' => 'posted',
            'posted_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function voidPostedEntry(int $entryId, ?int $companyId): bool
    {
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry || ($entry['status'] ?? '') !== 'posted') {
            return false;
        }
        if (($entry['source_type'] ?? '') !== 'manual') {
            return false;
        }
        (new JournalEntry())->update($entryId, ['status' => 'void']);
        return true;
    }

    /** @return array{rows: array<int, array<string, mixed>>, total_open: float, total_posted: float} */
    public function accountsPayable(?int $companyId): array
    {
        $pdo = Database::connection();
        $sql = "SELECT po.id, po.order_no, po.order_date, po.status, po.total_amount,
                       s.name AS supplier_name, s.code AS supplier_code,
                       je.id AS journal_id, je.entry_no
                FROM rateb_purchase_orders po
                LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
                LEFT JOIN rateb_journal_entries je ON je.source_type = 'purchase_order'
                    AND je.source_id = po.id AND je.status = 'posted'
                WHERE po.status IN ('sent','confirmed','partial','received')";
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND po.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY po.order_date DESC, po.id DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totalOpen = 0.0;
        $totalPosted = 0.0;
        foreach ($rows as $row) {
            $amt = (float) ($row['total_amount'] ?? 0);
            if (!empty($row['journal_id'])) {
                $totalPosted += $amt;
            } else {
                $totalOpen += $amt;
            }
        }
        return ['rows' => $rows, 'total_open' => $totalOpen, 'total_posted' => $totalPosted];
    }

    /** @return array{rows: array<int, array<string, mixed>>, total_open: float, total_paid: float} */
    public function accountsReceivable(?int $companyId): array
    {
        if ($companyId === null) {
            return ['rows' => [], 'total_open' => 0.0, 'total_paid' => 0.0];
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT i.*, je.id AS journal_id, je.entry_no
             FROM rateb_invoices i
             LEFT JOIN rateb_journal_entries je ON je.source_type = 'invoice'
                 AND je.source_id = i.id AND je.status = 'posted'
             WHERE i.company_id = :cid AND i.status != 'cancelled'
             ORDER BY i.issued_at DESC, i.id DESC LIMIT 200"
        );
        $stmt->execute(['cid' => $companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totalOpen = 0.0;
        $totalPaid = 0.0;
        foreach ($rows as $row) {
            $amt = (float) ($row['total_amount'] ?? 0);
            if (($row['status'] ?? '') === 'paid') {
                $totalPaid += $amt;
            } elseif (in_array((string) ($row['status'] ?? ''), ['sent', 'overdue', 'draft'], true)) {
                $totalOpen += $amt;
            }
        }
        return ['rows' => $rows, 'total_open' => $totalOpen, 'total_paid' => $totalPaid];
    }

    /** @return array{revenue: float, expenses: float, net: float, lines: array<int, array<string, mixed>>} */
    public function profitAndLoss(?int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $sql = "SELECT a.id, a.code, a.name, a.name_ar, a.account_type,
                       COALESCE(SUM(l.debit), 0) AS total_debit,
                       COALESCE(SUM(l.credit), 0) AS total_credit
                FROM rateb_chart_of_accounts a
                INNER JOIN rateb_journal_lines l ON l.account_id = a.id
                INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                WHERE a.company_id <=> :cid AND a.account_type IN ('revenue','expense') AND a.is_active = 1";
        $params = ['cid' => $companyId];
        if ($fromDate) {
            $sql .= ' AND e.entry_date >= :from';
            $params['from'] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND e.entry_date <= :to';
            $params['to'] = $toDate;
        }
        $sql .= ' GROUP BY a.id ORDER BY a.code';
        $lines = (new ChartOfAccount())->query($sql, $params);
        $revenue = 0.0;
        $expenses = 0.0;
        foreach ($lines as $line) {
            $dr = (float) ($line['total_debit'] ?? 0);
            $cr = (float) ($line['total_credit'] ?? 0);
            if (($line['account_type'] ?? '') === 'revenue') {
                $revenue += $cr - $dr;
            } else {
                $expenses += $dr - $cr;
            }
        }
        return ['revenue' => $revenue, 'expenses' => $expenses, 'net' => $revenue - $expenses, 'lines' => $lines];
    }

    /** @return array{assets: float, liabilities: float, equity: float, lines: array<int, array<string, mixed>>} */
    public function balanceSheet(?int $companyId, ?string $asOfDate = null): array
    {
        $sql = "SELECT a.id, a.code, a.name, a.name_ar, a.account_type,
                       COALESCE(SUM(l.debit), 0) AS total_debit,
                       COALESCE(SUM(l.credit), 0) AS total_credit
                FROM rateb_chart_of_accounts a
                LEFT JOIN rateb_journal_lines l ON l.account_id = a.id
                LEFT JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'";
        $params = ['cid' => $companyId];
        if ($asOfDate) {
            $sql .= ' AND (e.id IS NULL OR e.entry_date <= :asof)';
            $params['asof'] = $asOfDate;
        }
        $sql .= ' WHERE a.company_id <=> :cid AND a.is_active = 1
                  GROUP BY a.id ORDER BY a.code';
        $lines = (new ChartOfAccount())->query($sql, $params);
        $assets = 0.0;
        $liabilities = 0.0;
        $equity = 0.0;
        foreach ($lines as $line) {
            $dr = (float) ($line['total_debit'] ?? 0);
            $cr = (float) ($line['total_credit'] ?? 0);
            $type = (string) ($line['account_type'] ?? '');
            if ($type === 'asset') {
                $assets += $dr - $cr;
            } elseif ($type === 'liability') {
                $liabilities += $cr - $dr;
            } elseif ($type === 'equity') {
                $equity += $cr - $dr;
            }
        }
        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'lines' => $lines];
    }

    /** @return array<string, mixed>|null */
    private function findEntryForCompany(int $entryId, ?int $companyId): ?array
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id <=> :cid LIMIT 1',
            ['id' => $entryId, 'cid' => $companyId]
        );
        return $row ? (array) $row : null;
    }

    /** @return array<int, array{debit:float,credit:float}> */
    private function loadEntryLines(int $entryId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT debit, credit FROM rateb_journal_lines WHERE journal_entry_id = :id');
        $stmt->execute(['id' => $entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines */
    private function replaceJournalLines(int $entryId, array $lines): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM rateb_journal_lines WHERE journal_entry_id = :id')->execute(['id' => $entryId]);
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, cost_center_id, debit, credit, memo) VALUES (:eid, :aid, :cc, :dr, :cr, :memo)'
        );
        foreach ($lines as $line) {
            $cc = isset($line['cost_center_id']) && (int) $line['cost_center_id'] > 0 ? (int) $line['cost_center_id'] : null;
            $stmt->execute([
                'eid' => $entryId,
                'aid' => (int) $line['account_id'],
                'cc' => $cc,
                'dr' => $line['debit'],
                'cr' => $line['credit'],
                'memo' => $line['memo'] ?? null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function financialSummary(?int $companyId): array
    {
        $pdo = Database::connection();
        $cidSql = $companyId !== null ? ' AND company_id = ' . (int) $companyId : '';
        $cidParam = $companyId !== null ? ['cid' => $companyId] : [];

        $invoicePaid = $pdo->query(
            "SELECT COALESCE(SUM(total_amount), 0) AS t, COUNT(*) AS c FROM rateb_invoices WHERE status = 'paid'" . $cidSql
        )->fetch() ?: ['t' => 0, 'c' => 0];

        $invoiceOpen = $pdo->query(
            "SELECT COALESCE(SUM(total_amount), 0) AS t, COUNT(*) AS c FROM rateb_invoices WHERE status IN ('sent','overdue','draft')" . $cidSql
        )->fetch() ?: ['t' => 0, 'c' => 0];

        $payments = $pdo->query(
            "SELECT COALESCE(SUM(amount), 0) AS t, COUNT(*) AS c FROM rateb_payments WHERE status = 'completed'" . $cidSql
        )->fetch() ?: ['t' => 0, 'c' => 0];

        $journal = (new JournalEntry())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_journal_entries WHERE status = :st' . ($companyId !== null ? ' AND company_id = :cid' : ''),
            array_merge(['st' => 'posted'], $cidParam)
        ) ?: ['c' => 0];

        $accounts = (new ChartOfAccount())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_chart_of_accounts WHERE is_active = 1' . ($companyId !== null ? ' AND company_id = :cid' : ' AND company_id IS NULL'),
            $cidParam
        ) ?: ['c' => 0];

        $poReceived = $pdo->query(
            "SELECT COALESCE(SUM(total_amount), 0) AS t FROM rateb_purchase_orders WHERE status IN ('received','confirmed')" . $cidSql
        )->fetch() ?: ['t' => 0];

        return [
            'invoices_paid_total' => (float) ($invoicePaid['t'] ?? 0),
            'invoices_paid_count' => (int) ($invoicePaid['c'] ?? 0),
            'invoices_open_total' => (float) ($invoiceOpen['t'] ?? 0),
            'invoices_open_count' => (int) ($invoiceOpen['c'] ?? 0),
            'payments_total' => (float) ($payments['t'] ?? 0),
            'payments_count' => (int) ($payments['c'] ?? 0),
            'journal_posted' => (int) ($journal['c'] ?? 0),
            'accounts_active' => (int) ($accounts['c'] ?? 0),
            'procurement_received' => (float) ($poReceived['t'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function trialBalance(?int $companyId): array
    {
        $sql = 'SELECT a.id, a.code, a.name, a.name_ar, a.account_type,
                COALESCE(SUM(l.debit), 0) AS total_debit,
                COALESCE(SUM(l.credit), 0) AS total_credit
            FROM rateb_chart_of_accounts a
            LEFT JOIN rateb_journal_lines l ON l.account_id = a.id
            LEFT JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
            WHERE a.company_id <=> :cid AND a.is_active = 1
            GROUP BY a.id ORDER BY a.code';
        return (new ChartOfAccount())->query($sql, ['cid' => $companyId, 'posted' => 'posted']);
    }

    private function entryExists(string $sourceType, int $sourceId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_journal_entries WHERE source_type = :t AND source_id = :sid AND status != :void LIMIT 1',
            ['t' => $sourceType, 'sid' => $sourceId, 'void' => 'void']
        );
        return $row !== null;
    }

    /** @param array<int, array{debit:float,credit:float}> $lines */
    private function isBalanced(array $lines): bool
    {
        $dr = 0.0;
        $cr = 0.0;
        foreach ($lines as $line) {
            $dr += (float) $line['debit'];
            $cr += (float) $line['credit'];
        }
        return abs($dr - $cr) < 0.01 && $dr > 0;
    }

    /** @return array{output_vat:float,input_vat:float,net_vat:float,invoice_tax:float,po_tax:float} */
    public function vatReport(?int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $this->ensureDefaultAccounts($companyId);
        $output = $this->accountBalanceInPeriod($companyId, '2200', $fromDate, $toDate, 'credit');
        $input = $this->accountBalanceInPeriod($companyId, '1210', $fromDate, $toDate, 'debit');
        $pdo = Database::connection();
        $invSql = "SELECT COALESCE(SUM(tax_amount), 0) AS t FROM rateb_invoices WHERE status IN ('paid','sent','overdue')";
        $poSql = "SELECT COALESCE(SUM(tax_amount), 0) AS t FROM rateb_purchase_orders WHERE status IN ('received','confirmed','partial')";
        $params = [];
        if ($companyId !== null) {
            $invSql .= ' AND company_id = :cid';
            $poSql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        if ($fromDate) {
            $invSql .= ' AND issued_at >= :from';
            $poSql .= ' AND order_date >= :from';
            $params['from'] = $fromDate;
        }
        if ($toDate) {
            $invSql .= ' AND issued_at <= :to';
            $poSql .= ' AND order_date <= :to';
            $params['to'] = $toDate;
        }
        $invStmt = $pdo->prepare($invSql);
        $invStmt->execute($params);
        $poStmt = $pdo->prepare($poSql);
        $poStmt->execute($params);
        $invoiceTax = (float) (($invStmt->fetch(PDO::FETCH_ASSOC) ?: [])['t'] ?? 0);
        $poTax = (float) (($poStmt->fetch(PDO::FETCH_ASSOC) ?: [])['t'] ?? 0);
        return [
            'output_vat' => $output,
            'input_vat' => $input,
            'net_vat' => $output - $input,
            'invoice_tax' => $invoiceTax,
            'po_tax' => $poTax,
        ];
    }

    private function accountBalanceInPeriod(?int $companyId, string $code, ?string $from, ?string $to, string $side): float
    {
        $accountId = $this->accountIdByCode($companyId, $code);
        if (!$accountId) {
            return 0.0;
        }
        $sql = "SELECT COALESCE(SUM(l.debit), 0) AS dr, COALESCE(SUM(l.credit), 0) AS cr
                FROM rateb_journal_lines l
                JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                WHERE l.account_id = :aid AND e.company_id <=> :cid";
        $params = ['aid' => $accountId, 'cid' => $companyId];
        if ($from) {
            $sql .= ' AND e.entry_date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND e.entry_date <= :to';
            $params['to'] = $to;
        }
        $row = (new JournalEntry())->queryOne($sql, $params) ?: ['dr' => 0, 'cr' => 0];
        $dr = (float) ($row['dr'] ?? 0);
        $cr = (float) ($row['cr'] ?? 0);
        return $side === 'credit' ? $cr - $dr : $dr - $cr;
    }

    public function ensureCurrentFiscalPeriod(?int $companyId): void
    {
        if ($companyId === null || $companyId < 1) {
            return;
        }
        $year = (int) date('Y');
        $start = $year . '-01-01';
        $end = $year . '-12-31';
        $exists = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_fiscal_periods WHERE company_id = :cid AND start_date = :s AND end_date = :e LIMIT 1',
            ['cid' => $companyId, 's' => $start, 'e' => $end]
        );
        if ($exists) {
            return;
        }
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rateb_fiscal_periods (company_id, name, start_date, end_date, status) VALUES (:cid, :n, :s, :e, :st)'
        )->execute([
            'cid' => $companyId,
            'n' => (string) $year,
            's' => $start,
            'e' => $end,
            'st' => 'open',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listFiscalPeriods(?int $companyId): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        $this->ensureCurrentFiscalPeriod($companyId);
        return (new JournalEntry())->query(
            'SELECT * FROM rateb_fiscal_periods WHERE company_id = :cid ORDER BY start_date DESC',
            ['cid' => $companyId]
        );
    }

    public function closeFiscalPeriod(int $periodId, ?int $companyId, ?int $userId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_fiscal_periods WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $periodId, 'cid' => $companyId]
        );
        if (!$row || ($row['status'] ?? '') !== 'open') {
            return false;
        }
        (new JournalEntry())->query(
            'UPDATE rateb_fiscal_periods SET status = :st, closed_at = NOW(), closed_by = :uid WHERE id = :id',
            ['st' => 'closed', 'uid' => $userId, 'id' => $periodId]
        );
        return true;
    }

    /** @param array<string, mixed> $data */
    public function createCashVoucherDraft(?int $companyId, array $data, ?int $createdBy): int
    {
        $this->ensureDefaultAccounts($companyId);
        $pdo = Database::connection();
        $no = $this->nextVoucherNo($companyId, (string) ($data['voucher_type'] ?? 'receipt'));
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_cash_vouchers
             (company_id, voucher_no, voucher_type, voucher_date, amount, party_name, description, description_ar, counter_account_id, status, created_by)
             VALUES (:cid, :no, :type, :dt, :amt, :party, :desc, :desc_ar, :acct, :st, :uid)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'no' => $no,
            'type' => $data['voucher_type'],
            'dt' => $data['voucher_date'],
            'amt' => $data['amount'],
            'party' => $data['party_name'] ?? null,
            'desc' => $data['description'],
            'desc_ar' => $data['description_ar'] ?? null,
            'acct' => $data['counter_account_id'],
            'st' => 'draft',
            'uid' => $createdBy,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function postCashVoucher(int $voucherId, ?int $companyId): bool
    {
        $v = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $voucherId, 'cid' => $companyId]
        );
        if (!$v || ($v['status'] ?? '') !== 'draft') {
            return false;
        }
        $amount = (float) ($v['amount'] ?? 0);
        if ($amount <= 0) {
            return false;
        }
        $cash = $this->accountIdByCode($companyId, '1100');
        $counter = (int) ($v['counter_account_id'] ?? 0);
        if (!$cash || $counter < 1) {
            return false;
        }
        $type = (string) ($v['voucher_type'] ?? 'receipt');
        if ($type === 'receipt') {
            $lines = [
                ['account_id' => $cash, 'debit' => $amount, 'credit' => 0, 'memo' => 'Receipt'],
                ['account_id' => $counter, 'debit' => 0, 'credit' => $amount, 'memo' => 'Receipt offset'],
            ];
        } else {
            $lines = [
                ['account_id' => $counter, 'debit' => $amount, 'credit' => 0, 'memo' => 'Payment'],
                ['account_id' => $cash, 'debit' => 0, 'credit' => $amount, 'memo' => 'Cash out'],
            ];
        }
        $entryId = $this->createPostedEntry(
            $companyId,
            'cash_voucher',
            $voucherId,
            $lines,
            (string) $v['description'],
            (string) ($v['description_ar'] ?? $v['description']),
            (string) $v['voucher_date']
        );
        if ($entryId === null) {
            return false;
        }
        Database::connection()->prepare(
            'UPDATE rateb_cash_vouchers SET status = :st, journal_entry_id = :jid, posted_at = NOW() WHERE id = :id'
        )->execute(['st' => 'posted', 'jid' => $entryId, 'id' => $voucherId]);
        return true;
    }

    public function voidCashVoucher(int $voucherId, ?int $companyId): bool
    {
        $v = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $voucherId, 'cid' => $companyId]
        );
        if (!$v || ($v['status'] ?? '') !== 'posted') {
            return false;
        }
        $jid = (int) ($v['journal_entry_id'] ?? 0);
        if ($jid > 0) {
            $this->voidPostedEntry($jid, $companyId);
        }
        Database::connection()->prepare(
            'UPDATE rateb_cash_vouchers SET status = :st WHERE id = :id'
        )->execute(['st' => 'void', 'id' => $voucherId]);
        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function listCashVouchers(?int $companyId, int $limit = 100): array
    {
        if ($companyId === null) {
            return [];
        }
        return (new JournalEntry())->query(
            'SELECT v.*, a.code AS counter_code, a.name AS counter_name, a.name_ar AS counter_name_ar
             FROM rateb_cash_vouchers v
             JOIN rateb_chart_of_accounts a ON a.id = v.counter_account_id
             WHERE v.company_id = :cid ORDER BY v.id DESC LIMIT ' . max(1, min(500, $limit)),
            ['cid' => $companyId]
        );
    }

    public function periodBlocksPosting(?int $companyId, string $entryDate): bool
    {
        return !$this->isPeriodOpen($companyId, $entryDate);
    }

    /** @return array{lines: array<int, array<string, mixed>>} */
    public function costCenterReport(?int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($companyId === null || $companyId < 1) {
            return ['lines' => []];
        }
        $sql = 'SELECT cc.id, cc.code, cc.name, cc.name_ar,
                       COALESCE(SUM(l.debit), 0) AS total_debit,
                       COALESCE(SUM(l.credit), 0) AS total_credit
                FROM rateb_cost_centers cc
                INNER JOIN rateb_journal_lines l ON l.cost_center_id = cc.id
                INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
                WHERE cc.company_id = :cid AND cc.is_active = 1';
        $params = ['cid' => $companyId, 'posted' => 'posted'];
        if ($fromDate) {
            $sql .= ' AND e.entry_date >= :from';
            $params['from'] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND e.entry_date <= :to';
            $params['to'] = $toDate;
        }
        $sql .= ' GROUP BY cc.id ORDER BY cc.code';
        return ['lines' => (new JournalEntry())->query($sql, $params)];
    }

    private function isPeriodOpen(?int $companyId, string $entryDate): bool
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null) {
            return true;
        }
        $row = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_fiscal_periods
             WHERE company_id = :cid AND :dt BETWEEN start_date AND end_date AND status = :st LIMIT 1',
            ['cid' => $companyId, 'dt' => $entryDate, 'st' => 'closed']
        );
        return $row === null;
    }

    private function nextVoucherNo(?int $companyId, string $type): string
    {
        $prefix = $type === 'payment' ? 'PV' : 'RV';
        $row = (new JournalEntry())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_cash_vouchers WHERE company_id = :cid AND voucher_type = :t',
            ['cid' => $companyId, 't' => $type]
        );
        $n = (int) ($row['c'] ?? 0) + 1;
        return $prefix . '-' . ($companyId ?? '0') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function nextEntryNo(?int $companyId): string
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_journal_entries WHERE company_id <=> :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;
        return 'JE-' . ($companyId ?? '0') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
