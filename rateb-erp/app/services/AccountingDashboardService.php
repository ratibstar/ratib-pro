<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;

final class AccountingDashboardService
{
    private AccountingService $acct;

    /** @var array<string, mixed> Phase AI — request-scoped dashboard memo */
    private static array $requestMemo = [];

    public function __construct(?AccountingService $acct = null)
    {
        $this->acct = $acct ?? new AccountingService();
    }

    /** @param callable():mixed $fn */
    private static function requestMemo(string $key, callable $fn): mixed
    {
        if (array_key_exists($key, self::$requestMemo)) {
            return self::$requestMemo[$key];
        }

        return self::$requestMemo[$key] = $fn();
    }

    private static function companyMemoKey(?int $companyId): string
    {
        return ($companyId !== null && $companyId > 0) ? (string) $companyId : 'null';
    }

    /** @return array<string, mixed> */
    public function build(?int $companyId): array
    {
        $metrics = $this->metrics($companyId);
        return [
            'metrics' => $metrics,
            'trends' => $this->trends($companyId, $metrics),
            'kpis' => $this->kpiList($companyId, $metrics),
            'charts' => $this->charts($companyId),
            'alerts' => $this->alerts($companyId, $metrics),
            'recent' => $this->recentActivity($companyId),
            'top_customers' => $this->topCustomers($companyId),
            'top_items' => $this->topSoldItems($companyId),
            'expense_breakdown' => $this->expenseBreakdown($companyId),
        ];
    }

    /** @return array<string, float|int> */
    public function metrics(?int $companyId): array
    {
        return self::requestMemo('metrics:' . self::companyMemoKey($companyId), function () use ($companyId): array {
        $summary = $this->acct->financialSummary($companyId);
        $metrics = [
            'invoices_paid_total' => (float) ($summary['invoices_paid_total'] ?? 0),
            'invoices_paid_count' => (int) ($summary['invoices_paid_count'] ?? 0),
            'invoices_open_total' => (float) ($summary['invoices_open_total'] ?? 0),
            'invoices_open_count' => (int) ($summary['invoices_open_count'] ?? 0),
            'payments_total' => (float) ($summary['payments_total'] ?? 0),
            'payments_count' => (int) ($summary['payments_count'] ?? 0),
            'journal_posted' => (int) ($summary['journal_posted'] ?? 0),
            'accounts_active' => (int) ($summary['accounts_active'] ?? 0),
            'procurement_received' => (float) ($summary['procurement_received'] ?? 0),
            'cash_position' => 0.0,
            'ar_open' => 0.0,
            'ap_open' => 0.0,
            'revenue_ytd' => 0.0,
            'expenses_ytd' => 0.0,
            'net_profit_ytd' => 0.0,
            'vat_net' => 0.0,
            'unpaid_invoices' => 0,
            'overdue_invoices' => 0,
            'draft_journals' => 0,
            'pending_vouchers' => 0,
            'unreconciled_bank' => 0,
            'revenue' => (float) ($summary['payments_total'] ?? 0),
            'purchase_requests' => 0,
            'purchase_orders' => 0,
            'inventory_value' => 0.0,
            'new_customers' => 0,
            'total_expenses' => 0.0,
        ];

        if ($companyId === null || $companyId < 1) {
            $pdo = Database::connection();
            $metrics['purchase_requests'] = (int) (($pdo->query('SELECT COUNT(*) AS c FROM rateb_purchase_requests')->fetch()['c'] ?? 0));
            $metrics['purchase_orders'] = (int) (($pdo->query('SELECT COUNT(*) AS c FROM rateb_purchase_orders')->fetch()['c'] ?? 0));
            $metrics['inventory_value'] = (new Inventory())->totalValue();
            $poExp = $pdo->query(
                "SELECT COALESCE(SUM(total_amount), 0) AS t FROM rateb_purchase_orders WHERE status IN ('received','confirmed','partial')"
            )->fetch();
            $metrics['expenses_ytd'] = (float) ($poExp['t'] ?? 0);
            $metrics['total_expenses'] = $metrics['expenses_ytd'];
            $metrics['revenue_ytd'] = $metrics['revenue'];
            $metrics['net_profit_ytd'] = $metrics['revenue_ytd'] - $metrics['expenses_ytd'];
            $newCo = $pdo->query(
                "SELECT COUNT(*) AS c FROM rateb_companies WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            )->fetch();
            $metrics['new_customers'] = (int) ($newCo['c'] ?? 0);
            $invStats = $this->platformInvoiceStats();
            $metrics['unpaid_invoices'] = $invStats['unpaid'];
            $metrics['overdue_invoices'] = $invStats['overdue'];
            return $metrics;
        }

        TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
        $metrics['purchase_requests'] = (new PurchaseRequest())->count();
        $metrics['purchase_orders'] = (new PurchaseOrder())->count();
        $metrics['inventory_value'] = (new Inventory())->totalValue();
        $metrics['revenue'] = (float) ($summary['payments_total'] ?? 0);

        try {
            $cfo = $this->acct->cfoMetrics($companyId);
            $vat = $this->acct->vatReport($companyId, date('Y') . '-01-01', date('Y-m-d'));
            $bank = $this->acct->bankReconciliation($companyId);
            $metrics['cash_position'] = (float) ($cfo['cash_position'] ?? 0);
            $metrics['ar_open'] = (float) ($cfo['ar_open'] ?? 0);
            $metrics['ap_open'] = (float) ($cfo['ap_open'] ?? 0);
            $metrics['revenue_ytd'] = (float) ($cfo['revenue_ytd'] ?? 0);
            $metrics['expenses_ytd'] = (float) ($cfo['expenses_ytd'] ?? 0);
            $metrics['net_profit_ytd'] = (float) ($cfo['net_margin'] ?? 0);
            $metrics['vat_net'] = (float) ($vat['net_vat'] ?? 0);
            $unreconciled = 0;
            foreach ($bank['accounts'] ?? [] as $acc) {
                $unreconciled += (int) ($acc['unreconciled_count'] ?? 0);
            }
            $metrics['unreconciled_bank'] = $unreconciled;
        } catch (\Throwable $e) {
            error_log('AccountingDashboardService::metrics branch scope: ' . $e->getMessage());
        }

        $invoiceStats = $this->invoiceStats($companyId);
        $workflow = $this->workflowCounts($companyId);
        $metrics['unpaid_invoices'] = (int) ($invoiceStats['unpaid'] ?? 0);
        $metrics['overdue_invoices'] = (int) ($invoiceStats['overdue'] ?? 0);
        $metrics['draft_journals'] = (int) ($workflow['draft_journals'] ?? 0);
        $metrics['pending_vouchers'] = (int) ($workflow['pending_vouchers'] ?? 0);
        $metrics['total_expenses'] = (float) ($metrics['expenses_ytd'] ?? 0);
        $metrics['new_customers'] = $this->newCustomersCount($companyId);

        return $metrics;
        });
    }

    /** @return array<string, string> */
    public function trends(?int $companyId, ?array $metrics = null): array
    {
        $m = $metrics ?? $this->metrics($companyId);
        $cidSql = $companyId !== null && $companyId > 0 ? ' AND company_id = ' . (int) $companyId : '';
        $pdo = Database::connection();
        $thisMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));

        $revCur = (float) ($pdo->query(
            "SELECT COALESCE(SUM(amount),0) AS t FROM rateb_payments
             WHERE status='completed' AND DATE_FORMAT(COALESCE(paid_at,created_at),'%Y-%m')='$thisMonth'" . $cidSql
        )->fetch()['t'] ?? 0);
        $revPrev = (float) ($pdo->query(
            "SELECT COALESCE(SUM(amount),0) AS t FROM rateb_payments
             WHERE status='completed' AND DATE_FORMAT(COALESCE(paid_at,created_at),'%Y-%m')='$lastMonth'" . $cidSql
        )->fetch()['t'] ?? 0);

        $expCur = (float) ($pdo->query(
            "SELECT COALESCE(SUM(total_amount),0) AS t FROM rateb_purchase_orders
             WHERE status IN ('received','confirmed','partial') AND DATE_FORMAT(order_date,'%Y-%m')='$thisMonth'" . $cidSql
        )->fetch()['t'] ?? 0);
        $expPrev = (float) ($pdo->query(
            "SELECT COALESCE(SUM(total_amount),0) AS t FROM rateb_purchase_orders
             WHERE status IN ('received','confirmed','partial') AND DATE_FORMAT(order_date,'%Y-%m')='$lastMonth'" . $cidSql
        )->fetch()['t'] ?? 0);

        return [
            'revenue' => $this->pctChange($revCur, $revPrev),
            'expenses' => $this->pctChange($expCur, $expPrev),
            'net_profit' => $this->pctChange($revCur - $expCur, $revPrev - $expPrev),
            'inventory' => '',
            'customers' => '',
            'unpaid' => '',
            'revenue_ytd' => $this->pctChange($revCur, $revPrev),
            'expenses_ytd' => $this->pctChange($expCur, $expPrev),
            'net_profit_ytd' => $this->pctChange($revCur - $expCur, $revPrev - $expPrev),
            'inventory_value' => '',
            'new_customers' => '',
            'unpaid_invoices' => (int) ($m['unpaid_invoices'] ?? 0) > 0 ? '+' . (int) $m['unpaid_invoices'] : '',
        ];
    }

    /** @return array<int, array{name: string, total: float}> */
    public function topCustomers(?int $companyId): array
    {
        $pdo = Database::connection();
        if ($companyId !== null && $companyId > 0) {
            $stmt = $pdo->prepare(
                "SELECT c.name, COALESCE(SUM(v.amount), 0) AS total
                 FROM rateb_customers c
                 LEFT JOIN rateb_cash_vouchers v ON v.customer_id = c.id AND v.status = 'posted'
                 WHERE c.company_id = :cid
                 GROUP BY c.id ORDER BY total DESC LIMIT 5"
            );
            $stmt->execute(['cid' => $companyId]);
            $rows = $stmt->fetchAll() ?: [];
            if ($rows !== [] && (float) ($rows[0]['total'] ?? 0) > 0) {
                return array_map(static fn ($r) => ['name' => (string) ($r['name'] ?? ''), 'total' => (float) ($r['total'] ?? 0)], $rows);
            }
            $stmt = $pdo->prepare(
                "SELECT invoice_no AS name, total_amount AS total
                 FROM rateb_invoices WHERE company_id = :cid AND status != 'cancelled'
                 ORDER BY total_amount DESC LIMIT 5"
            );
            $stmt->execute(['cid' => $companyId]);
            $rows = $stmt->fetchAll() ?: [];
            return array_map(static fn ($r) => ['name' => (string) ($r['name'] ?? ''), 'total' => (float) ($r['total'] ?? 0)], $rows);
        }
        $rows = $pdo->query(
            "SELECT c.name, COALESCE(SUM(i.total_amount), 0) AS total
             FROM rateb_invoices i
             JOIN rateb_companies c ON c.id = i.company_id
             WHERE i.status != 'cancelled'
             GROUP BY i.company_id ORDER BY total DESC LIMIT 5"
        )->fetchAll() ?: [];
        return array_map(static fn ($r) => ['name' => (string) ($r['name'] ?? ''), 'total' => (float) ($r['total'] ?? 0)], $rows);
    }

    /** @return array<int, array{name: string, total: float}> */
    public function topSoldItems(?int $companyId): array
    {
        $pdo = Database::connection();
        $cidSql = $companyId !== null && $companyId > 0 ? ' AND i.company_id = ' . (int) $companyId : '';
        $rows = $pdo->query(
            "SELECT COALESCE(NULLIF(TRIM(il.description), ''), il.item_name) AS name,
                    COALESCE(SUM(il.line_total), 0) AS total
             FROM rateb_invoice_lines il
             JOIN rateb_invoices i ON i.id = il.invoice_id
             WHERE i.status != 'cancelled'" . $cidSql . "
             GROUP BY name ORDER BY total DESC LIMIT 5"
        )->fetchAll() ?: [];
        if ($rows === []) {
            $cidPo = $companyId !== null && $companyId > 0 ? ' AND po.company_id = ' . (int) $companyId : '';
            $rows = $pdo->query(
                "SELECT COALESCE(s.name, '—') AS name, COALESCE(SUM(po.total_amount), 0) AS total
                 FROM rateb_purchase_orders po
                 LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
                 WHERE po.status IN ('received','confirmed','partial')" . $cidPo . "
                 GROUP BY po.supplier_id ORDER BY total DESC LIMIT 5"
            )->fetchAll() ?: [];
        }
        return array_map(static fn ($r) => ['name' => (string) ($r['name'] ?? ''), 'total' => (float) ($r['total'] ?? 0)], $rows);
    }

    /** @return array<int, array{label: string, value: float}> */
    public function expenseBreakdown(?int $companyId): array
    {
        if ($companyId === null || $companyId < 1) {
            $pdo = Database::connection();
            $proc = (float) (($pdo->query(
                "SELECT COALESCE(SUM(total_amount), 0) AS t FROM rateb_purchase_orders WHERE status IN ('received','confirmed','partial')"
            )->fetch()['t'] ?? 0));
            $inv = (float) (($pdo->query(
                'SELECT COALESCE(SUM(quantity * unit_cost), 0) AS t FROM rateb_inventory'
            )->fetch()['t'] ?? 0));
            $out = [];
            if ($proc > 0) {
                $out[] = ['label' => 'procurement', 'value' => $proc];
            }
            if ($inv > 0) {
                $out[] = ['label' => 'inventory', 'value' => $inv];
            }
            return $out;
        }
        $year = date('Y') . '-01-01';
        $pl = $this->acct->profitAndLoss($companyId, $year, date('Y-m-d'));
        $byType = [];
        foreach ($pl['lines'] ?? [] as $line) {
            if (($line['account_type'] ?? '') !== 'expense') {
                continue;
            }
            $name = rateb_locale() === 'ar' && !empty($line['name_ar']) ? $line['name_ar'] : ($line['name'] ?? '');
            $amt = (float) (($line['total_debit'] ?? 0) - ($line['total_credit'] ?? 0));
            if ($amt <= 0) {
                continue;
            }
            $byType[] = ['label' => $name, 'value' => $amt];
        }
        usort($byType, static fn ($a, $b) => $b['value'] <=> $a['value']);
        return array_slice($byType, 0, 5);
    }

    private function pctChange(float $cur, float $prev): string
    {
        if ($prev <= 0) {
            return $cur > 0 ? '+100%' : '';
        }
        $pct = round((($cur - $prev) / $prev) * 100, 1);
        return ($pct >= 0 ? '+' : '') . $pct . '%';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function padMonthlySeries(array $rows): array
    {
        if ($rows !== []) {
            return $rows;
        }
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $out[] = ['month' => date('Y-m', strtotime('-' . $i . ' months')), 'total' => 0];
        }
        return $out;
    }

    private function newCustomersCount(int $companyId): int
    {
        $row = Database::connection()->prepare(
            "SELECT COUNT(*) AS c FROM rateb_customers
             WHERE company_id = :cid AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
        $row->execute(['cid' => $companyId]);
        return (int) (($row->fetch()['c'] ?? 0));
    }

    /** @return array{unpaid: int, overdue: int} */
    private function platformInvoiceStats(): array
    {
        $pdo = Database::connection();
        $today = date('Y-m-d');
        $unpaid = (int) (($pdo->query(
            "SELECT COUNT(*) AS c FROM rateb_invoices WHERE status IN ('sent','draft','overdue') AND payment_status IN ('unpaid','partial')"
        )->fetch()['c'] ?? 0));
        $overdue = (int) (($pdo->query(
            "SELECT COUNT(*) AS c FROM rateb_invoices WHERE status IN ('sent','overdue') AND payment_status IN ('unpaid','partial')
             AND due_date IS NOT NULL AND due_date < '$today'"
        )->fetch()['c'] ?? 0));
        return ['unpaid' => $unpaid, 'overdue' => $overdue];
    }

    /** @return array<int, array{key: string, label: string, value: string, trend: string, icon: string}> */
    public function kpiList(?int $companyId, ?array $metrics = null): array
    {
        $m = $metrics ?? $this->metrics($companyId);
        $growth = $this->revenueGrowth($companyId);

        return [
            ['key' => 'revenue', 'label' => 'revenue', 'value' => number_format((float) $m['revenue'], 2) . ' SAR', 'trend' => $growth, 'icon' => 'fa-coins'],
            ['key' => 'inventory_value', 'label' => 'inventory_value', 'value' => number_format((float) $m['inventory_value'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-boxes-stacked'],
            ['key' => 'purchase_requests', 'label' => 'purchase_requests', 'value' => (string) (int) $m['purchase_requests'], 'trend' => '', 'icon' => 'fa-file-circle-plus'],
            ['key' => 'purchase_orders', 'label' => 'purchase_orders', 'value' => (string) (int) $m['purchase_orders'], 'trend' => '', 'icon' => 'fa-file-invoice'],
            ['key' => 'revenue_ytd', 'label' => 'revenue_ytd', 'value' => number_format((float) $m['revenue_ytd'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-chart-line'],
            ['key' => 'net_profit_ytd', 'label' => 'net_profit_ytd', 'value' => number_format((float) $m['net_profit_ytd'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-chart-line'],
            ['key' => 'cash_position', 'label' => 'cash_position', 'value' => number_format((float) $m['cash_position'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-building-columns'],
            ['key' => 'ar_open', 'label' => 'ar_open', 'value' => number_format((float) $m['ar_open'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-hand-holding-dollar'],
            ['key' => 'ap_open', 'label' => 'ap_open', 'value' => number_format((float) $m['ap_open'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-file-invoice-dollar'],
            ['key' => 'unpaid_invoices', 'label' => 'unpaid_invoices', 'value' => (string) (int) $m['unpaid_invoices'], 'trend' => '', 'icon' => 'fa-file-circle-exclamation'],
            ['key' => 'overdue_invoices', 'label' => 'overdue_invoices', 'value' => (string) (int) $m['overdue_invoices'], 'trend' => '', 'icon' => 'fa-clock'],
            ['key' => 'vat_net', 'label' => 'vat_net_payable', 'value' => number_format((float) $m['vat_net'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-percent'],
            ['key' => 'journal_posted', 'label' => 'journal_entries', 'value' => (string) (int) $m['journal_posted'], 'trend' => '', 'icon' => 'fa-book'],
            ['key' => 'procurement_received', 'label' => 'procurement', 'value' => number_format((float) $m['procurement_received'], 2) . ' SAR', 'trend' => '', 'icon' => 'fa-cart-shopping'],
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function charts(?int $companyId): array
    {
        $cidSql = $companyId !== null && $companyId > 0 ? ' AND company_id = ' . (int) $companyId : '';

        $pdo = Database::connection();
        $revenue = $pdo->query(
            "SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') AS month,
                    COALESCE(SUM(amount), 0) AS total
             FROM rateb_payments
             WHERE status = 'completed'" . $cidSql . "
             GROUP BY DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m')
             ORDER BY month ASC LIMIT 12"
        )->fetchAll() ?: [];

        $expenses = $pdo->query(
            "SELECT DATE_FORMAT(order_date, '%Y-%m') AS month,
                    COALESCE(SUM(total_amount), 0) AS total
             FROM rateb_purchase_orders
             WHERE status IN ('received','confirmed','partial')" . $cidSql . "
             GROUP BY DATE_FORMAT(order_date, '%Y-%m')
             ORDER BY month ASC LIMIT 12"
        )->fetchAll() ?: [];

        $revenue = $this->padMonthlySeries($revenue);
        $expenses = $this->padMonthlySeries($expenses);

        $arAp = [];
        if ($companyId !== null && $companyId > 0) {
            try {
                $arData = $this->acct->accountsReceivable($companyId);
                $apData = $this->acct->accountsPayable($companyId);
                $arAp = [
                    ['label' => 'ar_open', 'value' => (float) ($arData['total_open'] ?? 0)],
                    ['label' => 'ap_open', 'value' => (float) ($apData['total_open'] ?? 0)],
                ];
            } catch (\Throwable $e) {
                error_log('AccountingDashboardService::charts ar/ap: ' . $e->getMessage());
            }
        } else {
            $invStats = $pdo->query(
                "SELECT payment_status, COUNT(*) AS total FROM rateb_invoices
                 WHERE status != 'cancelled' GROUP BY payment_status"
            )->fetchAll() ?: [];
            foreach ($invStats as $row) {
                $arAp[] = [
                    'label' => (string) ($row['payment_status'] ?? ''),
                    'value' => (int) ($row['total'] ?? 0),
                ];
            }
        }

        $journalActivity = [];
        try {
            $journalActivity = $pdo->query(
                "SELECT DATE_FORMAT(entry_date, '%Y-%m') AS month, COUNT(*) AS total
                 FROM rateb_journal_entries
                 WHERE status = 'posted'" . $cidSql . "
                 GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
                 ORDER BY month ASC LIMIT 12"
            )->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $journalActivity = [];
        }
        $journalActivity = $this->padMonthlySeries($journalActivity);

        return [
            'monthly_revenue' => $revenue,
            'monthly_expenses' => $expenses,
            'ar_ap' => $arAp,
            'journal_activity' => $journalActivity,
        ];
    }

    /** @return array<int, array{type: string, severity: string, message: string, url: string}> */
    public function alerts(?int $companyId, ?array $metrics = null): array
    {
        if ($companyId === null || $companyId < 1) {
            return $this->platformAlerts();
        }

        $alerts = [];
        $pdo = Database::connection();
        $today = date('Y-m-d');

        $overdue = $pdo->prepare(
            "SELECT invoice_no, due_date, total_amount FROM rateb_invoices
             WHERE company_id = :cid AND status IN ('sent','overdue')
               AND payment_status IN ('unpaid','partial')
               AND due_date IS NOT NULL AND due_date < :today
             ORDER BY due_date ASC LIMIT 5"
        );
        $overdue->execute(['cid' => $companyId, 'today' => $today]);
        foreach ($overdue->fetchAll() ?: [] as $row) {
            $alerts[] = [
                'type' => 'overdue_invoice',
                'severity' => 'danger',
                'message' => __('invoice_overdue_alert', [
                    'no' => (string) ($row['invoice_no'] ?? ''),
                    'date' => (string) ($row['due_date'] ?? ''),
                ]),
                'url' => rateb_app_url('accounting/accounts-receivable'),
            ];
        }

        $unpaid = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM rateb_invoices
             WHERE company_id = :cid AND status IN ('sent','draft')
               AND payment_status IN ('unpaid','partial')"
        );
        $unpaid->execute(['cid' => $companyId]);
        $unpaidCount = (int) (($unpaid->fetch()['c'] ?? 0));
        if ($unpaidCount > 0) {
            $alerts[] = [
                'type' => 'unpaid_invoices',
                'severity' => 'warning',
                'message' => __('accounting_alert_unpaid_invoices', ['count' => $unpaidCount]),
                'count' => $unpaidCount,
                'icon' => 'fa-file-invoice-dollar',
                'url' => rateb_app_url('accounting/accounts-receivable'),
            ];
        }

        $draftJournals = is_array($metrics)
            ? [
                'draft_journals' => (int) ($metrics['draft_journals'] ?? 0),
                'pending_vouchers' => (int) ($metrics['pending_vouchers'] ?? 0),
            ]
            : $this->workflowCounts($companyId);
        if ((int) ($draftJournals['draft_journals'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'draft_journals',
                'severity' => 'info',
                'message' => __('accounting_alert_draft_journals', ['count' => (int) $draftJournals['draft_journals']]),
                'url' => rateb_app_url('journal-entries'),
            ];
        }

        if ((int) ($draftJournals['pending_vouchers'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'pending_vouchers',
                'severity' => 'info',
                'message' => __('accounting_alert_pending_vouchers', ['count' => (int) $draftJournals['pending_vouchers']]),
                'url' => rateb_app_url('cash-vouchers'),
            ];
        }

        if (is_array($metrics) && array_key_exists('unreconciled_bank', $metrics)) {
            $unreconciled = (int) $metrics['unreconciled_bank'];
        } else {
            $bank = $this->acct->bankReconciliation($companyId);
            $unreconciled = 0;
            foreach ($bank['accounts'] ?? [] as $acc) {
                $unreconciled += (int) ($acc['unreconciled_count'] ?? 0);
            }
        }
        if ($unreconciled > 0) {
            $alerts[] = [
                'type' => 'bank_reconciliation',
                'severity' => 'warning',
                'message' => __('accounting_alert_unreconciled_bank', ['count' => $unreconciled]),
                'url' => rateb_app_url('accounting/bank-reconciliation'),
            ];
        }

        return $alerts;
    }

    /** @return array<int, array<string, mixed>> */
    public function recentActivity(?int $companyId): array
    {
        if ($companyId === null || $companyId < 1) {
            return $this->recentPlatformActivity();
        }

        $sql = "SELECT e.entry_no, e.entry_date, e.description, e.description_ar, e.status, e.source_type,
                       COALESCE(SUM(l.debit), 0) AS total_debit
                FROM rateb_journal_entries e
                LEFT JOIN rateb_journal_lines l ON l.journal_entry_id = e.id
                WHERE e.company_id = :cid
                GROUP BY e.id
                ORDER BY e.entry_date DESC, e.id DESC
                LIMIT 8";
        return (new JournalEntry())->query($sql, ['cid' => $companyId]);
    }

    /** @return array{unpaid: int, overdue: int} */
    private function invoiceStats(int $companyId): array
    {
        $pdo = Database::connection();
        $today = date('Y-m-d');
        $unpaid = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM rateb_invoices
             WHERE company_id = :cid AND status IN ('sent','draft','overdue')
               AND payment_status IN ('unpaid','partial')"
        );
        $unpaid->execute(['cid' => $companyId]);
        $overdue = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM rateb_invoices
             WHERE company_id = :cid AND status IN ('sent','overdue')
               AND payment_status IN ('unpaid','partial')
               AND due_date IS NOT NULL AND due_date < :today"
        );
        $overdue->execute(['cid' => $companyId, 'today' => $today]);

        return [
            'unpaid' => (int) (($unpaid->fetch()['c'] ?? 0)),
            'overdue' => (int) (($overdue->fetch()['c'] ?? 0)),
        ];
    }

    /** @return array{draft_journals: int, pending_vouchers: int} */
    private function workflowCounts(int $companyId): array
    {
        $pdo = Database::connection();
        $draftJ = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM rateb_journal_entries WHERE company_id = :cid AND status = 'draft'"
        );
        $draftJ->execute(['cid' => $companyId]);
        $pendingV = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM rateb_cash_vouchers
             WHERE company_id = :cid AND status = 'draft' AND submitted_for_approval_at IS NOT NULL"
        );
        $pendingV->execute(['cid' => $companyId]);

        return [
            'draft_journals' => (int) (($draftJ->fetch()['c'] ?? 0)),
            'pending_vouchers' => (int) (($pendingV->fetch()['c'] ?? 0)),
        ];
    }

    private function revenueGrowth(?int $companyId): string
    {
        if ($companyId === null || $companyId < 1) {
            return '';
        }
        $pdo = Database::connection();
        $thisMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') AS month,
                    COALESCE(SUM(amount), 0) AS total
             FROM rateb_payments
             WHERE company_id = :cid AND status = 'completed'
               AND DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') IN (:m1, :m2)
             GROUP BY DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m')"
        );
        $stmt->execute(['cid' => $companyId, 'm1' => $thisMonth, 'm2' => $lastMonth]);
        $byMonth = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $byMonth[(string) $row['month']] = (float) ($row['total'] ?? 0);
        }
        $cur = (float) ($byMonth[$thisMonth] ?? 0);
        $prev = (float) ($byMonth[$lastMonth] ?? 0);
        if ($prev <= 0) {
            return $cur > 0 ? '+100%' : '';
        }
        $pct = round((($cur - $prev) / $prev) * 100, 1);
        return ($pct >= 0 ? '+' : '') . $pct . '%';
    }

    /** @return array<int, array{type: string, severity: string, message: string, url: string}> */
    private function platformAlerts(): array
    {
        $pdo = Database::connection();
        $alerts = [];
        $overdue = $pdo->query(
            "SELECT COUNT(*) AS c FROM rateb_invoices WHERE status = 'overdue'"
        )->fetch();
        if ((int) ($overdue['c'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'overdue_invoice',
                'severity' => 'danger',
                'message' => __('accounting_alert_unpaid_invoices', ['count' => (int) $overdue['c']]),
                'url' => rateb_url('admin/invoices'),
            ];
        }
        return $alerts;
    }

    /** @return array<int, array<string, mixed>> */
    private function recentPlatformActivity(): array
    {
        $pdo = Database::connection();
        return $pdo->query(
            "SELECT invoice_no AS entry_no, issued_at AS entry_date, status, total_amount AS total_debit,
                    CONCAT('invoice') AS source_type, invoice_no AS description
             FROM rateb_invoices
             ORDER BY issued_at DESC, id DESC LIMIT 8"
        )->fetchAll() ?: [];
    }
}
