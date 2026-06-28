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

    public function __construct(?AccountingService $acct = null)
    {
        $this->acct = $acct ?? new AccountingService();
    }

    /** @return array<string, mixed> */
    public function build(?int $companyId): array
    {
        return [
            'metrics' => $this->metrics($companyId),
            'kpis' => $this->kpiList($companyId),
            'charts' => $this->charts($companyId),
            'alerts' => $this->alerts($companyId),
            'recent' => $this->recentActivity($companyId),
        ];
    }

    /** @return array<string, float|int> */
    public function metrics(?int $companyId): array
    {
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
        ];

        if ($companyId === null || $companyId < 1) {
            $pdo = Database::connection();
            $metrics['purchase_requests'] = (int) (($pdo->query('SELECT COUNT(*) AS c FROM rateb_purchase_requests')->fetch()['c'] ?? 0));
            $metrics['purchase_orders'] = (int) (($pdo->query('SELECT COUNT(*) AS c FROM rateb_purchase_orders')->fetch()['c'] ?? 0));
            $metrics['inventory_value'] = (new Inventory())->totalValue();
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

        $cfo = $this->acct->cfoMetrics($companyId);
        $vat = $this->acct->vatReport($companyId, date('Y') . '-01-01', date('Y-m-d'));
        $invoiceStats = $this->invoiceStats($companyId);
        $workflow = $this->workflowCounts($companyId);
        $bank = $this->acct->bankReconciliation($companyId);
        $unreconciled = 0;
        foreach ($bank['accounts'] ?? [] as $acc) {
            $unreconciled += (int) ($acc['unreconciled_count'] ?? 0);
        }

        $metrics['cash_position'] = (float) ($cfo['cash_position'] ?? 0);
        $metrics['ar_open'] = (float) ($cfo['ar_open'] ?? 0);
        $metrics['ap_open'] = (float) ($cfo['ap_open'] ?? 0);
        $metrics['revenue_ytd'] = (float) ($cfo['revenue_ytd'] ?? 0);
        $metrics['expenses_ytd'] = (float) ($cfo['expenses_ytd'] ?? 0);
        $metrics['net_profit_ytd'] = (float) ($cfo['net_margin'] ?? 0);
        $metrics['vat_net'] = (float) ($vat['net_vat'] ?? 0);
        $metrics['unpaid_invoices'] = (int) ($invoiceStats['unpaid'] ?? 0);
        $metrics['overdue_invoices'] = (int) ($invoiceStats['overdue'] ?? 0);
        $metrics['draft_journals'] = (int) ($workflow['draft_journals'] ?? 0);
        $metrics['pending_vouchers'] = (int) ($workflow['pending_vouchers'] ?? 0);
        $metrics['unreconciled_bank'] = $unreconciled;

        return $metrics;
    }

    /** @return array<int, array{key: string, label: string, value: string, trend: string, icon: string}> */
    public function kpiList(?int $companyId): array
    {
        $m = $this->metrics($companyId);
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

        $arAp = [];
        if ($companyId !== null && $companyId > 0) {
            $arData = $this->acct->accountsReceivable($companyId);
            $apData = $this->acct->accountsPayable($companyId);
            $arAp = [
                ['label' => 'ar_open', 'value' => (float) ($arData['total_open'] ?? 0)],
                ['label' => 'ap_open', 'value' => (float) ($apData['total_open'] ?? 0)],
            ];
        }

        return [
            'monthly_revenue' => $revenue,
            'monthly_expenses' => $expenses,
            'ar_ap' => $arAp,
        ];
    }

    /** @return array<int, array{type: string, severity: string, message: string, url: string}> */
    public function alerts(?int $companyId): array
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
                'url' => rateb_app_url('accounting/accounts-receivable'),
            ];
        }

        $draftJournals = $this->workflowCounts($companyId);
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

        $bank = $this->acct->bankReconciliation($companyId);
        $unreconciled = 0;
        foreach ($bank['accounts'] ?? [] as $acc) {
            $unreconciled += (int) ($acc['unreconciled_count'] ?? 0);
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
