<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Company;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\Payment;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class DashboardService
{
    public function adminMetrics(): array
    {
        $companies = (new Company())->getStats();
        $revenue = (new Payment())->queryOne(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM rateb_payments WHERE status = 'completed'"
        );
        $subscriptions = (new Subscription())->queryOne('SELECT COUNT(*) AS c FROM rateb_subscriptions WHERE status = \'active\'');
        $users = (new User())->queryOne('SELECT COUNT(*) AS c FROM rateb_users WHERE is_super_admin = 0');
        $purchaseRequests = (new PurchaseRequest())->queryOne('SELECT COUNT(*) AS c FROM rateb_purchase_requests');
        $purchaseOrders = (new PurchaseOrder())->queryOne('SELECT COUNT(*) AS c FROM rateb_purchase_orders');
        $inventoryValue = (new Inventory())->totalValue();

        return [
            'total_companies' => (int) ($companies['total'] ?? 0),
            'active_companies' => (int) ($companies['active'] ?? 0),
            'revenue' => (float) ($revenue['total'] ?? 0),
            'subscriptions' => (int) ($subscriptions['c'] ?? 0),
            'users' => (int) ($users['c'] ?? 0),
            'purchase_requests' => (int) ($purchaseRequests['c'] ?? 0),
            'purchase_orders' => (int) ($purchaseOrders['c'] ?? 0),
            'inventory_value' => $inventoryValue,
        ];
    }

    public function adminCharts(): array
    {
        $revenue = (new Payment())->query(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month, COALESCE(SUM(amount), 0) AS total
             FROM rateb_payments WHERE status = 'completed' AND paid_at IS NOT NULL
             GROUP BY DATE_FORMAT(paid_at, '%Y-%m') ORDER BY month ASC LIMIT 12"
        );
        $companyGrowth = (new Company())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_companies GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12"
        );
        $subscriptionGrowth = (new Subscription())->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
             FROM rateb_subscriptions GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC LIMIT 12"
        );

        return [
            'monthly_revenue' => $revenue,
            'company_growth' => $companyGrowth,
            'subscription_growth' => $subscriptionGrowth,
        ];
    }

    public function companyMetrics(int $companyId): array
    {
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        return [
            'purchase_requests' => (new PurchaseRequest())->count(),
            'purchase_orders' => (new PurchaseOrder())->count(),
            'inventory_value' => (new Inventory())->totalValue(),
            'suppliers' => (new \Rateb\App\Models\Supplier())->count(),
        ];
    }
}
