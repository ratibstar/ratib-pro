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

    public function ensureDefaultAccounts(?int $companyId): void
    {
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
        $companyId = isset($invoice['company_id']) ? (int) $invoice['company_id'] : null;
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
        $companyId = isset($payment['company_id']) ? (int) $payment['company_id'] : null;
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

        $expense = $this->accountIdByCode($companyId, '5100');
        $ap = $this->accountIdByCode($companyId, '2100');
        if (!$expense || !$ap) {
            return false;
        }

        return $this->createPostedEntry($companyId, 'purchase_order', (int) $po['id'], [
            ['account_id' => $expense, 'debit' => $total, 'credit' => 0, 'memo' => 'PO ' . ($po['order_no'] ?? '')],
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
