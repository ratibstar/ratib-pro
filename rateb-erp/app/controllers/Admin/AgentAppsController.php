<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AgentAppsOpsService;
use Rateb\App\Services\MobileAppConfigService;

/**
 * Agent / Agency App Management console under Admin.
 * Live modules use ESS/HR/notification/mobile-config data; unfinished modules show honest empty states.
 */
final class AgentAppsController extends Controller
{
    /** @var array<string, array{title:string,icon:string,tone:string,desc:string,mode:string}> */
    private const SECTIONS = [
        'settings' => [
            'title' => 'agent_apps_settings',
            'icon' => 'fa-sliders',
            'tone' => 'blue',
            'desc' => 'agent_apps_settings_desc',
            'mode' => 'settings',
        ],
        'complaints' => [
            'title' => 'agent_apps_complaints',
            'icon' => 'fa-exclamation-triangle',
            'tone' => 'orange',
            'desc' => 'agent_apps_complaints_desc',
            'mode' => 'list',
        ],
        'ratings' => [
            'title' => 'agent_apps_ratings',
            'icon' => 'fa-star',
            'tone' => 'cyan',
            'desc' => 'agent_apps_ratings_desc',
            'mode' => 'list',
        ],
        'notifications' => [
            'title' => 'agent_apps_notifications',
            'icon' => 'fa-bell',
            'tone' => 'red',
            'desc' => 'agent_apps_notifications_desc',
            'mode' => 'list',
        ],
        'payments' => [
            'title' => 'agent_apps_payments',
            'icon' => 'fa-credit-card',
            'tone' => 'slate',
            'desc' => 'agent_apps_payments_desc',
            'mode' => 'payments',
        ],
        'invoices' => [
            'title' => 'agent_apps_invoices',
            'icon' => 'fa-file-invoice',
            'tone' => 'navy',
            'desc' => 'agent_apps_invoices_desc',
            'mode' => 'invoices',
        ],
        'content' => [
            'title' => 'agent_apps_content',
            'icon' => 'fa-file-lines',
            'tone' => 'purple',
            'desc' => 'agent_apps_content_desc',
            'mode' => 'soon',
        ],
        'offers' => [
            'title' => 'agent_apps_offers',
            'icon' => 'fa-image',
            'tone' => 'teal',
            'desc' => 'agent_apps_offers_desc',
            'mode' => 'soon',
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
        $mode = (string) ($meta['mode'] ?? 'soon');
        $ops = new AgentAppsOpsService();
        $common = [
            'title' => __($meta['title']),
            'activeSection' => $key,
            'sectionKey' => $key,
            'sectionMeta' => $meta,
            'stats' => $this->buildStats(),
            'canManage' => $this->canManage(),
            'csrf' => Csrf::token(),
            'mobileAppsUrl' => rateb_url('admin/mobile-apps'),
        ];

        if ($mode === 'settings') {
            $rows = [];
            try {
                $svc = new MobileAppConfigService();
                $rows = $svc->listCompaniesWithConfig();
                foreach ($rows as &$row) {
                    $row['features'] = $svc->decodeFeatures($row['enabled_features'] ?? null);
                    $row['mobile_active'] = (string) ($row['mobile_status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE;
                }
                unset($row);
            } catch (\Throwable $e) {
                $rows = [];
            }
            $this->view('admin/agent-apps/settings', array_merge($common, [
                'rows' => $rows,
            ]), 'main');
            return;
        }

        if ($mode === 'payments') {
            $this->view('admin/agent-apps/payments', array_merge($common, [
                'rows' => $ops->listPaymentFeatureMatrix(),
            ]), 'main');
            return;
        }

        if ($mode === 'invoices') {
            $this->view('admin/agent-apps/hub', array_merge($common, [
                'hubTitle' => __('agent_apps_invoices'),
                'hubBody' => __('agent_apps_invoices_hub_body'),
                'hubCta' => __('agent_apps_open_invoices'),
                'hubUrl' => rateb_url('admin/subscription/invoices'),
                'hubSecondaryCta' => __('agent_apps_manage_branding'),
                'hubSecondaryUrl' => rateb_url('admin/mobile-apps'),
            ]), 'main');
            return;
        }

        if ($key === 'complaints') {
            $status = trim((string) ($_GET['status'] ?? ''));
            $type = trim((string) ($_GET['type'] ?? ''));
            $list = $ops->listComplaints(50, 0, $status, $type);
            $this->view('admin/agent-apps/list', array_merge($common, [
                'listKind' => 'complaints',
                'rows' => $list['items'],
                'total' => $list['total'],
                'pending' => $list['pending'] ?? 0,
                'filterStatus' => $status,
                'filterType' => $type,
                'actionUrl' => rateb_url('admin/agent-apps/complaints/action'),
            ]), 'main');
            return;
        }

        if ($key === 'ratings') {
            $list = $ops->listRatings(50, 0);
            $this->view('admin/agent-apps/list', array_merge($common, [
                'listKind' => 'ratings',
                'rows' => $list['items'],
                'total' => $list['total'],
                'avgLabel' => $list['avg'] ?? '0/5',
            ]), 'main');
            return;
        }

        if ($key === 'notifications') {
            $list = $ops->listNotifications(50, 0);
            $this->view('admin/agent-apps/list', array_merge($common, [
                'listKind' => 'notifications',
                'rows' => $list['items'],
                'total' => $list['total'],
            ]), 'main');
            return;
        }

        $this->view('admin/agent-apps/section', array_merge($common, [
            'comingSoon' => true,
        ]), 'main');
    }

    public function complaintAction(): void
    {
        if (!$this->canManage()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            Response::redirect(rateb_url('admin/agent-apps/complaints'));
            return;
        }

        $id = (int) $this->input('id', 0);
        $action = trim((string) $this->input('action', ''));
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        $ok = (new AgentAppsOpsService())->setComplaintStatus($id, $action, $userId);

        if ($ok) {
            SessionManager::flash('success', $action === 'approve' ? __('approved') : __('rejected'));
        } else {
            SessionManager::flash('error', __('agent_apps_action_failed'));
        }

        $redirect = rateb_url('admin/agent-apps/complaints');
        $qs = [];
        $status = trim((string) $this->input('return_status', ''));
        $type = trim((string) $this->input('return_type', ''));
        if ($status !== '') {
            $qs['status'] = $status;
        }
        if ($type !== '') {
            $qs['type'] = $type;
        }
        if ($qs !== []) {
            $redirect .= '?' . http_build_query($qs);
        }
        Response::redirect($redirect);
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
            $mode = (string) ($meta['mode'] ?? 'soon');
            $live = in_array($mode, ['list', 'settings', 'payments', 'invoices'], true);
            $out[] = [
                'key' => $key,
                'title' => __($meta['title']),
                'desc' => __($meta['desc']),
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'url' => rateb_url('admin/agent-apps/' . $key),
                'cta' => $live ? __('agent_apps_open_module') : __('agent_apps_coming_soon_badge'),
                'live' => $live,
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
            'live' => true,
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
            // keep zeros
        }

        $ops = new AgentAppsOpsService();
        $notifCount = 0;
        $ratingAvg = '0/5';
        $complaintsPending = 0;
        try {
            $notifCount = $ops->notificationCount();
            $ratingAvg = $ops->ratingsAvgLabel();
            $complaintsPending = $ops->countPendingComplaints();
        } catch (\Throwable $e) {
            // keep zeros
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
                'value' => (string) $notifCount,
                'icon' => 'fa-bell',
                'tone' => 'teal',
            ],
            [
                'key' => 'rating',
                'label' => __('agent_apps_stat_rating'),
                'value' => $ratingAvg,
                'icon' => 'fa-star',
                'tone' => 'gold',
            ],
            [
                'key' => 'complaints',
                'label' => __('agent_apps_stat_complaints'),
                'value' => (string) $complaintsPending,
                'icon' => 'fa-triangle-exclamation',
                'tone' => 'red',
            ],
        ];
    }
}
