<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AgentAppsOpsService;
use Rateb\App\Services\MobileAppConfigService;
use Rateb\App\Services\MobileAppContentSchemaBootstrap;

/**
 * Agent / Agency App Management console under Admin.
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
        'content' => [
            'title' => 'agent_apps_content',
            'icon' => 'fa-file-lines',
            'tone' => 'purple',
            'desc' => 'agent_apps_content_desc',
            'mode' => 'content',
        ],
        'offers' => [
            'title' => 'agent_apps_offers',
            'icon' => 'fa-image',
            'tone' => 'teal',
            'desc' => 'agent_apps_offers_desc',
            'mode' => 'offers',
        ],
        'invoices' => [
            'title' => 'agent_apps_invoices',
            'icon' => 'fa-file-invoice',
            'tone' => 'navy',
            'desc' => 'agent_apps_invoices_desc',
            'mode' => 'invoices',
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

        MobileAppContentSchemaBootstrap::ensure();
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
            'companies' => $this->companiesForForms($ops),
            'defaultCompanyId' => $ops->resolveWriteCompanyId(0),
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

        if ($mode === 'content') {
            $companyFilter = (int) ($_GET['company_id'] ?? 0);
            $editId = (int) ($_GET['edit'] ?? 0);
            $list = $ops->listContents($companyFilter, 100);
            $editRow = null;
            if ($editId > 0) {
                foreach ($list['items'] as $row) {
                    if ((int) ($row['id'] ?? 0) === $editId) {
                        $editRow = $row;
                        break;
                    }
                }
            }
            $this->view('admin/agent-apps/content', array_merge($common, [
                'rows' => $list['items'],
                'total' => $list['total'],
                'companyFilter' => $companyFilter,
                'editRow' => $editRow,
                'slugs' => AgentAppsOpsService::contentSlugs(),
                'saveUrl' => rateb_url('admin/agent-apps/content/save'),
                'deleteUrl' => rateb_url('admin/agent-apps/content/delete'),
            ]), 'main');
            return;
        }

        if ($mode === 'offers') {
            $companyFilter = (int) ($_GET['company_id'] ?? 0);
            $editId = (int) ($_GET['edit'] ?? 0);
            $list = $ops->listOffers($companyFilter, 100, false);
            $editRow = null;
            if ($editId > 0) {
                foreach ($list['items'] as $row) {
                    if ((int) ($row['id'] ?? 0) === $editId) {
                        $editRow = $row;
                        break;
                    }
                }
            }
            $this->view('admin/agent-apps/offers', array_merge($common, [
                'rows' => $list['items'],
                'total' => $list['total'],
                'companyFilter' => $companyFilter,
                'editRow' => $editRow,
                'saveUrl' => rateb_url('admin/agent-apps/offers/save'),
                'deleteUrl' => rateb_url('admin/agent-apps/offers/delete'),
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

    public function saveContent(): void
    {
        $this->mutateContentOrOffer('content', 'save');
    }

    public function deleteContent(): void
    {
        $this->mutateContentOrOffer('content', 'delete');
    }

    public function saveOffer(): void
    {
        $this->mutateContentOrOffer('offers', 'save');
    }

    public function deleteOffer(): void
    {
        $this->mutateContentOrOffer('offers', 'delete');
    }

    private function mutateContentOrOffer(string $section, string $op): void
    {
        if (!$this->canManage()) {
            http_response_code(403);
            echo '403';
            return;
        }
        $redirect = rateb_url('admin/agent-apps/' . $section);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            Response::redirect($redirect);
            return;
        }

        $ops = new AgentAppsOpsService();
        if ($op === 'delete') {
            $id = (int) $this->input('id', 0);
            $ok = $section === 'content' ? $ops->deleteContent($id) : $ops->deleteOffer($id);
            SessionManager::flash($ok ? 'success' : 'error', $ok ? __('deleted') : __('agent_apps_action_failed'));
            Response::redirect($redirect);
            return;
        }

        $input = [
            'id' => (int) $this->input('id', 0),
            'company_id' => (int) $this->input('company_id', 0),
            'slug' => (string) $this->input('slug', ''),
            'title_ar' => (string) $this->input('title_ar', ''),
            'title_en' => (string) $this->input('title_en', ''),
            'body_ar' => (string) $this->input('body_ar', ''),
            'body_en' => (string) $this->input('body_en', ''),
            'image_path' => (string) $this->input('image_path', ''),
            'discount_label' => (string) $this->input('discount_label', ''),
            'starts_at' => (string) $this->input('starts_at', ''),
            'ends_at' => (string) $this->input('ends_at', ''),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => (string) $this->input('is_active', '0') === '1' || (string) $this->input('is_active', '') === 'on',
        ];
        $result = $section === 'content'
            ? $ops->saveContent($input)
            : $ops->saveOffer($input);

        if (!empty($result['ok'])) {
            SessionManager::flash('success', __('saved_ok'));
        } else {
            $msg = (string) ($result['message'] ?? 'save_failed');
            $map = [
                'company_required' => __('agent_apps_company_required'),
                'slug_invalid' => __('agent_apps_slug_invalid'),
                'title_required' => __('agent_apps_title_required'),
                'not_found' => __('agent_apps_action_failed'),
                'save_failed' => __('agent_apps_action_failed'),
            ];
            SessionManager::flash('error', $map[$msg] ?? __('agent_apps_action_failed'));
        }
        Response::redirect($redirect);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function companiesForForms(AgentAppsOpsService $ops): array
    {
        $companies = $ops->listCompanyOptions();
        if ($companies !== []) {
            return $companies;
        }
        $cid = $ops->resolveWriteCompanyId(0);
        if ($cid > 0) {
            return [['id' => $cid, 'name' => '#' . $cid]];
        }

        return [];
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
            $live = in_array($mode, ['list', 'settings', 'payments', 'invoices', 'content', 'offers'], true);
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
        $offersActive = 0;
        $contentCount = 0;
        try {
            $notifCount = $ops->notificationCount();
            $ratingAvg = $ops->ratingsAvgLabel();
            $complaintsPending = $ops->countPendingComplaints();
            $offersActive = $ops->listOffers(0, 1, true)['total'];
            $contentCount = $ops->listContents(0, 1)['total'];
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
                'key' => 'content',
                'label' => __('agent_apps_stat_content'),
                'value' => (string) $contentCount,
                'icon' => 'fa-file-lines',
                'tone' => 'purple',
            ],
            [
                'key' => 'offers',
                'label' => __('agent_apps_stat_offers'),
                'value' => (string) $offersActive,
                'icon' => 'fa-tags',
                'tone' => 'teal',
            ],
            [
                'key' => 'notifications',
                'label' => __('agent_apps_stat_notifications'),
                'value' => (string) $notifCount,
                'icon' => 'fa-bell',
                'tone' => 'gold',
            ],
            [
                'key' => 'complaints',
                'label' => __('agent_apps_stat_complaints'),
                'value' => (string) $complaintsPending,
                'icon' => 'fa-triangle-exclamation',
                'tone' => 'red',
            ],
            [
                'key' => 'rating',
                'label' => __('agent_apps_stat_rating'),
                'value' => $ratingAvg,
                'icon' => 'fa-star',
                'tone' => 'cyan',
            ],
        ];
    }
}
