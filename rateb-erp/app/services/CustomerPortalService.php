<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Auth;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;
use Rateb\App\Models\Notification;
use Rateb\App\Models\Plan;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class CustomerPortalService
{
    /** @return array<string, array{route:string,icon:string,subs?:list<array{route:string,label:string}>}> */
    public static function moduleCatalog(): array
    {
        return [
            'procurement' => [
                'route' => 'purchase-requests',
                'icon' => 'fa-file-circle-plus',
                'subs' => [
                    ['route' => 'purchase-orders', 'label' => 'purchase_orders'],
                    ['route' => 'rfq', 'label' => 'rfq'],
                    ['route' => 'quotations', 'label' => 'quotations'],
                ],
            ],
            'inventory' => [
                'route' => 'inventory',
                'icon' => 'fa-boxes-stacked',
                'subs' => [
                    ['route' => 'warehouses', 'label' => 'warehouses'],
                    ['route' => 'stock-movements', 'label' => 'stock_movements'],
                    ['route' => 'product-categories', 'label' => 'product_categories'],
                ],
            ],
            'suppliers' => [
                'route' => 'suppliers',
                'icon' => 'fa-truck',
                'subs' => [
                    ['route' => 'supplier-evaluations', 'label' => 'supplier_evaluations'],
                ],
            ],
            'assets' => ['route' => 'assets', 'icon' => 'fa-building'],
            'contracts' => ['route' => 'contracts', 'icon' => 'fa-file-contract'],
            'tenders' => ['route' => 'tenders', 'icon' => 'fa-gavel'],
            'reports' => ['route' => 'reports', 'icon' => 'fa-chart-bar'],
            'medical_devices' => ['route' => 'medical-devices', 'icon' => 'fa-stethoscope'],
            'accounting' => [
                'route' => 'accounting',
                'icon' => 'fa-calculator',
                'subs' => [
                    ['route' => 'chart-of-accounts', 'label' => 'chart_of_accounts'],
                    ['route' => 'journal-entries', 'label' => 'journal_entries'],
                ],
            ],
            'documents' => ['route' => 'documents', 'icon' => 'fa-folder-open'],
            'workflows' => ['route' => 'workflows', 'icon' => 'fa-diagram-project'],
        ];
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new \RuntimeException('Not authenticated');
        }

        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1) {
            throw new \RuntimeException('No company context');
        }

        TenantContext::setCompanyId($companyId);
        $company = (new Company())->find($companyId);
        $limits = (new PlanLimitService())->getLimits($companyId);
        $metrics = (new DashboardService())->companyMetrics($companyId);
        $userCount = (new User())->count(['company_id' => $companyId]);
        $storageUsed = (new PlanLimitService())->storageUsedBytes($companyId);
        $subscription = $this->latestSubscription($companyId);
        $modules = $this->enabledModules($companyId, $limits['modules'] ?? []);
        $unreadNotifications = $this->unreadCount($companyId, (int) $user['id']);

        return [
            'user' => $user,
            'company' => $company,
            'limits' => $limits,
            'metrics' => $metrics,
            'userCount' => $userCount,
            'storageUsedMb' => round($storageUsed / 1024 / 1024, 1),
            'subscription' => $subscription,
            'trialDaysLeft' => $this->trialDaysLeft($subscription),
            'modules' => $modules,
            'unreadNotifications' => $unreadNotifications,
            'quickLinks' => $this->quickLinks(),
        ];
    }

    /** @param list<string> $enabled */
    /** @return list<array{key:string,label:string,icon:string,url:string,subs:list<array{label:string,url:string}>}> */
    private function enabledModules(int $companyId, array $enabled): array
    {
        $catalog = self::moduleCatalog();
        $planSvc = new PlanLimitService();
        $out = [];

        foreach ($enabled as $moduleKey) {
            if (!isset($catalog[$moduleKey])) {
                continue;
            }
            if (!$planSvc->companyHasModule($companyId, $moduleKey)) {
                continue;
            }
            $def = $catalog[$moduleKey];
            $subs = [];
            foreach ($def['subs'] ?? [] as $sub) {
                $subs[] = [
                    'label' => __($sub['label']),
                    'url' => rateb_app_url($sub['route']),
                ];
            }
            $out[] = [
                'key' => $moduleKey,
                'label' => __($moduleKey),
                'icon' => $def['icon'],
                'url' => rateb_app_url($def['route']),
                'subs' => $subs,
            ];
        }

        return $out;
    }

    /** @return list<array{label:string,url:string,icon:string}> */
    private function quickLinks(): array
    {
        return [
            ['label' => __('profile'), 'url' => rateb_app_url('profile'), 'icon' => 'fa-user-gear'],
            ['label' => __('notifications'), 'url' => rateb_app_url('notifications'), 'icon' => 'fa-bell'],
            ['label' => __('cms_view_plans'), 'url' => rateb_url('site/pricing'), 'icon' => 'fa-tags'],
            ['label' => __('cms_contact_us'), 'url' => rateb_url('site/contact'), 'icon' => 'fa-headset'],
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestSubscription(int $companyId): ?array
    {
        $row = (new Subscription())->queryOne(
            'SELECT s.*, p.name AS plan_name, p.slug AS plan_slug
             FROM rateb_subscriptions s
             LEFT JOIN rateb_plans p ON p.id = s.plan_id
             WHERE s.company_id = :cid
             ORDER BY s.id DESC LIMIT 1',
            ['cid' => $companyId]
        );
        if (!$row) {
            return null;
        }
        $row['plan_display'] = Plan::marketingName([
            'name' => $row['plan_name'] ?? '',
            'slug' => $row['plan_slug'] ?? '',
        ]);
        return $row;
    }

    /** @param array<string, mixed>|null $subscription */
    private function trialDaysLeft(?array $subscription): ?int
    {
        if (!$subscription) {
            return null;
        }
        $status = (string) ($subscription['status'] ?? '');
        $ends = (string) ($subscription['ends_at'] ?? '');
        if ($ends === '' || !in_array($status, ['trial', 'active'], true)) {
            return null;
        }
        $diff = (int) floor((strtotime($ends . ' 23:59:59') - time()) / 86400);
        return max(0, $diff);
    }

    private function unreadCount(int $companyId, int $userId): int
    {
        $row = (new Notification())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_notifications
             WHERE company_id = :cid AND is_read = 0
               AND (user_id IS NULL OR user_id = :uid)',
            ['cid' => $companyId, 'uid' => $userId]
        );
        return (int) ($row['c'] ?? 0);
    }
}
