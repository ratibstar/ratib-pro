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
    /** @return array<string, array{icon:string,subs?:list<string>}> */
    public static function moduleCatalog(): array
    {
        return [
            'procurement' => [
                'icon' => 'fa-file-circle-plus',
                'subs' => ['purchase_orders', 'rfq', 'quotations'],
            ],
            'inventory' => [
                'icon' => 'fa-boxes-stacked',
                'subs' => ['warehouses', 'stock_movements', 'product_categories'],
            ],
            'suppliers' => [
                'icon' => 'fa-truck',
                'subs' => ['supplier_evaluations'],
            ],
            'assets' => ['icon' => 'fa-building'],
            'contracts' => ['icon' => 'fa-file-contract'],
            'tenders' => ['icon' => 'fa-gavel'],
            'reports' => ['icon' => 'fa-chart-bar'],
            'medical_devices' => ['icon' => 'fa-stethoscope'],
            'accounting' => [
                'icon' => 'fa-calculator',
                'subs' => ['chart_of_accounts', 'journal_entries'],
            ],
            'documents' => ['icon' => 'fa-folder-open'],
            'workflows' => ['icon' => 'fa-diagram-project'],
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
        $modules = $this->planModules($companyId, $limits['modules'] ?? []);
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
            'quickLinks' => $this->quickLinks($unreadNotifications),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function notifications(int $companyId, int $userId, int $limit = 50): array
    {
        return (new Notification())->query(
            'SELECT * FROM rateb_notifications
             WHERE company_id = :cid AND (user_id IS NULL OR user_id = :uid)
             ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            ['cid' => $companyId, 'uid' => $userId]
        );
    }

    /** @param list<string> $enabled */
    /** @return list<array{key:string,label:string,icon:string,subs:list<string>}> */
    private function planModules(int $companyId, array $enabled): array
    {
        $catalog = self::moduleCatalog();
        $planSvc = new PlanLimitService();
        $out = [];

        foreach ($enabled as $moduleKey) {
            if (!isset($catalog[$moduleKey]) || !$planSvc->companyHasModule($companyId, $moduleKey)) {
                continue;
            }
            $def = $catalog[$moduleKey];
            $subs = [];
            foreach ($def['subs'] ?? [] as $subKey) {
                $subs[] = __($subKey);
            }
            $out[] = [
                'key' => $moduleKey,
                'label' => __($moduleKey),
                'icon' => $def['icon'],
                'subs' => $subs,
            ];
        }

        return $out;
    }

    /** @return list<array{label:string,url:string,icon:string,badge?:int}> */
    private function quickLinks(int $unreadNotifications): array
    {
        $links = [
            ['label' => __('profile'), 'url' => rateb_url('site/portal/profile'), 'icon' => 'fa-user-gear'],
            ['label' => __('cms_view_plans'), 'url' => rateb_url('site/pricing'), 'icon' => 'fa-tags'],
            ['label' => __('cms_contact_us'), 'url' => rateb_url('site/contact'), 'icon' => 'fa-headset'],
            ['label' => __('password_forgot'), 'url' => rateb_url('password/forgot'), 'icon' => 'fa-key'],
        ];
        $notif = [
            'label' => __('notifications'),
            'url' => rateb_url('site/portal/notifications'),
            'icon' => 'fa-bell',
        ];
        if ($unreadNotifications > 0) {
            $notif['badge'] = $unreadNotifications;
        }
        array_splice($links, 1, 0, [$notif]);
        return $links;
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
