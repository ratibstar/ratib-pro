<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\JournalEntry;
use PDO;

final class AccountingService
{
    use AccountingBranchScope;

    private string $lastVoucherPostDetail = '';

    /** @var array<string, mixed> Phase AI — request-scoped service memo */
    private static array $requestMemo = [];

    /** @var array<string, array<string, array<string, mixed>>> companyKey => code => row */
    private static array $coaMapByCompany = [];

    public function lastVoucherPostDetail(): string
    {
        return $this->lastVoucherPostDetail;
    }

    /** @param callable():mixed $fn */
    private static function requestMemo(string $key, callable $fn): mixed
    {
        if (array_key_exists($key, self::$requestMemo)) {
            return self::$requestMemo[$key];
        }

        return self::$requestMemo[$key] = $fn();
    }

    private function coaMapKey(?int $companyId): string
    {
        $companyId = $this->normalizeCompanyId($companyId);

        return $companyId === null ? 'null' : (string) $companyId;
    }

    /**
     * Load all COA rows for a company (or platform template) once per request.
     *
     * @return array<string, array<string, mixed>>
     */
    private function coaCodeMap(?int $companyId): array
    {
        $key = $this->coaMapKey($companyId);
        if (isset(self::$coaMapByCompany[$key])) {
            return self::$coaMapByCompany[$key];
        }
        $companyId = $this->normalizeCompanyId($companyId);
        $pdo = Database::connection();
        if ($companyId === null) {
            $stmt = $pdo->query('SELECT * FROM rateb_chart_of_accounts WHERE company_id IS NULL');
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } else {
            $stmt = $pdo->prepare('SELECT * FROM rateb_chart_of_accounts WHERE company_id = :cid');
            $stmt->execute(['cid' => $companyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $map = [];
        foreach ($rows as $row) {
            $map[(string) ($row['code'] ?? '')] = $row;
        }
        self::$coaMapByCompany[$key] = $map;

        return $map;
    }

    private function invalidateCoaCodeMap(?int $companyId): void
    {
        unset(self::$coaMapByCompany[$this->coaMapKey($companyId)]);
    }

    /** True when every DEFAULT_ACCOUNTS code already exists for the company/platform. */
    private function defaultCoaIsComplete(?int $companyId): bool
    {
        $map = $this->coaCodeMap($companyId);
        foreach (self::DEFAULT_ACCOUNTS as $def) {
            $code = (string) ($def['code'] ?? '');
            if ($code === '' || !isset($map[$code])) {
                return false;
            }
        }

        return true;
    }

    private static function accountingRepairMode(): bool
    {
        $v = getenv('RATEB_ACCOUNTING_REPAIR_COA');
        if ($v === false || $v === '') {
            $v = (string) ($_ENV['RATEB_ACCOUNTING_REPAIR_COA'] ?? '');
        }

        return $v === '1' || strtolower((string) $v) === 'true';
    }

    /** @var array<string, array{code:string,name:string,name_ar:string,type:string,parent?:string}> */
    /** Standard COA tree — keeps existing posting codes (1100, 1200, 1210, …). */
    private const DEFAULT_ACCOUNTS = [
        // ── 1xxx Assets ──
        'assets' => ['code' => '1000', 'name' => 'Assets', 'name_ar' => 'الأصول', 'type' => 'asset'],
        'cash' => ['code' => '1100', 'name' => 'Cash on Hand', 'name_ar' => 'النقدية / الصندوق', 'type' => 'asset', 'parent' => '1000'],
        'banks_grp' => ['code' => '1150', 'name' => 'Bank Accounts', 'name_ar' => 'الحسابات البنكية', 'type' => 'asset', 'parent' => '1000'],
        'ar' => ['code' => '1200', 'name' => 'Accounts Receivable', 'name_ar' => 'ذمم مدينة', 'type' => 'asset', 'parent' => '1000'],
        'vat_input' => ['code' => '1210', 'name' => 'VAT Recoverable', 'name_ar' => 'ضريبة قابلة للاسترداد', 'type' => 'asset', 'parent' => '1000'],
        'adv_suppliers' => ['code' => '1220', 'name' => 'Advances to Suppliers', 'name_ar' => 'سلف موردين', 'type' => 'asset', 'parent' => '1000'],
        'inventory' => ['code' => '1300', 'name' => 'Inventory', 'name_ar' => 'المخزون', 'type' => 'asset', 'parent' => '1000'],
        'prepaid' => ['code' => '1400', 'name' => 'Prepaid Expenses', 'name_ar' => 'مصروفات مقدمة', 'type' => 'asset', 'parent' => '1000'],
        'fixed_assets' => ['code' => '1500', 'name' => 'Fixed Assets', 'name_ar' => 'الأصول الثابتة', 'type' => 'asset', 'parent' => '1000'],
        'equipment' => ['code' => '1510', 'name' => 'Equipment', 'name_ar' => 'معدات', 'type' => 'asset', 'parent' => '1500'],
        'vehicles' => ['code' => '1520', 'name' => 'Vehicles', 'name_ar' => 'مركبات', 'type' => 'asset', 'parent' => '1500'],
        'buildings' => ['code' => '1530', 'name' => 'Buildings', 'name_ar' => 'مباني', 'type' => 'asset', 'parent' => '1500'],
        'accum_depr' => ['code' => '1590', 'name' => 'Accumulated Depreciation', 'name_ar' => 'مجمع الإهلاك', 'type' => 'asset', 'parent' => '1500'],

        // ── 2xxx Liabilities ──
        'liabilities' => ['code' => '2000', 'name' => 'Liabilities', 'name_ar' => 'الخصوم', 'type' => 'liability'],
        'ap' => ['code' => '2100', 'name' => 'Accounts Payable', 'name_ar' => 'ذمم دائنة', 'type' => 'liability', 'parent' => '2000'],
        'cust_advances' => ['code' => '2110', 'name' => 'Customer Advances', 'name_ar' => 'دفعات مقدمة من العملاء', 'type' => 'liability', 'parent' => '2000'],
        'vat' => ['code' => '2200', 'name' => 'VAT Payable', 'name_ar' => 'ضريبة مستحقة', 'type' => 'liability', 'parent' => '2000'],
        'accrued' => ['code' => '2300', 'name' => 'Accrued Expenses', 'name_ar' => 'مصروفات مستحقة', 'type' => 'liability', 'parent' => '2000'],
        'salaries_payable' => ['code' => '2400', 'name' => 'Salaries Payable', 'name_ar' => 'رواتب مستحقة', 'type' => 'liability', 'parent' => '2000'],
        'st_loans' => ['code' => '2500', 'name' => 'Short-term Loans', 'name_ar' => 'قروض قصيرة الأجل', 'type' => 'liability', 'parent' => '2000'],

        // ── 3xxx Equity ──
        'equity' => ['code' => '3000', 'name' => 'Equity', 'name_ar' => 'حقوق الملكية', 'type' => 'equity'],
        'retained' => ['code' => '3100', 'name' => 'Retained Earnings', 'name_ar' => 'أرباح محتجزة', 'type' => 'equity', 'parent' => '3000'],
        'capital' => ['code' => '3200', 'name' => 'Share Capital', 'name_ar' => 'رأس المال', 'type' => 'equity', 'parent' => '3000'],
        'current_pl' => ['code' => '3300', 'name' => 'Current Year Profit/Loss', 'name_ar' => 'أرباح/خسائر العام الحالي', 'type' => 'equity', 'parent' => '3000'],

        // ── 4xxx Revenue ──
        'revenue_grp' => ['code' => '4000', 'name' => 'Revenue', 'name_ar' => 'الإيرادات', 'type' => 'revenue'],
        'revenue' => ['code' => '4100', 'name' => 'Sales Revenue', 'name_ar' => 'إيرادات المبيعات', 'type' => 'revenue', 'parent' => '4000'],
        'service_rev' => ['code' => '4200', 'name' => 'Service Revenue', 'name_ar' => 'إيرادات الخدمات', 'type' => 'revenue', 'parent' => '4000'],
        'other_income' => ['code' => '4300', 'name' => 'Other Income', 'name_ar' => 'إيرادات أخرى', 'type' => 'revenue', 'parent' => '4000'],
        'sales_returns' => ['code' => '4900', 'name' => 'Sales Returns & Allowances', 'name_ar' => 'مردودات ومسموحات المبيعات', 'type' => 'revenue', 'parent' => '4000'],

        // ── 5xxx Expenses ──
        'expenses' => ['code' => '5000', 'name' => 'Expenses', 'name_ar' => 'المصروفات', 'type' => 'expense'],
        'procurement' => ['code' => '5100', 'name' => 'Procurement Expense', 'name_ar' => 'مصروفات المشتريات', 'type' => 'expense', 'parent' => '5000'],
        'cogs' => ['code' => '5200', 'name' => 'Cost of Goods Sold', 'name_ar' => 'تكلفة البضاعة المباعة', 'type' => 'expense', 'parent' => '5000'],
        'payroll' => ['code' => '5300', 'name' => 'Salaries & Wages', 'name_ar' => 'الرواتب والأجور', 'type' => 'expense', 'parent' => '5000'],
        'rent' => ['code' => '5400', 'name' => 'Rent Expense', 'name_ar' => 'مصروف الإيجار', 'type' => 'expense', 'parent' => '5000'],
        'utilities' => ['code' => '5500', 'name' => 'Utilities', 'name_ar' => 'مرافق (كهرباء، ماء، …)', 'type' => 'expense', 'parent' => '5000'],
        'depr_exp' => ['code' => '5600', 'name' => 'Depreciation Expense', 'name_ar' => 'مصروف الإهلاك', 'type' => 'expense', 'parent' => '5000'],
        'bank_charges' => ['code' => '5700', 'name' => 'Bank & Finance Charges', 'name_ar' => 'عمولات بنكية ومالية', 'type' => 'expense', 'parent' => '5000'],
        'ga_exp' => ['code' => '5800', 'name' => 'General & Administrative', 'name_ar' => 'مصروفات عمومية وإدارية', 'type' => 'expense', 'parent' => '5000'],
        'marketing' => ['code' => '5900', 'name' => 'Marketing & Sales', 'name_ar' => 'مصروفات تسويق ومبيعات', 'type' => 'expense', 'parent' => '5000'],
    ];

    public function normalizeCompanyId($companyId): ?int
    {
        if ($companyId === null || $companyId === '') {
            return null;
        }
        $id = (int) $companyId;
        return $id > 0 ? $id : null;
    }

    private function resolveBranchId(?int $companyId, ?int $branchId): int
    {
        if ($branchId !== null && $branchId > 0) {
            return $branchId;
        }
        $companyId = (int) ($this->normalizeCompanyId($companyId) ?? 0);
        if ($companyId < 1) {
            return 0;
        }
        return (new BranchService())->defaultBranchId($companyId);
    }

    /** @return array<string, mixed>|null */
    private function findCoaByCode(?int $companyId, string $code): ?array
    {
        $map = $this->coaCodeMap($companyId);

        return $map[$code] ?? null;
    }

    /** @param array<string, mixed> $def */
    private function insertCoaRow(?int $companyId, array $def, ?int $parentId = null): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
             VALUES (:cid, :code, :name, :name_ar, :type, :parent, 1)'
        );
        $cid = $this->normalizeCompanyId($companyId);
        $stmt->execute([
            'cid' => $cid,
            'code' => (string) ($def['code'] ?? ''),
            'name' => (string) ($def['name'] ?? ''),
            'name_ar' => $def['name_ar'] ?? null,
            'type' => (string) ($def['account_type'] ?? $def['type'] ?? 'asset'),
            'parent' => $parentId,
        ]);
        $this->invalidateCoaCodeMap($cid);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $def */
    private function touchCoaRow(int $id, array $def, ?array $existing = null): void
    {
        $name = (string) ($def['name'] ?? '');
        $nameAr = $def['name_ar'] ?? null;
        if (is_array($existing)) {
            $curActive = (int) ($existing['is_active'] ?? 0);
            $curName = (string) ($existing['name'] ?? '');
            $curAr = $existing['name_ar'] ?? null;
            if ($curActive === 1 && $curName === $name && (string) $curAr === (string) $nameAr) {
                return;
            }
        }
        Database::connection()->prepare(
            'UPDATE rateb_chart_of_accounts SET is_active = 1, name = :name, name_ar = :name_ar WHERE id = :id'
        )->execute([
            'id' => $id,
            'name' => $name,
            'name_ar' => $nameAr,
        ]);
        $cid = is_array($existing) ? $this->normalizeCompanyId($existing['company_id'] ?? null) : null;
        if ($cid !== null || (is_array($existing) && array_key_exists('company_id', $existing))) {
            $this->invalidateCoaCodeMap($cid);
        }
    }

    /**
     * Ensure a company COA code exists (clone platform template or create from defaults).
     *
     * @param array<string, int> $codeToId
     */
    private function provisionCompanyCoaCode(int $companyId, string $code, array $def, array $codeToId): int
    {
        $existing = $this->findCoaByCode($companyId, $code);
        if ($existing) {
            return (int) $existing['id'];
        }
        $parentId = null;
        if (!empty($def['parent'])) {
            if (isset($codeToId[$def['parent']])) {
                $parentId = $codeToId[$def['parent']];
            } else {
                $parentRow = $this->findCoaByCode($companyId, (string) $def['parent']);
                if ($parentRow) {
                    $parentId = (int) $parentRow['id'];
                }
            }
        }
        $template = $this->findCoaByCode(null, $code);
        try {
            if ($template) {
                return $this->insertCoaRow($companyId, [
                    'code' => $code,
                    'name' => (string) $template['name'],
                    'name_ar' => $template['name_ar'] ?? ($def['name_ar'] ?? null),
                    'account_type' => (string) ($template['account_type'] ?? $def['type'] ?? 'asset'),
                ], $parentId);
            }
            return $this->insertCoaRow($companyId, $def, $parentId);
        } catch (\Throwable $e) {
            $again = $this->findCoaByCode($companyId, $code);
            if ($again) {
                return (int) $again['id'];
            }
            throw $e;
        }
    }

    public function ensureCompanyCoaCode(int $companyId, string $code): ?int
    {
        $companyId = $this->normalizeCompanyId($companyId) ?? 0;
        if ($companyId < 1) {
            return null;
        }
        $def = null;
        foreach (self::DEFAULT_ACCOUNTS as $d) {
            if ($d['code'] === $code) {
                $def = $d;
                break;
            }
        }
        if ($def === null) {
            $row = $this->findCoaByCode($companyId, $code);
            return $row ? (int) $row['id'] : null;
        }
        $id = $this->provisionCompanyCoaCode($companyId, $code, $def, []);
        return $id > 0 ? $id : null;
    }

    /** Company-owned account or shared platform template (company_id IS NULL). */
    private function accountUsableForCompany(int $accountId, int $companyId): bool
    {
        if ($accountId < 1 || $companyId < 1) {
            return false;
        }
        $stmt = Database::connection()->prepare(
            'SELECT company_id FROM rateb_chart_of_accounts WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $owner = $row['company_id'];
        if ($owner === null || $owner === '') {
            return true;
        }
        return (int) $owner === $companyId;
    }

    public function ensureDefaultAccounts(?int $companyId): void
    {
        $normalized = $this->normalizeCompanyId($companyId);
        // Steady-state dashboard: skip when all default codes already exist (Phase AI).
        // Force with RATEB_ACCOUNTING_REPAIR_COA=1 (install/migration/repair).
        if (!self::accountingRepairMode() && $this->defaultCoaIsComplete($normalized)) {
            return;
        }
        $codeToId = [];
        foreach (self::DEFAULT_ACCOUNTS as $def) {
            $code = (string) $def['code'];
            if ($normalized !== null && $normalized > 0) {
                $row = $this->findCoaByCode($normalized, $code);
                if ($row) {
                    $id = (int) $row['id'];
                    $this->touchCoaRow($id, $def, $row);
                } else {
                    $id = $this->provisionCompanyCoaCode($normalized, $code, $def, $codeToId);
                }
            } else {
                $row = $this->findCoaByCode(null, $code);
                if ($row) {
                    $id = (int) $row['id'];
                    $this->touchCoaRow($id, $def, $row);
                } else {
                    $parentId = null;
                    if (!empty($def['parent']) && isset($codeToId[$def['parent']])) {
                        $parentId = $codeToId[$def['parent']];
                    }
                    $id = $this->insertCoaRow(null, $def, $parentId);
                }
            }
            $codeToId[$code] = $id;
        }
        $this->linkCoaParents($normalized, null);
        $this->invalidateCoaCodeMap($normalized);
    }

    /** Backfill parent_id for existing COA rows (company or platform template). */
    private function linkCoaParents(?int $companyId, ?ChartOfAccount $coa = null): void
    {
        $coa = $coa ?? new ChartOfAccount();
        if ($companyId !== null && $companyId > 0) {
            $rows = $coa->query(
                'SELECT id, code, parent_id FROM rateb_chart_of_accounts WHERE company_id = :cid',
                ['cid' => $companyId]
            );
        } else {
            $rows = $coa->query(
                'SELECT id, code, parent_id FROM rateb_chart_of_accounts WHERE company_id IS NULL'
            );
        }
        $codeToId = [];
        foreach ($rows as $row) {
            $codeToId[(string) $row['code']] = (int) $row['id'];
        }
        foreach (self::DEFAULT_ACCOUNTS as $def) {
            if (empty($def['parent']) || empty($codeToId[$def['code']]) || empty($codeToId[$def['parent']])) {
                continue;
            }
            $coa->update($codeToId[$def['code']], ['parent_id' => $codeToId[$def['parent']]]);
        }
        foreach ($rows as $row) {
            $childId = (int) $row['id'];
            if ((int) ($row['parent_id'] ?? 0) > 0) {
                continue;
            }
            $code = (string) ($row['code'] ?? '');
            if (preg_match('/^111\d$/', $code) && !empty($codeToId['1150'])) {
                $coa->update($childId, ['parent_id' => $codeToId['1150']]);
                continue;
            }
            $parentCode = $this->inferParentCode($code, $codeToId);
            if ($parentCode === null || empty($codeToId[$parentCode]) || $codeToId[$parentCode] === $childId) {
                continue;
            }
            $coa->update($childId, ['parent_id' => $codeToId[$parentCode]]);
        }
    }

    /** @param array<string, int> $codeToId */
    private function inferParentCode(string $code, array $codeToId): ?string
    {
        if (!preg_match('/^\d{4}$/', $code) || substr($code, -3) === '000') {
            return null;
        }
        if (substr($code, -2) !== '00') {
            $subGroup = substr($code, 0, 2) . '00';
            if ($subGroup !== $code && isset($codeToId[$subGroup])) {
                return $subGroup;
            }
        }
        $root = substr($code, 0, 1) . '000';
        return isset($codeToId[$root]) ? $root : null;
    }

    public function accountIdByCode(?int $companyId, string $code): ?int
    {
        $normalized = $this->normalizeCompanyId($companyId);
        $row = $this->findCoaByCode($normalized, $code);
        if ($row) {
            return (int) $row['id'];
        }
        if ($normalized !== null && $normalized > 0) {
            $template = $this->findCoaByCode(null, $code);
            return $template ? (int) $template['id'] : null;
        }
        return null;
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

        $piSql = "SELECT * FROM rateb_purchase_invoices WHERE status = 'posted'";
        if ($companyId !== null) {
            $piSql .= ' AND company_id = ' . (int) $companyId;
        }
        foreach ($pdo->query($piSql)->fetchAll() as $pi) {
            if ($this->postPurchaseInvoice((array) $pi)) {
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

        $ar = $this->accountIdByCode($companyId, '1200');
        $revenue = $this->accountIdByCode($companyId, '4100');
        $vat = $this->accountIdByCode($companyId, '2200');
        if (!$ar || !$revenue) {
            return false;
        }

        $lines = [
            ['account_id' => $ar, 'debit' => $total, 'credit' => 0, 'memo' => 'Invoice ' . ($invoice['invoice_no'] ?? '')],
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
        $ar = $this->accountIdByCode($companyId, '1200');
        if (!$cash || !$ar) {
            return false;
        }

        return $this->createPostedEntry($companyId, 'payment', (int) $payment['id'], [
            ['account_id' => $cash, 'debit' => $amount, 'credit' => 0, 'memo' => 'Payment'],
            ['account_id' => $ar, 'debit' => 0, 'credit' => $amount, 'memo' => 'AR collection'],
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
        $net = max(0, (float) ($po['subtotal'] ?? 0) - (float) ($po['discount_amount'] ?? 0));
        if ($net <= 0 && $tax <= 0) {
            $net = max(0, $total - $tax);
        }
        if ($total <= 0 && $net <= 0) {
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
        $ccId = isset($po['cost_center_id']) && (int) $po['cost_center_id'] > 0 ? (int) $po['cost_center_id'] : null;
        $lines = [
            ['account_id' => $debitAccount, 'debit' => $net > 0 ? $net : $total, 'credit' => 0, 'memo' => $debitMemo . ' PO ' . ($po['order_no'] ?? ''), 'cost_center_id' => $ccId],
        ];
        if ($tax > 0 && $vatInput) {
            $lines[] = ['account_id' => $vatInput, 'debit' => $tax, 'credit' => 0, 'memo' => 'Input VAT', 'cost_center_id' => $ccId];
        }
        $lines[] = ['account_id' => $ap, 'debit' => 0, 'credit' => $total, 'memo' => 'AP'];

        return $this->createPostedEntry($companyId, 'purchase_order', (int) $po['id'], $lines,
            'Purchase order ' . ($po['order_no'] ?? ''),
            'أمر شراء ' . ($po['order_no'] ?? ''),
            (string) ($po['order_date'] ?? date('Y-m-d'))
        ) !== null;
    }

    public function autoPostPurchaseInvoice(int $purchaseInvoiceId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_purchase_invoices WHERE id = :id LIMIT 1',
            ['id' => $purchaseInvoiceId]
        );
        if (!$row || (string) ($row['status'] ?? '') !== 'posted') {
            return false;
        }
        return $this->postPurchaseInvoice((array) $row);
    }

    public function postPurchaseInvoice(array $invoice): bool
    {
        $companyId = (int) ($invoice['company_id'] ?? 0);
        if ($this->entryExists('purchase_invoice', (int) $invoice['id'])) {
            return false;
        }

        $shipping = max(0, (float) ($invoice['shipping_amount'] ?? 0));
        $customs = max(0, (float) ($invoice['customs_clearance_amount'] ?? 0));
        $landed = round($shipping + $customs, 2);
        if ($landed <= 0) {
            return false;
        }

        $inventory = $this->accountIdByCode($companyId, '1300');
        $ap = $this->accountIdByCode($companyId, '2100');
        if (!$inventory || !$ap) {
            return false;
        }

        $po = (new JournalEntry())->queryOne(
            'SELECT order_no, cost_center_id FROM rateb_purchase_orders WHERE id = :id LIMIT 1',
            ['id' => (int) ($invoice['purchase_order_id'] ?? 0)]
        );
        $ccId = isset($po['cost_center_id']) && (int) $po['cost_center_id'] > 0 ? (int) $po['cost_center_id'] : null;
        $ref = (string) ($invoice['invoice_no'] ?? $invoice['id']);
        $poNo = (string) ($po['order_no'] ?? '');

        return $this->createPostedEntry($companyId, 'purchase_invoice', (int) $invoice['id'], [
            ['account_id' => $inventory, 'debit' => $landed, 'credit' => 0, 'memo' => 'Landed costs PI ' . $ref, 'cost_center_id' => $ccId],
            ['account_id' => $ap, 'debit' => 0, 'credit' => $landed, 'memo' => 'AP landed costs PO ' . $poNo],
        ], 'Purchase invoice landed costs ' . $ref,
            'تكاليف إضافية فاتورة شراء ' . $ref,
            (string) ($invoice['invoice_date'] ?? date('Y-m-d'))
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

        try {
            $this->enforceLedgerMutableForWrite($companyId, $entryDate);
        } catch (\Throwable $e) {
            if ($this->isLedgerLockedException($e)) {
                error_log('createPostedEntry ledger lock: ' . $e->getMessage());

                return null;
            }
            throw $e;
        }

        $this->ensureJournalSourceTypeEnum();

        $entryModel = new JournalEntry();
        $entryNo = $this->nextEntryNo($companyId);
        $companyId = $this->normalizeCompanyId($companyId);
        try {
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
        } catch (\Throwable $e) {
            throw $e instanceof \PDOException
                ? $e
                : ($e->getPrevious() instanceof \PDOException ? $e->getPrevious() : $e);
        }

        $pdo = Database::connection();
        $withCostCenter = $this->journalLinesHaveCostCenter();
        $sql = $withCostCenter
            ? 'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, cost_center_id, debit, credit, memo) VALUES (:eid, :aid, :cc, :dr, :cr, :memo)'
            : 'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, debit, credit, memo) VALUES (:eid, :aid, :dr, :cr, :memo)';
        $stmt = $pdo->prepare($sql);
        foreach ($lines as $line) {
            $params = [
                'eid' => $entryId,
                'aid' => (int) $line['account_id'],
                'dr' => $line['debit'],
                'cr' => $line['credit'],
                'memo' => $line['memo'] ?? null,
            ];
            if ($withCostCenter) {
                $params['cc'] = isset($line['cost_center_id']) && (int) $line['cost_center_id'] > 0
                    ? (int) $line['cost_center_id']
                    : null;
            }
            $stmt->execute($params);
        }

        $this->emitAccountingGatewayPostedEvent(
            (int) $entryId,
            $companyId,
            $sourceType,
            $sourceId,
            $lines,
            $description,
            $entryDate
        );

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
        ?int $createdBy = null,
        ?int $branchId = null
    ): int {
        if (!$this->isBalanced($lines)) {
            throw new \InvalidArgumentException('Journal entry is not balanced.');
        }
        $this->ensureDefaultAccounts($companyId);
        $companyId = $this->normalizeCompanyId($companyId);
        $branchId = $this->resolveBranchId($companyId, $branchId);
        $entryModel = new JournalEntry();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $entryData = [
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
            ];
            if ($branchId > 0) {
                $entryData['branch_id'] = $branchId;
            }
            $entryId = $entryModel->create($entryData);
            $this->replaceJournalLines($entryId, $lines);
            $pdo->commit();
            return $entryId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines */
    public function updateManualDraft(
        int $entryId,
        ?int $companyId,
        string $entryDate,
        string $description,
        string $descriptionAr,
        array $lines,
        ?int $branchId = null
    ): bool {
        if (!$this->isBalanced($lines)) {
            throw new \InvalidArgumentException('Journal entry is not balanced.');
        }
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry || !$this->isManualJournalEditable($entry)) {
            return false;
        }
        if (in_array((string) ($entry['status'] ?? ''), ['rejected', 'void'], true)) {
            (new JournalEntry())->update($entryId, [
                'status' => 'draft',
                'reject_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
            ]);
        }
        $companyId = $this->normalizeCompanyId($companyId);
        $branchId = $this->resolveBranchId($companyId, $branchId);
        $update = [
            'entry_date' => $entryDate,
            'description' => $description,
            'description_ar' => $descriptionAr,
        ];
        if ($branchId > 0) {
            $update['branch_id'] = $branchId;
        }
        (new JournalEntry())->update($entryId, $update);
        $this->clearJournalSubmission($entryId);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->replaceJournalLines($entryId, $lines);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function ensureApprovalSubmitColumns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $this->ensureAccountingStatusEnums();
        $this->ensureAccountingRejectColumns();
        $pdo = Database::connection();
        foreach (['rateb_journal_entries', 'rateb_cash_vouchers'] as $table) {
            try {
                $has = Database::liveTableHasColumn($table, 'submitted_for_approval_at');
                if (!$has) {
                    $pdo->exec(
                        "ALTER TABLE {$table} ADD COLUMN submitted_for_approval_at DATETIME NULL AFTER posted_at"
                    );
                }
            } catch (\Throwable $e) {
                // Migration 116 or host permissions may apply later.
            }
        }
    }

    public function ensureAccountingStatusEnums(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $pdo = Database::connection();
        foreach ([
            'rateb_journal_entries' => "ENUM('draft','posted','void','rejected') NOT NULL DEFAULT 'draft'",
            'rateb_cash_vouchers' => "ENUM('draft','posted','void','rejected') NOT NULL DEFAULT 'draft'",
        ] as $table => $enumDef) {
            try {
                if (Database::liveTableHasColumn($table, 'status')) {
                    $pdo->exec("ALTER TABLE {$table} MODIFY status {$enumDef}");
                }
            } catch (\Throwable $e) {
                // Host may block ALTER; migration 117 applies on deploy.
            }
        }
    }

    public function ensureAccountingRejectColumns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $pdo = Database::connection();
        $columnDdls = [
            'reject_reason' => 'ADD COLUMN reject_reason VARCHAR(500) NULL AFTER status',
            'rejected_at' => 'ADD COLUMN rejected_at DATETIME NULL AFTER reject_reason',
            'rejected_by' => 'ADD COLUMN rejected_by INT UNSIGNED NULL AFTER rejected_at',
        ];
        foreach (['rateb_journal_entries', 'rateb_cash_vouchers'] as $table) {
            foreach ($columnDdls as $column => $ddl) {
                try {
                    if (!Database::liveTableHasColumn($table, $column)) {
                        $pdo->exec("ALTER TABLE {$table} {$ddl}");
                    }
                } catch (\Throwable $e) {
                    // Migration 118 or host permissions may apply later.
                }
            }
        }
    }

    public function undoJournalFromOversight(int $entryId, ?int $companyId): bool
    {
        $this->ensureApprovalSubmitColumns();
        $this->ensureAccountingRejectColumns();
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry) {
            return false;
        }
        $st = (string) ($entry['status'] ?? '');
        $db = Database::connection();
        if ($st === 'posted') {
            if (!$this->voidPostedEntry($entryId, $companyId, ['manual'])) {
                return false;
            }
            $db->prepare(
                'UPDATE rateb_journal_entries SET status = :st, posted_at = NULL, submitted_for_approval_at = NULL WHERE id = :id'
            )->execute(['st' => 'draft', 'id' => $entryId]);
            return true;
        }
        if ($st === 'rejected') {
            $db->prepare(
                'UPDATE rateb_journal_entries SET status = :st, reject_reason = NULL, rejected_at = NULL, rejected_by = NULL, submitted_for_approval_at = NULL WHERE id = :id'
            )->execute(['st' => 'draft', 'id' => $entryId]);
            return true;
        }

        return $st === 'draft';
    }

    public function undoCashVoucherFromOversight(int $voucherId, ?int $companyId): bool
    {
        $this->ensureApprovalSubmitColumns();
        $this->ensureAccountingRejectColumns();
        $v = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id' . ($companyId !== null && $companyId > 0 ? ' AND company_id = :cid' : '') . ' LIMIT 1',
            $companyId !== null && $companyId > 0 ? ['id' => $voucherId, 'cid' => $companyId] : ['id' => $voucherId]
        );
        if (!$v) {
            return false;
        }
        $st = (string) ($v['status'] ?? '');
        $db = Database::connection();
        if ($st === 'posted') {
            if (!$this->voidCashVoucher($voucherId, $companyId)) {
                return false;
            }
            $db->prepare(
                'UPDATE rateb_cash_vouchers SET status = :st, posted_at = NULL, journal_entry_id = NULL, submitted_for_approval_at = NULL WHERE id = :id'
            )->execute(['st' => 'draft', 'id' => $voucherId]);
            return true;
        }
        if ($st === 'rejected') {
            $db->prepare(
                'UPDATE rateb_cash_vouchers SET status = :st, reject_reason = NULL, rejected_at = NULL, rejected_by = NULL, submitted_for_approval_at = NULL WHERE id = :id'
            )->execute(['st' => 'draft', 'id' => $voucherId]);
            return true;
        }

        return $st === 'draft';
    }

    /** @param array<string, mixed> $entry */
    public function isManualJournalEditable(array $entry): bool
    {
        if (($entry['source_type'] ?? '') !== 'manual') {
            return false;
        }

        return in_array((string) ($entry['status'] ?? ''), ['draft', 'rejected', 'void'], true);
    }

    /** @param array<string, mixed> $entry */
    public function canDeleteManualJournal(array $entry): bool
    {
        return $this->isManualJournalEditable($entry) && !$this->isSubmittedForApproval($entry);
    }

    /** @param array<string, mixed> $row */
    public function isCashVoucherEditable(array $row): bool
    {
        return in_array((string) ($row['status'] ?? ''), ['draft', 'rejected', 'void'], true);
    }

    /** @param array<string, mixed> $row */
    public function canDeleteCashVoucher(array $row): bool
    {
        return $this->isCashVoucherEditable($row) && !$this->isSubmittedForApproval($row);
    }

    /** @param array<string, mixed> $row */
    public function accountingRowDisplayStatus(array $row, string $statusKey = 'status'): string
    {
        $st = (string) ($row[$statusKey] ?? '');
        $submitted = trim((string) ($row['submitted_for_approval_at'] ?? '')) !== '';
        if ($st === 'draft' && $submitted) {
            return 'awaiting_oversight_approval';
        }
        if ($st === 'draft') {
            return 'draft';
        }
        if ($st === 'posted') {
            return 'approved';
        }
        return $st;
    }

    /** @param array<string, mixed> $row */
    public function isSubmittedForApproval(array $row): bool
    {
        return trim((string) ($row['submitted_for_approval_at'] ?? '')) !== '';
    }

    public function submitJournalForApproval(int $entryId, ?int $companyId): ?string
    {
        $this->ensureApprovalSubmitColumns();
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry) {
            return 'invalid_request';
        }
        if (($entry['status'] ?? '') !== 'draft' || ($entry['source_type'] ?? '') !== 'manual') {
            return 'journal_post_not_draft';
        }
        if ($this->isSubmittedForApproval($entry)) {
            return 'approval_already_submitted';
        }
        $lines = $this->loadEntryLines($entryId);
        if ($lines === []) {
            return 'journal_no_lines';
        }
        if (!$this->isBalanced($lines)) {
            return 'journal_not_balanced';
        }
        if (!$this->isPeriodOpen($companyId, (string) ($entry['entry_date'] ?? date('Y-m-d')))) {
            return 'fiscal_period_closed_block';
        }
        Database::connection()->prepare(
            'UPDATE rateb_journal_entries SET submitted_for_approval_at = NOW() WHERE id = :id'
        )->execute(['id' => $entryId]);
        return null;
    }

    public function submitCashVoucherForApproval(int $voucherId, ?int $companyId): ?string
    {
        $this->ensureApprovalSubmitColumns();
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null || $companyId < 1) {
            return 'invalid_request';
        }
        $v = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $voucherId, 'cid' => $companyId]
        );
        if (!$v || ($v['status'] ?? '') !== 'draft') {
            return 'voucher_post_not_draft';
        }
        if ($this->isSubmittedForApproval($v)) {
            return 'approval_already_submitted';
        }
        $amount = (float) ($v['amount'] ?? 0);
        if ($amount <= 0) {
            return 'voucher_no_amount';
        }
        if ((int) ($v['counter_account_id'] ?? 0) < 1) {
            return 'voucher_no_counter_account';
        }
        if (!$this->isPeriodOpen($companyId, (string) ($v['voucher_date'] ?? date('Y-m-d')))) {
            return 'fiscal_period_closed_block';
        }
        Database::connection()->prepare(
            'UPDATE rateb_cash_vouchers SET submitted_for_approval_at = NOW() WHERE id = :id'
        )->execute(['id' => $voucherId]);
        return null;
    }

    private function clearJournalSubmission(int $entryId): void
    {
        $this->ensureApprovalSubmitColumns();
        try {
            Database::connection()->prepare(
                'UPDATE rateb_journal_entries SET submitted_for_approval_at = NULL WHERE id = :id'
            )->execute(['id' => $entryId]);
        } catch (\Throwable $e) {
            // Column may be missing until migration runs.
        }
    }

    private function stampJournalSubmittedForApproval(int $entryId): void
    {
        $this->ensureApprovalSubmitColumns();
        Database::connection()->prepare(
            'UPDATE rateb_journal_entries SET submitted_for_approval_at = NOW() WHERE id = :id AND submitted_for_approval_at IS NULL'
        )->execute(['id' => $entryId]);
    }

    private function stampCashVoucherSubmittedForApproval(int $voucherId): void
    {
        $this->ensureApprovalSubmitColumns();
        Database::connection()->prepare(
            'UPDATE rateb_cash_vouchers SET submitted_for_approval_at = NOW() WHERE id = :id AND submitted_for_approval_at IS NULL'
        )->execute(['id' => $voucherId]);
    }

    private function clearCashVoucherSubmission(int $voucherId): void
    {
        $this->ensureApprovalSubmitColumns();
        try {
            Database::connection()->prepare(
                'UPDATE rateb_cash_vouchers SET submitted_for_approval_at = NULL WHERE id = :id'
            )->execute(['id' => $voucherId]);
        } catch (\Throwable $e) {
            // Column may be missing until migration runs.
        }
    }

    /** Post manual draft; returns null on success or a lang key for the failure reason. */
    public function postDraftEntryReason(int $entryId, ?int $companyId, bool $fromOversight = false): ?string
    {
        $this->ensureApprovalSubmitColumns();
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry) {
            return 'journal_post_failed';
        }
        if (($entry['status'] ?? '') !== 'draft') {
            return 'journal_post_not_draft';
        }
        if ((string) ($entry['source_type'] ?? '') !== 'manual') {
            return 'journal_post_not_manual';
        }
        if (!$this->isSubmittedForApproval($entry)) {
            if (!$fromOversight) {
                return 'not_submitted_for_approval';
            }
            $this->stampJournalSubmittedForApproval($entryId);
        }
        $lines = $this->loadEntryLines($entryId);
        if ($lines === []) {
            return 'journal_no_lines';
        }
        if (!$this->isBalanced($lines)) {
            return 'journal_not_balanced';
        }
        if (!$this->isPeriodOpen($companyId, (string) ($entry['entry_date'] ?? date('Y-m-d')))) {
            return 'fiscal_period_closed_block';
        }
        try {
            $this->enforceLedgerMutableForWrite(
                $companyId,
                (string) ($entry['entry_date'] ?? date('Y-m-d')),
                isset($entry['branch_id']) ? (int) $entry['branch_id'] : null
            );
            (new JournalEntry())->update($entryId, [
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            return 'journal_post_failed';
        }
        return null;
    }

    public function postDraftEntry(int $entryId, ?int $companyId): bool
    {
        return $this->postDraftEntryReason($entryId, $companyId) === null;
    }

    public function voidPostedEntry(int $entryId, ?int $companyId, ?array $allowedSourceTypes = null): bool
    {
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry || ($entry['status'] ?? '') !== 'posted') {
            return false;
        }
        $allowed = $allowedSourceTypes ?? ['manual'];
        if (!in_array((string) ($entry['source_type'] ?? ''), $allowed, true)) {
            return false;
        }
        if (!$this->isPeriodOpen($companyId, (string) ($entry['entry_date'] ?? date('Y-m-d')))) {
            return false;
        }
        (new JournalEntry())->update($entryId, ['status' => 'void']);
        return true;
    }

    /** @return array{rows: array<int, array<string, mixed>>, total_open: float, total_posted: float} */
    public function accountsPayable(?int $companyId, bool $skipOperationalBranchScope = false): array
    {
        $memoKey = 'accountsPayable:' . $this->coaMapKey($companyId) . ':' . ($skipOperationalBranchScope ? '1' : '0');

        return self::requestMemo($memoKey, function () use ($companyId, $skipOperationalBranchScope): array {
        $sql = "SELECT po.id, po.order_no, po.order_date, po.status, po.total_amount, po.supplier_id,
                       s.name AS supplier_name, s.code AS supplier_code,
                       je.id AS journal_id, je.entry_no,
                       COALESCE(sp.paid, 0) AS paid_amount
                FROM rateb_purchase_orders po
                LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
                LEFT JOIN rateb_journal_entries je ON je.source_type = 'purchase_order'
                    AND je.source_id = po.id AND je.status = 'posted'
                LEFT JOIN (
                    SELECT purchase_order_id, SUM(amount) AS paid
                    FROM rateb_supplier_payments
                    WHERE status = 'posted'
                    GROUP BY purchase_order_id
                ) sp ON sp.purchase_order_id = po.id
                WHERE po.status IN ('sent','confirmed','partial','received')";
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND po.company_id = :cid';
            $params['cid'] = $companyId;
        }
        if (!$skipOperationalBranchScope) {
            [$sql, $params] = $this->scopeOperationalSql($sql, $params, 'po', 'rateb_purchase_orders');
            [$sql, $params] = $this->scopeOptionalJournalEntrySql($sql, $params, 'je');
        }
        $sql .= ' ORDER BY po.order_date DESC, po.id DESC LIMIT 200';
        try {
            $rows = $this->executeScopedSql($sql, $params);
        } catch (\RuntimeException $e) {
            if (!$skipOperationalBranchScope && $this->isBranchColumnSqlError($e)) {
                return $this->accountsPayable($companyId, true);
            }
            throw $e;
        }
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
        });
    }

    /** Outstanding AP balance for a supplier (posted PO totals minus payments). */
    public function supplierOutstandingBalance(?int $companyId, int $supplierId): float
    {
        if ($companyId === null || $companyId < 1 || $supplierId < 1) {
            return 0.0;
        }
        $sql = "SELECT COALESCE(SUM(GREATEST(po.total_amount - COALESCE(sp.paid, 0), 0)), 0) AS due
             FROM rateb_purchase_orders po
             INNER JOIN rateb_journal_entries je ON je.source_type = 'purchase_order'
                 AND je.source_id = po.id AND je.status = 'posted'
             LEFT JOIN (
                 SELECT purchase_order_id, SUM(amount) AS paid
                 FROM rateb_supplier_payments WHERE status = 'posted'
                 GROUP BY purchase_order_id
             ) sp ON sp.purchase_order_id = po.id
             WHERE po.company_id = :cid AND po.supplier_id = :sid
               AND po.status IN ('sent','confirmed','partial','received')";
        $params = ['cid' => $companyId, 'sid' => $supplierId];
        [$sql, $params] = $this->scopeOperationalSql($sql, $params, 'po', 'rateb_purchase_orders');
        [$sql, $params] = $this->scopeJournalEntrySql($sql, $params, 'je');
        $row = $this->executeScopedSqlOne($sql, $params);

        return (float) ($row['due'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function purchaseOrderPayable(?int $companyId, int $poId): ?array
    {
        if ($companyId === null || $companyId < 1 || $poId < 1) {
            return null;
        }
        $sql = 'SELECT po.*, s.name AS supplier_name, je.id AS journal_id
             FROM rateb_purchase_orders po
             LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
             LEFT JOIN rateb_journal_entries je ON je.source_type = \'purchase_order\'
                 AND je.source_id = po.id AND je.status = \'posted\'
             WHERE po.id = :id AND po.company_id = :cid';
        $params = ['id' => $poId, 'cid' => $companyId];
        [$sql, $params] = $this->scopeOperationalSql($sql, $params, 'po', 'rateb_purchase_orders');
        [$sql, $params] = $this->scopeOptionalJournalEntrySql($sql, $params, 'je');
        $sql .= ' LIMIT 1';
        $po = $this->executeScopedSqlOne($sql, $params);
        if (!$po || empty($po['journal_id'])) {
            return null;
        }
        $paidRow = (new JournalEntry())->queryOne(
            'SELECT COALESCE(SUM(amount), 0) AS paid FROM rateb_supplier_payments
             WHERE purchase_order_id = :poid AND status = :st',
            ['poid' => $poId, 'st' => 'posted']
        );
        $total = (float) ($po['total_amount'] ?? 0);
        $paid = (float) ($paidRow['paid'] ?? 0);
        $due = max(0, $total - $paid);
        $dueDate = (string) ($po['expected_date'] ?? $po['order_date'] ?? '');

        return [
            'po' => $po,
            'total' => $total,
            'paid' => $paid,
            'due' => $due,
            'due_date' => $dueDate,
            'supplier_id' => (int) ($po['supplier_id'] ?? 0),
            'supplier_name' => (string) ($po['supplier_name'] ?? ''),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listPayablePurchaseOrders(?int $companyId, ?int $supplierId = null): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        $sql = "SELECT po.id, po.order_no, po.order_date, po.expected_date, po.total_amount, po.supplier_id,
                       s.name AS supplier_name,
                       COALESCE(sp.paid, 0) AS paid_amount,
                       GREATEST(po.total_amount - COALESCE(sp.paid, 0), 0) AS due_amount
                FROM rateb_purchase_orders po
                LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
                INNER JOIN rateb_journal_entries je ON je.source_type = 'purchase_order'
                    AND je.source_id = po.id AND je.status = 'posted'
                LEFT JOIN (
                    SELECT purchase_order_id, SUM(amount) AS paid
                    FROM rateb_supplier_payments WHERE status = 'posted'
                    GROUP BY purchase_order_id
                ) sp ON sp.purchase_order_id = po.id
                WHERE po.company_id = :cid
                  AND po.status IN ('sent','confirmed','partial','received')
                  AND po.total_amount > COALESCE(sp.paid, 0) + 0.009";
        $params = ['cid' => $companyId];
        if ($supplierId !== null && $supplierId > 0) {
            $sql .= ' AND po.supplier_id = :sid';
            $params['sid'] = $supplierId;
        }
        $sql .= ' ORDER BY po.order_date DESC, po.id DESC LIMIT 300';
        [$sql, $params] = $this->scopeOperationalSql($sql, $params, 'po', 'rateb_purchase_orders');
        [$sql, $params] = $this->scopeJournalEntrySql($sql, $params, 'je');

        return $this->executeScopedSql($sql, $params);
    }

    /** Supplier invoices linked via po_number (open balances). */
    /** @return array<int, array<string, mixed>> */
    public function listPayableSupplierInvoices(?int $companyId, ?int $supplierId = null, ?string $orderNo = null): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        $sql = "SELECT i.id, i.invoice_no, i.po_number, i.total_amount, i.due_date, i.issued_at,
                       i.payment_status, po.id AS purchase_order_id, po.supplier_id, s.name AS supplier_name
                FROM rateb_invoices i
                INNER JOIN rateb_purchase_orders po ON po.company_id = i.company_id
                    AND po.order_no = i.po_number
                LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
                WHERE i.company_id = :cid
                  AND i.status IN ('sent','overdue')
                  AND i.payment_status IN ('unpaid','partial')
                  AND i.po_number IS NOT NULL AND i.po_number != ''";
        $params = ['cid' => $companyId];
        if ($supplierId !== null && $supplierId > 0) {
            $sql .= ' AND po.supplier_id = :sid';
            $params['sid'] = $supplierId;
        }
        if ($orderNo !== null && $orderNo !== '') {
            $sql .= ' AND i.po_number = :ono';
            $params['ono'] = $orderNo;
        }
        $sql .= ' ORDER BY i.due_date ASC, i.id DESC LIMIT 200';
        [$sql, $params] = $this->scopeOperationalSql($sql, $params, 'po', 'rateb_purchase_orders');

        return $this->executeScopedSql($sql, $params);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total_open: float, total_paid: float} */
    public function accountsReceivable(?int $companyId): array
    {
        return self::requestMemo('accountsReceivable:' . $this->coaMapKey($companyId), function () use ($companyId): array {
        if ($companyId === null) {
            return ['rows' => [], 'total_open' => 0.0, 'total_paid' => 0.0];
        }
        $sql = "SELECT i.*, je.id AS journal_id, je.entry_no
             FROM rateb_invoices i
             LEFT JOIN rateb_journal_entries je ON je.source_type = 'invoice'
                 AND je.source_id = i.id AND je.status = 'posted'
             WHERE i.company_id = :cid AND i.status != 'cancelled'";
        $params = ['cid' => $companyId];
        [$sql, $params] = $this->scopeOptionalJournalEntrySql($sql, $params, 'je');
        $branchIds = $this->accountingBranch()->effectiveBranchIds();
        if ($branchIds !== [] && $this->operationalTableSupportsBranchFilter('rateb_purchase_orders')) {
            $parts = [];
            foreach ($branchIds as $i => $bid) {
                $key = '_ar_pob_' . $i;
                $parts[] = ':' . $key;
                $params[$key] = $bid;
            }
            $sql .= ' AND (je.id IS NOT NULL OR EXISTS (
                SELECT 1 FROM rateb_purchase_orders _arpo
                WHERE _arpo.company_id = i.company_id AND _arpo.order_no = i.po_number
                  AND _arpo.branch_id IN (' . implode(',', $parts) . ')
            ))';
        }
        $sql .= ' ORDER BY i.issued_at DESC, i.id DESC LIMIT 200';
        $rows = $this->executeScopedSql($sql, $params);
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
        });
    }

    /** @return array{revenue: float, expenses: float, net: float, lines: array<int, array<string, mixed>>} */
    public function profitAndLoss(?int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $memoKey = 'profitAndLoss:' . $this->coaMapKey($companyId) . ':' . ($fromDate ?? '') . ':' . ($toDate ?? '');

        return self::requestMemo($memoKey, function () use ($companyId, $fromDate, $toDate): array {
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
        [$sql, $params] = $this->scopeJournalLineSql($sql, $params, 'l', 'e');
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
        });
    }

    /** @return array{total: float, accounts: array<int, array<string, mixed>>, entries: array<int, array<string, mixed>>} */
    public function costOfSalesReport(?int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($companyId === null || $companyId < 1) {
            return ['total' => 0.0, 'accounts' => [], 'entries' => []];
        }

        $accountSql = "SELECT a.id, a.code, a.name, a.name_ar,
                              COALESCE(SUM(l.debit), 0) AS total_debit,
                              COALESCE(SUM(l.credit), 0) AS total_credit
                       FROM rateb_chart_of_accounts a
                       INNER JOIN rateb_journal_lines l ON l.account_id = a.id
                       INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                       WHERE a.company_id = :cid AND a.is_active = 1
                         AND a.account_type = 'expense'
                         AND (a.code = '5200' OR a.code LIKE '520%')";
        $params = ['cid' => $companyId];
        if ($fromDate) {
            $accountSql .= ' AND e.entry_date >= :from';
            $params['from'] = $fromDate;
        }
        if ($toDate) {
            $accountSql .= ' AND e.entry_date <= :to';
            $params['to'] = $toDate;
        }
        $accountSql .= ' GROUP BY a.id ORDER BY a.code';
        [$accountSql, $params] = $this->scopeJournalLineSql($accountSql, $params, 'l', 'e');
        $accounts = (new ChartOfAccount())->query($accountSql, $params);

        $total = 0.0;
        foreach ($accounts as $row) {
            $total += (float) ($row['total_debit'] ?? 0) - (float) ($row['total_credit'] ?? 0);
        }

        $entrySql = "SELECT e.entry_no, e.entry_date, e.description, e.description_ar,
                            a.code, a.name, a.name_ar, l.debit, l.credit, l.memo
                     FROM rateb_journal_lines l
                     INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                     INNER JOIN rateb_chart_of_accounts a ON a.id = l.account_id
                     WHERE a.company_id = :cid AND a.is_active = 1
                       AND a.account_type = 'expense'
                       AND (a.code = '5200' OR a.code LIKE '520%')";
        $entryParams = ['cid' => $companyId];
        if ($fromDate) {
            $entrySql .= ' AND e.entry_date >= :from';
            $entryParams['from'] = $fromDate;
        }
        if ($toDate) {
            $entrySql .= ' AND e.entry_date <= :to';
            $entryParams['to'] = $toDate;
        }
        $entrySql .= ' ORDER BY e.entry_date DESC, e.id DESC, l.id ASC';
        [$entrySql, $entryParams] = $this->scopeJournalLineSql($entrySql, $entryParams, 'l', 'e');
        $entries = (new JournalEntry())->query($entrySql, $entryParams);

        return ['total' => $total, 'accounts' => $accounts, 'entries' => $entries];
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
        [$sql, $params] = $this->scopeJournalLineSql($sql, $params, 'l', 'e');
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
        $branchId = $this->resolveJournalLineBranchId($entryId);
        $hasBranchCol = $this->journalLineBranchColumnExists();
        if ($hasBranchCol) {
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_journal_lines (journal_entry_id, branch_id, account_id, cost_center_id, debit, credit, memo) VALUES (:eid, :bid, :aid, :cc, :dr, :cr, :memo)'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, cost_center_id, debit, credit, memo) VALUES (:eid, :aid, :cc, :dr, :cr, :memo)'
            );
        }
        foreach ($lines as $line) {
            $cc = isset($line['cost_center_id']) && (int) $line['cost_center_id'] > 0 ? (int) $line['cost_center_id'] : null;
            $params = [
                'eid' => $entryId,
                'aid' => (int) $line['account_id'],
                'cc' => $cc,
                'dr' => $line['debit'],
                'cr' => $line['credit'],
                'memo' => $line['memo'] ?? null,
            ];
            if ($hasBranchCol) {
                $params['bid'] = $branchId > 0 ? $branchId : null;
            }
            $stmt->execute($params);
        }
    }

    /** @return array<string, mixed> */
    public function financialSummary(?int $companyId): array
    {
        return self::requestMemo('financialSummary:' . $this->coaMapKey($companyId), function () use ($companyId): array {
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

        $journal = $this->journalScopedQueryOne(
            'SELECT COUNT(*) AS c FROM rateb_journal_entries e WHERE e.status = :st' . ($companyId !== null ? ' AND e.company_id = :cid' : ''),
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
        });
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
        [$sql, $params] = $this->scopeJournalLineSql($sql, ['cid' => $companyId, 'posted' => 'posted'], 'l', 'e');
        return (new ChartOfAccount())->query($sql, $params);
    }

    /** @return array<int, array<string, mixed>> */
    public function coaTreeWithBalances(?int $companyId): array
    {
        $this->ensureDefaultAccounts($companyId);
        $accounts = (new ChartOfAccount())->query(
            'SELECT * FROM rateb_chart_of_accounts WHERE company_id <=> :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
        $balanceMap = [];
        foreach ($this->trialBalance($companyId) as $row) {
            $balanceMap[(int) $row['id']] = $row;
        }
        foreach ($accounts as &$account) {
            $id = (int) $account['id'];
            $bal = $balanceMap[$id] ?? [];
            $account['total_debit'] = (float) ($bal['total_debit'] ?? 0);
            $account['total_credit'] = (float) ($bal['total_credit'] ?? 0);
            $account['balance'] = $account['total_debit'] - $account['total_credit'];
            $account['children'] = [];
        }
        unset($account);
        $tree = $this->buildAccountTree($accounts);
        $this->rollupTreeBalances($tree);
        return $tree;
    }

    /**
     * @param array<int, array<string, mixed>> $accounts
     * @return array<int, array<string, mixed>>
     */
    public function buildAccountTree(array $accounts): array
    {
        $byId = [];
        $codeToId = [];
        foreach ($accounts as $account) {
            $account['children'] = $account['children'] ?? [];
            $byId[(int) $account['id']] = $account;
            $codeToId[(string) ($account['code'] ?? '')] = (int) $account['id'];
        }
        $roots = [];
        foreach ($byId as $id => $account) {
            $parentId = (int) ($account['parent_id'] ?? 0);
            $code = (string) ($account['code'] ?? '');
            if ($parentId < 1) {
                if (preg_match('/^111\d$/', $code) && isset($codeToId['1150'])) {
                    $parentId = $codeToId['1150'];
                } else {
                    $parentCode = $this->inferParentCode($code, $codeToId);
                    if ($parentCode !== null && isset($codeToId[$parentCode])) {
                        $parentId = $codeToId[$parentCode];
                    }
                }
            }
            if ($parentId > 0 && isset($byId[$parentId]) && $parentId !== $id) {
                $byId[$parentId]['children'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }
        usort($roots, static fn (array $a, array $b): int => strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? '')));
        return array_values($roots);
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function rollupTreeBalances(array &$nodes): void
    {
        foreach ($nodes as &$node) {
            if (empty($node['children'])) {
                continue;
            }
            $this->rollupTreeBalances($node['children']);
            foreach ($node['children'] as $child) {
                $node['total_debit'] = (float) ($node['total_debit'] ?? 0) + (float) ($child['total_debit'] ?? 0);
                $node['total_credit'] = (float) ($node['total_credit'] ?? 0) + (float) ($child['total_credit'] ?? 0);
                $node['balance'] = (float) ($node['balance'] ?? 0) + (float) ($child['balance'] ?? 0);
            }
        }
    }

    private function entryExists(string $sourceType, int $sourceId): bool
    {
        return $this->journalExistsForSource($sourceType, $sourceId);
    }

    public function journalExistsForSource(string $sourceType, int $sourceId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_journal_entries WHERE source_type = :t AND source_id = :sid AND status != :void LIMIT 1',
            ['t' => $sourceType, 'sid' => $sourceId, 'void' => 'void']
        );

        return $row !== null;
    }

    public function journalIdForSource(string $sourceType, int $sourceId): ?int
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_journal_entries WHERE source_type = :t AND source_id = :sid AND status != :void LIMIT 1',
            ['t' => $sourceType, 'sid' => $sourceId, 'void' => 'void']
        );

        return $row !== null ? (int) $row['id'] : null;
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
        $memoKey = 'vatReport:' . $this->coaMapKey($companyId) . ':' . ($fromDate ?? '') . ':' . ($toDate ?? '');

        return self::requestMemo($memoKey, function () use ($companyId, $fromDate, $toDate): array {
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
        $invParams = $params;
        $poParams = $params;
        if ($companyId !== null) {
            [$invSql, $invParams] = $this->scopeOperationalSql($invSql, $invParams, '', 'rateb_invoices');
        }
        [$poSql, $poParams] = $this->scopeOperationalSql($poSql, $poParams, '', 'rateb_purchase_orders');
        $pdo = Database::connection();
        $invStmt = $pdo->prepare($invSql);
        $invStmt->execute($invParams);
        $poStmt = $pdo->prepare($poSql);
        $poStmt->execute($poParams);
        $invoiceTax = (float) (($invStmt->fetch(PDO::FETCH_ASSOC) ?: [])['t'] ?? 0);
        $poTax = (float) (($poStmt->fetch(PDO::FETCH_ASSOC) ?: [])['t'] ?? 0);
        return [
            'output_vat' => $output,
            'input_vat' => $input,
            'net_vat' => $output - $input,
            'invoice_tax' => $invoiceTax,
            'po_tax' => $poTax,
        ];
        });
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
        [$sql, $params] = $this->scopeJournalLineSql($sql, $params, 'l', 'e');
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

    public function closeFiscalPeriod(int $periodId, ?int $companyId, ?int $userId, bool $withClosingEntry = false): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_fiscal_periods WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $periodId, 'cid' => $companyId]
        );
        if (!$row || ($row['status'] ?? '') !== 'open') {
            return false;
        }
        $closingEntryId = null;
        if ($withClosingEntry) {
            $closingEntryId = $this->createYearEndClosingEntry(
                $companyId,
                (string) ($row['start_date'] ?? ''),
                (string) ($row['end_date'] ?? '')
            );
            if ($closingEntryId === null) {
                return false;
            }
        }
        (new JournalEntry())->query(
            'UPDATE rateb_fiscal_periods SET status = :st, closed_at = NOW(), closed_by = :uid, closing_entry_id = :jid WHERE id = :id',
            ['st' => 'closed', 'uid' => $userId, 'jid' => $closingEntryId, 'id' => $periodId]
        );
        return true;
    }

    public function reopenFiscalPeriod(int $periodId, ?int $companyId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_fiscal_periods WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $periodId, 'cid' => $companyId]
        );
        if (!$row || ($row['status'] ?? '') !== 'closed') {
            return false;
        }
        (new JournalEntry())->query(
            'UPDATE rateb_fiscal_periods SET status = :st, closed_at = NULL, closed_by = NULL WHERE id = :id',
            ['st' => 'open', 'id' => $periodId]
        );
        return true;
    }

    /** @param array<string, mixed> $data */
    public function createBankAccount(?int $companyId, array $data): int
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null) {
            throw new \InvalidArgumentException('Company required');
        }
        $this->ensureDefaultAccounts($companyId);
        $code = $this->nextBankAccountCode($companyId);
        $coa = new ChartOfAccount();
        $banksParent = $this->accountIdByCode($companyId, '1150');
        $coaId = $coa->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => (string) ($data['name'] ?? 'Bank'),
            'name_ar' => (string) ($data['name_ar'] ?? $data['name'] ?? 'بنك'),
            'account_type' => 'asset',
            'parent_id' => $banksParent,
            'is_active' => 1,
        ]);
        $pdo = Database::connection();
        if (!empty($data['is_default'])) {
            $pdo->prepare('UPDATE rateb_bank_accounts SET is_default = 0 WHERE company_id = :cid')->execute(['cid' => $companyId]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_bank_accounts
             (company_id, name, bank_name, account_number, chart_account_id, opening_balance, is_default, is_active)
             VALUES (:cid, :name, :bank, :acct_no, :coa, :ob, :def, 1)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'name' => trim((string) ($data['name'] ?? '')),
            'bank' => trim((string) ($data['bank_name'] ?? '')),
            'acct_no' => trim((string) ($data['account_number'] ?? '')),
            'coa' => $coaId,
            'ob' => (float) ($data['opening_balance'] ?? 0),
            'def' => !empty($data['is_default']) ? 1 : 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function listBankAccounts(?int $companyId): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        $sql = 'SELECT b.*, a.code AS account_code
             FROM rateb_bank_accounts b
             JOIN rateb_chart_of_accounts a ON a.id = b.chart_account_id
             WHERE b.company_id = :cid AND b.is_active = 1
             ORDER BY b.is_default DESC, b.name';
        $params = ['cid' => $companyId];
        [$sql, $params] = $this->scopeBankAccountSql($sql, $params, 'b');
        $rows = (new JournalEntry())->query($sql, $params);
        foreach ($rows as &$row) {
            $row['book_balance'] = $this->chartAccountBalance($companyId, (int) $row['chart_account_id'], (float) ($row['opening_balance'] ?? 0));
        }
        unset($row);
        return $rows;
    }

    /** @return array{accounts: array<int, array<string, mixed>>, total_cash: float, petty_cash: float} */
    public function bankReconciliation(?int $companyId): array
    {
        return self::requestMemo('bankReconciliation:' . $this->coaMapKey($companyId), function () use ($companyId): array {
        $accounts = $this->listBankAccounts($companyId);
        foreach ($accounts as &$acc) {
            $bankId = (int) ($acc['id'] ?? 0);
            $acc['statement_balance'] = $this->bankStatementBalance($companyId, $bankId);
            $acc['unreconciled_count'] = $this->countUnreconciledStatementLines($companyId, $bankId);
        }
        unset($acc);
        $total = 0.0;
        foreach ($accounts as $acc) {
            $total += (float) ($acc['book_balance'] ?? 0);
        }
        $petty = $this->chartAccountBalance($companyId, $this->accountIdByCode($companyId, '1100') ?? 0, 0.0);
        return ['accounts' => $accounts, 'total_cash' => $total + $petty, 'petty_cash' => $petty];
        });
    }

    /** @return array<string, mixed>|null */
    public function bankAccountReconciliation(?int $companyId, int $bankAccountId): ?array
    {
        if ($companyId === null || $companyId < 1) {
            return null;
        }
        $bankSql = 'SELECT b.*, a.code AS account_code
             FROM rateb_bank_accounts b
             JOIN rateb_chart_of_accounts a ON a.id = b.chart_account_id
             WHERE b.id = :id AND b.company_id = :cid';
        $bankParams = ['id' => $bankAccountId, 'cid' => $companyId];
        [$bankSql, $bankParams] = $this->scopeBankAccountSql($bankSql, $bankParams, 'b');
        $bankSql .= ' LIMIT 1';
        $bank = (new JournalEntry())->queryOne($bankSql, $bankParams);
        if (!$bank) {
            return null;
        }
        $coaId = (int) ($bank['chart_account_id'] ?? 0);
        $bookBalance = $this->chartAccountBalance($companyId, $coaId, (float) ($bank['opening_balance'] ?? 0));
        $bookSql = 'SELECT e.id, e.entry_no, e.entry_date, e.description, e.source_type,
                    SUM(l.debit) AS debit, SUM(l.credit) AS credit
             FROM rateb_journal_lines l
             JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
             WHERE l.account_id = :aid AND e.company_id = :cid
             GROUP BY e.id
             ORDER BY e.entry_date DESC, e.id DESC
             LIMIT 100';
        $bookParams = ['posted' => 'posted', 'aid' => $coaId, 'cid' => $companyId];
        [$bookSql, $bookParams] = $this->scopeJournalLineSql($bookSql, $bookParams, 'l', 'e');
        $bookLines = (new JournalEntry())->query($bookSql, $bookParams);
        $statementLines = $this->listBankStatementLines($companyId, $bankAccountId);
        $statementBalance = $this->bankStatementBalance($companyId, $bankAccountId);
        return [
            'bank' => $bank,
            'book_balance' => $bookBalance,
            'statement_balance' => $statementBalance,
            'difference' => $bookBalance - $statementBalance,
            'book_lines' => $bookLines,
            'statement_lines' => $statementLines,
        ];
    }

    /** @return array{imported:int, batch:string} */
    public function importBankStatementCsv(?int $companyId, int $bankAccountId, string $csv): array
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null) {
            return ['imported' => 0, 'batch' => ''];
        }
        $bankLookupSql = 'SELECT b.id FROM rateb_bank_accounts b WHERE b.id = :id AND b.company_id = :cid';
        $bankLookupParams = ['id' => $bankAccountId, 'cid' => $companyId];
        [$bankLookupSql, $bankLookupParams] = $this->scopeBankAccountSql($bankLookupSql, $bankLookupParams, 'b');
        $bankLookupSql .= ' LIMIT 1';
        $bank = (new JournalEntry())->queryOne($bankLookupSql, $bankLookupParams);
        if (!$bank) {
            return ['imported' => 0, 'batch' => ''];
        }
        $batch = 'IMP-' . date('Ymd-His');
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_bank_statement_lines
             (company_id, bank_account_id, line_date, description, amount, reference_no, import_batch)
             VALUES (:cid, :bid, :dt, :desc, :amt, :ref, :batch)'
        );
        $imported = 0;
        foreach (preg_split('/\R/', $csv) ?: [] as $i => $line) {
            $line = trim($line);
            if ($line === '' || ($i === 0 && stripos($line, 'date') !== false)) {
                continue;
            }
            $parts = str_getcsv($line);
            if (count($parts) < 3) {
                continue;
            }
            $date = trim((string) $parts[0]);
            $desc = trim((string) ($parts[1] ?? ''));
            $amount = (float) str_replace(',', '', (string) ($parts[2] ?? '0'));
            $ref = trim((string) ($parts[3] ?? ''));
            if ($date === '' || $amount == 0.0) {
                continue;
            }
            $stmt->execute([
                'cid' => $companyId,
                'bid' => $bankAccountId,
                'dt' => $date,
                'desc' => $desc !== '' ? $desc : '—',
                'amt' => $amount,
                'ref' => $ref !== '' ? $ref : null,
                'batch' => $batch,
            ]);
            $imported++;
        }
        return ['imported' => $imported, 'batch' => $batch];
    }

    public function markStatementLineReconciled(int $lineId, ?int $companyId, ?int $journalEntryId = null): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_bank_statement_lines WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $lineId, 'cid' => $companyId]
        );
        if (!$row) {
            return false;
        }
        (new JournalEntry())->query(
            'UPDATE rateb_bank_statement_lines SET is_reconciled = 1, journal_entry_id = :jid WHERE id = :id',
            ['jid' => $journalEntryId, 'id' => $lineId]
        );
        return true;
    }

    /** @param array<string, mixed> $data */
    public function postSupplierPayment(?int $companyId, array $data, ?int $userId): ?int
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null) {
            return null;
        }
        $this->ensureDefaultAccounts($companyId);
        $amount = (float) ($data['amount'] ?? 0);
        $poId = (int) ($data['purchase_order_id'] ?? 0);
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $paymentDate = (string) ($data['payment_date'] ?? date('Y-m-d'));
        $dueDate = trim((string) ($data['due_date'] ?? ''));
        $paymentMethod = (string) ($data['payment_method'] ?? 'bank');
        if (!in_array($paymentMethod, ['bank', 'cheque', 'cash', 'bank_transfer'], true)) {
            $paymentMethod = 'bank';
        }
        if ($paymentMethod === 'bank_transfer') {
            $paymentMethod = 'bank';
        }
        if ($amount <= 0 || !$this->isPeriodOpen($companyId, $paymentDate)) {
            return null;
        }
        if ($poId > 0) {
            $payable = $this->purchaseOrderPayable($companyId, $poId);
            if ($payable === null || $amount > (float) $payable['due'] + 0.01) {
                return null;
            }
            if ($dueDate === '' && !empty($payable['due_date'])) {
                $dueDate = (string) $payable['due_date'];
            }
            if ((int) ($data['supplier_id'] ?? 0) < 1) {
                $data['supplier_id'] = (int) ($payable['supplier_id'] ?? 0);
            }
        }
        $ap = $this->accountIdByCode($companyId, '2100');
        if (!$ap) {
            return null;
        }
        $creditAccountId = $ap;
        $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
        if ($paymentMethod === 'cash') {
            $cashId = $this->accountIdByCode($companyId, '1100');
            if ($cashId) {
                $creditAccountId = $cashId;
            }
            $bankAccountId = 0;
        } elseif ($bankAccountId > 0) {
            $bank = (new JournalEntry())->queryOne(
                'SELECT chart_account_id FROM rateb_bank_accounts WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $bankAccountId, 'cid' => $companyId]
            );
            if ($bank) {
                $creditAccountId = (int) $bank['chart_account_id'];
            }
        } else {
            $cashId = $this->accountIdByCode($companyId, '1100');
            if ($cashId) {
                $creditAccountId = $cashId;
            }
        }
        $pdo = Database::connection();
        $paymentNo = $this->nextSupplierPaymentNo($companyId);
        $supplierId = (int) ($data['supplier_id'] ?? 0) ?: null;
        $entryId = $this->createPostedEntry(
            $companyId,
            'supplier_payment',
            null,
            [
                ['account_id' => $ap, 'debit' => $amount, 'credit' => 0, 'memo' => 'Supplier payment'],
                ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $amount, 'memo' => 'Payment'],
            ],
            'Supplier payment ' . $paymentNo,
            'سداد مورد ' . $paymentNo,
            $paymentDate
        );
        if ($entryId === null) {
            return null;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_supplier_payments
             (company_id, supplier_id, purchase_order_id, invoice_id, payment_no, payment_date, due_date, amount, bank_account_id,
              payment_method, reference_no, journal_entry_id, status, notes, created_by, posted_at)
             VALUES (:cid, :sid, :poid, :iid, :no, :dt, :due, :amt, :bid, :meth, :ref, :jid, :st, :notes, :uid, NOW())'
        );
        $stmt->execute([
            'cid' => $companyId,
            'sid' => $supplierId,
            'poid' => $poId > 0 ? $poId : null,
            'iid' => $invoiceId > 0 ? $invoiceId : null,
            'no' => $paymentNo,
            'dt' => $paymentDate,
            'due' => $dueDate !== '' ? $dueDate : null,
            'amt' => $amount,
            'bid' => $bankAccountId > 0 ? $bankAccountId : null,
            'meth' => $paymentMethod,
            'ref' => trim((string) ($data['reference_no'] ?? '')) ?: null,
            'jid' => $entryId,
            'st' => 'posted',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'uid' => $userId,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        (new JournalEntry())->update($entryId, ['source_id' => $paymentId]);
        return $paymentId;
    }

    public function createYearEndClosingEntry(?int $companyId, string $startDate, string $endDate): ?int
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null || $startDate === '' || $endDate === '') {
            return null;
        }
        $this->ensureDefaultAccounts($companyId);
        $retainedId = $this->accountIdByCode($companyId, '3100');
        if (!$retainedId) {
            return null;
        }
        $pl = $this->profitAndLoss($companyId, $startDate, $endDate);
        $lines = [];
        $revenueClose = 0.0;
        $expenseClose = 0.0;
        foreach ($pl['lines'] as $line) {
            $accountId = (int) ($line['id'] ?? 0);
            $dr = (float) ($line['total_debit'] ?? 0);
            $cr = (float) ($line['total_credit'] ?? 0);
            if (($line['account_type'] ?? '') === 'revenue') {
                $bal = $cr - $dr;
                if ($bal > 0.01) {
                    $lines[] = ['account_id' => $accountId, 'debit' => $bal, 'credit' => 0, 'memo' => 'Close revenue'];
                    $revenueClose += $bal;
                }
            } elseif (($line['account_type'] ?? '') === 'expense') {
                $bal = $dr - $cr;
                if ($bal > 0.01) {
                    $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => $bal, 'memo' => 'Close expense'];
                    $expenseClose += $bal;
                }
            }
        }
        if ($revenueClose > 0.01) {
            $lines[] = ['account_id' => $retainedId, 'debit' => 0, 'credit' => $revenueClose, 'memo' => 'Revenue close'];
        }
        if ($expenseClose > 0.01) {
            $lines[] = ['account_id' => $retainedId, 'debit' => $expenseClose, 'credit' => 0, 'memo' => 'Expense close'];
        }
        if ($lines === []) {
            return null;
        }
        return $this->createPostedEntry(
            $companyId,
            'year_end_close',
            null,
            $lines,
            'Year-end closing ' . substr($endDate, 0, 4),
            'قيد إقفال سنة ' . substr($endDate, 0, 4),
            $endDate
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listBankStatementLines(?int $companyId, int $bankAccountId): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        return (new JournalEntry())->query(
            'SELECT * FROM rateb_bank_statement_lines
             WHERE company_id = :cid AND bank_account_id = :bid
             ORDER BY line_date DESC, id DESC LIMIT 200',
            ['cid' => $companyId, 'bid' => $bankAccountId]
        );
    }

    private function bankStatementBalance(?int $companyId, int $bankAccountId): float
    {
        if ($companyId === null || $companyId < 1) {
            return 0.0;
        }
        $bank = (new JournalEntry())->queryOne(
            'SELECT opening_balance FROM rateb_bank_accounts WHERE id = :id AND company_id = :cid',
            ['id' => $bankAccountId, 'cid' => $companyId]
        );
        $row = (new JournalEntry())->queryOne(
            'SELECT COALESCE(SUM(amount), 0) AS t FROM rateb_bank_statement_lines WHERE bank_account_id = :bid AND company_id = :cid',
            ['bid' => $bankAccountId, 'cid' => $companyId]
        );
        return (float) ($bank['opening_balance'] ?? 0) + (float) ($row['t'] ?? 0);
    }

    private function countUnreconciledStatementLines(?int $companyId, int $bankAccountId): int
    {
        if ($companyId === null || $companyId < 1) {
            return 0;
        }
        $row = (new JournalEntry())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bank_statement_lines
             WHERE company_id = :cid AND bank_account_id = :bid AND is_reconciled = 0',
            ['cid' => $companyId, 'bid' => $bankAccountId]
        );
        return (int) ($row['c'] ?? 0);
    }

    private function nextSupplierPaymentNo(?int $companyId): string
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_supplier_payments WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;
        return 'SP-' . ($companyId ?? '0') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** @param array<int, array{account_id:int,amount:float}> $lines */
    public function saveBudgetLines(?int $companyId, int $fiscalYear, array $lines): void
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null || $fiscalYear < 2000) {
            return;
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM rateb_budget_lines WHERE company_id = :cid AND fiscal_year = :yr')
            ->execute(['cid' => $companyId, 'yr' => $fiscalYear]);
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_budget_lines (company_id, fiscal_year, account_id, amount) VALUES (:cid, :yr, :aid, :amt)'
        );
        foreach ($lines as $line) {
            $aid = (int) ($line['account_id'] ?? 0);
            $amt = (float) ($line['amount'] ?? 0);
            if ($aid < 1 || $amt <= 0) {
                continue;
            }
            $stmt->execute(['cid' => $companyId, 'yr' => $fiscalYear, 'aid' => $aid, 'amt' => $amt]);
        }
    }

    /** @return array{year:int, lines: array<int, array<string, mixed>>, totals: array{budget:float,actual:float,variance:float}} */
    public function budgetVsActual(?int $companyId, int $fiscalYear): array
    {
        if ($companyId === null || $companyId < 1) {
            return ['year' => $fiscalYear, 'lines' => [], 'totals' => ['budget' => 0, 'actual' => 0, 'variance' => 0]];
        }
        $from = $fiscalYear . '-01-01';
        $to = $fiscalYear . '-12-31';
        $sql = 'SELECT a.id, a.code, a.name, a.name_ar, a.account_type,
                       COALESCE(b.amount, 0) AS budget_amount,
                       COALESCE(SUM(l.debit), 0) AS total_debit,
                       COALESCE(SUM(l.credit), 0) AS total_credit
                FROM rateb_chart_of_accounts a
                LEFT JOIN rateb_budget_lines b ON b.account_id = a.id AND b.company_id = :cid_b AND b.fiscal_year = :yr
                LEFT JOIN rateb_journal_lines l ON l.account_id = a.id
                LEFT JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
                    AND e.entry_date >= :from AND e.entry_date <= :to
                WHERE a.company_id = :cid AND a.is_active = 1
                  AND a.account_type IN (\'revenue\', \'expense\')
                  AND (b.amount IS NOT NULL OR l.id IS NOT NULL)
                GROUP BY a.id, b.amount
                HAVING budget_amount > 0 OR total_debit > 0 OR total_credit > 0
                ORDER BY a.code';
        $params = [
            'cid' => $companyId, 'cid_b' => $companyId, 'yr' => $fiscalYear, 'posted' => 'posted', 'from' => $from, 'to' => $to,
        ];
        [$sql, $params] = $this->scopeJournalLineSql($sql, $params, 'l', 'e');
        $rows = (new ChartOfAccount())->query($sql, $params);
        $budgetTotal = 0.0;
        $actualTotal = 0.0;
        foreach ($rows as &$row) {
            $dr = (float) ($row['total_debit'] ?? 0);
            $cr = (float) ($row['total_credit'] ?? 0);
            $budget = (float) ($row['budget_amount'] ?? 0);
            if (($row['account_type'] ?? '') === 'revenue') {
                $actual = $cr - $dr;
            } else {
                $actual = $dr - $cr;
            }
            $row['actual_amount'] = $actual;
            $row['variance'] = $budget - $actual;
            $budgetTotal += $budget;
            $actualTotal += $actual;
        }
        unset($row);
        return [
            'year' => $fiscalYear,
            'lines' => $rows,
            'totals' => [
                'budget' => $budgetTotal,
                'actual' => $actualTotal,
                'variance' => $budgetTotal - $actualTotal,
            ],
        ];
    }

    /** @return array<string, float|int> */
    public function cfoMetrics(?int $companyId): array
    {
        return self::requestMemo('cfoMetrics:' . $this->coaMapKey($companyId), function () use ($companyId): array {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        $year = (int) date('Y');
        $pl = $this->profitAndLoss($companyId, $year . '-01-01', date('Y-m-d'));
        $arId = $this->accountIdByCode($companyId, '1200');
        $apId = $this->accountIdByCode($companyId, '2100');
        $arBal = $arId ? $this->chartAccountBalance($companyId, $arId, 0.0) : 0.0;
        $apBal = $apId ? abs($this->chartAccountBalance($companyId, $apId, 0.0)) : 0.0;
        $bank = $this->bankReconciliation($companyId);
        $arData = $this->accountsReceivable($companyId);
        $apData = $this->accountsPayable($companyId);
        $revenue = max(0.01, (float) ($pl['revenue'] ?? 0));
        $summary = $this->financialSummary($companyId);
        $procurement = max(0.01, (float) ($summary['procurement_received'] ?? 0));
        $dso = ((float) ($arData['total_open'] ?? 0)) / ($revenue / 365);
        $dpo = ((float) ($apData['total_open'] ?? 0)) / ($procurement / 365);
        return [
            'cash_position' => (float) ($bank['total_cash'] ?? 0),
            'ar_balance' => $arBal,
            'ap_balance' => $apBal,
            'ar_open' => (float) ($arData['total_open'] ?? 0),
            'ap_open' => (float) ($apData['total_open'] ?? 0),
            'revenue_ytd' => (float) ($pl['revenue'] ?? 0),
            'expenses_ytd' => (float) ($pl['expenses'] ?? 0),
            'net_margin' => (float) ($pl['net'] ?? 0),
            'dso_days' => round($dso, 1),
            'dpo_days' => round($dpo, 1),
            'procurement_ytd' => (float) ($summary['procurement_received'] ?? 0),
        ];
        });
    }

    /**
     * General account statement (كشف حساب عام) with opening balance and running totals.
     *
     * @return array<string, mixed>
     */
    public function accountStatement(?int $companyId, int $accountId, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($companyId === null || $companyId < 1 || $accountId < 1) {
            return ['account' => null, 'lines' => [], 'opening' => 0.0, 'closing' => 0.0, 'total_debit' => 0.0, 'total_credit' => 0.0];
        }
        $account = (new ChartOfAccount())->queryOne(
            'SELECT * FROM rateb_chart_of_accounts WHERE id = :id AND company_id <=> :cid LIMIT 1',
            ['id' => $accountId, 'cid' => $companyId]
        );
        if (!$account) {
            return ['account' => null, 'lines' => [], 'opening' => 0.0, 'closing' => 0.0, 'total_debit' => 0.0, 'total_credit' => 0.0];
        }

        $opening = 0.0;
        if ($fromDate) {
            $openRow = $this->journalScopedQueryOne(
                'SELECT COALESCE(SUM(l.debit), 0) AS dr, COALESCE(SUM(l.credit), 0) AS cr
                 FROM rateb_journal_lines l
                 JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
                 WHERE l.account_id = :aid AND e.company_id = :cid AND e.entry_date < :from',
                ['posted' => 'posted', 'aid' => $accountId, 'cid' => $companyId, 'from' => $fromDate]
            );
            $opening = $openRow ? (float) (($openRow['dr'] ?? 0) - ($openRow['cr'] ?? 0)) : 0.0;
        }

        $sql = 'SELECT e.entry_no, e.entry_date, e.description, e.description_ar, e.source_type,
                       l.debit, l.credit, l.memo
                FROM rateb_journal_lines l
                JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
                WHERE l.account_id = :aid AND e.company_id = :cid';
        $params = ['posted' => 'posted', 'aid' => $accountId, 'cid' => $companyId];
        if ($fromDate) {
            $sql .= ' AND e.entry_date >= :from';
            $params['from'] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND e.entry_date <= :to';
            $params['to'] = $toDate;
        }
        $sql .= ' ORDER BY e.entry_date, e.id, l.id';
        $rawLines = $this->journalScopedQuery($sql, $params, 'e');

        $balance = $opening;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $lines = [];
        foreach ($rawLines as $row) {
            $dr = (float) ($row['debit'] ?? 0);
            $cr = (float) ($row['credit'] ?? 0);
            $totalDebit += $dr;
            $totalCredit += $cr;
            $balance += $dr - $cr;
            $row['balance'] = round($balance, 2);
            $lines[] = $row;
        }

        return [
            'account' => $account,
            'lines' => $lines,
            'opening' => round($opening, 2),
            'closing' => round($balance, 2),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    /**
     * Partner subsidiary ledger (كشف حساب مساعد للشركاء) — equity partner capital accounts.
     *
     * @return array<string, mixed>
     */
    public function partnersSubsidiaryLedger(?int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($companyId === null || $companyId < 1) {
            return ['accounts' => [], 'from' => $fromDate, 'to' => $toDate];
        }
        $parent = (new ChartOfAccount())->queryOne(
            'SELECT id FROM rateb_chart_of_accounts WHERE company_id <=> :cid AND code = :code LIMIT 1',
            ['cid' => $companyId, 'code' => '3200']
        );
        $parentId = (int) ($parent['id'] ?? 0);
        $sql = 'SELECT * FROM rateb_chart_of_accounts
                WHERE company_id <=> :cid AND is_active = 1 AND account_type = :eq';
        $params = ['cid' => $companyId, 'eq' => 'equity'];
        if ($parentId > 0) {
            $sql .= ' AND (parent_id = :pid OR code LIKE :pfx)';
            $params['pid'] = $parentId;
            $params['pfx'] = '321%';
        } else {
            $sql .= ' AND code LIKE :pfx';
            $params['pfx'] = '321%';
        }
        $sql .= ' ORDER BY code';
        $partnerAccounts = (new ChartOfAccount())->query($sql, $params);

        $accounts = [];
        foreach ($partnerAccounts as $acct) {
            $stmt = $this->accountStatement($companyId, (int) $acct['id'], $fromDate, $toDate);
            if ($stmt['lines'] === [] && abs($stmt['closing']) < 0.01 && abs($stmt['opening']) < 0.01) {
                continue;
            }
            $accounts[] = [
                'account' => $acct,
                'opening' => $stmt['opening'],
                'closing' => $stmt['closing'],
                'total_debit' => $stmt['total_debit'],
                'total_credit' => $stmt['total_credit'],
                'lines' => $stmt['lines'],
            ];
        }

        return ['accounts' => $accounts, 'from' => $fromDate, 'to' => $toDate];
    }

    /** @return array<int, array<string, mixed>> */
    public function exportJournalEntries(?int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        $sql = 'SELECT e.entry_no, e.entry_date, e.description, e.description_ar, e.status, e.source_type,
                       a.code, a.name, a.name_ar, l.debit, l.credit, l.memo
                FROM rateb_journal_entries e
                JOIN rateb_journal_lines l ON l.journal_entry_id = e.id
                JOIN rateb_chart_of_accounts a ON a.id = l.account_id
                WHERE e.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($fromDate) {
            $sql .= ' AND e.entry_date >= :from';
            $params['from'] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND e.entry_date <= :to';
            $params['to'] = $toDate;
        }
        $sql .= ' ORDER BY e.entry_date, e.id, l.id';
        return $this->journalScopedQuery($sql, $params, 'e');
    }

    private function resolveCashAccountId(?int $companyId, array $voucher): ?int
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null) {
            return null;
        }
        $bankId = (int) ($voucher['bank_account_id'] ?? 0);
        if ($bankId > 0) {
            $ba = (new JournalEntry())->queryOne(
                'SELECT chart_account_id FROM rateb_bank_accounts WHERE id = :id AND company_id = :cid AND is_active = 1',
                ['id' => $bankId, 'cid' => $companyId]
            );
            if ($ba) {
                $acctId = (int) $ba['chart_account_id'];
                if ($this->accountUsableForCompany($acctId, $companyId)) {
                    return $acctId;
                }
            }
        }
        return $this->accountIdByCode($companyId, '1100');
    }

    /** @param array<string, mixed> $voucher */
    private function resolveCashVoucherCostCenter(?int $companyId, array $voucher): ?int
    {
        $customerId = (int) ($voucher['customer_id'] ?? 0);
        if ($customerId < 1 || $companyId === null || $companyId < 1) {
            return null;
        }
        $row = (new JournalEntry())->queryOne(
            'SELECT cost_center_id FROM rateb_customers WHERE id = :id AND company_id = :cid AND is_active = 1 LIMIT 1',
            ['id' => $customerId, 'cid' => $companyId]
        );
        $ccId = (int) ($row['cost_center_id'] ?? 0);
        return $ccId > 0 ? $ccId : null;
    }

    /** @param array<string, mixed> $voucher */
    private function cashVoucherCustomerMemo(?int $companyId, array $voucher): string
    {
        $customerId = (int) ($voucher['customer_id'] ?? 0);
        if ($customerId < 1 || $companyId === null || $companyId < 1) {
            return '';
        }
        $row = (new JournalEntry())->queryOne(
            'SELECT name, name_ar FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $customerId, 'cid' => $companyId]
        );
        if (!$row) {
            return '';
        }
        $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? (string) $row['name_ar'] : (string) ($row['name'] ?? '');
        return $name !== '' ? ' — ' . $name : '';
    }

    private function chartAccountBalance(?int $companyId, int $accountId, float $opening): float
    {
        if ($accountId < 1) {
            return $opening;
        }
        $row = $this->journalScopedQueryOne(
            'SELECT COALESCE(SUM(l.debit), 0) AS dr, COALESCE(SUM(l.credit), 0) AS cr
             FROM rateb_journal_lines l
             JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
             WHERE l.account_id = :aid AND e.company_id <=> :cid',
            ['posted' => 'posted', 'aid' => $accountId, 'cid' => $companyId]
        );
        $dr = (float) ($row['dr'] ?? 0);
        $cr = (float) ($row['cr'] ?? 0);
        return $opening + $dr - $cr;
    }

    private function nextBankAccountCode(?int $companyId): string
    {
        $row = (new ChartOfAccount())->queryOne(
            'SELECT code FROM rateb_chart_of_accounts
             WHERE company_id = :cid AND code LIKE :pfx
             ORDER BY code DESC LIMIT 1',
            ['cid' => $companyId, 'pfx' => '111%']
        );
        if (!$row) {
            return '1110';
        }
        $num = (int) preg_replace('/\D/', '', (string) $row['code']);
        return (string) ($num + 1);
    }

    /** @param array<string, mixed> $voucher */
    public function createCashVoucherDraft(?int $companyId, array $data, ?int $createdBy): int
    {
        $this->ensureDefaultAccounts($companyId);
        $pdo = Database::connection();
        $companyId = $this->normalizeCompanyId($companyId);
        $branchId = $this->resolveBranchId($companyId, isset($data['branch_id']) ? (int) $data['branch_id'] : null);
        $no = $this->nextVoucherNo($companyId, (string) ($data['voucher_type'] ?? 'receipt'));
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_cash_vouchers
             (company_id, branch_id, voucher_no, voucher_type, voucher_date, amount, party_name, customer_id, description, description_ar, counter_account_id, bank_account_id, status, created_by)
             VALUES (:cid, :bid, :no, :type, :dt, :amt, :party, :cust, :desc, :desc_ar, :acct, :bank, :st, :uid)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId > 0 ? $branchId : null,
            'no' => $no,
            'type' => $data['voucher_type'],
            'dt' => $data['voucher_date'],
            'amt' => $data['amount'],
            'party' => $data['party_name'] ?? null,
            'cust' => isset($data['customer_id']) && (int) $data['customer_id'] > 0 ? (int) $data['customer_id'] : null,
            'desc' => $data['description'],
            'desc_ar' => $data['description_ar'] ?? null,
            'acct' => $data['counter_account_id'],
            'bank' => isset($data['bank_account_id']) && (int) $data['bank_account_id'] > 0 ? (int) $data['bank_account_id'] : null,
            'st' => 'draft',
            'uid' => $createdBy,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function postCashVoucher(int $voucherId, ?int $companyId): bool
    {
        return $this->postCashVoucherReason($voucherId, $companyId) === null;
    }

    /** Post cash voucher draft; returns null on success or a lang key for the failure reason. */
    public function postCashVoucherReason(int $voucherId, ?int $companyId, bool $fromOversight = false): ?string
    {
        $this->lastVoucherPostDetail = '';
        $this->ensureApprovalSubmitColumns();
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null || $companyId < 1) {
            return 'voucher_post_failed';
        }
        $v = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $voucherId, 'cid' => $companyId]
        );
        if (!$v || ($v['status'] ?? '') !== 'draft') {
            return 'voucher_post_not_draft';
        }
        if (!$this->isSubmittedForApproval($v)) {
            if (!$fromOversight) {
                return 'not_submitted_for_approval';
            }
            $this->stampCashVoucherSubmittedForApproval($voucherId);
        }
        $voucherDate = (string) ($v['voucher_date'] ?? date('Y-m-d'));
        try {
            $this->ensureFiscalPeriodForDate($companyId, $voucherDate);
        } catch (\Throwable $e) {
            $this->lastVoucherPostDetail = DatabaseErrorService::technicalDetail($e);
        }
        if (!$this->isPeriodOpen($companyId, $voucherDate)) {
            return 'fiscal_period_closed_block';
        }
        $amount = (float) ($v['amount'] ?? 0);
        if ($amount <= 0) {
            return 'voucher_no_amount';
        }
        try {
            $this->ensureDefaultAccounts($companyId);
        } catch (\Throwable $e) {
            $this->lastVoucherPostDetail = DatabaseErrorService::technicalDetail($e);
            return 'voucher_no_cash_account';
        }
        $cash = $this->resolveCashAccountId($companyId, $v);
        if (!$cash || !$this->accountUsableForCompany($cash, $companyId)) {
            $cash = $this->ensureCompanyCoaCode($companyId, '1100') ?? $this->accountIdByCode($companyId, '1100');
        }
        $counter = (int) ($v['counter_account_id'] ?? 0);
        if (!$cash || !$this->accountUsableForCompany($cash, $companyId)) {
            $this->lastVoucherPostDetail = 'cash account 1100 not found for company ' . $companyId;
            return 'voucher_no_cash_account';
        }
        if ($counter < 1 || !$this->accountUsableForCompany($counter, $companyId)) {
            return 'voucher_no_counter_account';
        }
        $type = (string) ($v['voucher_type'] ?? 'receipt');
        $ccId = $this->resolveCashVoucherCostCenter($companyId, $v);
        $customerMemo = $this->cashVoucherCustomerMemo($companyId, $v);
        if ($type === 'receipt') {
            $lines = [
                ['account_id' => $cash, 'debit' => $amount, 'credit' => 0, 'memo' => 'Receipt' . $customerMemo, 'cost_center_id' => $ccId],
                ['account_id' => $counter, 'debit' => 0, 'credit' => $amount, 'memo' => 'Receipt offset' . $customerMemo, 'cost_center_id' => $ccId],
            ];
        } else {
            $lines = [
                ['account_id' => $counter, 'debit' => $amount, 'credit' => 0, 'memo' => 'Payment' . $customerMemo, 'cost_center_id' => $ccId],
                ['account_id' => $cash, 'debit' => 0, 'credit' => $amount, 'memo' => 'Cash out' . $customerMemo, 'cost_center_id' => $ccId],
            ];
        }
        try {
            $entryId = $this->createPostedEntry(
                $companyId,
                'cash_voucher',
                $voucherId,
                $lines,
                (string) $v['description'],
                (string) ($v['description_ar'] ?? $v['description']),
                $voucherDate
            );
        } catch (\Throwable $e) {
            $this->lastVoucherPostDetail = DatabaseErrorService::technicalDetail($e);
            return 'voucher_post_failed';
        }
        if ($entryId === null || $entryId < 1) {
            if (!$this->isBalanced($lines)) {
                $this->lastVoucherPostDetail = 'journal lines not balanced';
            } elseif (!$this->isPeriodOpen($companyId, $voucherDate)) {
                $this->lastVoucherPostDetail = 'fiscal period closed for ' . $voucherDate;
            } else {
                $this->lastVoucherPostDetail = 'createPostedEntry returned empty';
            }
            return 'voucher_post_failed';
        }
        try {
            Database::connection()->prepare(
                'UPDATE rateb_cash_vouchers SET status = :st, journal_entry_id = :jid, posted_at = NOW() WHERE id = :id'
            )->execute(['st' => 'posted', 'jid' => $entryId, 'id' => $voucherId]);
        } catch (\PDOException $e) {
            $this->lastVoucherPostDetail = DatabaseErrorService::technicalDetail($e);
            return 'voucher_post_failed';
        }
        return null;
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
        if (!$this->isPeriodOpen($companyId, (string) ($v['voucher_date'] ?? date('Y-m-d')))) {
            return false;
        }
        $jid = (int) ($v['journal_entry_id'] ?? 0);
        if ($jid > 0) {
            $this->voidPostedEntry($jid, $companyId, ['manual', 'cash_voucher']);
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

    private function ensureJournalSourceTypeEnum(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $pdo = Database::connection();
            if ($pdo->inTransaction()) {
                return;
            }
            $stmt = $pdo->query("SHOW COLUMNS FROM rateb_journal_entries LIKE 'source_type'");
            $row = $stmt !== false ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
            $columnType = is_array($row) ? strtolower((string) ($row['Type'] ?? '')) : '';
            if ($columnType !== '' && str_contains($columnType, 'pos_sale_revenue')) {
                return;
            }
            $pdo->exec(
                "ALTER TABLE rateb_journal_entries MODIFY source_type ENUM(
                    'manual','invoice','payment','purchase_order','subscription',
                    'cash_voucher','stock_movement','purchase_invoice',
                    'supplier_payment','year_end_close','branch_transfer',
                    'pos_sale_revenue','pos_sale_cogs',
                    'pos_return_revenue','pos_return_cogs',
                    'pos_exchange_revenue','pos_exchange_cogs'
                ) NOT NULL DEFAULT 'manual'"
            );
        } catch (\Throwable $e) {
            // Host may block ALTER; migration 115 fixes via ERP migrate.
        }
    }

    private function journalLinesHaveCostCenter(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $has = Database::liveTableHasColumn('rateb_journal_lines', 'cost_center_id');

        return $has;
    }

    private function accountBelongsToCompany(int $accountId, int $companyId): bool
    {
        return $this->accountUsableForCompany($accountId, $companyId);
    }

    public function ensureFiscalPeriodForDate(?int $companyId, string $date): void
    {
        if ($companyId === null || $companyId < 1 || strlen($date) < 4) {
            return;
        }
        $year = (int) substr($date, 0, 4);
        if ($year < 2000 || $year > 2100) {
            return;
        }
        $start = $year . '-01-01';
        $end = $year . '-12-31';
        $exists = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_fiscal_periods WHERE company_id = :cid AND :dt BETWEEN start_date AND end_date LIMIT 1',
            ['cid' => $companyId, 'dt' => $date]
        );
        if ($exists) {
            return;
        }
        Database::connection()->prepare(
            'INSERT INTO rateb_fiscal_periods (company_id, name, start_date, end_date, status) VALUES (:cid, :n, :s, :e, :st)'
        )->execute([
            'cid' => $companyId,
            'n' => (string) $year,
            's' => $start,
            'e' => $end,
            'st' => 'open',
        ]);
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

    public function deleteManualDraft(int $entryId, ?int $companyId): bool
    {
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry || !$this->canDeleteManualJournal($entry)) {
            return false;
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM rateb_journal_lines WHERE journal_entry_id = :id')->execute(['id' => $entryId]);
        return (new JournalEntry())->delete($entryId);
    }

    public function rejectManualDraft(int $entryId, ?int $companyId, ?string $reason, ?int $userId): bool
    {
        $this->ensureAccountingStatusEnums();
        $this->ensureAccountingRejectColumns();
        $entry = $this->findEntryForCompany($entryId, $companyId);
        if (!$entry || ($entry['source_type'] ?? '') !== 'manual' || ($entry['status'] ?? '') !== 'draft') {
            return false;
        }
        $reason = $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null;
        (new JournalEntry())->update($entryId, [
            'status' => 'rejected',
            'reject_reason' => $reason,
            'rejected_at' => date('Y-m-d H:i:s'),
            'rejected_by' => $userId,
        ]);
        return true;
    }

    /** @param array<int, int> $ids */
    public function bulkRejectManualDrafts(array $ids, ?int $companyId, ?string $reason, ?int $userId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->rejectManualDraft((int) $id, $companyId, $reason, $userId)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<int, int> $ids */
    public function bulkDeleteManualDrafts(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->deleteManualDraft((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<int, int> $ids */
    public function bulkPostDraftEntries(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->postDraftEntry((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<int, int> $ids */
    public function bulkVoidPostedManual(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->voidPostedEntry((int) $id, $companyId, ['manual'])) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<string, mixed> $data */
    public function updateCashVoucherDraft(int $voucherId, ?int $companyId, array $data): bool
    {
        $v = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $voucherId, 'cid' => $companyId]
        );
        if (!$v || !$this->isCashVoucherEditable($v)) {
            return false;
        }
        if (in_array((string) ($v['status'] ?? ''), ['rejected', 'void'], true)) {
            Database::connection()->prepare(
                'UPDATE rateb_cash_vouchers SET status = :st, reject_reason = NULL, rejected_at = NULL, rejected_by = NULL WHERE id = :id'
            )->execute(['st' => 'draft', 'id' => $voucherId]);
        }
        $amount = (float) ($data['amount'] ?? 0);
        $counter = (int) ($data['counter_account_id'] ?? 0);
        if ($amount <= 0 || $counter < 1) {
            return false;
        }
        $type = (string) ($data['voucher_type'] ?? 'receipt');
        if (!in_array($type, ['receipt', 'payment'], true)) {
            $type = 'receipt';
        }
        Database::connection()->prepare(
            'UPDATE rateb_cash_vouchers SET voucher_type = :type, voucher_date = :dt, amount = :amt,
             party_name = :party, customer_id = :cust, description = :desc, description_ar = :desc_ar,
             counter_account_id = :acct, bank_account_id = :bank, branch_id = :bid, submitted_for_approval_at = NULL
             WHERE id = :id'
        )->execute([
            'type' => $type,
            'dt' => (string) ($data['voucher_date'] ?? date('Y-m-d')),
            'amt' => $amount,
            'party' => trim((string) ($data['party_name'] ?? '')) ?: null,
            'cust' => isset($data['customer_id']) && (int) $data['customer_id'] > 0 ? (int) $data['customer_id'] : null,
            'desc' => trim((string) ($data['description'] ?? '')) ?: ($type === 'receipt' ? 'Cash receipt' : 'Cash payment'),
            'desc_ar' => trim((string) ($data['description_ar'] ?? '')) ?: null,
            'acct' => $counter,
            'bank' => isset($data['bank_account_id']) && (int) $data['bank_account_id'] > 0
                ? (int) $data['bank_account_id'] : null,
            'bid' => $this->resolveBranchId($companyId, isset($data['branch_id']) ? (int) $data['branch_id'] : null) ?: null,
            'id' => $voucherId,
        ]);
        return true;
    }

    public function deleteCashVoucherDraft(int $voucherId, ?int $companyId): bool
    {
        $v = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid AND status = :st LIMIT 1',
            ['id' => $voucherId, 'cid' => $companyId, 'st' => 'draft']
        );
        if (!$v) {
            return false;
        }
        return Database::connection()
            ->prepare('DELETE FROM rateb_cash_vouchers WHERE id = :id')
            ->execute(['id' => $voucherId]);
    }

    /** @param array<int, int> $ids */
    public function bulkDeleteCashVoucherDrafts(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->deleteCashVoucherDraft((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    public function rejectCashVoucherDraft(int $voucherId, ?int $companyId, ?string $reason, ?int $userId): bool
    {
        $this->ensureAccountingStatusEnums();
        $this->ensureAccountingRejectColumns();
        $v = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid AND status = :st LIMIT 1',
            ['id' => $voucherId, 'cid' => $companyId, 'st' => 'draft']
        );
        if (!$v) {
            return false;
        }
        $reason = $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null;
        Database::connection()->prepare(
            'UPDATE rateb_cash_vouchers SET status = :st, reject_reason = :reason, rejected_at = NOW(), rejected_by = :uid WHERE id = :id'
        )->execute(['st' => 'rejected', 'reason' => $reason, 'uid' => $userId, 'id' => $voucherId]);
        return true;
    }

    /** @param array<int, int> $ids */
    public function bulkRejectCashVoucherDrafts(array $ids, ?int $companyId, ?string $reason, ?int $userId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->rejectCashVoucherDraft((int) $id, $companyId, $reason, $userId)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<int, int> $ids */
    public function bulkVoidCashVouchers(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->voidCashVoucher((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<int, int> $ids */
    public function bulkPostCashVouchers(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->postCashVoucher((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<string, mixed> $data */
    public function updateBankAccount(int $bankId, ?int $companyId, array $data): bool
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_bank_accounts WHERE id = :id AND company_id = :cid AND is_active = 1 LIMIT 1',
            ['id' => $bankId, 'cid' => $companyId]
        );
        if (!$row) {
            return false;
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return false;
        }
        $pdo = Database::connection();
        if (!empty($data['is_default'])) {
            $pdo->prepare('UPDATE rateb_bank_accounts SET is_default = 0 WHERE company_id = :cid')->execute(['cid' => $companyId]);
        }
        $pdo->prepare(
            'UPDATE rateb_bank_accounts SET name = :name, bank_name = :bank, account_number = :acct_no, is_default = :def WHERE id = :id'
        )->execute([
            'name' => $name,
            'bank' => trim((string) ($data['bank_name'] ?? '')),
            'acct_no' => trim((string) ($data['account_number'] ?? '')),
            'def' => !empty($data['is_default']) ? 1 : 0,
            'id' => $bankId,
        ]);
        $coaId = (int) ($row['chart_account_id'] ?? 0);
        if ($coaId > 0) {
            (new ChartOfAccount())->update($coaId, [
                'name' => $name,
                'name_ar' => trim((string) ($data['name_ar'] ?? $name)),
            ]);
        }
        return true;
    }

    public function deactivateBankAccount(int $bankId, ?int $companyId): bool
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }
        $row = (new JournalEntry())->queryOne(
            'SELECT id, chart_account_id FROM rateb_bank_accounts WHERE id = :id AND company_id = :cid AND is_active = 1 LIMIT 1',
            ['id' => $bankId, 'cid' => $companyId]
        );
        if (!$row) {
            return false;
        }
        Database::connection()->prepare(
            'UPDATE rateb_bank_accounts SET is_active = 0, is_default = 0 WHERE id = :id'
        )->execute(['id' => $bankId]);
        $coaId = (int) ($row['chart_account_id'] ?? 0);
        if ($coaId > 0) {
            $this->deactivateChartAccount($coaId, $companyId);
        }
        return true;
    }

    /** @param array<int, int> $ids */
    public function bulkDeactivateBankAccounts(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->deactivateBankAccount((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    public function chartAccountHasPostedLines(int $accountId, ?int $companyId): bool
    {
        $companyId = $this->normalizeCompanyId($companyId);
        $row = (new JournalEntry())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_journal_lines l
             INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id
             WHERE l.account_id = :aid AND e.company_id <=> :cid AND e.status = :st',
            ['aid' => $accountId, 'cid' => $companyId, 'st' => 'posted']
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    public function deactivateChartAccount(int $accountId, ?int $companyId): bool
    {
        $companyId = $this->normalizeCompanyId($companyId);
        $row = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_chart_of_accounts WHERE id = :id AND company_id <=> :cid AND is_active = 1 LIMIT 1',
            ['id' => $accountId, 'cid' => $companyId]
        );
        if (!$row) {
            return false;
        }
        $child = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_chart_of_accounts WHERE parent_id = :pid AND is_active = 1 LIMIT 1',
            ['pid' => $accountId]
        );
        if ($child) {
            return false;
        }
        (new ChartOfAccount())->update($accountId, ['is_active' => 0]);
        return true;
    }

    public function destroyChartAccount(int $accountId, ?int $companyId): bool
    {
        $companyId = $this->normalizeCompanyId($companyId);
        $row = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_chart_of_accounts WHERE id = :id AND company_id <=> :cid LIMIT 1',
            ['id' => $accountId, 'cid' => $companyId]
        );
        if (!$row) {
            return false;
        }
        $child = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_chart_of_accounts WHERE parent_id = :pid LIMIT 1',
            ['pid' => $accountId]
        );
        if ($child) {
            return false;
        }
        if ($this->chartAccountHasPostedLines($accountId, $companyId)) {
            return false;
        }
        $bank = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_bank_accounts WHERE chart_account_id = :aid LIMIT 1',
            ['aid' => $accountId]
        );
        if ($bank) {
            return false;
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM rateb_journal_lines WHERE account_id = :aid')->execute(['aid' => $accountId]);
        return (new ChartOfAccount())->delete($accountId);
    }

    /** @param array<int, int> $ids */
    public function bulkDeactivateChartAccounts(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->deactivateChartAccount((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    public function createFiscalPeriod(?int $companyId, string $name, string $startDate, string $endDate): ?int
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($companyId === null || $name === '' || $startDate === '' || $endDate === '') {
            return null;
        }
        if ($startDate > $endDate) {
            return null;
        }
        $overlap = (new JournalEntry())->queryOne(
            'SELECT id FROM rateb_fiscal_periods
             WHERE company_id = :cid AND start_date <= :end AND end_date >= :start LIMIT 1',
            ['cid' => $companyId, 'start' => $startDate, 'end' => $endDate]
        );
        if ($overlap) {
            return null;
        }
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rateb_fiscal_periods (company_id, name, start_date, end_date, status) VALUES (:cid, :n, :s, :e, :st)'
        )->execute(['cid' => $companyId, 'n' => $name, 's' => $startDate, 'e' => $endDate, 'st' => 'open']);
        return (int) $pdo->lastInsertId();
    }

    public function deleteOpenFiscalPeriod(int $periodId, ?int $companyId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_fiscal_periods WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $periodId, 'cid' => $companyId]
        );
        if (!$row || ($row['status'] ?? '') !== 'open') {
            return false;
        }
        return Database::connection()
            ->prepare('DELETE FROM rateb_fiscal_periods WHERE id = :id')
            ->execute(['id' => $periodId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listSupplierPayments(?int $companyId, int $limit = 200): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }
        return (new JournalEntry())->query(
            'SELECT sp.*, s.name AS supplier_name, po.order_no, je.entry_no, inv.invoice_no,
                    ba.name AS bank_name
             FROM rateb_supplier_payments sp
             LEFT JOIN rateb_suppliers s ON s.id = sp.supplier_id
             LEFT JOIN rateb_purchase_orders po ON po.id = sp.purchase_order_id
             LEFT JOIN rateb_invoices inv ON inv.id = sp.invoice_id
             LEFT JOIN rateb_journal_entries je ON je.id = sp.journal_entry_id
             LEFT JOIN rateb_bank_accounts ba ON ba.id = sp.bank_account_id
             WHERE sp.company_id = :cid
             ORDER BY sp.id DESC LIMIT ' . (int) $limit,
            ['cid' => $companyId]
        );
    }

    public function voidSupplierPayment(int $paymentId, ?int $companyId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_supplier_payments WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $paymentId, 'cid' => $companyId]
        );
        if (!$row || ($row['status'] ?? '') !== 'posted') {
            return false;
        }
        $jid = (int) ($row['journal_entry_id'] ?? 0);
        if ($jid > 0) {
            $this->voidPostedEntry($jid, $companyId, ['supplier_payment']);
        }
        Database::connection()->prepare(
            'UPDATE rateb_supplier_payments SET status = :st WHERE id = :id'
        )->execute(['st' => 'void', 'id' => $paymentId]);
        return true;
    }

    /** @param array<int, int> $ids */
    public function bulkVoidSupplierPayments(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->voidSupplierPayment((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    public function deleteUnreconciledBankLine(int $lineId, ?int $companyId): bool
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT l.id FROM rateb_bank_statement_lines l
             INNER JOIN rateb_bank_accounts b ON b.id = l.bank_account_id
             WHERE l.id = :id AND b.company_id = :cid AND l.is_reconciled = 0 LIMIT 1',
            ['id' => $lineId, 'cid' => $companyId]
        );
        if (!$row) {
            return false;
        }
        return Database::connection()
            ->prepare('DELETE FROM rateb_bank_statement_lines WHERE id = :id')
            ->execute(['id' => $lineId]);
    }

    /** @param array<int, int> $ids */
    public function bulkDeleteUnreconciledBankLines(array $ids, ?int $companyId): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->deleteUnreconciledBankLine((int) $id, $companyId)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Optional unified accounting gateway hook (non-breaking when disabled).
     *
     * @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines
     */
    private function emitAccountingGatewayPostedEvent(
        int $entryId,
        ?int $companyId,
        string $sourceType,
        ?int $sourceId,
        array $lines,
        string $description,
        string $entryDate
    ): void {
        $bootstrap = $this->resolveAccountingGatewayBootstrapPath();
        if ($bootstrap === null) {
            return;
        }
        require_once $bootstrap;
        if (!function_exists('postAccountingEvent')) {
            return;
        }

        $totalDebit = 0.0;
        $debitAccountId = 0;
        $creditAccountId = 0;
        foreach ($lines as $line) {
            $dr = (float) ($line['debit'] ?? 0);
            $cr = (float) ($line['credit'] ?? 0);
            $totalDebit += $dr;
            if ($dr > 0 && $debitAccountId === 0) {
                $debitAccountId = (int) ($line['account_id'] ?? 0);
            }
            if ($cr > 0 && $creditAccountId === 0) {
                $creditAccountId = (int) ($line['account_id'] ?? 0);
            }
        }

        postAccountingEvent([
            'source_system' => 'rateb-erp',
            'event_type' => $this->mapGatewayEventType($sourceType),
            'company_id' => (int) ($companyId ?? 0),
            'branch_id' => null,
            'amount' => round($totalDebit, 2),
            'currency' => 'SAR',
            'debit_account' => $debitAccountId > 0 ? 'id:' . $debitAccountId : 'unknown',
            'credit_account' => $creditAccountId > 0 ? 'id:' . $creditAccountId : 'unknown',
            'reference_type' => $sourceType,
            'reference_id' => $sourceId ?? $entryId,
            'metadata' => [
                'legacy_write' => true,
                'journal_entry_id' => $entryId,
                'entry_date' => $entryDate,
                'description' => $description,
                'lines' => $lines,
            ],
        ]);
    }

    private function resolveAccountingGatewayBootstrapPath(): ?string
    {
        $candidates = [];
        if (defined('RATEB_ROOT')) {
            $candidates[] = dirname((string) RATEB_ROOT) . '/app/Accounting/Support/post_accounting_event.php';
        }
        $candidates[] = dirname(__DIR__, 3) . '/app/Accounting/Support/post_accounting_event.php';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function resolveAccountingIntegrityBootstrapPath(): ?string
    {
        $candidates = [];
        if (defined('RATEB_ROOT')) {
            $candidates[] = dirname((string) RATEB_ROOT) . '/app/Accounting/Support/post_accounting_integrity.php';
        }
        $candidates[] = dirname(__DIR__, 3) . '/app/Accounting/Support/post_accounting_integrity.php';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function enforceLedgerMutableForWrite(?int $companyId, string $entryDate, ?int $branchId = null): void
    {
        $path = $this->resolveAccountingIntegrityBootstrapPath();
        if ($path === null) {
            return;
        }
        require_once $path;
        if (!function_exists('accounting_enforce_ledger_mutable')) {
            return;
        }

        accounting_enforce_ledger_mutable((int) ($companyId ?? 0), $entryDate, $branchId, 'create');
    }

    private function isLedgerLockedException(\Throwable $e): bool
    {
        if ($e instanceof \App\Accounting\Integrity\AccountingLedgerLockedException) {
            return true;
        }

        return $e->getPrevious() instanceof \App\Accounting\Integrity\AccountingLedgerLockedException;
    }

    private function mapGatewayEventType(string $sourceType): string
    {
        return match ($sourceType) {
            'invoice', 'purchase_invoice' => 'invoice',
            'payment', 'supplier_payment', 'cash_voucher' => 'payment',
            'purchase_order', 'expense' => 'expense',
            'branch_transfer', 'stock_movement' => 'transfer',
            default => 'journal',
        };
    }
}
