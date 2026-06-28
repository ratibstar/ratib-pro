<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\LoginActivity;
use Rateb\App\Models\Plan;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class DashboardService
{
    /** @return array<string, mixed> */
    public function adminBuild(): array
    {
        return [
            'metrics' => $this->adminMetrics(),
            'charts' => $this->adminCharts(),
            'alerts' => $this->adminAlerts(),
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
        $companyGrowth = (new Company())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_companies GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12"
        );
        $subscriptionGrowth = (new Subscription())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_subscriptions GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12"
        );
        $userGrowth = (new User())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_users WHERE is_super_admin = 0
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12"
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

        $loginActivity = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS success_total,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) AS failed_total
             FROM rateb_login_activity
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC LIMIT 12"
        )->fetchAll() ?: [];
        if ($loginActivity === []) {
            for ($i = 5; $i >= 0; $i--) {
                $loginActivity[] = [
                    'month' => date('Y-m', strtotime('-' . $i . ' months')),
                    'success_total' => 0,
                    'failed_total' => 0,
                ];
            }
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

    /** @return array<int, array{type: string, severity: string, message: string, url: string}> */
    public function adminAlerts(): array
    {
        $m = $this->adminMetrics();
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
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
        return [
            'purchase_requests' => (new PurchaseRequest())->count(),
            'purchase_orders' => (new PurchaseOrder())->count(),
            'inventory_value' => (new Inventory())->totalValue(),
            'suppliers' => (new \Rateb\App\Models\Supplier())->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function companyBuild(int $companyId): array
    {
        return [
            'metrics' => $this->companyMetrics($companyId),
        ];
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
}
