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
        );
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
        );
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
        if ($total <= 0) {
            return false;
        }

        $status = (string) ($po['status'] ?? '');
        $debitCode = $status === 'received' ? '1300' : '5100';
        $debitAccount = $this->accountIdByCode($companyId, $debitCode);
        $ap = $this->accountIdByCode($companyId, '2100');
        if (!$debitAccount || !$ap) {
            return false;
        }

        $debitMemo = $status === 'received' ? 'Inventory' : 'Procurement expense';

        return $this->createPostedEntry($companyId, 'purchase_order', (int) $po['id'], [
            ['account_id' => $debitAccount, 'debit' => $total, 'credit' => 0, 'memo' => $debitMemo . ' PO ' . ($po['order_no'] ?? '')],
            ['account_id' => $ap, 'debit' => 0, 'credit' => $total, 'memo' => 'AP'],
        ], 'Purchase order ' . ($po['order_no'] ?? ''),
            'أمر شراء ' . ($po['order_no'] ?? ''),
            (string) ($po['order_date'] ?? date('Y-m-d'))
        );
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
    ): bool {
        if (!$this->isBalanced($lines)) {
            return false;
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
            'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, debit, credit, memo) VALUES (:eid, :aid, :dr, :cr, :memo)'
        );
        foreach ($lines as $line) {
            $stmt->execute([
                'eid' => $entryId,
                'aid' => (int) $line['account_id'],
                'dr' => $line['debit'],
                'cr' => $line['credit'],
                'memo' => $line['memo'] ?? null,
            ]);
        }

        return true;
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
            'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, debit, credit, memo) VALUES (:eid, :aid, :dr, :cr, :memo)'
        );
        foreach ($lines as $line) {
            $stmt->execute([
                'eid' => $entryId,
                'aid' => (int) $line['account_id'],
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
