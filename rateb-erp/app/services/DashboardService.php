<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Company;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class DashboardService
{
    public function adminMetrics(): array
    {
        $companies = (new Company())->getStats();
        $subscriptions = (new Subscription())->queryOne('SELECT COUNT(*) AS c FROM rateb_subscriptions WHERE status = \'active\'');
        $users = (new User())->queryOne('SELECT COUNT(*) AS c FROM rateb_users WHERE is_super_admin = 0');

        return [
            'total_companies' => (int) ($companies['total'] ?? 0),
            'active_companies' => (int) ($companies['active'] ?? 0),
            'subscriptions' => (int) ($subscriptions['c'] ?? 0),
            'users' => (int) ($users['c'] ?? 0),
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

        return [
            'company_growth' => $companyGrowth,
            'subscription_growth' => $subscriptionGrowth,
        ];
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
}
