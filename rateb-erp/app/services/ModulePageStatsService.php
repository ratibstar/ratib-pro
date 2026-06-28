<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Customer;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Models\Notification;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\Supplier;
use Rateb\App\Models\User;

/** Status-card metrics (cm-strip) for module list pages — real DB calculations. */
final class ModulePageStatsService
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private static array $cache = [];

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    public function forRoute(string $route): array
    {
        $route = trim($route, '/');
        if ($route === '' || $this->skipRoute($route)) {
            return [];
        }
        if (isset(self::$cache[$route])) {
            return self::$cache[$route];
        }

        $module = $this->detectModule($route);
        if ($module === null) {
            return [];
        }

        $metrics = match ($module) {
            'oversight' => $this->oversightStats($route),
            'procurement' => $this->procurementStats(),
            'inventory' => $this->inventoryStats($route),
            'suppliers' => $this->supplierStats(),
            'hr' => $this->hrStats(),
            'accounting' => $this->accountingStats($route),
            'contracts' => $this->contractsStats($route),
            'notifications' => $this->notificationStats(),
            'profile' => $this->profileStats(),
            'executive' => $this->executiveStats(),
            'cms' => $this->cmsStats(),
            'access' => $this->accessStats(),
            'workflows' => $this->workflowStats(),
            'platform' => $this->platformStats($route),
            'branches' => $this->branchStats($route),
            'analytics' => $this->analyticsStats($route),
            default => [],
        };

        self::$cache[$route] = $metrics;
        return $metrics;
    }

    private function skipRoute(string $route): bool
    {
        static $exact = [
            'admin',
            'admin/accounting',
            'admin/ops/accounting',
            'admin/ops/hr',
            'admin/executive-dashboard',
        ];
        if (in_array($route, $exact, true)) {
            return true;
        }
        if (preg_match('#/(create|edit)$#', $route)) {
            return true;
        }
        if (preg_match('#/\d+$#', $route)) {
            return true;
        }
        return false;
    }

    private function detectModule(string $route): ?string
    {
        if (str_starts_with($route, 'admin/oversight/')) {
            return 'oversight';
        }
        if ($route === 'admin/executive-dashboard') {
            return 'executive';
        }
        if (str_starts_with($route, 'admin/cms')) {
            return 'cms';
        }
        if (preg_match('#^admin/(access-control|users|roles|permissions|plans|audit-logs|support-tickets|email-templates|sms-templates)(/|$)#', $route)) {
            return 'access';
        }
        if (preg_match('#^admin/(companies|subscriptions|invoices|payments|settings)(/|$)#', $route)) {
            return 'platform';
        }
        if ($route === 'admin/reports' || str_starts_with($route, 'admin/reports/')) {
            return 'platform';
        }

        $path = $this->opsPath($route);

        if (preg_match('#^(branch-dashboard|branch-financial|branch-transfers)(/|$)#', $path)) {
            return 'branches';
        }
        if (str_starts_with($path, 'hr')) {
            return 'hr';
        }
        if (preg_match('#^(purchase-requests|purchase-orders|rfq|quotations)(/|$)#', $path)) {
            return 'procurement';
        }
        if (preg_match('#^reports/inventory-valuation(/|$)#', $path)) {
            return 'inventory';
        }
        if (preg_match('#^reports/(cost-analysis|procurement|kpi|supplier-performance)(/|$)#', $path)) {
            return str_contains($path, 'cost-analysis') ? 'accounting' : 'analytics';
        }
        if ($path === 'reports' || str_starts_with($path, 'reports/')) {
            return 'analytics';
        }
        if (preg_match('#^(inventory|inventory-batches|inventory-audits|inventory-forecast|inventory-codes|warehouses|warehouse-transfers|stock-movements|product-categories)(/|$)#', $path)) {
            return 'inventory';
        }
        if (preg_match('#^(suppliers|supplier-comms|supplier-evaluations|supplier-classifications|supplier-kpi)(/|$)#', $path)) {
            return 'suppliers';
        }
        if (preg_match('#^(accounting|chart-of-accounts|journal-entries|cash-vouchers|fiscal-periods|cost-centers|bank-accounts|customers|asset-depreciation)(/|$)#', $path)) {
            return 'accounting';
        }
        if (preg_match('#^(contracts|contract-renewals|tenders|assets|asset-maintenance|asset-assignments|medical-devices|device-maintenance|device-spare-parts|device-warranty|documents)(/|$)#', $path)) {
            return 'contracts';
        }
        if (preg_match('#^notifications(/|$)#', $path)) {
            return 'notifications';
        }
        if (preg_match('#^profile(/|$)#', $path)) {
            return 'profile';
        }
        if (preg_match('#^workflows(/|$)#', $path)) {
            return 'workflows';
        }
        return null;
    }

    private function opsPath(string $route): string
    {
        if (str_starts_with($route, 'admin/ops/')) {
            return substr($route, strlen('admin/ops/'));
        }
        if (str_starts_with($route, 'admin/')) {
            return substr($route, strlen('admin/'));
        }
        return $route;
    }

    private function companyId(): ?int
    {
        $cid = function_exists('rateb_resolve_ops_company_id') ? rateb_resolve_ops_company_id() : 0;
        if ($cid > 0) {
            return $cid;
        }
        $sess = (int) (SessionManager::get('rateb_company_id') ?? 0);
        return $sess > 0 ? $sess : null;
    }

    private function bootstrapTenant(?int $companyId): void
    {
        if ($companyId === null || $companyId < 1) {
            return;
        }
        TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function card(string $labelKey, string $value, string $tone = 'blue', string $trend = ''): array
    {
        $row = ['label' => __($labelKey), 'value' => $value, 'tone' => $tone];
        if ($trend !== '') {
            $row['trend'] = $trend;
        }
        return [$row];
    }

    /** @param array<int, array{label: string, value: string, tone?: string, trend?: string}> $rows */
    private function cards(array $rows): array
    {
        return $rows;
    }

    private function money(float $n): string
    {
        return number_format($n, 2) . ' <small>SAR</small>';
    }

    private function intStr(int $n): string
    {
        return number_format($n);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function oversightStats(string $route): array
    {
        $filter = $this->companyId();
        $menu = (new ApprovalOversightService())->menuCounts($filter);
        $summary = (new ApprovalOversightService())->summary($filter);

        if (str_contains($route, 'procurement')) {
            $proc = (new ErpAnalyticsService())->procurementDashboard($filter);
            return $this->cards([
                ['label' => __('procurement_oversight'), 'value' => $this->intStr((int) ($menu['procurement'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('purchase_requests'), 'value' => $this->intStr((int) ($proc['purchase_requests'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('purchase_orders'), 'value' => $this->intStr((int) ($proc['purchase_orders'] ?? 0)), 'tone' => 'purple'],
                ['label' => __('approvals_total_pending'), 'value' => $this->intStr((int) ($menu['total'] ?? 0)), 'tone' => 'red'],
            ]);
        }
        if (str_contains($route, 'inventory')) {
            return $this->cards([
                ['label' => __('inventory_oversight'), 'value' => $this->intStr((int) ($menu['inventory'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('inventory_audits'), 'value' => $this->intStr((int) ($summary['inventory_audit'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('warehouse_transfers'), 'value' => $this->intStr((int) ($summary['warehouse_transfer'] ?? 0)), 'tone' => 'teal'],
                ['label' => __('approvals_total_pending'), 'value' => $this->intStr((int) ($menu['total'] ?? 0)), 'tone' => 'red'],
            ]);
        }
        if (str_contains($route, 'rfq')) {
            return $this->cards([
                ['label' => __('rfq_oversight'), 'value' => $this->intStr((int) ($menu['rfq'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('quotations'), 'value' => $this->intStr((int) ($summary['quotation'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('rfq'), 'value' => $this->intStr((int) ($summary['rfq'] ?? 0)), 'tone' => 'purple'],
                ['label' => __('approvals_total_pending'), 'value' => $this->intStr((int) ($menu['total'] ?? 0)), 'tone' => 'red'],
            ]);
        }
        if (str_contains($route, 'supplier-evaluations')) {
            return $this->cards([
                ['label' => __('supplier_evaluations_oversight'), 'value' => $this->intStr((int) ($menu['supplier_evaluations'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('supplier_evaluations'), 'value' => $this->intStr((int) ($summary['supplier_evaluation'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('approvals_total_pending'), 'value' => $this->intStr((int) ($menu['total'] ?? 0)), 'tone' => 'red'],
            ]);
        }
        if (str_contains($route, 'workflows')) {
            return $this->cards([
                ['label' => __('workflow_definitions'), 'value' => $this->intStr($this->countRows('rateb_workflow_definitions', $filter)), 'tone' => 'blue'],
                ['label' => __('pending_approvals'), 'value' => $this->intStr((int) ($menu['approvals'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('approvals_total_pending'), 'value' => $this->intStr((int) ($menu['total'] ?? 0)), 'tone' => 'red'],
            ]);
        }

        return $this->cards([
            ['label' => __('approvals_oversight'), 'value' => $this->intStr((int) ($menu['approvals'] ?? 0)), 'tone' => 'orange'],
            ['label' => __('procurement_oversight'), 'value' => $this->intStr((int) ($menu['procurement'] ?? 0)), 'tone' => 'blue'],
            ['label' => __('inventory_oversight'), 'value' => $this->intStr((int) ($menu['inventory'] ?? 0)), 'tone' => 'teal'],
            ['label' => __('rfq_oversight'), 'value' => $this->intStr((int) ($menu['rfq'] ?? 0)), 'tone' => 'purple'],
            ['label' => __('supplier_evaluations_oversight'), 'value' => $this->intStr((int) ($menu['supplier_evaluations'] ?? 0)), 'tone' => 'green'],
            ['label' => __('approvals_total_pending'), 'value' => $this->intStr((int) ($menu['total'] ?? 0)), 'tone' => 'red'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function procurementStats(): array
    {
        $cid = $this->companyId();
        $this->bootstrapTenant($cid);
        $dash = (new ErpAnalyticsService())->procurementDashboard($cid);
        $pendingPr = 0;
        $pendingPo = 0;
        foreach ($dash['pr_by_status'] ?? [] as $row) {
            if (in_array((string) ($row['status'] ?? ''), ['pending', 'submitted', 'draft'], true)) {
                $pendingPr += (int) ($row['c'] ?? 0);
            }
        }
        foreach ($dash['po_by_status'] ?? [] as $row) {
            if (in_array((string) ($row['status'] ?? ''), ['pending', 'confirmed', 'partial'], true)) {
                $pendingPo += (int) ($row['c'] ?? 0);
            }
        }

        return $this->cards([
            ['label' => __('purchase_requests'), 'value' => $this->intStr((int) ($dash['purchase_requests'] ?? 0)), 'tone' => 'blue'],
            ['label' => __('purchase_orders'), 'value' => $this->intStr((int) ($dash['purchase_orders'] ?? 0)), 'tone' => 'purple'],
            ['label' => __('pending_approvals'), 'value' => $this->intStr($pendingPr + $pendingPo), 'tone' => 'orange'],
            ['label' => __('procurement'), 'value' => $this->money((float) ($dash['total_po_value'] ?? 0)), 'tone' => 'green'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function inventoryStats(string $route): array
    {
        $cid = $this->companyId();
        $this->bootstrapTenant($cid);
        $inv = new Inventory();
        $kpi = (new ErpAnalyticsService())->companyKpi($cid);
        $itemCount = $cid !== null ? $inv->count() : (int) ($inv->queryOne('SELECT COUNT(*) AS c FROM rateb_inventory')['c'] ?? 0);
        $whCount = $cid !== null ? (new WarehouseService())->countForCompany($cid) : $this->countRows('rateb_warehouses', null);

        if (str_contains($path, 'inventory-valuation')) {
            return $this->cards([
                ['label' => __('inventory_value'), 'value' => $this->money((float) ($kpi['inventory_value'] ?? $inv->totalValue($cid))), 'tone' => 'green'],
                ['label' => __('inventory'), 'value' => $this->intStr($itemCount), 'tone' => 'blue'],
                ['label' => __('low_stock'), 'value' => $this->intStr((int) ($kpi['low_stock'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('expiring_soon'), 'value' => $this->intStr((int) ($kpi['expiring_soon'] ?? 0)), 'tone' => 'red'],
            ]);
        }

        return $this->cards([
            ['label' => __('inventory'), 'value' => $this->intStr($itemCount), 'tone' => 'blue'],
            ['label' => __('inventory_value'), 'value' => $this->money((float) ($kpi['inventory_value'] ?? $inv->totalValue($cid))), 'tone' => 'green'],
            ['label' => __('warehouses'), 'value' => $this->intStr($whCount), 'tone' => 'teal'],
            ['label' => __('low_stock'), 'value' => $this->intStr((int) ($kpi['low_stock'] ?? 0)), 'tone' => 'orange'],
            ['label' => __('expiring_soon'), 'value' => $this->intStr((int) ($kpi['expiring_soon'] ?? 0)), 'tone' => 'red'],
            ['label' => __('pending_approvals'), 'value' => $this->intStr((int) ($kpi['pending_workflows'] ?? 0)), 'tone' => 'purple'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function supplierStats(): array
    {
        $cid = $this->companyId();
        $this->bootstrapTenant($cid);
        $sup = new Supplier();
        $total = $cid !== null ? $sup->count() : (int) ($sup->queryOne('SELECT COUNT(*) AS c FROM rateb_suppliers')['c'] ?? 0);
        $active = (int) ($sup->queryOne(
            ($cid !== null ? 'SELECT COUNT(*) AS c FROM rateb_suppliers WHERE company_id = :cid AND status = \'active\'' : "SELECT COUNT(*) AS c FROM rateb_suppliers WHERE status = 'active'"),
            $cid !== null ? ['cid' => $cid] : []
        )['c'] ?? 0);
        $evalPending = $this->countRowsWhere('rateb_supplier_evaluations', "status = 'submitted'", $cid);
        $poWithSup = (int) ((new PurchaseOrder())->queryOne(
            ($cid !== null
                ? 'SELECT COUNT(DISTINCT supplier_id) AS c FROM rateb_purchase_orders WHERE company_id = :cid AND supplier_id IS NOT NULL'
                : 'SELECT COUNT(DISTINCT supplier_id) AS c FROM rateb_purchase_orders WHERE supplier_id IS NOT NULL'),
            $cid !== null ? ['cid' => $cid] : []
        )['c'] ?? 0);

        return $this->cards([
            ['label' => __('suppliers'), 'value' => $this->intStr($total), 'tone' => 'blue'],
            ['label' => __('active'), 'value' => $this->intStr($active), 'tone' => 'green'],
            ['label' => __('supplier_evaluations'), 'value' => $this->intStr($evalPending), 'tone' => 'orange'],
            ['label' => __('purchase_orders'), 'value' => $this->intStr($poWithSup), 'tone' => 'purple'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function hrStats(): array
    {
        $cid = $this->companyId() ?? 0;
        $stats = (new HrService())->dashboardStats($cid);
        return $this->cards([
            ['label' => __('hr_employees'), 'value' => $this->intStr((int) ($stats['employees'] ?? 0)), 'tone' => 'blue'],
            ['label' => __('hr_active_employees'), 'value' => $this->intStr((int) ($stats['active'] ?? 0)), 'tone' => 'green'],
            ['label' => __('hr_present_today'), 'value' => $this->intStr((int) ($stats['present_today'] ?? 0)), 'tone' => 'teal'],
            ['label' => __('hr_absent_today'), 'value' => $this->intStr((int) ($stats['absent_today'] ?? 0)), 'tone' => 'orange'],
            ['label' => __('hr_pending_leaves'), 'value' => $this->intStr((int) ($stats['pending_leaves'] ?? 0)), 'tone' => 'purple'],
            ['label' => __('hr_draft_payrolls'), 'value' => $this->intStr((int) ($stats['draft_payrolls'] ?? 0)), 'tone' => 'red'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function accountingStats(string $route): array
    {
        $cid = $this->companyId();
        $acct = new AccountingService();
        $acctDash = new AccountingDashboardService($acct);
        $m = $acctDash->metrics($cid);
        $path = $this->opsPath($route);

        if (str_contains($path, 'cfo-dashboard')) {
            $cfo = ($cid !== null && $cid > 0) ? $acct->cfoMetrics($cid) : [];
            return $this->cards([
                ['label' => __('cash_position'), 'value' => $this->money((float) ($m['cash_position'] ?? 0)), 'tone' => 'green'],
                ['label' => __('revenue_ytd'), 'value' => $this->money((float) ($m['revenue_ytd'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('expenses_ytd'), 'value' => $this->money((float) ($m['expenses_ytd'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('net_profit_ytd'), 'value' => $this->money((float) ($m['net_profit_ytd'] ?? 0)), 'tone' => 'teal'],
                ['label' => __('ar_open'), 'value' => $this->money((float) ($m['ar_open'] ?? 0)), 'tone' => 'purple'],
                ['label' => __('ap_open'), 'value' => $this->money((float) ($m['ap_open'] ?? 0)), 'tone' => 'red'],
                ['label' => __('dso_days'), 'value' => number_format((float) ($cfo['dso_days'] ?? 0), 1), 'tone' => 'blue'],
                ['label' => __('dpo_days'), 'value' => number_format((float) ($cfo['dpo_days'] ?? 0), 1), 'tone' => 'green'],
            ]);
        }
        if (str_contains($path, 'accounts-receivable')) {
            $ar = $acct->accountsReceivable($cid);
            return $this->cards([
                ['label' => __('ar_open_total'), 'value' => $this->money((float) ($ar['total_open'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('ar_paid_total'), 'value' => $this->money((float) ($ar['total_paid'] ?? 0)), 'tone' => 'green'],
                ['label' => __('unpaid_invoices'), 'value' => $this->intStr((int) ($m['unpaid_invoices'] ?? 0)), 'tone' => 'red'],
                ['label' => __('overdue_invoices'), 'value' => $this->intStr((int) ($m['overdue_invoices'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'accounts-payable')) {
            $ap = $acct->accountsPayable($cid);
            return $this->cards([
                ['label' => __('ap_open_total'), 'value' => $this->money((float) ($ap['total_open'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('payments_total'), 'value' => $this->money((float) ($ap['total_posted'] ?? 0)), 'tone' => 'green'],
                ['label' => __('supplier_payments'), 'value' => $this->intStr((int) ($m['pending_vouchers'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('procurement'), 'value' => $this->money((float) ($m['procurement_received'] ?? 0)), 'tone' => 'teal'],
            ]);
        }
        if (str_contains($path, 'customers')) {
            $this->bootstrapTenant($cid);
            $custTotal = $cid !== null ? (new Customer())->count() : $this->countRows('rateb_customers', null);
            return $this->cards([
                ['label' => __('customers'), 'value' => $this->intStr($custTotal), 'tone' => 'blue'],
                ['label' => __('new_customers'), 'value' => $this->intStr((int) ($m['new_customers'] ?? 0)), 'tone' => 'green'],
                ['label' => __('ar_open'), 'value' => $this->money((float) ($m['ar_open'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('revenue'), 'value' => $this->money((float) ($m['revenue'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'chart-of-accounts') || str_contains($path, 'coa-tree')) {
            $active = (int) ($m['accounts_active'] ?? 0);
            return $this->cards([
                ['label' => __('chart_of_accounts'), 'value' => $this->intStr($active), 'tone' => 'blue'],
                ['label' => __('accounts_active'), 'value' => $this->intStr($active), 'tone' => 'green'],
                ['label' => __('journal_entries'), 'value' => $this->intStr((int) ($m['journal_posted'] ?? 0)), 'tone' => 'purple'],
                ['label' => __('draft_journals'), 'value' => $this->intStr((int) ($m['draft_journals'] ?? 0)), 'tone' => 'orange'],
            ]);
        }
        if (str_contains($path, 'journal-entries')) {
            return $this->cards([
                ['label' => __('journal_entries'), 'value' => $this->intStr((int) ($m['journal_posted'] ?? 0)), 'tone' => 'green'],
                ['label' => __('draft_journals'), 'value' => $this->intStr((int) ($m['draft_journals'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('pending_approvals'), 'value' => $this->intStr((int) ($m['pending_vouchers'] ?? 0)), 'tone' => 'red'],
                ['label' => __('accounts_active'), 'value' => $this->intStr((int) ($m['accounts_active'] ?? 0)), 'tone' => 'blue'],
            ]);
        }
        if (str_contains($path, 'cash-vouchers')) {
            $posted = $this->countRowsWhere('rateb_cash_vouchers', "status = 'posted'", $cid);
            $draft = $this->countRowsWhere('rateb_cash_vouchers', "status IN ('draft','pending')", $cid);
            return $this->cards([
                ['label' => __('cash_vouchers'), 'value' => $this->intStr($posted + $draft), 'tone' => 'blue'],
                ['label' => __('posted'), 'value' => $this->intStr($posted), 'tone' => 'green'],
                ['label' => __('draft_journals'), 'value' => $this->intStr($draft), 'tone' => 'orange'],
                ['label' => __('cash_position'), 'value' => $this->money((float) ($m['cash_position'] ?? 0)), 'tone' => 'teal'],
            ]);
        }
        if (str_contains($path, 'supplier-payments')) {
            return $this->cards([
                ['label' => __('supplier_payments'), 'value' => $this->intStr((int) ($m['pending_vouchers'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('ap_open'), 'value' => $this->money((float) ($m['ap_open'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('payments_total'), 'value' => $this->money((float) ($m['payments_total'] ?? 0)), 'tone' => 'green'],
                ['label' => __('procurement'), 'value' => $this->money((float) ($m['procurement_received'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'fiscal-periods')) {
            $open = $this->countRowsWhere('rateb_fiscal_periods', "status = 'open'", $cid);
            $closed = $this->countRowsWhere('rateb_fiscal_periods', "status = 'closed'", $cid);
            return $this->cards([
                ['label' => __('fiscal_periods'), 'value' => $this->intStr($open + $closed), 'tone' => 'blue'],
                ['label' => __('open'), 'value' => $this->intStr($open), 'tone' => 'green'],
                ['label' => __('closed'), 'value' => $this->intStr($closed), 'tone' => 'orange'],
                ['label' => __('journal_entries'), 'value' => $this->intStr((int) ($m['journal_posted'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'cost-centers')) {
            $total = $this->countRows('rateb_cost_centers', $cid);
            $active = $this->countRowsWhere('rateb_cost_centers', 'is_active = 1', $cid);
            return $this->cards([
                ['label' => __('cost_centers'), 'value' => $this->intStr($total), 'tone' => 'blue'],
                ['label' => __('active'), 'value' => $this->intStr($active), 'tone' => 'green'],
                ['label' => __('expenses_ytd'), 'value' => $this->money((float) ($m['expenses_ytd'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('net_profit_ytd'), 'value' => $this->money((float) ($m['net_profit_ytd'] ?? 0)), 'tone' => 'teal'],
            ]);
        }
        if (str_contains($path, 'bank-accounts')) {
            $bank = $acct->bankReconciliation($cid);
            return $this->cards([
                ['label' => __('bank_accounts'), 'value' => $this->intStr(count($bank['accounts'] ?? [])), 'tone' => 'blue'],
                ['label' => __('total_cash'), 'value' => $this->money((float) ($bank['total_cash'] ?? 0)), 'tone' => 'green'],
                ['label' => __('petty_cash'), 'value' => $this->money((float) ($bank['petty_cash'] ?? 0)), 'tone' => 'teal'],
                ['label' => __('unreconciled_bank'), 'value' => $this->intStr((int) ($m['unreconciled_bank'] ?? 0)), 'tone' => 'orange'],
            ]);
        }
        if (str_contains($path, 'bank-reconciliation')) {
            $bank = $acct->bankReconciliation($cid);
            return $this->cards([
                ['label' => __('total_cash'), 'value' => $this->money((float) ($bank['total_cash'] ?? 0)), 'tone' => 'green'],
                ['label' => __('petty_cash'), 'value' => $this->money((float) ($bank['petty_cash'] ?? 0)), 'tone' => 'teal'],
                ['label' => __('bank_accounts'), 'value' => $this->intStr(count($bank['accounts'] ?? [])), 'tone' => 'blue'],
                ['label' => __('unreconciled_bank'), 'value' => $this->intStr((int) ($m['unreconciled_bank'] ?? 0)), 'tone' => 'orange'],
            ]);
        }
        if (str_contains($path, 'zatca-settings')) {
            $vat = $acct->vatReport($cid, date('Y') . '-01-01', date('Y-m-d'));
            return $this->cards([
                ['label' => __('vat_net_payable'), 'value' => $this->money((float) ($vat['net_vat'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('output_vat'), 'value' => $this->money((float) ($vat['output_vat'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('input_vat'), 'value' => $this->money((float) ($vat['input_vat'] ?? 0)), 'tone' => 'green'],
                ['label' => __('revenue_ytd'), 'value' => $this->money((float) ($m['revenue_ytd'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'cost-analysis') || str_contains($path, 'cost-of-sales')) {
            $cost = (new ErpAnalyticsService())->costAnalysis($cid);
            return $this->cards([
                ['label' => __('procurement_spend'), 'value' => $this->money((float) ($cost['procurement_spend'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('inventory_value'), 'value' => $this->money((float) ($cost['inventory_value'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('asset_value'), 'value' => $this->money((float) ($cost['asset_value'] ?? 0)), 'tone' => 'green'],
                ['label' => __('expenses_ytd'), 'value' => $this->money((float) ($m['expenses_ytd'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'asset-depreciation')) {
            $pending = $this->countRowsWhere('rateb_asset_depreciation_runs', "status IN ('draft','pending')", $cid);
            $posted = $this->countRowsWhere('rateb_asset_depreciation_runs', "status = 'posted'", $cid);
            return $this->cards([
                ['label' => __('asset_depreciation'), 'value' => $this->intStr($pending + $posted), 'tone' => 'blue'],
                ['label' => __('posted'), 'value' => $this->intStr($posted), 'tone' => 'green'],
                ['label' => __('pending_approvals'), 'value' => $this->intStr($pending), 'tone' => 'orange'],
                ['label' => __('total_asset_value'), 'value' => $this->money((float) ((new ErpAnalyticsService())->costAnalysis($cid)['asset_value'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'reports') || str_contains($path, 'trial-balance') || str_contains($path, 'profit-loss') || str_contains($path, 'balance-sheet') || str_contains($path, 'vat-report')) {
            return $this->cards([
                ['label' => __('revenue_ytd'), 'value' => $this->money((float) ($m['revenue_ytd'] ?? 0)), 'tone' => 'green'],
                ['label' => __('expenses_ytd'), 'value' => $this->money((float) ($m['expenses_ytd'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('net_profit_ytd'), 'value' => $this->money((float) ($m['net_profit_ytd'] ?? 0)), 'tone' => 'teal'],
                ['label' => __('vat_net_payable'), 'value' => $this->money((float) ($m['vat_net'] ?? 0)), 'tone' => 'purple'],
                ['label' => __('journal_entries'), 'value' => $this->intStr((int) ($m['journal_posted'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('accounts_active'), 'value' => $this->intStr((int) ($m['accounts_active'] ?? 0)), 'tone' => 'blue'],
            ]);
        }

        return $this->cards([
            ['label' => __('revenue'), 'value' => $this->money((float) ($m['revenue'] ?? 0)), 'tone' => 'green'],
            ['label' => __('cash_position'), 'value' => $this->money((float) ($m['cash_position'] ?? 0)), 'tone' => 'teal'],
            ['label' => __('ar_open'), 'value' => $this->money((float) ($m['ar_open'] ?? 0)), 'tone' => 'purple'],
            ['label' => __('ap_open'), 'value' => $this->money((float) ($m['ap_open'] ?? 0)), 'tone' => 'orange'],
            ['label' => __('unpaid_invoices'), 'value' => $this->intStr((int) ($m['unpaid_invoices'] ?? 0)), 'tone' => 'red'],
            ['label' => __('journal_entries'), 'value' => $this->intStr((int) ($m['journal_posted'] ?? 0)), 'tone' => 'blue'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function contractsStats(string $route): array
    {
        $cid = $this->companyId();
        $contracts = $this->countRows('rateb_contracts', $cid);
        $assets = $this->countRows('rateb_assets', $cid);
        $expiring = $this->countRowsWhere(
            'rateb_contracts',
            'end_date IS NOT NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
            $cid
        );
        $maint = $this->countRowsWhere('rateb_asset_maintenance', "status IN ('scheduled','overdue')", $cid);
        $path = $this->opsPath($route);
        if (str_contains($path, 'medical-devices') || str_contains($path, 'device-')) {
            $devices = $this->countRows('rateb_medical_devices', $cid);
            return $this->cards([
                ['label' => __('medical_devices'), 'value' => $this->intStr($devices), 'tone' => 'blue'],
                ['label' => __('asset_maintenance'), 'value' => $this->intStr($maint), 'tone' => 'orange'],
                ['label' => __('assets'), 'value' => $this->intStr($assets), 'tone' => 'green'],
                ['label' => __('contracts'), 'value' => $this->intStr($contracts), 'tone' => 'purple'],
            ]);
        }
        return $this->cards([
            ['label' => __('contracts'), 'value' => $this->intStr($contracts), 'tone' => 'blue'],
            ['label' => __('contract_expiry_alerts'), 'value' => $this->intStr($expiring), 'tone' => 'orange'],
            ['label' => __('assets'), 'value' => $this->intStr($assets), 'tone' => 'green'],
            ['label' => __('asset_maintenance'), 'value' => $this->intStr($maint), 'tone' => 'red'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function notificationStats(): array
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $cid = $this->companyId();
        $unread = 0;
        $total = 0;
        if ($uid > 0) {
            $unread = (int) ((new Notification())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_notifications WHERE user_id = :uid AND is_read = 0',
                ['uid' => $uid]
            )['c'] ?? 0);
            $total = (int) ((new Notification())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_notifications WHERE user_id = :uid',
                ['uid' => $uid]
            )['c'] ?? 0);
        }
        $pending = $cid !== null
            ? (int) ((new JournalEntry())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE company_id = :cid AND status = :st',
                ['cid' => $cid, 'st' => 'pending']
            )['c'] ?? 0)
            : 0;

        return $this->cards([
            ['label' => __('notifications'), 'value' => $this->intStr($total), 'tone' => 'blue'],
            ['label' => __('portal_unread'), 'value' => $this->intStr($unread), 'tone' => 'orange'],
            ['label' => __('pending_approvals'), 'value' => $this->intStr($pending), 'tone' => 'red'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function profileStats(): array
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $cid = $this->companyId() ?? 0;
        $branches = $cid > 0 ? (new BranchService())->countForCompany($cid) : 0;
        $unread = 0;
        if ($uid > 0) {
            $unread = (int) ((new Notification())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_notifications WHERE user_id = :uid AND is_read = 0',
                ['uid' => $uid]
            )['c'] ?? 0);
        }
        return $this->cards([
            ['label' => __('profile'), 'value' => $uid > 0 ? '#' . $uid : '—', 'tone' => 'blue'],
            ['label' => __('branches'), 'value' => $this->intStr($branches), 'tone' => 'teal'],
            ['label' => __('portal_unread'), 'value' => $this->intStr($unread), 'tone' => 'orange'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function executiveStats(): array
    {
        $platform = (new DashboardService())->adminMetrics();
        $inv = (new Inventory())->totalValue();
        return $this->cards([
            ['label' => __('total_companies'), 'value' => $this->intStr((int) ($platform['total_companies'] ?? 0)), 'tone' => 'blue'],
            ['label' => __('active_companies'), 'value' => $this->intStr((int) ($platform['active_companies'] ?? 0)), 'tone' => 'green'],
            ['label' => __('subscriptions'), 'value' => $this->intStr((int) ($platform['subscriptions'] ?? 0)), 'tone' => 'purple'],
            ['label' => __('inventory_value'), 'value' => $this->money($inv), 'tone' => 'teal'],
            ['label' => __('pending_approvals'), 'value' => $this->intStr((int) ($platform['pending_approvals'] ?? 0)), 'tone' => 'orange'],
            ['label' => __('users'), 'value' => $this->intStr((int) ($platform['users'] ?? 0)), 'tone' => 'blue'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function cmsStats(): array
    {
        $s = (new CmsService())->dashboardStats();
        return $this->cards([
            ['label' => __('cms_leads'), 'value' => $this->intStr((int) ($s['leads_total'] ?? 0)), 'tone' => 'blue'],
            ['label' => __('cms_leads_new'), 'value' => $this->intStr((int) ($s['leads_new'] ?? 0)), 'tone' => 'orange'],
            ['label' => __('cms_newsletter'), 'value' => $this->intStr((int) ($s['newsletter'] ?? 0)), 'tone' => 'green'],
            ['label' => __('cms_blog_stats'), 'value' => $this->intStr((int) ($s['blog_published'] ?? 0)), 'tone' => 'purple'],
            ['label' => __('cms_visitors_today'), 'value' => $this->intStr((int) ($s['visitors_today'] ?? 0)), 'tone' => 'teal'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function accessStats(): array
    {
        $users = (int) ((new User())->queryOne('SELECT COUNT(*) AS c FROM rateb_users WHERE is_super_admin = 0')['c'] ?? 0);
        $roles = $this->countRows('rateb_roles', null);
        $perms = $this->countRows('rateb_permissions', null);
        $plans = $this->countRows('rateb_plans', null);
        return $this->cards([
            ['label' => __('users'), 'value' => $this->intStr($users), 'tone' => 'blue'],
            ['label' => __('roles'), 'value' => $this->intStr($roles), 'tone' => 'purple'],
            ['label' => __('permissions'), 'value' => $this->intStr($perms), 'tone' => 'teal'],
            ['label' => __('plans'), 'value' => $this->intStr($plans), 'tone' => 'green'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function workflowStats(): array
    {
        $cid = $this->companyId();
        $pending = $cid !== null
            ? (int) ((new JournalEntry())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE company_id = :cid AND status = :st',
                ['cid' => $cid, 'st' => 'pending']
            )['c'] ?? 0)
            : $this->countRowsWhere('rateb_approval_instances', "status = 'pending'", null);
        return $this->cards([
            ['label' => __('pending_approvals'), 'value' => $this->intStr($pending), 'tone' => 'orange'],
            ['label' => __('workflow_definitions'), 'value' => $this->intStr($this->countRows('rateb_workflow_definitions', $cid)), 'tone' => 'blue'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function platformStats(string $route): array
    {
        $dash = (new DashboardService())->adminMetrics();
        $acct = new AccountingDashboardService();
        $m = $acct->metrics(null);

        if (str_contains($route, 'companies')) {
            return $this->cards([
                ['label' => __('total_companies'), 'value' => $this->intStr((int) ($dash['total_companies'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('active_companies'), 'value' => $this->intStr((int) ($dash['active_companies'] ?? 0)), 'tone' => 'green'],
                ['label' => __('pending_companies'), 'value' => $this->intStr((int) ($dash['pending_companies'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('suspended_companies'), 'value' => $this->intStr((int) ($dash['suspended_companies'] ?? 0)), 'tone' => 'red'],
            ]);
        }
        if (str_contains($route, 'subscriptions')) {
            return $this->cards([
                ['label' => __('subscriptions'), 'value' => $this->intStr((int) ($dash['subscriptions'] ?? 0)), 'tone' => 'purple'],
                ['label' => __('expiring_subscriptions'), 'value' => $this->intStr((int) ($dash['expiring_subscriptions'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('active_plans'), 'value' => $this->intStr((int) ($dash['active_plans'] ?? 0)), 'tone' => 'green'],
                ['label' => __('users'), 'value' => $this->intStr((int) ($dash['users'] ?? 0)), 'tone' => 'blue'],
            ]);
        }
        if (str_contains($route, 'invoices')) {
            return $this->cards([
                ['label' => __('invoices'), 'value' => $this->intStr((int) ($m['invoices_open_count'] ?? 0) + (int) ($m['invoices_paid_count'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('unpaid_invoices'), 'value' => $this->intStr((int) ($m['unpaid_invoices'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('overdue_invoices'), 'value' => $this->intStr((int) ($m['overdue_invoices'] ?? 0)), 'tone' => 'red'],
                ['label' => __('payments_total'), 'value' => $this->money((float) ($m['payments_total'] ?? 0)), 'tone' => 'green'],
            ]);
        }
        if (str_contains($route, 'payments')) {
            return $this->cards([
                ['label' => __('payments'), 'value' => $this->intStr((int) ($m['payments_count'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('payments_total'), 'value' => $this->money((float) ($m['payments_total'] ?? 0)), 'tone' => 'green'],
                ['label' => __('revenue'), 'value' => $this->money((float) ($m['revenue'] ?? 0)), 'tone' => 'teal'],
                ['label' => __('subscriptions'), 'value' => $this->intStr((int) ($dash['subscriptions'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        return $this->cards([
            ['label' => __('total_companies'), 'value' => $this->intStr((int) ($dash['total_companies'] ?? 0)), 'tone' => 'blue'],
            ['label' => __('subscriptions'), 'value' => $this->intStr((int) ($dash['subscriptions'] ?? 0)), 'tone' => 'purple'],
            ['label' => __('users'), 'value' => $this->intStr((int) ($dash['users'] ?? 0)), 'tone' => 'teal'],
            ['label' => __('pending_approvals'), 'value' => $this->intStr((int) ($dash['pending_approvals'] ?? 0)), 'tone' => 'orange'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function branchStats(string $route): array
    {
        $cid = $this->companyId() ?? 0;
        $branchCount = $cid > 0 ? (new BranchService())->countForCompany($cid) : 0;
        $employees = 0;
        $inventoryValue = 0.0;
        $purchases = 0.0;
        if ($cid > 0) {
            foreach ((new BranchReportingService())->branchesOverview($cid) as $row) {
                $employees += (int) ($row['employees_count'] ?? 0);
                $inventoryValue += (float) ($row['inventory_value'] ?? 0);
                $purchases += (float) ($row['purchases_total'] ?? 0);
            }
        }
        return $this->cards([
            ['label' => __('branches'), 'value' => $this->intStr($branchCount), 'tone' => 'blue'],
            ['label' => __('hr_employees'), 'value' => $this->intStr($employees), 'tone' => 'green'],
            ['label' => __('inventory_value'), 'value' => $this->money($inventoryValue), 'tone' => 'teal'],
            ['label' => __('procurement'), 'value' => $this->money($purchases), 'tone' => 'purple'],
        ]);
    }

    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    private function analyticsStats(string $route): array
    {
        $cid = $this->companyId();
        $this->bootstrapTenant($cid);
        $path = $this->opsPath($route);

        if (str_contains($path, 'procurement')) {
            $proc = (new ErpAnalyticsService())->procurementDashboard($cid);
            return $this->cards([
                ['label' => __('purchase_requests'), 'value' => $this->intStr((int) ($proc['purchase_requests'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('purchase_orders'), 'value' => $this->intStr((int) ($proc['purchase_orders'] ?? 0)), 'tone' => 'purple'],
                ['label' => __('procurement'), 'value' => $this->money((float) ($proc['total_po_value'] ?? 0)), 'tone' => 'green'],
            ]);
        }
        if (str_contains($path, 'supplier-performance')) {
            $rows = (new ErpAnalyticsService())->supplierPerformance($cid);
            return $this->cards([
                ['label' => __('suppliers'), 'value' => $this->intStr(count($rows)), 'tone' => 'blue'],
                ['label' => __('supplier_evaluations'), 'value' => $this->intStr($this->countRowsWhere('rateb_supplier_evaluations', "status = 'published'", $cid)), 'tone' => 'green'],
                ['label' => __('purchase_orders'), 'value' => $this->intStr((int) ((new PurchaseOrder())->count())), 'tone' => 'purple'],
            ]);
        }
        if (str_contains($path, 'kpi')) {
            $kpi = (new ErpAnalyticsService())->companyKpi($cid);
            return $this->cards([
                ['label' => __('purchase_requests'), 'value' => $this->intStr((int) ($kpi['purchase_requests'] ?? 0)), 'tone' => 'blue'],
                ['label' => __('inventory_value'), 'value' => $this->money((float) ($kpi['inventory_value'] ?? 0)), 'tone' => 'green'],
                ['label' => __('low_stock'), 'value' => $this->intStr((int) ($kpi['low_stock'] ?? 0)), 'tone' => 'orange'],
                ['label' => __('suppliers'), 'value' => $this->intStr((int) ($kpi['suppliers'] ?? 0)), 'tone' => 'purple'],
            ]);
        }
        $kpi = (new ErpAnalyticsService())->companyKpi($cid);
        return $this->cards([
            ['label' => __('company_kpi'), 'value' => $this->intStr((int) ($kpi['purchase_orders'] ?? 0)), 'tone' => 'blue'],
            ['label' => __('inventory_value'), 'value' => $this->money((float) ($kpi['inventory_value'] ?? 0)), 'tone' => 'green'],
            ['label' => __('pending_approvals'), 'value' => $this->intStr((int) ($kpi['pending_workflows'] ?? 0)), 'tone' => 'orange'],
        ]);
    }

    private function countRows(string $table, ?int $companyId): int
    {
        try {
            $pdo = Database::connection();
            if ($companyId !== null && $companyId > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM {$table} WHERE company_id = :cid");
                $stmt->execute(['cid' => $companyId]);
            } else {
                $stmt = $pdo->query("SELECT COUNT(*) AS c FROM {$table}");
            }
            return (int) (($stmt ? $stmt->fetch()['c'] : 0) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countRowsWhere(string $table, string $where, ?int $companyId): int
    {
        try {
            $pdo = Database::connection();
            $sql = "SELECT COUNT(*) AS c FROM {$table} WHERE {$where}";
            if ($companyId !== null && $companyId > 0) {
                $sql .= ' AND company_id = :cid';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['cid' => $companyId]);
            } else {
                $stmt = $pdo->query($sql);
            }
            return (int) (($stmt ? $stmt->fetch()['c'] : 0) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
