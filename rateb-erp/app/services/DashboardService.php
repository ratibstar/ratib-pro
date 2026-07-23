<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Branch;
use Rateb\App\Models\Company;
use Rateb\App\Models\Employee;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\LoginActivity;
use Rateb\App\Models\Plan;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class DashboardService
{
    /** Fast first paint: metrics + alerts only. Charts/lists load via admin/api/dashboard-charts. */
    public function adminBuildLite(): array
    {
        $metrics = $this->adminMetrics();

        return [
            'metrics' => $metrics,
            'charts' => [],
            'alerts' => $this->adminAlerts($metrics),
            'recent_companies' => [],
            'recent_logins' => [],
            'top_companies' => [],
            'charts_deferred' => true,
        ];
    }

    /** @return array{charts: array<string, mixed>, recent_companies: list<array>, recent_logins: list<array>, top_companies: list<array>} */
    public function adminDeferredPanels(): array
    {
        return [
            'charts' => $this->adminCharts(),
            'recent_companies' => $this->recentCompanies(),
            'recent_logins' => $this->recentLogins(),
            'top_companies' => $this->topCompaniesByActivity(),
        ];
    }

    /** @return array<string, mixed> */
    public function adminBuild(): array
    {
        $metrics = $this->adminMetrics();

        return [
            'metrics' => $metrics,
            'charts' => $this->adminCharts(),
            'alerts' => $this->adminAlerts($metrics),
            'recent_companies' => $this->recentCompanies(),
            'recent_logins' => $this->recentLogins(),
            'top_companies' => $this->topCompaniesByActivity(),
        ];
    }

    public function adminMetrics(): array
    {
        $companies = (new Company())->getStats();
        $pdo = Database::connection();
        $subscriptions = (new Subscription())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_subscriptions WHERE status = 'active'"
        );
        $users = (new User())->queryOne('SELECT COUNT(*) AS c FROM rateb_users WHERE is_super_admin = 0');
        $pending = $pdo->query(
            "SELECT COUNT(*) AS c FROM rateb_companies WHERE status = 'pending'"
        )->fetch() ?: ['c' => 0];
        $expiringSubs = $pdo->query(
            "SELECT COUNT(*) AS c FROM rateb_subscriptions
             WHERE status = 'active' AND ends_at IS NOT NULL
               AND ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        )->fetch() ?: ['c' => 0];
        $newUsersMonth = $pdo->query(
            "SELECT COUNT(*) AS c FROM rateb_users
             WHERE is_super_admin = 0 AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetch() ?: ['c' => 0];
        $plans = (new Plan())->queryOne('SELECT COUNT(*) AS c FROM rateb_plans WHERE is_active = 1');

        $pendingApprovals = 0;
        if (function_exists('rateb_oversight_pending_approvals_count')) {
            $pendingApprovals = rateb_oversight_pending_approvals_count();
        }

        return [
            'total_companies' => (int) ($companies['total'] ?? 0),
            'active_companies' => (int) ($companies['active'] ?? 0),
            'suspended_companies' => (int) ($companies['suspended'] ?? 0),
            'pending_companies' => (int) ($pending['c'] ?? 0),
            'subscriptions' => (int) ($subscriptions['c'] ?? 0),
            'expiring_subscriptions' => (int) ($expiringSubs['c'] ?? 0),
            'users' => (int) ($users['c'] ?? 0),
            'new_users_month' => (int) ($newUsersMonth['c'] ?? 0),
            'active_plans' => (int) ($plans['c'] ?? 0),
            'pending_approvals' => $pendingApprovals,
        ];
    }

    public function adminCharts(): array
    {
        // Bound time window — full-table GROUP BY on login_activity blocked deferred charts (2.5s abort).
        $since = date('Y-m-01', strtotime('-11 months'));

        $companyGrowth = (new Company())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_companies
             WHERE created_at >= :since
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12",
            ['since' => $since]
        );
        $subscriptionGrowth = (new Subscription())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_subscriptions
             WHERE created_at >= :since
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12",
            ['since' => $since]
        );
        $userGrowth = (new User())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_users
             WHERE is_super_admin = 0 AND created_at >= :since
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12",
            ['since' => $since]
        );
        $statusRows = (new Company())->query(
            "SELECT status, COUNT(*) AS total FROM rateb_companies GROUP BY status"
        );
        $companyStatus = [];
        foreach ($statusRows as $row) {
            $companyStatus[] = [
                'label' => (string) ($row['status'] ?? ''),
                'value' => (int) ($row['total'] ?? 0),
            ];
        }

        $pdo = Database::connection();
        $planRows = $pdo->query(
            "SELECT COALESCE(p.name, '—') AS name, COUNT(c.id) AS total
             FROM rateb_companies c
             LEFT JOIN rateb_plans p ON p.id = c.plan_id
             GROUP BY c.plan_id ORDER BY total DESC LIMIT 6"
        )->fetchAll() ?: [];
        $planDistribution = array_map(static fn ($r) => [
            'label' => (string) ($r['name'] ?? ''),
            'value' => (int) ($r['total'] ?? 0),
        ], $planRows);

        $subStatusRows = $pdo->query(
            'SELECT status, COUNT(*) AS total FROM rateb_subscriptions GROUP BY status'
        )->fetchAll() ?: [];
        $subscriptionStatus = array_map(static fn ($r) => [
            'label' => (string) ($r['status'] ?? ''),
            'value' => (int) ($r['total'] ?? 0),
        ], $subStatusRows);

        // Skip heavy login_activity GROUP BY on shared hosts — was blocking chart hydrate.
        $loginActivity = [];
        for ($i = 5; $i >= 0; $i--) {
            $loginActivity[] = [
                'month' => date('Y-m', strtotime('-' . $i . ' months')),
                'success_total' => 0,
                'failed_total' => 0,
            ];
        }

        return [
            'company_growth' => $this->padMonthlySeries($companyGrowth),
            'subscription_growth' => $this->padMonthlySeries($subscriptionGrowth),
            'user_growth' => $this->padMonthlySeries($userGrowth),
            'company_status' => $companyStatus,
            'plan_distribution' => $planDistribution,
            'subscription_status' => $subscriptionStatus,
            'login_activity' => $loginActivity,
        ];
    }

    /**
     * @param array<string, mixed>|null $metrics Reuse metrics from adminBuild() to avoid duplicate COUNT queries.
     * @return array<int, array{type: string, severity: string, message: string, url: string}>
     */
    public function adminAlerts(?array $metrics = null): array
    {
        $m = $metrics ?? $this->adminMetrics();
        $alerts = [];

        if ((int) ($m['pending_companies'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'pending_companies',
                'severity' => 'warning',
                'message' => __('dashboard_alert_pending_companies', ['count' => (int) $m['pending_companies']]),
                'url' => rateb_url('admin/oversight/companies-approvals'),
                'count' => (int) $m['pending_companies'],
                'icon' => 'fa-hourglass-half',
            ];
        }
        if ((int) ($m['suspended_companies'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'suspended_companies',
                'severity' => 'danger',
                'message' => __('dashboard_alert_suspended_companies', ['count' => (int) $m['suspended_companies']]),
                'url' => rateb_url('admin/companies'),
                'count' => (int) $m['suspended_companies'],
                'icon' => 'fa-ban',
            ];
        }
        if ((int) ($m['expiring_subscriptions'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'expiring_subscriptions',
                'severity' => 'warning',
                'message' => __('dashboard_alert_expiring_subscriptions', ['count' => (int) $m['expiring_subscriptions']]),
                'url' => rateb_url('admin/subscriptions'),
                'count' => (int) $m['expiring_subscriptions'],
                'icon' => 'fa-calendar-xmark',
            ];
        }
        if ((int) ($m['pending_approvals'] ?? 0) > 0 && rateb_nav_can('workflows.view')) {
            $alerts[] = [
                'type' => 'pending_approvals',
                'severity' => 'info',
                'message' => __('dashboard_alert_pending_approvals', ['count' => (int) $m['pending_approvals']]),
                'url' => rateb_url('admin/oversight/approvals'),
                'count' => (int) $m['pending_approvals'],
                'icon' => 'fa-clipboard-check',
            ];
        }

        return $alerts;
    }

    /** @return array<int, array<string, mixed>> */
    public function recentCompanies(): array
    {
        return (new Company())->query(
            'SELECT id, name, email, status, created_at FROM rateb_companies ORDER BY created_at DESC LIMIT 8'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recentLogins(): array
    {
        return (new LoginActivity())->query(
            'SELECT la.email, la.success, la.created_at, u.name AS user_name
             FROM rateb_login_activity la
             LEFT JOIN rateb_users u ON u.id = la.user_id
             WHERE la.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             ORDER BY la.created_at DESC LIMIT 8'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function topCompaniesByActivity(): array
    {
        return (new User())->query(
            'SELECT c.name AS company_name, COUNT(u.id) AS user_count
             FROM rateb_users u
             JOIN rateb_companies c ON c.id = u.company_id
             WHERE u.is_super_admin = 0
             GROUP BY u.company_id
             ORDER BY user_count DESC
             LIMIT 5'
        );
    }

    public function companyMetrics(int $companyId): array
    {
        $this->bootstrapCompanyContext($companyId);
        $inventory = new Inventory();
        $invValue = $inventory->totalValue();
        $lowStock = 0;
        $row = $inventory->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_inventory
             WHERE quantity > 0
               AND quantity <= GREATEST(COALESCE(reorder_level, 0), COALESCE(min_stock, 0), 0)'
        );
        if ($row) {
            $lowStock = (int) ($row['c'] ?? 0);
        }

        return [
            'purchase_requests' => (new PurchaseRequest())->count(),
            'purchase_orders' => (new PurchaseOrder())->count(),
            'pending_purchase_requests' => $this->pendingPurchaseRequestCount(),
            'inventory_items' => $inventory->count(),
            'inventory_value' => $invValue,
            'inventory_value_fmt' => number_format($invValue, 0) . ' ' . __('sar'),
            'low_stock_items' => $lowStock,
            'suppliers' => (new \Rateb\App\Models\Supplier())->count(),
            'employees' => $this->employeeCount(),
            'branches' => (new Branch())->count(['status' => 'active']),
        ];
    }

    /** @return array<string, mixed> */
    public function companyBuild(int $companyId): array
    {
        $this->bootstrapCompanyContext($companyId);
        $company = (new Company())->find($companyId) ?: [];
        $limits = (new \Rateb\App\Services\PlanLimitService())->getLimits($companyId);
        $metrics = $this->companyMetrics($companyId);

        return [
            'company_id' => $companyId,
            'company_name' => (string) ($company['name'] ?? ''),
            'company_status' => (string) ($company['status'] ?? ''),
            'metrics' => $metrics,
            'charts' => $this->companyCharts($companyId),
            'modules' => $this->companyModuleTiles($limits['modules'] ?? [], $companyId),
            'recent_activity' => $this->companyRecentActivity($companyId),
            'limits' => $limits,
        ];
    }

    /** @return array<string, mixed> */
    public function companyCharts(int $companyId): array
    {
        $this->bootstrapCompanyContext($companyId);
        $prRows = (new PurchaseRequest())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_purchase_requests
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC"
        );
        $poRows = (new PurchaseOrder())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_purchase_orders
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC"
        );
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime('-' . $i . ' months'));
        }
        $prMap = [];
        foreach ($prRows as $row) {
            $prMap[(string) ($row['month'] ?? '')] = (int) ($row['total'] ?? 0);
        }
        $poMap = [];
        foreach ($poRows as $row) {
            $poMap[(string) ($row['month'] ?? '')] = (int) ($row['total'] ?? 0);
        }
        $prSeries = [];
        $poSeries = [];
        foreach ($months as $month) {
            $prSeries[] = $prMap[$month] ?? 0;
            $poSeries[] = $poMap[$month] ?? 0;
        }

        $health = (new Inventory())->queryOne(
            "SELECT
                SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN quantity > 0 AND quantity <= GREATEST(COALESCE(reorder_level, 0), COALESCE(min_stock, 0), 0) THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN quantity > GREATEST(COALESCE(reorder_level, 0), COALESCE(min_stock, 0), 0)
                          AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) AS healthy
             FROM rateb_inventory"
        ) ?: [];

        return [
            'procurement_trend' => [
                'labels' => $months,
                'purchase_requests' => $prSeries,
                'purchase_orders' => $poSeries,
            ],
            'inventory_health' => [
                ['label' => __('inventory_health_ok'), 'value' => (int) ($health['healthy'] ?? 0)],
                ['label' => __('inventory_health_low'), 'value' => (int) ($health['low_stock'] ?? 0)],
                ['label' => __('inventory_health_out'), 'value' => (int) ($health['out_of_stock'] ?? 0)],
                ['label' => __('inventory_health_expired'), 'value' => (int) ($health['expired'] ?? 0)],
            ],
        ];
    }

    /** @param array<int, string> $planModules @return array<int, array<string, string>> */
    public function companyModuleTiles(array $planModules, int $companyId = 0): array
    {
        $catalog = [
            'procurement' => ['path' => 'purchase-requests', 'icon' => 'fa-cart-shopping', 'label' => 'procurement'],
            'inventory' => ['path' => 'inventory', 'icon' => 'fa-boxes-stacked', 'label' => 'inventory'],
            'suppliers' => ['path' => 'suppliers', 'icon' => 'fa-truck-field', 'label' => 'suppliers'],
            'hr' => ['path' => 'hr/employees', 'icon' => 'fa-users', 'label' => 'hr_employees'],
            'accounting' => ['path' => 'accounting', 'icon' => 'fa-calculator', 'label' => 'accounting_module', 'permission' => 'accounting.view'],
            'contracts' => ['path' => 'contracts', 'icon' => 'fa-file-contract', 'label' => 'contracts'],
            'assets' => ['path' => 'assets', 'icon' => 'fa-toolbox', 'label' => 'assets'],
            'tenders' => ['path' => 'tenders', 'icon' => 'fa-gavel', 'label' => 'tenders'],
            'reports' => ['path' => 'reports', 'icon' => 'fa-chart-pie', 'label' => 'reports'],
            'documents' => ['path' => 'documents', 'icon' => 'fa-folder-open', 'label' => 'documents'],
            'medical_devices' => ['path' => 'medical-devices', 'icon' => 'fa-stethoscope', 'label' => 'medical_devices'],
        ];

        $tiles = [];
        $seen = [];
        foreach ($planModules as $module) {
            $module = (string) $module;
            if ($module === '' || isset($seen[$module])) {
                continue;
            }
            $seen[$module] = true;
            $def = $catalog[$module] ?? null;
            if ($def === null) {
                continue;
            }
            $entity = function_exists('rateb_entity_perms') ? rateb_entity_perms($def['path']) : ['view' => '', 'module' => $module];
            $permission = (string) ($def['permission'] ?? $entity['view'] ?? '');
            $mod = (string) ($entity['module'] ?? $module);
            if (!$this->moduleTileAllowed($permission, $mod, $companyId)) {
                continue;
            }
            $tiles[] = [
                'href' => rateb_app_url($def['path']),
                'label' => __($def['label']),
                'icon' => (string) $def['icon'],
            ];
        }

        if ($this->moduleTileAllowed('accounting.view', 'accounting', $companyId) && !isset($seen['accounting'])) {
            $tiles[] = [
                'href' => rateb_app_url('accounting'),
                'label' => __('accounting_module'),
                'icon' => 'fa-calculator',
            ];
        }
        if ($this->moduleTileAllowed('notifications.view', 'notifications', $companyId)) {
            $tiles[] = [
                'href' => rateb_app_url('notifications'),
                'label' => __('notifications'),
                'icon' => 'fa-bell',
            ];
        }

        return $tiles;
    }

    /** @return array<int, array<string, mixed>> */
    public function companyRecentActivity(int $companyId): array
    {
        $this->bootstrapCompanyContext($companyId);
        $rows = (new PurchaseRequest())->query(
            "SELECT 'purchase_request' AS kind, request_no AS ref, title, status, created_at
             FROM rateb_purchase_requests
             ORDER BY created_at DESC LIMIT 4"
        );
        $poRows = (new PurchaseOrder())->query(
            "SELECT 'purchase_order' AS kind, order_no AS ref, title, status, created_at
             FROM rateb_purchase_orders
             ORDER BY created_at DESC LIMIT 4"
        );
        $merged = array_merge($rows, $poRows);
        usort($merged, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($merged, 0, 6);
    }

    private function bootstrapCompanyContext(int $companyId): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
    }

    private function employeeCount(): int
    {
        $active = (new Employee())->count(['status' => 'active']);
        if ($active > 0) {
            return $active;
        }

        return (new Employee())->count();
    }

    private function moduleTileAllowed(string $permission, string $module, int $companyId): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            if ($companyId > 0 && $module !== '') {
                return (new \Rateb\App\Services\PlanLimitService())->companyHasModule($companyId, $module);
            }

            return true;
        }

        return rateb_nav_can($permission, $module);
    }

    private function pendingPurchaseRequestCount(): int
    {
        $row = (new PurchaseRequest())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_purchase_requests
             WHERE status IN ('draft', 'submitted', 'pending', 'in_review')"
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function padMonthlySeries(array $rows): array
    {
        $byMonth = [];
        foreach ($rows as $row) {
            $m = (string) ($row['month'] ?? '');
            if ($m === '') {
                continue;
            }
            $byMonth[$m] = (int) ($row['total'] ?? 0);
        }
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime('-' . $i . ' months'));
            $out[] = ['month' => $month, 'total' => $byMonth[$month] ?? 0];
        }

        return $out;
    }
}
