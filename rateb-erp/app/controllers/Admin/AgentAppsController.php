<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Services\MobileAppConfigService;

/**
 * Agent / Agency App Management console under Admin.
 * Dashboard + ops modules for white-label Workforce apps (not public/v2).
 */
final class AgentAppsController extends Controller
{
    /** @var array<string, array{title:string,icon:string,tone:string,desc:string}> */
    private const SECTIONS = [
        'settings' => [
            'title' => 'agent_apps_settings',
            'icon' => 'fa-sliders',
            'tone' => 'blue',
            'desc' => 'agent_apps_settings_desc',
        ],
        'ratings' => [
            'title' => 'agent_apps_ratings',
            'icon' => 'fa-star',
            'tone' => 'cyan',
            'desc' => 'agent_apps_ratings_desc',
        ],
        'complaints' => [
            'title' => 'agent_apps_complaints',
            'icon' => 'fa-exclamation-triangle',
            'tone' => 'orange',
            'desc' => 'agent_apps_complaints_desc',
        ],
        'notifications' => [
            'title' => 'agent_apps_notifications',
            'icon' => 'fa-bell',
            'tone' => 'red',
            'desc' => 'agent_apps_notifications_desc',
        ],
        'payments' => [
            'title' => 'agent_apps_payments',
            'icon' => 'fa-credit-card',
            'tone' => 'slate',
            'desc' => 'agent_apps_payments_desc',
        ],
        'content' => [
            'title' => 'agent_apps_content',
            'icon' => 'fa-file-lines',
            'tone' => 'purple',
            'desc' => 'agent_apps_content_desc',
        ],
        'offers' => [
            'title' => 'agent_apps_offers',
            'icon' => 'fa-image',
            'tone' => 'teal',
            'desc' => 'agent_apps_offers_desc',
        ],
        'invoices' => [
            'title' => 'agent_apps_invoices',
            'icon' => 'fa-file-invoice',
            'tone' => 'navy',
            'desc' => 'agent_apps_invoices_desc',
        ],
    ];

    public function dashboard(): void
    {
        if (!$this->canView()) {
            http_response_code(403);
            echo '403';
            return;
        }

        $this->view('admin/agent-apps/dashboard', [
            'title' => __('agent_apps_dashboard_title'),
            'activeSection' => 'dashboard',
            'modules' => $this->buildModules(),
            'stats' => $this->buildStats(),
            'canManage' => $this->canManage(),
        ], 'main');
    }

    public function section(array $params = []): void
    {
        if (!$this->canView()) {
            http_response_code(403);
            echo '403';
            return;
        }

        $key = trim((string) ($params['section'] ?? ''));
        if ($key === 'requests' || $key === '' || !isset(self::SECTIONS[$key])) {
            Response::redirect(rateb_url('admin/agent-apps'));
            return;
        }

        $meta = self::SECTIONS[$key];
        $this->view('admin/agent-apps/section', [
            'title' => __($meta['title']),
            'activeSection' => $key,
            'sectionKey' => $key,
            'sectionMeta' => $meta,
            'stats' => $this->buildStats(),
            'canManage' => $this->canManage(),
            'mobileAppsUrl' => rateb_url('admin/mobile-apps'),
        ], 'main');
    }

    private function canView(): bool
    {
        return rateb_is_super_admin()
            || rateb_can('mobile_apps.view')
            || rateb_can('settings.manage');
    }

    private function canManage(): bool
    {
        return rateb_is_super_admin()
            || rateb_can('mobile_apps.manage')
            || rateb_can('settings.manage');
    }

    /** @return list<array<string, mixed>> */
    private function buildModules(): array
    {
        $out = [];
        foreach (self::SECTIONS as $key => $meta) {
            $out[] = [
                'key' => $key,
                'title' => __($meta['title']),
                'desc' => __($meta['desc']),
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'url' => rateb_url('admin/agent-apps/' . $key),
                'cta' => __('agent_apps_open_module'),
            ];
        }
        $out[] = [
            'key' => 'branding',
            'title' => __('mobile_apps_title'),
            'desc' => __('mobile_apps_intro'),
            'icon' => 'fa-mobile-alt',
            'tone' => 'green',
            'url' => rateb_url('admin/mobile-apps'),
            'cta' => __('agent_apps_manage_branding'),
        ];
        return $out;
    }

    /** @return list<array{key:string,label:string,value:string,icon:string,tone:string}> */
    private function buildStats(): array
    {
        $activeApps = 0;
        $totalCompanies = 0;
        try {
            $rows = (new MobileAppConfigService())->listCompaniesWithConfig();
            $totalCompanies = count($rows);
            foreach ($rows as $row) {
                if ((string) ($row['mobile_status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE) {
                    $activeApps++;
                }
            }
        } catch (\Throwable $e) {
            // Stats are progressive; keep zeros on failure.
        }

        return [
            [
                'key' => 'active_apps',
                'label' => __('agent_apps_stat_active_apps'),
                'value' => (string) $activeApps,
                'icon' => 'fa-mobile-screen-button',
                'tone' => 'blue',
            ],
            [
                'key' => 'companies',
                'label' => __('agent_apps_stat_companies'),
                'value' => (string) $totalCompanies,
                'icon' => 'fa-building',
                'tone' => 'green',
            ],
            [
                'key' => 'notifications',
                'label' => __('agent_apps_stat_notifications'),
                'value' => '0',
                'icon' => 'fa-bell',
                'tone' => 'teal',
            ],
            [
                'key' => 'revenue',
                'label' => __('agent_apps_stat_revenue'),
                'value' => '0 ' . __('currency_sar'),
                'icon' => 'fa-money-bill-wave',
                'tone' => 'green',
            ],
            [
                'key' => 'rating',
                'label' => __('agent_apps_stat_rating'),
                'value' => '0/5',
                'icon' => 'fa-star',
                'tone' => 'gold',
            ],
            [
                'key' => 'complaints',
                'label' => __('agent_apps_stat_complaints'),
                'value' => '0',
                'icon' => 'fa-triangle-exclamation',
                'tone' => 'red',
            ],
        ];
    }
}
