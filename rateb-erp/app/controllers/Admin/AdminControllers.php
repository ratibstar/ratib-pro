<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\RateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\User;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DashboardService;
use Rateb\App\Services\LoginActivityService;

final class AuthController extends Controller
{
    public function logout(): void
    {
        Auth::logout();
        // Always ERP staff login — never marketing /site/login (customer portal).
        $url = function_exists('rateb_list_url')
            ? rateb_list_url('login', ['logged_out' => '1'])
            : (rateb_url('login') . '?logged_out=1');
        Response::redirect($url);
    }
}

final class DashboardController extends Controller
{
    public function index(): void
    {
        try {
            $this->renderDashboard();
        } catch (\Throwable $e) {
            if (class_exists(\Rateb\App\Services\DatabaseErrorService::class)) {
                \Rateb\App\Services\DatabaseErrorService::renderHttpError($e);
                return;
            }
            throw $e;
        }
    }

    private function renderDashboard(): void
    {
        if (function_exists('rateb_is_portal_branch_session') && rateb_is_portal_branch_session()) {
            $branchId = rateb_portal_branch_id();
            $branch = (new \Rateb\App\Services\BranchService())->findActiveForPortal($branchId);
            if ($branch) {
                $companyId = (int) ($branch['company_id'] ?? 0);
                TenantContext::setCompanyId($companyId);
                $service = new DashboardService();
                $limits = (new \Rateb\App\Services\PlanLimitService())->getLimits($companyId);
                $userCount = (new User())->count(['company_id' => $companyId]);
                $invSvc = new \Rateb\App\Services\InventoryWorkflowService();
                $ctrSvc = new \Rateb\App\Services\ContractWorkflowService();
                $this->view('company/dashboard', [
                    'title' => __('dashboard'),
                    'metrics' => $service->companyMetrics($companyId),
                    'limits' => $limits,
                    'userCount' => $userCount,
                    'expiringInventory' => $invSvc->expiringItems(30),
                    'expiringContracts' => $ctrSvc->expiringContracts(60),
                    'csrf' => Csrf::token(),
                ], 'main');
                return;
            }
        }

        if (SessionManager::get('rateb_is_super_admin')) {
            if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
                $service = new DashboardService();
                // Fast first paint (metrics only); charts hydrate via /admin/api/dashboard-charts.
                $dash = $service->adminBuildLite();
                $this->view('admin/dashboard', [
                    'title' => __('dashboard'),
                    'dash' => $dash,
                    'metrics' => $dash['metrics'],
                    'charts' => $dash['charts'],
                    'dashboardChartsUrl' => rateb_url('admin/api/dashboard-charts'),
                    'csrf' => Csrf::token(),
                ], 'main');

                return;
            }

            $this->renderCompanyDashboard($this->resolveAgencySuperAdminCompanyId());

            return;
        }

        $companyId = (int) SessionManager::get('rateb_company_id');
        $this->renderCompanyDashboard($companyId);
    }

    private function resolveAgencySuperAdminCompanyId(): int
    {
        if (\Rateb\App\Services\DedicatedTenantPolicy::isDedicated()) {
            $id = \Rateb\App\Services\DedicatedTenantPolicy::primaryCompanyId();
            if ($id > 0) {
                return $id;
            }
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            $id = rateb_resolve_ops_company_id();
            if ($id > 0) {
                return $id;
            }
        }
        if (\Rateb\App\Services\DedicatedTenantPolicy::companyCount() === 1) {
            return \Rateb\App\Services\DedicatedTenantPolicy::primaryCompanyId();
        }

        return \Rateb\App\Services\DedicatedTenantPolicy::primaryCompanyId();
    }

    private function renderCompanyDashboard(int $companyId): void
    {
        if ($companyId < 1) {
            SessionManager::flash('error', __('agency_erp_no_company_context'));
            Response::redirect(rateb_url('admin/settings'));

            return;
        }
        TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_adopt_ops_company_id')) {
            rateb_adopt_ops_company_id($companyId);
        }
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
        $service = new DashboardService();
        $dash = $this->safeDashboardBuild($service, $companyId);
        $limits = $dash['limits'] ?? (new \Rateb\App\Services\PlanLimitService())->getLimits($companyId);
        $userCount = $this->resolveCompanyUserCount($companyId);
        $invSvc = new \Rateb\App\Services\InventoryWorkflowService();
        $ctrSvc = new \Rateb\App\Services\ContractWorkflowService();
        $expiringInventory = [];
        $expiringContracts = [];
        try {
            $expiringInventory = $invSvc->expiringItems(30);
        } catch (\Throwable $e) {
            error_log('Dashboard expiring inventory: ' . $e->getMessage());
        }
        try {
            $expiringContracts = $ctrSvc->expiringContracts(60);
        } catch (\Throwable $e) {
            error_log('Dashboard expiring contracts: ' . $e->getMessage());
        }

        $this->view('company/dashboard', [
            'title' => __('dashboard'),
            'dash' => $dash,
            'metrics' => $dash['metrics'] ?? [],
            'charts' => $dash['charts'] ?? [],
            'modules' => $dash['modules'] ?? [],
            'recentActivity' => $dash['recent_activity'] ?? [],
            'companyName' => (string) ($dash['company_name'] ?? ''),
            'limits' => $limits,
            'userCount' => $userCount,
            'expiringInventory' => $expiringInventory,
            'expiringContracts' => $expiringContracts,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    /** @return array<string, mixed> */
    private function safeDashboardBuild(DashboardService $service, int $companyId): array
    {
        try {
            return $service->companyBuild($companyId);
        } catch (\Throwable $e) {
            error_log('Dashboard companyBuild: ' . $e->getMessage());
            if (class_exists(\Rateb\App\Services\DatabaseErrorService::class)) {
                SessionManager::flash('warning', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            }

            return [
                'company_id' => $companyId,
                'company_name' => '',
                'company_status' => '',
                'metrics' => [],
                'charts' => [
                    'procurement_trend' => ['labels' => [], 'purchase_requests' => [], 'purchase_orders' => []],
                    'inventory_health' => [],
                ],
                'modules' => [],
                'recent_activity' => [],
                'limits' => (new \Rateb\App\Services\PlanLimitService())->getLimits($companyId),
            ];
        }
    }

    private function resolveCompanyUserCount(int $companyId): int
    {
        $userCount = (new User())->count(['company_id' => $companyId]);
        if ($userCount > 0) {
            return $userCount;
        }
        if (\Rateb\App\Services\DedicatedTenantPolicy::isDedicated()) {
            $row = (new User())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_users WHERE is_super_admin = 0'
            );

            return (int) ($row['c'] ?? 0);
        }

        return 0;
    }
}

final class CompaniesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Company();
        $this->viewPrefix = 'admin/companies';
        $this->routePrefix = 'admin/companies';
        $this->entityName = 'companies';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['pending', 'active', 'suspended']],
            ['name' => 'plan_id', 'label' => 'plan_id', 'type' => 'number'],
            ['name' => 'user_limit', 'label' => 'user_limit', 'type' => 'number'],
            ['name' => 'branch_limit', 'label' => 'branch_limit', 'type' => 'number'],
            ['name' => 'storage_limit_mb', 'label' => 'storage_limit_mb', 'type' => 'number'],
        ];
        $this->indexFields = [
            ['name' => 'id', 'label' => 'id', 'type' => 'id'],
            ['name' => 'agency_id', 'label' => 'agency_id', 'type' => 'number'],
            ['name' => 'name', 'label' => 'name', 'type' => 'clip'],
            ['name' => 'site_url', 'label' => 'company_agency_site', 'type' => 'url'],
            ['name' => 'agency_login_url', 'label' => 'company_agency_login', 'type' => 'url'],
            ['name' => 'erp_status', 'label' => 'company_agency_erp_status', 'type' => 'clip'],
            ['name' => 'email', 'label' => 'email', 'type' => 'clip'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'plan_id', 'label' => 'plan_id', 'type' => 'number'],
            ['name' => 'user_limit', 'label' => 'user_limit', 'type' => 'number'],
            ['name' => 'storage_limit_mb', 'label' => 'storage_limit_mb', 'type' => 'number'],
        ];
        // Platform: create agencies in Control Panel — not a second create here.
        $this->createEnabled = !(function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host());
    }

    public function index(): void
    {
        $this->createEnabled = !(function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host());
        parent::index();
    }

    protected function indexViewData(int $limit, int $offset, int $page, string $search = ''): array
    {
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return $this->platformAgenciesIndexData($limit, $offset, $page, $search);
        }

        $data = parent::indexViewData($limit, $offset, $page, $search);
        $data['createEnabled'] = $this->createEnabled;
        $data['companyCreateAgencyHint'] = false;

        return $data;
    }

    /**
     * Platform companies list = Control Panel agencies (وكالة = الشركة).
     *
     * @return array<string, mixed>
     */
    private function platformAgenciesIndexData(int $limit, int $offset, int $page, string $search): array
    {
        $svc = new \Rateb\App\Services\AgencyErpMigrationService();
        $agencies = $svc->listControlAgencies(true);
        $search = trim($search);
        $items = [];
        foreach ($agencies as $agency) {
            if (!is_array($agency)) {
                continue;
            }
            $agencyId = (int) ($agency['id'] ?? 0);
            if ($agencyId < 1) {
                continue;
            }
            $name = trim((string) ($agency['name'] ?? ''));
            $siteRaw = trim((string) ($agency['site_url'] ?? ''));
            $site = function_exists('rateb_normalize_agency_site_url')
                ? rateb_normalize_agency_site_url($siteRaw)
                : $siteRaw;
            $loginUrl = function_exists('rateb_agency_erp_login_url')
                ? rateb_agency_erp_login_url($site)
                : '';
            $erpStatus = trim((string) ($agency['erp_status'] ?? ''));
            if ($search !== '') {
                $hay = strtolower($name . ' ' . $site . ' ' . $loginUrl . ' ' . $agencyId . ' ' . $erpStatus);
                if (!str_contains($hay, strtolower($search))) {
                    continue;
                }
            }
            try {
                $companyId = $svc->ensurePlatformCompanyForAgency($agency);
            } catch (\Throwable $e) {
                error_log('ensurePlatformCompanyForAgency #' . $agencyId . ': ' . $e->getMessage());
                continue;
            }
            $company = $this->model->find($companyId);
            if ($company === null) {
                continue;
            }
            $items[] = array_merge($company, [
                'id' => $companyId,
                'agency_id' => $agencyId,
                'name' => $name !== '' ? $name : (string) ($company['name'] ?? ''),
                'site_url' => $site,
                'agency_login_url' => $loginUrl,
                'erp_status' => $erpStatus !== '' ? $erpStatus : '—',
            ]);
        }

        $total = count($items);
        $slice = array_slice($items, $offset, $limit);
        $needsErpProvisioning = false;
        foreach ($items as $item) {
            $st = strtolower(trim((string) ($item['erp_status'] ?? 'none')));
            if ($st !== 'ready' && $st !== '—') {
                $needsErpProvisioning = true;
                break;
            }
        }

        return [
            'title' => __('companies'),
            'items' => $slice,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'routePrefix' => $this->routePrefix,
            'exportRoute' => rateb_url($this->routePrefix . '/export'),
            'fields' => $this->resolveIndexFields(),
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => false,
            'actionsEnabled' => $this->actionsEnabled,
            'documentEntityType' => '',
            'companyCreateAgencyHint' => true,
            'companyProvisionErpHint' => $needsErpProvisioning,
            'controlPanelAgenciesUrl' => function_exists('rateb_control_panel_agencies_url')
                ? rateb_control_panel_agencies_url()
                : '',
        ];
    }

    public function create(): void
    {
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            SessionManager::flash('error', __('company_create_use_control_panel'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            \Rateb\App\Services\DedicatedTenantPolicy::assertCanCreateCompany();
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->view($this->viewPrefix . '/form', $this->formData(null), $this->layout());
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formData($item), $this->layout());
    }

    /** @return array<string, mixed> */
    private function formData(?array $item): array
    {
        $selectedModules = [];
        if ($item && !empty($item['modules'])) {
            $decoded = json_decode((string) $item['modules'], true);
            $selectedModules = is_array($decoded) ? $decoded : [];
        }
        if ($selectedModules === [] && $item) {
            $selectedModules = (new \Rateb\App\Services\PlanLimitService())->getLimits((int) $item['id'])['modules'];
        }

        $plans = (new \Rateb\App\Models\Plan())->all(100, 0);
        $planPresets = [];
        foreach ($plans as $plan) {
            $planId = (int) ($plan['id'] ?? 0);
            if ($planId < 1) {
                continue;
            }
            $decoded = json_decode((string) ($plan['modules'] ?? '[]'), true);
            $slug = strtolower(trim((string) ($plan['slug'] ?? '')));
            $tierMods = $slug !== '' ? \Rateb\App\Services\PlanLimitService::modulesForSlug($slug) : [];
            $planPresets[$planId] = [
                'modules' => is_array($decoded) && $decoded !== []
                    ? array_values(array_filter(array_map('strval', $decoded)))
                    : $tierMods,
                'max_users' => max(1, (int) ($plan['max_users'] ?? 10)),
                'max_storage_mb' => max(128, (int) ($plan['max_storage_mb'] ?? 1024)),
                'max_branches' => max(1, (int) ($plan['max_branches'] ?? 10)),
                'name' => \Rateb\App\Models\Plan::marketingName($plan),
            ];
        }

        $companyId = $item ? (int) ($item['id'] ?? 0) : 0;
        $adminUser = $companyId > 0 ? $this->findCompanyAdminUser($companyId) : null;
        $linkedAgency = null;
        if ($companyId > 0) {
            try {
                $agencySvc = new \Rateb\App\Services\AgencyErpMigrationService();
                $aid = $agencySvc->suggestedAgencyIdForCompany($companyId);
                if ($aid > 0) {
                    foreach ($agencySvc->listControlAgencies(false) as $row) {
                        if ((int) ($row['id'] ?? 0) === $aid) {
                            $linkedAgency = $row;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $linkedAgency = null;
            }
        }
        $agencySite = '';
        $companyLoginUrl = '';
        $companyAdminUrl = '';
        if (is_array($linkedAgency)) {
            $agencySite = function_exists('rateb_normalize_agency_site_url')
                ? rateb_normalize_agency_site_url((string) ($linkedAgency['site_url'] ?? ''))
                : trim((string) ($linkedAgency['site_url'] ?? ''));
            $linkedAgency['site_url'] = $agencySite;
            if ($agencySite !== '' && function_exists('rateb_agency_erp_login_url')) {
                $companyLoginUrl = rateb_agency_erp_login_url($agencySite);
                $companyAdminUrl = rateb_agency_erp_admin_url($agencySite);
            }
        }
        $companyAdminLogin = '';
        if (is_array($linkedAgency)) {
            try {
                $agencyLogin = (new \Rateb\App\Services\AgencyErpMigrationService())
                    ->readDedicatedAdminLogin($linkedAgency);
                if (is_array($agencyLogin)) {
                    $companyAdminLogin = (string) ($agencyLogin['username'] ?? '');
                }
            } catch (\Throwable $e) {
                $companyAdminLogin = \Rateb\App\Services\DedicatedCompanySeedService::DEFAULT_LOGIN;
            }
            if ($companyAdminLogin === '') {
                $companyAdminLogin = \Rateb\App\Services\DedicatedCompanySeedService::DEFAULT_LOGIN;
            }
        } elseif ($adminUser) {
            $companyAdminLogin = $this->displayCompanyAdminLogin($adminUser);
        }
        if ($companyLoginUrl === '') {
            $slug = trim((string) ($item['slug'] ?? ''));
            $companyLoginUrl = $slug !== '' && function_exists('rateb_public_url')
                ? rateb_public_url('login?company=' . rawurlencode($slug))
                : '';
        }

        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('companies'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'plans' => $plans,
            'planPresets' => $planPresets,
            'moduleCatalog' => \Rateb\App\Services\PlanLimitService::moduleCatalog(),
            'selectedModules' => $selectedModules,
            'limits' => $item ? (new \Rateb\App\Services\PlanLimitService())->getLimits($companyId) : null,
            'companyAdminLogin' => $companyAdminLogin,
            'companyLoginUrl' => $companyLoginUrl,
            'companyAdminUrl' => $companyAdminUrl,
            'linkedAgency' => $linkedAgency,
            'agencyPortalMode' => is_array($linkedAgency) && $agencySite !== '',
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $modules = $this->input('modules', []);
        if (is_array($modules)) {
            $data['modules'] = json_encode(
                \Rateb\App\Services\PlanLimitService::filterKnownModules($modules),
                JSON_UNESCAPED_UNICODE
            );
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before */
    private function applyCompanyPlanFields(array &$data, ?array $before): void
    {
        $planId = (int) ($data['plan_id'] ?? 0);
        if ($planId < 1) {
            return;
        }
        $plan = (new \Rateb\App\Models\Plan())->find($planId);
        if (!$plan) {
            return;
        }

        $syncFromPlan = $this->input('sync_from_plan') === '1';
        $planChanged = $before !== null && $planId !== (int) ($before['plan_id'] ?? 0);
        $forceFromPlan = $syncFromPlan || $planChanged;
        $defaultModules = $plan['modules'] ?? json_encode(
            \Rateb\App\Services\PlanLimitService::defaultModules(),
            JSON_UNESCAPED_UNICODE
        );

        if ($forceFromPlan) {
            $data['modules'] = $defaultModules;
            $data['user_limit'] = (int) ($plan['max_users'] ?? 10);
            $data['branch_limit'] = (int) ($plan['max_branches'] ?? 10);
            $data['storage_limit_mb'] = (int) ($plan['max_storage_mb'] ?? 1024);

            return;
        }

        if (empty($data['modules']) || $data['modules'] === '[]') {
            $data['modules'] = $defaultModules;
        }
        if (empty($data['user_limit']) || (int) $data['user_limit'] < 1) {
            $data['user_limit'] = (int) ($plan['max_users'] ?? 10);
        }
        if (empty($data['branch_limit']) || (int) $data['branch_limit'] < 1) {
            $data['branch_limit'] = (int) ($plan['max_branches'] ?? 10);
        }
        if (empty($data['storage_limit_mb']) || (int) $data['storage_limit_mb'] < 1) {
            $data['storage_limit_mb'] = (int) ($plan['max_storage_mb'] ?? 1024);
        }
    }

    public function store(): void
    {
        $this->guardManage();
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            SessionManager::flash('error', __('company_create_use_control_panel'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            \Rateb\App\Services\DedicatedTenantPolicy::assertCanCreateCompany();
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $this->applyCompanyPlanFields($data, null);
        $data['status'] = 'pending';
        $adminUsername = trim((string) $this->input('admin_username', ''));
        $adminPassword = (string) $this->input('admin_password', '');
        if ($adminUsername === '' || strlen($adminPassword) < 6) {
            SessionManager::flash('error', __('company_admin_login_required'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        try {
            $id = $this->model->create($data);
            (new \Rateb\App\Services\AuthorizationService())->ensureCompanyRoles($id);
            (new \Rateb\App\Services\BranchService())->ensureMainBranch($id);
            $this->createCompanyAdminUser(
                $id,
                $adminUsername,
                $adminPassword,
                (string) ($data['name'] ?? ''),
                (string) ($data['phone'] ?? ''),
                (string) ($data['slug'] ?? '')
            );
            $auditPayload = $data;
            unset($auditPayload['admin_password'], $auditPayload['password']);
            (new AuditService())->log('create', $this->entityName, $id, $auditPayload);
            \Rateb\App\Services\ApprovalOversightService::notifyPendingSubmission(
                $id,
                'company_registration',
                (string) ($data['name'] ?? ('#' . $id)),
                $id
            );
            SessionManager::flash('success', __('company_saved_pending_oversight') . ' — ' . __('company_admin_created_hint'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $this->redirect(rateb_url('admin/oversight/companies-approvals'));
    }

    /** @return array{email:string,name:string} */
    private function resolveCompanyAdminIdentity(string $username, string $companyName, string $slug, int $companyId): array
    {
        $username = trim($username);
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $slug), '-'));
        if ($slug === '') {
            $slug = 'c' . $companyId;
        }

        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower($username);
            $displayName = $companyName !== '' ? $companyName : (string) strstr($email, '@', true);

            return ['email' => $email, 'name' => (string) $displayName];
        }

        $safeUser = strtolower(trim((string) preg_replace('/[^a-z0-9._-]+/i', '', $username)));
        if ($safeUser === '') {
            $safeUser = 'admin';
        }

        return [
            'email' => $safeUser . '+' . $slug . '@rateb.sa',
            'name' => $safeUser,
        ];
    }

    /** @param array<string, mixed> $user */
    private function displayCompanyAdminLogin(array $user): string
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['name'] ?? ''));
        if ($email !== '' && !str_ends_with($email, '@rateb.sa') && !str_ends_with($email, '@local')) {
            return $email;
        }
        if ($name !== '') {
            return strtolower($name);
        }

        return $email;
    }

    /** @return array<string, mixed>|null */
    private function findCompanyAdminUser(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        $users = new \Rateb\App\Models\User();
        $row = $users->queryOne(
            "SELECT u.* FROM rateb_users u
             INNER JOIN rateb_user_roles ur ON ur.user_id = u.id
             INNER JOIN rateb_roles r ON r.id = ur.role_id AND r.slug = 'company-full-access'
             WHERE u.company_id = :cid AND COALESCE(u.is_super_admin, 0) = 0
             ORDER BY u.id ASC
             LIMIT 1",
            ['cid' => $companyId]
        );
        if ($row) {
            return $row;
        }

        return $users->queryOne(
            'SELECT * FROM rateb_users
             WHERE company_id = :cid AND COALESCE(is_super_admin, 0) = 0
             ORDER BY id ASC
             LIMIT 1',
            ['cid' => $companyId]
        );
    }

    /**
     * Create the client company admin (tenant user) — not platform agency access-control users.
     */
    private function createCompanyAdminUser(
        int $companyId,
        string $username,
        string $password,
        string $companyName,
        string $companyPhone,
        string $slug
    ): int {
        $identity = $this->resolveCompanyAdminIdentity($username, $companyName, $slug, $companyId);
        $email = $identity['email'];
        $displayName = $identity['name'];

        $users = new \Rateb\App\Models\User();
        if ($users->findByEmail($email)) {
            throw new \RuntimeException(__('company_admin_username_taken'));
        }
        if (!filter_var(trim($username), FILTER_VALIDATE_EMAIL)) {
            $nameTaken = $users->queryOne(
                'SELECT id FROM rateb_users WHERE LOWER(name) = :n LIMIT 1',
                ['n' => strtolower($displayName)]
            );
            if ($nameTaken) {
                throw new \RuntimeException(__('company_admin_username_taken'));
            }
        }

        $userId = (int) $users->create([
            'company_id' => $companyId,
            'name' => $displayName,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => $companyPhone,
            'is_super_admin' => 0,
            'status' => 'active',
            'locale' => function_exists('rateb_locale') ? rateb_locale() : 'ar',
        ]);
        if ($userId < 1) {
            throw new \RuntimeException(__('company_admin_create_failed'));
        }

        $authz = new \Rateb\App\Services\AuthorizationService();
        $roleRow = $authz->findRoleBySlug('company-full-access', $companyId);
        if ($roleRow) {
            $authz->assignRole($userId, (int) $roleRow['id']);
        }
        if (class_exists(\Rateb\App\Services\BarcodeLoginService::class)) {
            (new \Rateb\App\Services\BarcodeLoginService())->ensureUserBarcode($userId);
        }

        return $userId;
    }

    /**
     * Update existing company admin login, or create one if missing.
     * Blank password on edit keeps the current password (platform only).
     * Linked agency ERP requires password (≥6) so login works on the agency domain.
     *
     * @return array{username:string,login_url:string}|null Agency sync result when pushed.
     */
    private function syncCompanyAdminUser(
        int $companyId,
        string $username,
        string $password,
        string $companyName,
        string $companyPhone,
        string $slug
    ): ?array {
        $username = trim($username);
        $agencyPush = $this->resolveLinkedAgencyForLoginPush($companyId);
        if ($agencyPush !== null && strlen($password) < 6) {
            throw new \RuntimeException(__('company_agency_admin_password_required'));
        }

        $existing = $this->findCompanyAdminUser($companyId);
        if ($existing === null) {
            if ($username === '' || strlen($password) < 6) {
                throw new \RuntimeException(__('company_admin_login_required'));
            }
            $this->createCompanyAdminUser($companyId, $username, $password, $companyName, $companyPhone, $slug);

            return $this->pushAdminLoginToLinkedAgency($companyId, $username, $password);
        }

        if ($username === '') {
            throw new \RuntimeException(__('company_admin_login_required'));
        }
        if ($password !== '' && strlen($password) < 6) {
            throw new \RuntimeException(__('company_admin_login_required'));
        }

        $identity = $this->resolveCompanyAdminIdentity($username, $companyName, $slug, $companyId);
        $users = new \Rateb\App\Models\User();
        $userId = (int) ($existing['id'] ?? 0);
        $emailTaken = $users->queryOne(
            'SELECT id FROM rateb_users WHERE email = :email AND id <> :uid LIMIT 1',
            ['email' => $identity['email'], 'uid' => $userId]
        );
        if ($emailTaken) {
            throw new \RuntimeException(__('company_admin_username_taken'));
        }
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $nameTaken = $users->queryOne(
                'SELECT id FROM rateb_users WHERE LOWER(name) = :n AND id <> :uid LIMIT 1',
                ['n' => strtolower($identity['name']), 'uid' => $userId]
            );
            if ($nameTaken) {
                throw new \RuntimeException(__('company_admin_username_taken'));
            }
        }

        $payload = [
            'company_id' => $companyId,
            'name' => $identity['name'],
            'email' => $identity['email'],
            'phone' => $companyPhone,
            'status' => 'active',
        ];
        if ($password !== '') {
            $payload['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $users->update($userId, $payload);

        $authz = new \Rateb\App\Services\AuthorizationService();
        $roleRow = $authz->findRoleBySlug('company-full-access', $companyId);
        if ($roleRow) {
            $authz->assignRole($userId, (int) $roleRow['id']);
        }

        return $this->pushAdminLoginToLinkedAgency($companyId, $username, $password);
    }

    /** @return array<string, mixed>|null */
    private function resolveLinkedAgencyForLoginPush(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        if (!function_exists('rateb_is_platform_oversight_host') || !rateb_is_platform_oversight_host()) {
            return null;
        }
        try {
            $svc = new \Rateb\App\Services\AgencyErpMigrationService();
            $agencyId = $svc->suggestedAgencyIdForCompany($companyId);
            if ($agencyId < 1) {
                return null;
            }
            foreach ($svc->listControlAgencies(false) as $row) {
                if ((int) ($row['id'] ?? 0) !== $agencyId) {
                    continue;
                }
                $cfg = $svc->agencyDatabaseConfig($row);
                if (trim((string) ($cfg['db'] ?? '')) === '') {
                    return null;
                }

                return $row;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Sync client login into dedicated agency ERP DB when this platform company is linked.
     *
     * @return array{username:string,login_url:string}|null
     */
    private function pushAdminLoginToLinkedAgency(int $companyId, string $username, string $password): ?array
    {
        if ($companyId < 1 || $username === '') {
            return null;
        }
        $agency = $this->resolveLinkedAgencyForLoginPush($companyId);
        if ($agency === null) {
            return null;
        }
        try {
            $svc = new \Rateb\App\Services\AgencyErpMigrationService();
            $synced = $svc->syncDedicatedAdminLogin($agency, $username, $password);
            $site = function_exists('rateb_normalize_agency_site_url')
                ? rateb_normalize_agency_site_url((string) ($agency['site_url'] ?? ''))
                : trim((string) ($agency['site_url'] ?? ''));
            $loginUrl = ($site !== '' && function_exists('rateb_agency_erp_login_url'))
                ? rateb_agency_erp_login_url($site)
                : '';

            return [
                'username' => (string) ($synced['username'] ?? $username),
                'login_url' => $loginUrl,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(__('company_agency_admin_sync_failed') . ' — ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $old = $this->model->find($id);
        if (!$old) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $adminUsername = trim((string) $this->input('admin_username', ''));
        $adminPassword = (string) $this->input('admin_password', '');
        $data = $this->collectData();
        $this->applyCompanyPlanFields($data, $old);
        unset($data['status']);
        $oldStatus = (string) ($old['status'] ?? 'pending');
        if ($oldStatus === 'suspended') {
            $data['status'] = 'suspended';
        } elseif ($oldStatus === 'active' && $this->companyProfileChanged($old, $data)) {
            $data['status'] = 'pending';
        } else {
            $data['status'] = $oldStatus;
        }
        try {
            $this->model->update($id, $data);
            (new \Rateb\App\Services\AuthorizationService())->ensureCompanyRoles($id);
            if (isset($data['modules']) && is_string($data['modules']) && $data['modules'] !== '') {
                $decodedModules = json_decode($data['modules'], true);
                if (is_array($decodedModules)) {
                    try {
                        (new \Rateb\App\Services\AgencyErpMigrationService())
                            ->pushModulesToLinkedAgency($id, array_values(array_map('strval', $decodedModules)));
                    } catch (\Throwable $agencyModErr) {
                        error_log('company update agency modules sync #' . $id . ': ' . $agencyModErr->getMessage());
                        SessionManager::flash(
                            'error',
                            __('company_permissions_agency_sync_failed') . ' — ' . $agencyModErr->getMessage()
                        );
                        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
                    }
                    \Rateb\App\Services\PlanLimitService::forgetCompanyLimits($id);
                }
            }
            $agencyLogin = $this->syncCompanyAdminUser(
                $id,
                $adminUsername,
                $adminPassword,
                (string) ($data['name'] ?? ($old['name'] ?? '')),
                (string) ($data['phone'] ?? ($old['phone'] ?? '')),
                (string) ($data['slug'] ?? ($old['slug'] ?? ''))
            );
            $auditPayload = $data;
            unset($auditPayload['admin_password'], $auditPayload['password']);
            (new AuditService())->log('update', $this->entityName, $id, $auditPayload);
            if (($data['status'] ?? '') === 'pending' && $oldStatus === 'active') {
                \Rateb\App\Services\ApprovalOversightService::notifyPendingSubmission(
                    $id,
                    'company_registration',
                    (string) ($data['name'] ?? ($old['name'] ?? ('#' . $id))),
                    $id
                );
                SessionManager::flash('success', __('company_edit_pending_oversight'));
                $this->redirect(rateb_url('admin/oversight/companies-approvals'));
            }
            if (is_array($agencyLogin) && ($agencyLogin['login_url'] ?? '') !== '') {
                SessionManager::flash(
                    'success',
                    __('company_agency_login_ready', [
                        'user' => (string) ($agencyLogin['username'] ?? $adminUsername),
                        'url' => (string) $agencyLogin['login_url'],
                    ])
                );
            } else {
                SessionManager::flash('success', __('save') . ' OK');
            }
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function companyProfileChanged(array $before, array $after): bool
    {
        foreach (['name', 'slug', 'email', 'phone', 'plan_id', 'user_limit', 'branch_limit', 'storage_limit_mb', 'modules'] as $key) {
            $a = trim((string) ($after[$key] ?? ''));
            $b = trim((string) ($before[$key] ?? ''));
            if ($a !== $b) {
                return true;
            }
        }
        return false;
    }

    public function suspend(array $params): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $id = (int) ($params['id'] ?? 0);
        $this->model->suspend($id);
        $this->detachAndDeactivateAgencyForCompany($id);
        (new AuditService())->log('suspend', 'company', $id);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/companies'));
    }

    public function destroy(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            $this->removeCompanyFromPlatformList($id);
            (new AuditService())->log('delete', $this->entityName, $id);
            SessionManager::flash('success', __('company_deleted_with_agency'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function bulkDestroy(): void
    {
        $this->guardManage();
        if (!$this->bulkEnabled) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $deleted = 0;
        $errors = 0;
        foreach ($ids as $id) {
            try {
                $this->removeCompanyFromPlatformList((int) $id);
                (new AuditService())->log('bulk_delete', $this->entityName, (int) $id);
                $deleted++;
            } catch (\Throwable $e) {
                $errors++;
                error_log('companies bulkDestroy #' . $id . ': ' . $e->getMessage());
            }
        }
        if ($deleted > 0) {
            SessionManager::flash(
                'success',
                __('bulk_deleted', ['count' => $deleted])
                . ($errors > 0 ? ' — ' . __('company_delete_partial_errors', ['count' => $errors]) : '')
            );
        } else {
            SessionManager::flash('error', __('company_delete_failed'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    /**
     * Agency = company: remove from ERP list by deactivating CP agency + deleting platform company row.
     * Prevents auto-recreate on next companies index load.
     */
    private function removeCompanyFromPlatformList(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $this->detachAndDeactivateAgencyForCompany($companyId);
        try {
            $this->model->delete($companyId);
        } catch (\Throwable $e) {
            // FK leftovers — hide company so it stays off the agency-driven list.
            $this->model->suspend($companyId);
            error_log('removeCompanyFromPlatformList delete fallback suspend #' . $companyId . ': ' . $e->getMessage());
        }
    }

    private function detachAndDeactivateAgencyForCompany(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        try {
            $svc = new \Rateb\App\Services\AgencyErpMigrationService();
            $agencyId = $svc->suggestedAgencyIdForCompany($companyId);
            if ($agencyId < 1) {
                return;
            }
            try {
                $svc->linkAgencyToCompany($agencyId, 0);
            } catch (\Throwable $e) {
                error_log('unlink agency #' . $agencyId . ': ' . $e->getMessage());
            }
            if (function_exists('rateb_deactivate_control_agency')) {
                rateb_deactivate_control_agency($agencyId);
            }
        } catch (\Throwable $e) {
            error_log('detachAndDeactivateAgencyForCompany #' . $companyId . ': ' . $e->getMessage());
        }
    }

    public function activate(array $params): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $row = $this->model->find($id);
        if (!$row) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/companies'));
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === 'pending') {
            SessionManager::flash('info', __('company_approve_in_oversight'));
            Response::redirect(rateb_url('admin/oversight/companies-approvals'));
        }
        if ($status === 'active') {
            SessionManager::flash('info', __('company_already_active'));
            $this->redirectAfterCompanyActivate($id);
        }
        if ($status !== 'suspended') {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/companies'));
        }
        $this->model->activate($id);
        \Rateb\App\Services\PlanLimitService::forgetCompanyLimits($id);
        (new AuditService())->log('activate', 'company', $id);
        SessionManager::flash('success', __('company_activated'));
        $this->redirectAfterCompanyActivate($id);
    }

    private function redirectAfterCompanyActivate(int $id): void
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '' && str_contains($referer, 'company-permissions')) {
            Response::redirect(rateb_url('admin/company-permissions/' . $id));
        }
        if ($referer !== '' && str_contains($referer, '/companies/' . $id)) {
            Response::redirect(rateb_url('admin/companies/' . $id . '/edit'));
        }
        Response::redirect(rateb_url('admin/companies'));
    }

    public function branchesHub(): void
    {
        if (!function_exists('rateb_platform_branch_manage_enabled') || !rateb_platform_branch_manage_enabled()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url($this->routePrefix));
        }
        $companies = \Rateb\App\Services\PlatformCompanyBranchService::listCompanies();
        $this->view($this->viewPrefix . '/branches-hub', [
            'title' => __('manage_branches_cp'),
            'companies' => $companies,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function manageBranches(array $params): void
    {
        if (!function_exists('rateb_platform_branch_manage_enabled') || !rateb_platform_branch_manage_enabled()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url($this->routePrefix));
        }
        $companyId = (int) ($params['id'] ?? 0);
        $company = \Rateb\App\Services\PlatformCompanyBranchService::companyRow($companyId);
        if (!$company) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $branchUrl = rateb_url($this->routePrefix . '/' . $companyId . '/branches');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) {
                SessionManager::flash('error', __('invalid_request'));
                Response::redirect($branchUrl);
            }
            $action = (string) $this->input('action', '');
            if ($action === 'set_branch_limit') {
                $limit = max(0, (int) $this->input('branch_limit', 0));
                if (\Rateb\App\Services\PlatformCompanyBranchService::setBranchLimit($companyId, $limit)) {
                    SessionManager::flash('success', __('save') . ' OK');
                } else {
                    SessionManager::flash('error', __('invalid_request'));
                }
            } elseif ($action === 'create_branch') {
                $result = \Rateb\App\Services\PlatformCompanyBranchService::createBranch($companyId, [
                    'name' => (string) $this->input('branch_name', ''),
                    'code' => (string) $this->input('branch_code', ''),
                    'address' => (string) $this->input('branch_address', ''),
                    'phone' => (string) $this->input('branch_phone', ''),
                    'email' => (string) $this->input('branch_email', ''),
                ]);
                if (!empty($result['ok'])) {
                    SessionManager::flash('success', __('save') . ' OK');
                    if (!empty($result['portal_url'])) {
                        SessionManager::flash('branch_portal_url', (string) $result['portal_url']);
                    }
                } else {
                    $err = (string) ($result['error'] ?? '');
                    SessionManager::flash(
                        'error',
                        $err === 'branch_limit_reached'
                            ? __('branch_limit_reached')
                            : ($err === 'branch_name_required'
                                ? __('branch_name')
                                : ($err === 'branch_code_duplicate' ? __('branch_code_duplicate') : $err))
                    );
                }
            } elseif ($action === 'toggle_branch') {
                $branchId = (int) $this->input('branch_id', 0);
                $status = (string) $this->input('status', '') === 'active' ? 'active' : 'inactive';
                $result = \Rateb\App\Services\PlatformCompanyBranchService::setBranchStatus($companyId, $branchId, $status);
                if (!empty($result['ok'])) {
                    if (empty($result['noop'])) {
                        SessionManager::flash(
                            'success',
                            $status === 'active' ? __('branch_activated') : __('branch_deactivated')
                        );
                    }
                } else {
                    $err = (string) ($result['error'] ?? '');
                    SessionManager::flash(
                        'error',
                        $err === 'branch_last_active'
                            ? __('branch_last_active')
                            : ($err === 'record_not_found' ? __('no_records') : __('invalid_request'))
                    );
                }
            } elseif ($action === 'update_branch') {
                $branchId = (int) $this->input('branch_id', 0);
                $result = \Rateb\App\Services\PlatformCompanyBranchService::updateBranch($companyId, $branchId, [
                    'name' => (string) $this->input('branch_name', ''),
                    'code' => (string) $this->input('branch_code', ''),
                    'phone' => (string) $this->input('branch_phone', ''),
                    'email' => (string) $this->input('branch_email', ''),
                    'address' => (string) $this->input('branch_address', ''),
                    'map_url' => (string) $this->input('branch_map_url', ''),
                    'status' => (string) $this->input('branch_status', ''),
                ]);
                if (!empty($result['ok'])) {
                    SessionManager::flash('success', __('saved_ok'));
                } else {
                    $err = (string) ($result['error'] ?? '');
                    SessionManager::flash(
                        'error',
                        $err === 'branch_name_required'
                            ? __('branch_name')
                            : ($err === 'branch_code_duplicate'
                                ? __('branch_code_duplicate')
                                : ($err === 'branch_last_active'
                                    ? __('branch_last_active')
                                    : ($err === 'record_not_found'
                                        ? __('no_records')
                                        : $err)))
                    );
                }
            } elseif ($action === 'archive_branch') {
                $branchId = (int) $this->input('branch_id', 0);
                $result = \Rateb\App\Services\PlatformCompanyBranchService::archiveBranch($companyId, $branchId);
                if (!empty($result['ok']) && empty($result['noop'])) {
                    SessionManager::flash('success', __('branch_archive') . ' OK');
                } elseif (empty($result['ok'])) {
                    $err = (string) ($result['error'] ?? '');
                    SessionManager::flash(
                        'error',
                        $err === 'branch_main_archive_denied'
                            ? __('branch_main_archive_denied')
                            : ($err === 'branch_last_active'
                                ? __('branch_last_active')
                                : __('invalid_request'))
                    );
                }
            } elseif ($action === 'restore_branch') {
                $branchId = (int) $this->input('branch_id', 0);
                $result = \Rateb\App\Services\PlatformCompanyBranchService::restoreBranch($companyId, $branchId);
                if (!empty($result['ok']) && empty($result['noop'])) {
                    SessionManager::flash('success', __('branch_restore') . ' OK');
                } elseif (empty($result['ok'])) {
                    SessionManager::flash('error', __('invalid_request'));
                }
            } elseif ($action === 'bulk_branch') {
                $ids = $this->input('branch_ids', []);
                if (!is_array($ids)) {
                    $ids = [];
                }
                $result = \Rateb\App\Services\PlatformCompanyBranchService::bulkBranchAction(
                    $companyId,
                    $ids,
                    (string) $this->input('bulk_action', '')
                );
                if (!empty($result['ok'])) {
                    SessionManager::flash('success', __('bulk_apply') . ': ' . (int) ($result['success'] ?? 0));
                } else {
                    SessionManager::flash(
                        'error',
                        (int) ($result['success'] ?? 0) > 0
                            ? __('bulk_apply') . ' (' . (int) $result['success'] . '/' . ((int) ($result['success'] ?? 0) + (int) ($result['failed'] ?? 0)) . ')'
                            : __('invalid_request')
                    );
                }
            }
            Response::redirect($branchUrl);
        }
        $listOpts = \Rateb\App\Services\PlatformCompanyBranchService::listOptionsFromRequest($_GET);
        $branchList = \Rateb\App\Services\PlatformCompanyBranchService::listBranches($companyId, $listOpts);
        $newPortalUrl = trim((string) (SessionManager::flash('branch_portal_url') ?? ''));
        $this->view($this->viewPrefix . '/branches', [
            'title' => __('manage_branches_cp') . ' — ' . (string) ($company['name'] ?? '') . ' #' . $companyId,
            'company' => $company,
            'branchList' => $branchList,
            'listOpts' => $listOpts,
            'companyId' => $companyId,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'newPortalUrl' => $newPortalUrl,
            'branchAction' => $branchUrl,
            'listBaseUrl' => $branchUrl,
        ], $this->layout());
    }

    public function bulkSuspend(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $count = 0;
        foreach ($this->parseBulkIds() as $id) {
            $id = (int) $id;
            $this->model->suspend($id);
            $this->detachAndDeactivateAgencyForCompany($id);
            (new AuditService())->log('bulk_suspend', 'company', $id);
            $count++;
        }
        SessionManager::flash('success', __('bulk_suspended', ['count' => $count]));
        Response::redirect(rateb_url('admin/companies'));
    }

    public function bulkActivate(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $activated = 0;
        $pending = 0;
        foreach ($this->parseBulkIds() as $id) {
            $id = (int) $id;
            $row = $this->model->find($id);
            if (!$row) {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            if ($status === 'pending') {
                $pending++;
                continue;
            }
            if ($status !== 'suspended') {
                continue;
            }
            $this->model->activate($id);
            \Rateb\App\Services\PlanLimitService::forgetCompanyLimits($id);
            (new AuditService())->log('bulk_activate', 'company', $id);
            $activated++;
        }
        if ($activated > 0) {
            SessionManager::flash('success', __('bulk_activated', ['count' => $activated]));
        }
        if ($pending > 0) {
            SessionManager::flash('info', __('company_bulk_pending_use_oversight', ['count' => $pending]));
        }
        if ($activated === 0 && $pending === 0) {
            SessionManager::flash('info', __('company_bulk_nothing_to_activate'));
        }
        Response::redirect(rateb_url('admin/companies'));
    }
}

final class SubscriptionsController extends \Rateb\App\Controllers\CrudController
{
    private \Rateb\App\Services\BillingService $billing;

    public function __construct()
    {
        $this->billing = new \Rateb\App\Services\BillingService();
        $this->model = new \Rateb\App\Models\Subscription();
        $this->viewPrefix = 'admin/subscriptions';
        $this->routePrefix = 'admin/subscriptions';
        $this->entityName = 'subscriptions';
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'company_select'],
            ['name' => 'plan_id', 'label' => 'plan_id', 'type' => 'plan_select'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'options' => ['trial', 'active', 'cancelled', 'expired']],
            ['name' => 'billing_cycle', 'label' => 'billing_cycle', 'type' => 'select', 'options' => ['monthly', 'yearly']],
            ['name' => 'amount', 'label' => 'amount', 'type' => 'number'],
            ['name' => 'starts_at', 'label' => 'start_date', 'type' => 'date'],
            ['name' => 'ends_at', 'label' => 'end_date', 'type' => 'date'],
        ];
    }

    public function index(): void
    {
        $this->billing->ensureBillingReady();
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $this->view($this->viewPrefix . '/index', [
            'title' => __('subscriptions'),
            'items' => $this->model->withRelations($limit, $offset),
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => [
                ['name' => 'company_name', 'label' => 'company_name'],
                ['name' => 'plan_name', 'label' => 'plans'],
                ['name' => 'status', 'label' => 'status'],
                ['name' => 'billing_cycle', 'label' => 'billing_cycle'],
                ['name' => 'amount', 'label' => 'amount'],
                ['name' => 'starts_at', 'label' => 'start_date'],
            ],
            'csrf' => Csrf::token(),
            'createEnabled' => rateb_can('subscriptions.manage'),
            'actionsEnabled' => rateb_can('subscriptions.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can('subscriptions.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->billing->ensureBillingReady();
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __('subscriptions'),
            'item' => ['starts_at' => date('Y-m-d'), 'status' => 'trial', 'billing_cycle' => 'monthly'],
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'plans' => $this->billing->planOptions(),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectBillingData();
        if (!$this->validateSubscriptionData($data)) {
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        if (!empty($data['company_id']) && !empty($data['plan_id']) && in_array((string) ($data['status'] ?? ''), ['active', 'trial'], true)) {
            (new \Rateb\App\Services\PlanLimitService())->syncFromPlan((int) $data['company_id'], (int) $data['plan_id']);
        }
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __('subscriptions'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'plans' => $this->billing->planOptions(),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectBillingData();
        if (!$this->validateSubscriptionData($data)) {
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        if (!empty($data['company_id']) && !empty($data['plan_id']) && in_array((string) ($data['status'] ?? ''), ['active', 'trial'], true)) {
            (new \Rateb\App\Services\PlanLimitService())->syncFromPlan((int) $data['company_id'], (int) $data['plan_id']);
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @param array<string, mixed> $data */
    private function validateSubscriptionData(array $data): bool
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $planId = (int) ($data['plan_id'] ?? 0);
        if (!$this->billing->companyExists($companyId)) {
            SessionManager::flash('error', __('billing_company_required'));
            return false;
        }
        if (!$this->billing->planExists($planId)) {
            SessionManager::flash('error', __('billing_plan_required'));
            return false;
        }
        return true;
    }

    /** @return array<string, mixed> */
    private function collectBillingData(): array
    {
        $data = $this->collectData();
        $data['company_id'] = (int) ($data['company_id'] ?? 0);
        $data['plan_id'] = (int) ($data['plan_id'] ?? 0);
        $data['amount'] = (float) ($data['amount'] ?? 0);
        $data['auto_renew'] = 1;
        if (($data['starts_at'] ?? '') === '') {
            $data['starts_at'] = date('Y-m-d');
        }
        if (($data['ends_at'] ?? '') === '') {
            unset($data['ends_at']);
        }
        return $data;
    }
}

final class PlansController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Plan();
        $this->viewPrefix = 'admin/plans';
        $this->routePrefix = 'admin/plans';
        $this->entityName = 'plans';
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text'],
            ['name' => 'slug', 'label' => 'slug', 'type' => 'text'],
            ['name' => 'description', 'label' => 'description', 'type' => 'textarea'],
            ['name' => 'price_monthly', 'label' => 'price_monthly', 'type' => 'number'],
            ['name' => 'price_yearly', 'label' => 'price_yearly', 'type' => 'number'],
            ['name' => 'max_users', 'label' => 'user_limit', 'type' => 'number'],
            ['name' => 'max_branches', 'label' => 'max_branches', 'type' => 'number'],
            ['name' => 'max_storage_mb', 'label' => 'storage_limit_mb', 'type' => 'number'],
        ];
        $this->indexFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'slug', 'label' => 'slug', 'type' => 'slug'],
            ['name' => 'price_monthly', 'label' => 'price_monthly'],
            ['name' => 'price_yearly', 'label' => 'price_yearly'],
            ['name' => 'max_users', 'label' => 'user_limit'],
            ['name' => 'max_branches', 'label' => 'max_branches'],
            ['name' => 'max_storage_mb', 'label' => 'storage_limit_mb'],
            ['name' => 'modules_summary', 'label' => 'plan_modules'],
        ];
    }

    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        // Insert missing tiers only — never rewrite prices/names after admin edits.
        try {
            \Rateb\App\Services\PlanLimitService::ensureCanonicalPlansPersisted();
            (new \Rateb\App\Services\MigrationService())
                ->repairMarketingPlansCanonicalIfNeeded(\Rateb\App\Core\Database::connection());
        } catch (\Throwable $e) {
            error_log('PlansController ensure plans: ' . $e->getMessage());
        }

        $page = max(1, (int) $this->input('page', 1));
        // Show all six packages on one page (default list size is only 5).
        $limit = max(rateb_list_per_page(), 25);
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));

        $this->view(
            $this->viewPrefix . '/index',
            $this->applyPermissionFlags($this->indexViewData($limit, $offset, $page, $search)),
            $this->layout()
        );
    }

    protected function redirectAfterSave(int $id): void
    {
        // Stay on the edit form so admin can confirm their values persisted.
        if ($id > 0) {
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
            return;
        }
        parent::redirectAfterSave($id);
    }

    protected function indexViewData(int $limit, int $offset, int $page, string $search = ''): array
    {
        $data = parent::indexViewData($limit, $offset, $page, $search);
        $catalog = \Rateb\App\Services\PlanLimitService::moduleCatalog();
        $recommended = \Rateb\App\Services\PlanLimitService::recommendedSlug();
        foreach ($data['items'] as &$row) {
            $dbName = trim((string) ($row['name'] ?? ''));
            // Show DB name; fall back to localized label only when DB name is empty/corrupt.
            if ($dbName === '' || strtolower($dbName) === 'label' || strtolower($dbName) === (string) ($row['slug'] ?? '')) {
                $row['name'] = \Rateb\App\Models\Plan::marketingName($row);
            }
            if (strtolower(trim((string) ($row['slug'] ?? ''))) === $recommended) {
                $row['name'] = trim((string) $row['name']) . ' · ' . __('cms_plan_recommended');
            }
            $row['modules_summary'] = $this->formatModulesSummary($row, $catalog);
        }
        unset($row);

        return $data;
    }

    /** @param array<string, mixed> $row @param array<string, string> $catalog */
    private function formatModulesSummary(array $row, array $catalog): string
    {
        $decoded = json_decode((string) ($row['modules'] ?? ''), true);
        if (!is_array($decoded) || $decoded === []) {
            $slug = (string) ($row['slug'] ?? '');
            $decoded = $slug !== ''
                ? \Rateb\App\Services\PlanLimitService::modulesForSlug($slug)
                : [];
        }
        if ($decoded === []) {
            return '—';
        }
        $labels = [];
        foreach ($decoded as $mod) {
            $key = (string) $mod;
            $labels[] = __((string) ($catalog[$key] ?? $key));
        }

        return implode(' · ', $labels);
    }

    public function create(): void
    {
        $slug = strtolower(trim((string) ($_GET['tier'] ?? '')));
        $preset = $slug !== '' ? \Rateb\App\Services\PlanLimitService::tierForSlug($slug) : null;
        $item = null;
        if (is_array($preset)) {
            $item = [
                'slug' => $slug,
                'name' => (string) ($preset['name'] ?? ''),
                'description' => (string) ($preset['description'] ?? ''),
                'price_monthly' => $preset['price_monthly'] ?? '',
                'price_yearly' => $preset['price_yearly'] ?? '',
                'max_users' => $preset['max_users'] ?? '',
                'max_branches' => $preset['max_branches'] ?? '',
                'max_storage_mb' => $preset['max_storage_mb'] ?? '',
            ];
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __('plans'),
            'item' => $item,
            'tierPresets' => array_keys(\Rateb\App\Services\PlanLimitService::tierDefinitions()),
        ]), $this->layout());
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __('plans'),
            'item' => $item,
        ]), $this->layout());
    }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        $item = $extra['item'] ?? null;
        $selectedModules = [];
        if (is_array($item) && !empty($item['modules'])) {
            $decoded = json_decode((string) $item['modules'], true);
            $selectedModules = is_array($decoded) ? $decoded : [];
        }
        if ($selectedModules === [] && is_array($item)) {
            $slug = (string) ($item['slug'] ?? '');
            if ($slug !== '') {
                $selectedModules = \Rateb\App\Services\PlanLimitService::modulesForSlug($slug);
            }
        }
        if ($selectedModules === []) {
            $selectedModules = \Rateb\App\Services\PlanLimitService::defaultModules();
        }

        return array_merge(parent::formViewData($extra), [
            'moduleCatalog' => \Rateb\App\Services\PlanLimitService::moduleCatalog(),
            'selectedModules' => $selectedModules,
            'tierPresets' => $extra['tierPresets'] ?? array_keys(\Rateb\App\Services\PlanLimitService::tierDefinitions()),
        ]);
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        foreach (['price_monthly', 'price_yearly', 'max_users', 'max_branches', 'max_storage_mb'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = rateb_western_digits((string) $data[$key]);
            }
        }
        // Keep Arabic names/descriptions intact (JSON_UNESCAPED_UNICODE for modules only).
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }
        if (isset($data['description'])) {
            $data['description'] = trim((string) $data['description']);
        }
        $slug = strtolower(trim((string) ($data['slug'] ?? $this->input('slug', ''))));
        if ($slug !== '' && (trim((string) ($data['name'] ?? '')) === '' || strtolower((string) $data['name']) === 'label')) {
            $data['name'] = \Rateb\App\Models\Plan::marketingName(['slug' => $slug, 'name' => (string) ($data['name'] ?? '')]);
        }
        if ($slug !== '' && (trim((string) ($data['description'] ?? '')) === '' || trim((string) $data['description']) === '. ERP')) {
            $data['description'] = \Rateb\App\Models\Plan::marketingDescription(['slug' => $slug, 'description' => (string) ($data['description'] ?? '')]);
        }
        $modules = $this->input('modules', []);
        if (is_array($modules)) {
            $data['modules'] = json_encode(
                \Rateb\App\Services\PlanLimitService::filterKnownModules($modules),
                JSON_UNESCAPED_UNICODE
            );
        }
        if (empty($data['modules']) || $data['modules'] === '[]') {
            $data['modules'] = json_encode(
                \Rateb\App\Services\PlanLimitService::defaultModules(),
                JSON_UNESCAPED_UNICODE
            );
        }
        if ((int) ($data['max_users'] ?? 0) < 1) {
            $data['max_users'] = 10;
        }
        if ((int) ($data['max_branches'] ?? 0) < 1) {
            $data['max_branches'] = 10;
        }
        if ((int) ($data['max_storage_mb'] ?? 0) < 1) {
            $data['max_storage_mb'] = 1024;
        }
        return $data;
    }
}

final class UsersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\User();
        $this->viewPrefix = 'admin/users';
        $this->routePrefix = rateb_app_route('users');
        $this->entityName = 'users';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'suspended']],
            ['name' => 'locale', 'label' => 'language', 'type' => 'select', 'options' => ['ar', 'en']],
        ];
    }

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = max(rateb_list_per_page(), 25);
        $offset = ($page - 1) * $limit;
        $authz = new \Rateb\App\Services\AuthorizationService();
        $companyId = $this->scopedCompanyId();
        $isPlatformSa = function_exists('rateb_is_super_admin') && rateb_is_super_admin();

        // scope=company | platform (full SA) | staff (platform RBAC) | all
        $scopeRaw = strtolower(trim((string) $this->input('scope', '')));
        if (!in_array($scopeRaw, ['company', 'platform', 'staff', 'all'], true)) {
            $scopeRaw = ($companyId > 0) ? 'company' : ($isPlatformSa ? 'all' : 'company');
        }
        if (!$isPlatformSa) {
            $scopeRaw = 'company';
        }
        if ($scopeRaw === 'company' && $companyId < 1 && $isPlatformSa) {
            $scopeRaw = 'all';
        }

        $where = '1=1';
        $params = [];
        if ($scopeRaw === 'platform') {
            $where = 'COALESCE(is_super_admin, 0) = 1';
        } elseif ($scopeRaw === 'staff') {
            $where = 'COALESCE(is_super_admin, 0) = 0 AND (company_id IS NULL OR company_id = 0)';
        } elseif ($scopeRaw === 'company') {
            $where = 'COALESCE(is_super_admin, 0) = 0 AND company_id = :cid';
            $params['cid'] = $companyId;
        } elseif ($companyId > 0) {
            $where = '(COALESCE(is_super_admin, 0) = 1)
                   OR (COALESCE(is_super_admin, 0) = 0 AND company_id = :cid)
                   OR (COALESCE(is_super_admin, 0) = 0 AND (company_id IS NULL OR company_id = 0))';
            $params['cid'] = $companyId;
        }

        $items = $this->model->query(
            'SELECT * FROM rateb_users WHERE ' . $where . '
             ORDER BY COALESCE(is_super_admin, 0) DESC, id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
        $total = (int) ($this->model->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_users WHERE ' . $where,
            $params
        )['c'] ?? 0);

        $companyNames = [];
        foreach ($items as &$row) {
            $row['roles_list'] = $authz->getUserRoleNames((int) $row['id']);
            $isSa = !empty($row['is_super_admin']);
            $cid = (int) ($row['company_id'] ?? 0);
            if ($isSa) {
                $row['account_type'] = __('users_type_platform_sa');
                $row['company_label'] = '—';
                $row['roles_list'] = __('super_admin')
                    . ($row['roles_list'] !== '' ? ' · ' . $row['roles_list'] : '');
            } elseif ($cid < 1) {
                $row['account_type'] = __('users_type_platform_staff');
                $row['company_label'] = '—';
            } else {
                $row['account_type'] = __('users_type_company');
                if (!isset($companyNames[$cid])) {
                    $co = (new \Rateb\App\Models\Company())->find($cid);
                    $companyNames[$cid] = $co
                        ? ((string) ($co['name'] ?? '') . ' (#' . $cid . ')')
                        : ('#' . $cid);
                }
                $row['company_label'] = $companyNames[$cid];
            }
        }
        unset($row);

        $displayFields = [
            ['name' => 'account_type', 'label' => 'account_type'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'email', 'label' => 'email'],
            ['name' => 'phone', 'label' => 'phone'],
            ['name' => 'company_label', 'label' => 'companies'],
            ['name' => 'roles_list', 'label' => 'roles'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'locale', 'label' => 'language'],
        ];

        $listHelp = '';
        if ($isPlatformSa) {
            if ($scopeRaw === 'company') {
                $listHelp = __('users_list_company_only');
            } elseif ($scopeRaw === 'platform') {
                $listHelp = __('users_list_platform_only');
            } elseif ($scopeRaw === 'staff') {
                $listHelp = __('users_list_staff_only');
            } else {
                $listHelp = __('users_list_all_separated');
            }
        }

        $createUrl = rateb_url($this->routePrefix . '/create');
        if ($isPlatformSa && $scopeRaw === 'platform') {
            $createUrl = rateb_url_query(rateb_url('admin/users/create'), ['for' => 'platform']);
        } elseif ($isPlatformSa && $scopeRaw === 'staff') {
            $createUrl = rateb_url_query(rateb_url('admin/users/create'), ['for' => 'staff']);
        }

        $this->view($this->viewPrefix . '/index', [
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $displayFields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
            'listHelp' => $listHelp,
            'usersScope' => $scopeRaw,
            'usersScopeCompanyId' => $companyId,
            'showUsersScopeTabs' => $isPlatformSa,
            'createUrl' => $createUrl,
        ], $this->layout());
    }

    public function create(): void
    {
        if ($this->wantsPlatformUserForm()) {
            SessionManager::set('_rateb_users_form_platform', 1);
            SessionManager::forget('_rateb_users_form_staff');
        } elseif ($this->wantsPlatformStaffForm()) {
            SessionManager::set('_rateb_users_form_staff', 1);
            SessionManager::forget('_rateb_users_form_platform');
        } else {
            SessionManager::forget('_rateb_users_form_platform');
            SessionManager::forget('_rateb_users_form_staff');
        }
        $this->view($this->viewPrefix . '/form', $this->userFormData(null), $this->layout());
    }

    /** Platform SA creating/editing a platform super-admin (ignore ops company scope). */
    private function wantsPlatformUserForm(): bool
    {
        if (!(function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
            return false;
        }
        $for = strtolower(trim((string) $this->input('for', '')));
        $scope = strtolower(trim((string) $this->input('scope', '')));
        if ($for === 'platform' || $scope === 'platform') {
            return true;
        }
        if (!empty(SessionManager::get('_rateb_users_form_platform'))) {
            return true;
        }
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '' && (str_contains($ref, 'scope=platform') || str_contains($ref, 'for=platform'))) {
            return true;
        }

        return false;
    }

    /** Platform staff: not SA, no company — permissions via global roles. */
    private function wantsPlatformStaffForm(): bool
    {
        if (!(function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
            return false;
        }
        if ($this->wantsPlatformUserForm()) {
            return false;
        }
        $for = strtolower(trim((string) $this->input('for', '')));
        $scope = strtolower(trim((string) $this->input('scope', '')));
        if ($for === 'staff' || $scope === 'staff') {
            return true;
        }
        if (!empty(SessionManager::get('_rateb_users_form_staff'))) {
            return true;
        }
        // SA create/edit with company_id=0 → staff (not full SA), unless for=platform.
        if (array_key_exists('company_id', $_GET)) {
            $rawCid = trim((string) ($_GET['company_id'] ?? ''));
            if ($rawCid === '' || $rawCid === '0') {
                return true;
            }
        }
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        return $ref !== '' && (str_contains($ref, 'scope=staff') || str_contains($ref, 'for=staff'));
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item || !$this->userInScope($item)) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        // Edit mode follows the user record — never inherit create-session platform/staff flags.
        SessionManager::forget('_rateb_users_form_platform');
        SessionManager::forget('_rateb_users_form_staff');
        $for = strtolower(trim((string) $this->input('for', '')));
        $authz = new \Rateb\App\Services\AuthorizationService();
        $asStaff = $for === 'staff'
            || (empty($item['is_super_admin']) && (int) ($item['company_id'] ?? 0) < 1)
            || (
                $for !== 'platform'
                && !empty($item['is_super_admin'])
                && (int) ($item['company_id'] ?? 0) < 1
                && $this->userHasPlatformStaffRoles($authz, (int) $item['id'])
            );
        if ($for === 'platform' || (!empty($item['is_super_admin']) && !$asStaff)) {
            SessionManager::set('_rateb_users_form_platform', 1);
        } elseif ($asStaff) {
            SessionManager::set('_rateb_users_form_staff', 1);
            // Heal mis-saved SA flag so role permissions actually gate login/nav.
            if (!empty($item['is_super_admin'])) {
                $this->model->update($id, ['is_super_admin' => 0, 'company_id' => null]);
                $item['is_super_admin'] = 0;
                $item['company_id'] = null;
                (new AuditService())->log('update', $this->entityName, $id, [
                    'heal' => 'platform_staff_from_sa',
                ]);
            }
        }
        $this->view($this->viewPrefix . '/form', $this->userFormData($item), $this->layout());
    }

    /** @return array<string, mixed> */
    private function userFormData(?array $item): array
    {
        $authz = new \Rateb\App\Services\AuthorizationService();
        $userId = $item ? (int) $item['id'] : 0;
        $barcodeSvc = new \Rateb\App\Services\BarcodeLoginService();
        $barcode = null;
        $badgeScanQrUrl = '';
        $badgeLoginUrl = '';
        if ($userId > 0) {
            $barcode = $barcodeSvc->ensureUserBarcode($userId);
            if ($barcode) {
                $badgeScanQrUrl = $barcodeSvc->badgeScanQrUrl($barcode);
                $badgeLoginUrl = $barcodeSvc->badgeLoginUrl($barcode);
            }
        }
        $branchSvc = new \Rateb\App\Services\BranchService();
        $actorIsSa = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
        $for = strtolower(trim((string) $this->input('for', '')));
        if ($item !== null && $actorIsSa) {
            // Existing user: record wins over session/referer (except explicit for=).
            if ($for === 'platform') {
                $platformForm = true;
                $staffForm = false;
            } elseif ($for === 'staff' || (empty($item['is_super_admin']) && (int) ($item['company_id'] ?? 0) < 1)) {
                $platformForm = false;
                $staffForm = true;
            } elseif (!empty($item['is_super_admin']) && (int) ($item['company_id'] ?? 0) < 1) {
                // Mis-saved SA who already has platform staff roles → staff lock UI on edit.
                $platformForm = !$this->userHasPlatformStaffRoles($authz, (int) $item['id']);
                $staffForm = !$platformForm;
            } else {
                $platformForm = !empty($item['is_super_admin']);
                $staffForm = false;
            }
        } else {
            $platformForm = $this->wantsPlatformUserForm();
            $staffForm = !$platformForm && $this->wantsPlatformStaffForm();
        }
        $companyId = ($platformForm || $staffForm) ? 0 : $this->scopedCompanyId();
        $rolesCompanyId = $companyId > 0 ? $companyId : (int) ($item['company_id'] ?? 0);
        if ($rolesCompanyId > 0) {
            $authz->ensureCompanyRoles($rolesCompanyId);
        }
        if ($staffForm || $platformForm) {
            $authz->ensureSuggestedRoles();
        }
        // Staff/platform forms must use global roles (company_id 0) — never ops-company clones.
        $scopedRoles = $authz->allRoles(($staffForm || $platformForm) ? 0 : ($rolesCompanyId > 0 ? $rolesCompanyId : 0));
        if ($staffForm) {
            $platformSlugs = \Rateb\App\Services\AuthorizationService::platformRoleSlugs();
            // Staff use global platform roles only; skip legacy «super-admin» slug (flag instead).
            $scopedRoles = array_values(array_filter(
                $scopedRoles,
                static function (array $role) use ($platformSlugs): bool {
                    $slug = (string) ($role['slug'] ?? '');
                    if ($slug === '' || $slug === 'super-admin') {
                        return false;
                    }
                    if ($platformSlugs === []) {
                        return (int) ($role['company_id'] ?? 0) < 1;
                    }

                    return in_array($slug, $platformSlugs, true) && (int) ($role['company_id'] ?? 0) < 1;
                }
            ));
        }
        $companies = $companyId > 0
            ? array_values(array_filter(
                (new \Rateb\App\Models\Company())->all(200, 0),
                static fn (array $row): bool => (int) ($row['id'] ?? 0) === $companyId
            ))
            : (new \Rateb\App\Models\Company())->all(200, 0);
        $formHelp = '';
        if ($platformForm) {
            $formHelp = __('users_create_platform_sa_hint');
        } elseif ($staffForm) {
            $formHelp = __('users_create_platform_staff_hint');
        } elseif ($companyId > 0) {
            $formHelp = __('users_create_company_hint');
        } elseif (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            $formHelp = __('users_create_platform_hint');
        }

        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('users'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'roles' => $scopedRoles,
            'companies' => $companies,
            'selectedRoles' => $userId > 0 ? $authz->getUserRoleIds($userId) : [],
            'selectedBranches' => $userId > 0 ? $branchSvc->getUserBranchIds($userId) : [],
            'branchesByCompany' => $branchSvc->optionsByCompany(),
            'isSuperAdmin' => $platformForm ? true : !empty($item['is_super_admin']),
            // Platform SA must always see the super-admin checkbox (ops company must not hide it).
            'hideSuperAdminFlag' => ($companyId > 0 || $staffForm)
                && !$platformForm
                && !(function_exists('rateb_is_super_admin') && rateb_is_super_admin() && !$staffForm),
            'defaultCompanyId' => ($platformForm || $staffForm || $companyId < 1) ? null : $companyId,
            'platformUserForm' => $platformForm,
            'platformStaffForm' => $staffForm,
            'formHelp' => $formHelp,
            'loginBarcode' => $barcode,
            'badgeScanQrUrl' => $badgeScanQrUrl,
            'badgeLoginUrl' => $badgeLoginUrl,
            'badgeRegenerateAction' => $userId > 0 ? rateb_url($this->routePrefix . '/' . $userId . '/regenerate-barcode') : '',
            'branchRestrictedRoleIds' => array_values(array_map(
                static fn (array $role): int => (int) $role['id'],
                array_filter(
                    $scopedRoles,
                    static fn (array $role): bool => (new \Rateb\App\Services\BranchAccessService())
                        ->slugRequiresBranchAssignment((string) ($role['slug'] ?? ''))
                )
            )),
            'rolesGrouped' => $this->groupRolesForUserForm($scopedRoles),
            'permissionGroups' => $staffForm ? $authz->allPermissionsGrouped() : [],
            'rolePermissionMap' => $staffForm ? $this->rolePermissionMapForRoles($authz, $scopedRoles) : [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $roles
     * @return array<int, array<int, int>>
     */
    private function rolePermissionMapForRoles(\Rateb\App\Services\AuthorizationService $authz, array $roles): array
    {
        $map = [];
        foreach ($roles as $role) {
            $rid = (int) ($role['id'] ?? 0);
            if ($rid > 0) {
                $map[$rid] = $authz->getRolePermissionIds($rid);
            }
        }

        return $map;
    }

    /** True when user already has a global platform staff role (not the SA flag/slug). */
    private function userHasPlatformStaffRoles(\Rateb\App\Services\AuthorizationService $authz, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $roleIds = $authz->getUserRoleIds($userId);
        if ($roleIds === []) {
            return false;
        }
        $platformSlugs = \Rateb\App\Services\AuthorizationService::platformRoleSlugs();
        $roleModel = new \Rateb\App\Models\Role();
        foreach ($roleIds as $rid) {
            $role = $roleModel->find((int) $rid);
            if (!$role || (int) ($role['company_id'] ?? 0) > 0) {
                continue;
            }
            $slug = (string) ($role['slug'] ?? '');
            if ($slug === '' || $slug === 'super-admin') {
                continue;
            }
            if ($platformSlugs === [] || in_array($slug, $platformSlugs, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $roles
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupRolesForUserForm(array $roles): array
    {
        $groups = [
            'branch' => [],
            'hq' => [],
            'operations' => [],
            'admin' => [],
            'other' => [],
        ];
        foreach ($roles as $role) {
            $slug = (string) ($role['slug'] ?? '');
            if (in_array($slug, ['branch_manager', 'branch_user'], true)) {
                $groups['branch'][] = $role;
            } elseif (in_array($slug, ['hq_admin', 'hq_manager'], true)) {
                $groups['hq'][] = $role;
            } elseif (in_array($slug, ['procurement-manager', 'inventory-manager', 'accountant', 'accounting-approver', 'hr-manager'], true)) {
                $groups['operations'][] = $role;
            } elseif (in_array($slug, ['access-manager', 'company-full-access', 'super-admin'], true)) {
                $groups['admin'][] = $role;
            } else {
                $groups['other'][] = $role;
            }
        }

        return $groups;
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (trim((string) $this->input('password', '')) === '') {
            SessionManager::flash('error', __('password_required'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $data = $this->collectData();
        $roleIds = array_map('intval', (array) $this->input('role_ids', []));
        $this->prepareCompanyUserForLogin($data, $roleIds);
        $this->assertPlatformStaffRoles($data, $roleIds, rateb_url_query(rateb_url('admin/users/create'), ['for' => 'staff']));
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0 && !(new \Rateb\App\Services\PlanLimitService())->canAddUser($companyId)) {
            SessionManager::flash('error', __('user_limit_reached'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $this->assertBranchAssignmentForRoles($data, $roleIds, rateb_url($this->routePrefix . '/create'));
        $id = $this->model->create($data);
        (new \Rateb\App\Services\AuthorizationService())->syncUserRoles($id, $roleIds);
        $branchIds = array_map('intval', (array) $this->input('branch_ids', []));
        (new \Rateb\App\Services\BranchService())->syncUserBranches($id, $companyId, $branchIds);
        if ((string) ($data['status'] ?? '') === 'active') {
            (new \Rateb\App\Services\BarcodeLoginService())->ensureUserBarcode($id);
        }
        if (trim((string) $this->input('password', '')) !== '') {
            (new \Rateb\App\Services\AccountLockoutService())->clearLock($id);
        }
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $isStaff = empty($data['is_super_admin']) && (int) ($data['company_id'] ?? 0) < 1;
        $this->redirect($this->usersListRedirectUrl(!empty($data['is_super_admin']), $isStaff));
    }

    private function usersListRedirectUrl(bool $platformSa, bool $platformStaff = false): string
    {
        $url = rateb_url('admin/users');
        if ($platformSa && function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return function_exists('rateb_url_query')
                ? rateb_url_query($url, ['scope' => 'platform'])
                : ($url . '?scope=platform');
        }
        if ($platformStaff && function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return function_exists('rateb_url_query')
                ? rateb_url_query($url, ['scope' => 'staff'])
                : ($url . '?scope=staff');
        }
        $url = rateb_url($this->routePrefix);

        return function_exists('rateb_url_query')
            ? rateb_url_query($url, ['scope' => 'company'])
            : ($url . '?scope=company');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing || !$this->userInScope($existing)) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $data = $this->collectData();
        $roleIds = array_map('intval', (array) $this->input('role_ids', []));
        $this->prepareCompanyUserForLogin($data, $roleIds);
        $this->assertPlatformStaffRoles(
            $data,
            $roleIds,
            rateb_url_query(rateb_url('admin/users/' . $id . '/edit'), ['for' => 'staff'])
        );
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0) {
            $wasOtherCompany = (int) ($existing['company_id'] ?? 0) !== $companyId;
            if ($wasOtherCompany) {
                try {
                    (new \Rateb\App\Services\PlanLimitService())->assertCanAddUser($companyId);
                } catch (\RuntimeException $e) {
                    SessionManager::flash('error', $e->getMessage());
                    $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
                }
            }
        }
        $this->assertBranchAssignmentForRoles($data, $roleIds, rateb_url($this->routePrefix . '/' . $id . '/edit'));
        $this->model->update($id, $data);
        (new \Rateb\App\Services\AuthorizationService())->syncUserRoles($id, $roleIds);
        $branchIds = array_map('intval', (array) $this->input('branch_ids', []));
        (new \Rateb\App\Services\BranchService())->syncUserBranches($id, $companyId, $branchIds);
        if ((string) ($data['status'] ?? '') === 'active') {
            (new \Rateb\App\Services\BarcodeLoginService())->ensureUserBarcode($id);
        }
        if (trim((string) $this->input('password', '')) !== '') {
            (new \Rateb\App\Services\AccountLockoutService())->clearLock($id);
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $isStaff = empty($data['is_super_admin']) && (int) ($data['company_id'] ?? 0) < 1;
        $this->redirect($this->usersListRedirectUrl(!empty($data['is_super_admin']), $isStaff));
    }

    public function destroy(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing || !$this->userInScope($existing)) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        parent::destroy($params);
    }

    public function bulkDestroy(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $ids = array_values(array_filter(array_map('intval', (array) $this->input('ids', []))));
        if ($this->scopedCompanyId() > 0) {
            $ids = array_values(array_filter($ids, function (int $id): bool {
                $row = $this->model->find($id);
                return $row !== null && $this->userInScope($row);
            }));
        }
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $deleted = $this->model->deleteMany($ids);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', $this->entityName, $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $password = (string) $this->input('password', '');
        unset($data['password'], $data['password_hash']);
        if ($password !== '') {
            $this->model->applyPassword($data, $password);
        }
        $checkedSa = (bool) $this->input('is_super_admin');
        $data['is_super_admin'] = $checkedSa ? 1 : 0;
        $scopedCompanyId = $this->scopedCompanyId();
        $platformForm = $this->wantsPlatformUserForm();
        $staffForm = $this->wantsPlatformStaffForm();
        $actorIsPlatformSa = function_exists('rateb_is_super_admin') && rateb_is_super_admin();

        if ($actorIsPlatformSa && ($platformForm || $checkedSa) && !$staffForm) {
            $data['is_super_admin'] = 1;
            $data['company_id'] = null;
            SessionManager::forget('_rateb_users_form_platform');
            SessionManager::forget('_rateb_users_form_staff');
        } elseif ($actorIsPlatformSa && $staffForm) {
            $data['is_super_admin'] = 0;
            $data['company_id'] = null;
            SessionManager::forget('_rateb_users_form_platform');
            SessionManager::forget('_rateb_users_form_staff');
        } elseif ($scopedCompanyId > 0) {
            $data['is_super_admin'] = 0;
            $data['company_id'] = $scopedCompanyId;
            SessionManager::forget('_rateb_users_form_platform');
            SessionManager::forget('_rateb_users_form_staff');
        } elseif (!empty($data['is_super_admin'])) {
            $data['company_id'] = null;
        } elseif ($data['company_id'] === '' || $data['company_id'] === '0' || $data['company_id'] === null) {
            $data['company_id'] = null;
        } else {
            $companyId = (int) $data['company_id'];
            $company = (new \Rateb\App\Models\Company())->find($companyId);
            $data['company_id'] = $company ? $companyId : null;
        }
        return $data;
    }

    /** @param array<string, mixed> $data @param array<int, int> $roleIds */
    private function assertBranchAssignmentForRoles(array $data, array $roleIds, string $redirectUrl): void
    {
        if (!empty($data['is_super_admin'])) {
            return;
        }
        if (!(new \Rateb\App\Services\BranchAccessService())->roleIdsRequireBranchAssignment($roleIds)) {
            return;
        }
        $branchIds = array_values(array_filter(
            array_map('intval', (array) $this->input('branch_ids', [])),
            static fn (int $id): bool => $id > 0
        ));
        if ($branchIds !== []) {
            return;
        }
        SessionManager::flash('error', __('branch_assignment_required'));
        $this->redirect($redirectUrl);
    }

    /** Platform staff need at least one global role to log in and use RBAC. */
    /** @param array<string, mixed> $data @param array<int, int> $roleIds */
    private function assertPlatformStaffRoles(array $data, array $roleIds, string $redirectUrl): void
    {
        if (!empty($data['is_super_admin']) || (int) ($data['company_id'] ?? 0) > 0) {
            return;
        }
        $for = strtolower(trim((string) $this->input('for', '')));
        if (!$this->wantsPlatformStaffForm() && $for !== 'staff') {
            return;
        }
        $ids = array_values(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0));
        if ($ids !== []) {
            return;
        }
        SessionManager::set('_rateb_users_form_staff', 1);
        SessionManager::flash('error', __('users_staff_roles_required'));
        $this->redirect($redirectUrl);
    }

    /** @param array<string, mixed> $data @param array<int, int> $roleIds */
    private function prepareCompanyUserForLogin(array &$data, array &$roleIds): void
    {
        if (!empty($data['is_super_admin'])) {
            return;
        }

        // Platform staff: no tenant company; permissions come from global roles only.
        $for = strtolower(trim((string) $this->input('for', '')));
        if ($this->wantsPlatformStaffForm() || $for === 'staff') {
            $data['company_id'] = null;
            $data['is_super_admin'] = 0;

            return;
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId < 1) {
            $companyId = \Rateb\App\Services\DedicatedTenantPolicy::primaryCompanyId();
            if ($companyId > 0) {
                $data['company_id'] = $companyId;
            }
        }

        // Platform SaaS + agency/dedicated: company users need an active subscription row
        // or login fails with err=access («الاشتراك منتهٍ / موقوف»).
        if ($companyId > 0) {
            try {
                (new \Rateb\App\Services\DedicatedCompanySeedService())->ensureCompanyLoginReady($companyId);
            } catch (\Throwable $e) {
                error_log('prepareCompanyUserForLogin: ' . $e->getMessage());
            }
        }

        if ($roleIds === [] && $companyId > 0) {
            $authz = new \Rateb\App\Services\AuthorizationService();
            $authz->ensureCompanyRoles($companyId);
            $roleRow = $authz->findRoleBySlug('company-full-access', $companyId);
            if ($roleRow) {
                $roleIds = [(int) $roleRow['id']];
            }
        }
    }

    public function regenerateBarcode(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $user = $this->model->find($id);
        if (!$user || !$this->userInScope($user)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $svc = new \Rateb\App\Services\BarcodeLoginService();
        $newCode = $svc->generateBarcodeValue($id, bin2hex(random_bytes(4)));
        $this->model->update($id, ['login_barcode' => $newCode]);
        (new AuditService())->log('regenerate', 'login_barcode', $id);
        SessionManager::flash('success', __('barcode_regenerated'));
        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
    }

    private function scopedCompanyId(): int
    {
        // When platform SA selected an ops company (e.g. aaa), treat users as company users.
        if (function_exists('rateb_resolve_ops_company_id')) {
            $opsId = (int) rateb_resolve_ops_company_id();
            if ($opsId > 0) {
                return $opsId;
            }
        }
        if (!function_exists('rateb_company_access_routes_enabled') || !rateb_company_access_routes_enabled()) {
            return 0;
        }
        if (rateb_is_super_admin()) {
            return 0;
        }

        return (int) ($_SESSION['rateb_company_id'] ?? 0);
    }

    /** @param array<string, mixed>|null $user */
    private function userInScope(?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        // Platform SA manages SA + staff + any company user regardless of ops picker.
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            if (!empty($user['is_super_admin']) || (int) ($user['company_id'] ?? 0) < 1) {
                return true;
            }
        }
        $companyId = $this->scopedCompanyId();
        if ($companyId < 1) {
            return true;
        }
        if (!empty($user['is_super_admin'])) {
            return function_exists('rateb_is_super_admin') && rateb_is_super_admin();
        }

        return (int) ($user['company_id'] ?? 0) === $companyId;
    }
}

final class RolesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Role();
        $this->viewPrefix = 'admin/roles';
        $this->routePrefix = rateb_app_route('roles');
        $this->entityName = 'roles';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'text'],
        ];
    }

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $authz = new \Rateb\App\Services\AuthorizationService();
        $rbac = $this->rbacScope();
        $companyId = (int) $rbac['company_id'];
        if ($companyId > 0) {
            $authz->ensureCompanyRoles($companyId);
        } else {
            $authz->ensureSuggestedRoles();
        }
        $items = $authz->allRoles($companyId > 0 ? $companyId : 0);
        $total = count($items);
        $offset = ($page - 1) * $limit;
        $items = array_slice($items, $offset, $limit);
        foreach ($items as &$row) {
            $row['permission_count'] = $authz->getRolePermissionCount((int) $row['id']);
            if (function_exists('rateb_role_label')) {
                $row['name'] = rateb_role_label($row);
            }
            if (function_exists('rateb_role_description')) {
                $row['description'] = rateb_role_description($row);
            }
        }
        unset($row);

        $displayFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'slug', 'label' => 'slug'],
            ['name' => 'description', 'label' => 'description'],
            ['name' => 'permission_count', 'label' => 'permissions_count'],
        ];

        $rolePermissionMap = [];
        foreach ($items as $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid > 0) {
                $rolePermissionMap[$rid] = $authz->getRolePermissionIds($rid);
            }
        }

        $rolesUrl = rateb_url($this->routePrefix);
        $this->view($this->viewPrefix . '/index', [
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $displayFields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled && $rbac['scope'] === 'company',
            'createEnabled' => $this->createEnabled && $rbac['scope'] === 'company',
            'actionsEnabled' => $this->actionsEnabled,
            'rbacScope' => $rbac['scope'],
            'rbacOpsCompanyId' => $this->opsCompanyIdForRbac(),
            'rbacBaseUrl' => $rolesUrl,
            'listHelp' => $rbac['scope'] === 'platform'
                ? __('rbac_roles_platform_list_help')
                : '',
            'roleLockUi' => $rbac['scope'] === 'platform',
            'permissionGroups' => $authz->allPermissionsGrouped(),
            'rolePermissionMap' => $rolePermissionMap,
        ], $this->layout());
    }

    /** Save only permission checkboxes for a role (lock panel). */
    public function savePermissions(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect($this->rolesListUrl());
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing || !$this->roleInScope($existing)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect($this->rolesListUrl());
        }
        $permIds = array_map('intval', (array) $this->input('permission_ids', []));
        (new \Rateb\App\Services\AuthorizationService())->syncRolePermissions($id, $permIds);
        (new AuditService())->log('update', 'role_permissions', $id, ['count' => count($permIds)]);

        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if ($wantsJson) {
            Response::json([
                'ok' => true,
                'role_id' => $id,
                'count' => count($permIds),
                'message' => __('role_lock_saved'),
            ]);
        }

        SessionManager::flash('success', __('role_lock_saved'));
        $return = trim((string) $this->input('return', ''));
        $origin = function_exists('rateb_site_origin') ? rateb_site_origin() : '';
        if ($return !== '' && $origin !== '' && str_starts_with($return, $origin . '/')) {
            $this->redirect($return);
        }
        $this->redirect($this->rolesListUrl());
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', $this->roleFormData(null), $this->layout());
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        if (!$this->roleInScope($item)) {
            // Platform admin switched company while editing a global/other-tenant role:
            // rematch the same slug to the active company's role copy.
            $resolved = $this->resolveRoleForActiveCompany($item);
            if ($resolved !== null) {
                $qid = (int) $resolved['id'];
                $url = rateb_url($this->routePrefix . '/' . $qid . '/edit');
                $cid = $this->scopedCompanyId();
                if ($cid > 0) {
                    $url .= (str_contains($url, '?') ? '&' : '?') . 'company_id=' . $cid;
                }
                $this->redirect($url);
                return;
            }
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->roleFormData($item), $this->layout());
    }

    /** @return array<string, mixed> */
    private function roleFormData(?array $item): array
    {
        $authz = new \Rateb\App\Services\AuthorizationService();
        $roleId = $item ? (int) $item['id'] : 0;
        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('roles'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'permissionGroups' => $authz->allPermissionsGrouped(),
            'selectedPermissions' => $roleId > 0 ? $authz->getRolePermissionIds($roleId) : [],
        ];
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $companyId = $this->scopedCompanyId();
        if ($companyId > 0) {
            $data['company_id'] = $companyId;
        }
        $permIds = array_map('intval', (array) $this->input('permission_ids', []));
        $id = $this->model->create($data);
        (new \Rateb\App\Services\AuthorizationService())->syncRolePermissions($id, $permIds);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect($this->rolesListUrl());
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect($this->rolesListUrl());
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect($this->rolesListUrl());
        }
        if (!$this->roleInScope($existing)) {
            $resolved = $this->resolveRoleForActiveCompany($existing);
            if ($resolved === null) {
                SessionManager::flash('error', __('invalid_request'));
                $this->redirect($this->rolesListUrl());
            }
            $existing = $resolved;
            $id = (int) $existing['id'];
        }
        $data = $this->collectData();
        // Keep system slug/name stable for seeded company roles; still allow description edits.
        if (!empty($existing['is_system'])) {
            unset($data['slug']);
        }
        $permIds = array_map('intval', (array) $this->input('permission_ids', []));
        $this->model->update($id, $data);
        (new \Rateb\App\Services\AuthorizationService())->syncRolePermissions($id, $permIds);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect($this->rolesListUrl());
    }

    private function rolesListUrl(): string
    {
        $url = rateb_url($this->routePrefix);
        $rbac = $this->rbacScope();
        if (function_exists('rateb_url_query')) {
            return rateb_url_query($url, ['scope' => $rbac['scope']]);
        }

        return $url . '?scope=' . rawurlencode($rbac['scope']);
    }

    /** @param array<int, int> $ids */
    private function purgeRoleLinks(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return;
        }
        $db = \Rateb\App\Core\Database::connection();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("DELETE FROM rateb_role_permissions WHERE role_id IN ($ph)")->execute($ids);
        $db->prepare("DELETE FROM rateb_user_roles WHERE role_id IN ($ph)")->execute($ids);
    }

    public function destroy(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            $this->redirect(rateb_url($this->routePrefix));
        }
        $existing = $this->model->find($id);
        if (!$existing || !$this->roleInScope($existing)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->purgeRoleLinks([$id]);
        try {
            $this->model->delete($id);
            (new AuditService())->log('delete', $this->entityName, $id);
            SessionManager::flash('success', __('delete') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function bulkDestroy(): void
    {
        $this->guardManage();
        if (!$this->bulkEnabled) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->purgeRoleLinks($ids);
        try {
            $deleted = $this->model->deleteMany($ids);
            foreach ($ids as $id) {
                (new AuditService())->log('bulk_delete', $this->entityName, $id);
            }
            SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    private function scopedCompanyId(): int
    {
        return (int) $this->rbacScope()['company_id'];
    }

    /** @return array{scope:string,company_id:int} */
    private function rbacScope(): array
    {
        return \Rateb\App\Services\AuthorizationService::resolveRbacUiScope(
            (string) $this->input('scope', '')
        );
    }

    private function opsCompanyIdForRbac(): int
    {
        if (function_exists('rateb_resolve_ops_company_id')) {
            $id = (int) rateb_resolve_ops_company_id();
            if ($id > 0) {
                return $id;
            }
        }

        return (int) ($_SESSION['rateb_company_id'] ?? 0);
    }

    /** @param array<string, mixed>|null $role */
    private function roleInScope(?array $role): bool
    {
        if ($role === null) {
            return false;
        }
        $companyId = $this->scopedCompanyId();
        if ($companyId < 1) {
            // Platform scope: only global roles.
            return (int) ($role['company_id'] ?? 0) < 1;
        }

        return (int) ($role['company_id'] ?? 0) === $companyId;
    }

    /**
     * When a company is selected, map a platform/global role to that company's clone by slug.
     *
     * @param array<string, mixed> $role
     * @return array<string, mixed>|null
     */
    private function resolveRoleForActiveCompany(array $role): ?array
    {
        $companyId = $this->scopedCompanyId();
        $slug = trim((string) ($role['slug'] ?? ''));
        if ($companyId < 1 || $slug === '') {
            return null;
        }
        $authz = new \Rateb\App\Services\AuthorizationService();
        $authz->ensureCompanyRoles($companyId);
        $clone = $authz->findRoleBySlug($slug, $companyId);
        if (!is_array($clone) || (int) ($clone['id'] ?? 0) < 1) {
            return null;
        }
        if ((int) ($clone['id'] ?? 0) === (int) ($role['id'] ?? 0)) {
            return null;
        }

        return $clone;
    }
}

final class PermissionsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Permission();
        $this->viewPrefix = 'admin/permissions';
        $this->routePrefix = rateb_app_route('permissions');
        $this->entityName = 'permissions';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text'],
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'module', 'label' => 'Module', 'type' => 'select', 'lookup' => 'permission_modules'],
            ['name' => 'description', 'label' => 'description', 'type' => 'textarea'],
            ['name' => 'description_ar', 'label' => 'description_ar', 'type' => 'textarea'],
        ];
    }

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $items = $this->model->all($limit, $offset);

        foreach ($items as &$row) {
            $row['name'] = rateb_permission_label($row);
            $row['description'] = rateb_permission_description($row);
        }
        unset($row);

        $displayFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'slug', 'label' => 'slug'],
            ['name' => 'module', 'label' => 'module'],
        ];

        $catalogLocked = function_exists('rateb_tenant_permission_catalog_locked') && rateb_tenant_permission_catalog_locked();

        $this->view($this->viewPrefix . '/index', [
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $displayFields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $catalogLocked ? false : $this->bulkEnabled,
            'createEnabled' => $catalogLocked ? false : $this->createEnabled,
            'actionsEnabled' => $catalogLocked ? false : $this->actionsEnabled,
            'catalogLocked' => $catalogLocked,
        ], $this->layout());
    }

    public function create(): void
    {
        $this->guardPermissionCatalogWrite();
        parent::create();
    }

    public function store(): void
    {
        $this->guardPermissionCatalogWrite();
        parent::store();
    }

    public function update(array $params): void
    {
        $this->guardPermissionCatalogWrite();
        parent::update($params);
    }

    public function destroy(array $params): void
    {
        $this->guardPermissionCatalogWrite();
        parent::destroy($params);
    }

    public function bulkDestroy(): void
    {
        $this->guardPermissionCatalogWrite();
        parent::bulkDestroy();
    }

    private function guardPermissionCatalogWrite(): void
    {
        if (function_exists('rateb_tenant_permission_catalog_locked') && rateb_tenant_permission_catalog_locked()) {
            \Rateb\App\Core\SessionManager::flash('error', __('tenant_permission_catalog_locked'));
            $this->redirect(rateb_url($this->routePrefix));
        }
    }
}

final class PaymentsController extends \Rateb\App\Controllers\CrudController
{
    private \Rateb\App\Services\BillingService $billing;

    public function __construct()
    {
        $this->billing = new \Rateb\App\Services\BillingService();
        $this->model = new \Rateb\App\Models\Payment();
        $this->viewPrefix = 'admin/payments';
        $this->routePrefix = 'admin/payments';
        $this->entityName = 'payments';
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'company_select'],
            ['name' => 'subscription_id', 'label' => 'subscriptions', 'type' => 'subscription_select'],
            ['name' => 'invoice_id', 'label' => 'link_invoice', 'type' => 'invoice_select'],
            ['name' => 'amount', 'label' => 'amount', 'type' => 'number'],
            ['name' => 'currency', 'label' => 'currency', 'type' => 'select', 'lookup' => 'currencies'],
            ['name' => 'method', 'label' => 'payment_method', 'type' => 'select', 'lookup' => 'payment_methods'],
            ['name' => 'reference_no', 'label' => 'reference_no', 'type' => 'text'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'options' => ['pending', 'completed', 'failed', 'refunded']],
            ['name' => 'paid_at', 'label' => 'paid_at', 'type' => 'datetime-local'],
        ];
    }

    public function index(): void
    {
        $this->billing->ensureBillingReady();
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $this->view($this->viewPrefix . '/index', [
            'title' => __('payments'),
            'items' => $this->model->withRelations($limit, $offset),
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => [
                ['name' => 'company_name', 'label' => 'company_name'],
                ['name' => 'amount', 'label' => 'amount'],
                ['name' => 'currency', 'label' => 'currency'],
                ['name' => 'method', 'label' => 'payment_method'],
                ['name' => 'status', 'label' => 'status'],
                ['name' => 'paid_at', 'label' => 'paid_at'],
            ],
            'csrf' => Csrf::token(),
            'createEnabled' => rateb_can('billing.manage'),
            'actionsEnabled' => rateb_can('billing.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can('billing.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->billing->ensureBillingReady();
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __('payments'),
            'item' => ['currency' => 'SAR', 'status' => 'pending', 'paid_at' => date('Y-m-d\TH:i')],
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'subscriptions' => $this->billing->subscriptionOptions(),
            'invoices' => $this->billing->invoiceOptions(),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        if (!rateb_can('billing.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectPaymentData();
        if (!$this->validatePaymentData($data)) {
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        $row = $this->model->find($id);
        if ($row && ($row['status'] ?? '') === 'completed') {
            (new \Rateb\App\Services\AccountingService())->postPayment($row);
            $this->syncPaymentsToInvoices($row);
        }
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        if (!empty($item['paid_at'])) {
            $item['paid_at'] = date('Y-m-d\TH:i', strtotime((string) $item['paid_at']));
        }
        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __('payments'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'subscriptions' => $this->billing->subscriptionOptions((int) ($item['company_id'] ?? 0)),
            'invoices' => $this->billing->invoiceOptions((int) ($item['company_id'] ?? 0), (int) ($item['invoice_id'] ?? 0)),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectPaymentData();
        if (!$this->validatePaymentData($data)) {
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        $row = $this->model->find($id);
        if ($row && ($row['status'] ?? '') === 'completed') {
            (new \Rateb\App\Services\AccountingService())->postPayment($row);
            $this->syncPaymentsToInvoices($row);
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @param array<string, mixed> $data */
    private function validatePaymentData(array $data): bool
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        if (!$this->billing->companyExists($companyId)) {
            SessionManager::flash('error', __('billing_company_required'));
            return false;
        }
        $subId = isset($data['subscription_id']) && $data['subscription_id'] !== '' ? (int) $data['subscription_id'] : null;
        if (!$this->billing->subscriptionBelongsToCompany($subId, $companyId)) {
            SessionManager::flash('error', __('billing_subscription_invalid'));
            return false;
        }
        return true;
    }

    /** @return array<string, mixed> */
    private function collectPaymentData(): array
    {
        $data = $this->collectData();
        $data['company_id'] = (int) ($data['company_id'] ?? 0);
        $data['amount'] = (float) ($data['amount'] ?? 0);
        $data['currency'] = ($data['currency'] ?? '') !== '' ? strtoupper((string) $data['currency']) : 'SAR';
        if (($data['subscription_id'] ?? '') === '') {
            $data['subscription_id'] = null;
        } else {
            $data['subscription_id'] = (int) $data['subscription_id'];
        }
        if (($data['paid_at'] ?? '') === '') {
            $data['paid_at'] = null;
        } else {
            $data['paid_at'] = date('Y-m-d H:i:s', strtotime((string) $data['paid_at']));
        }
        if (($data['status'] ?? '') === 'completed' && $data['paid_at'] === null) {
            $data['paid_at'] = date('Y-m-d H:i:s');
        }
        if (($data['invoice_id'] ?? '') === '') {
            $data['invoice_id'] = null;
        } else {
            $data['invoice_id'] = (int) $data['invoice_id'];
        }
        return $data;
    }

    /** @param array<string, mixed> $row */
    private function syncPaymentsToInvoices(array $row): void
    {
        $automation = new \Rateb\App\Services\BillingAutomationService();
        $invoiceId = (int) ($row['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $automation->recalculatePaymentStatus($invoiceId);
            return;
        }
        $companyId = (int) ($row['company_id'] ?? 0);
        if ($companyId < 1) {
            return;
        }
        $invoices = (new \Rateb\App\Models\Invoice())->query(
            "SELECT id FROM rateb_invoices WHERE company_id = :cid AND payment_status <> 'paid' ORDER BY due_date ASC",
            ['cid' => $companyId]
        );
        foreach ($invoices as $inv) {
            $automation->recalculatePaymentStatus((int) ($inv['id'] ?? 0));
        }
    }
}

final class InvoicesController extends \Rateb\App\Controllers\CrudController
{
    private \Rateb\App\Services\BillingService $billing;

    public function __construct()
    {
        $this->billing = new \Rateb\App\Services\BillingService();
        $this->model = new \Rateb\App\Models\Invoice();
        $this->viewPrefix = 'admin/invoices';
        $this->routePrefix = 'admin/invoices';
        $this->entityName = 'invoices';
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'company_select'],
            ['name' => 'subscription_id', 'label' => 'subscriptions', 'type' => 'subscription_select'],
            ['name' => 'invoice_no', 'label' => 'invoice_no', 'type' => 'text'],
            ['name' => 'amount', 'label' => 'amount', 'type' => 'number'],
            ['name' => 'tax_amount', 'label' => 'tax_amount', 'type' => 'number'],
            ['name' => 'total_amount', 'label' => 'total_amount', 'type' => 'number'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'options' => ['draft', 'sent', 'paid', 'overdue', 'cancelled']],
            ['name' => 'issued_at', 'label' => 'issued_at', 'type' => 'date'],
            ['name' => 'due_date', 'label' => 'due_date', 'type' => 'date'],
        ];
    }

    public function index(): void
    {
        $this->billing->ensureBillingReady();
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $items = $this->model->withRelations($limit, $offset);
        $dueAlerts = $this->buildDueAlerts($items);
        $this->view($this->viewPrefix . '/index', [
            'title' => __('invoices'),
            'items' => $items,
            'dueAlerts' => $dueAlerts,
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => [
                ['name' => 'invoice_no', 'label' => 'invoice_no'],
                ['name' => 'company_name', 'label' => 'company_name'],
                ['name' => 'barcode', 'label' => 'document_barcode', 'type' => 'barcode'],
                ['name' => 'total_amount', 'label' => 'total_amount'],
                ['name' => 'tax_amount', 'label' => 'tax_amount'],
                ['name' => 'status', 'label' => 'status'],
                ['name' => 'issued_at', 'label' => 'issued_at'],
            ],
            'csrf' => Csrf::token(),
            'createEnabled' => rateb_can('billing.manage'),
            'actionsEnabled' => rateb_can('billing.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can('billing.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->billing->ensureBillingReady();
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create_tax_invoice'),
            'item' => [
                'invoice_no' => $this->billing->nextInvoiceNo(),
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'issued_at' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'tax_amount' => '0',
                'total_amount' => '0',
                'tax_rate' => '15',
                'discount_amount' => '0',
                'discount_type' => 'value',
                'payment_terms_days' => 30,
                'payment_method' => 'bank_transfer',
                'invoice_type' => 'tax',
                'currency' => 'SAR',
            ],
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'subscriptions' => $this->billing->subscriptionOptions(),
            'lineItems' => [],
            'bankAccounts' => $this->billing->supplierBankAccountOptions(),
            'chartAccounts' => [],
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        if (!rateb_can('billing.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectInvoiceData();
        if (!$this->validateInvoiceData($data)) {
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        $this->finalizeInvoiceSave($id, $data);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', $this->invoiceFlashMessage($data));
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __('invoices'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'subscriptions' => $this->billing->subscriptionOptions((int) ($item['company_id'] ?? 0), (int) ($item['subscription_id'] ?? 0)),
            'lineItems' => \Rateb\App\Helpers\LineItems::loadInvoiceLines($id),
            'bankAccounts' => $this->billing->supplierBankAccountOptions(),
            'chartAccounts' => $this->billing->chartAccountOptionsForCompany((int) ($item['company_id'] ?? 0)),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectInvoiceData($id);
        if (!$this->validateInvoiceData($data, $id)) {
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        $this->finalizeInvoiceSave($id, $data);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', $this->invoiceFlashMessage($data));
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function previewDraft(): void
    {
        if (!rateb_can('billing.manage') || !$this->validateCsrf()) {
            http_response_code(403);
            echo __('access_denied');
            return;
        }
        $data = $this->collectInvoiceData();
        $lines = \Rateb\App\Helpers\LineItems::collectFromRequest();
        $company = (new \Rateb\App\Models\Company())->find((int) ($data['company_id'] ?? 0));
        $this->view('admin/invoices/print', [
            'title' => __('invoice_preview'),
            'item' => $data,
            'company' => $company,
            'lines' => $lines,
            'draft' => true,
        ], 'print');
    }

    public function subscriptionLookup(): void
    {
        if (!rateb_can('billing.manage')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $companyId = (int) $this->input('company_id', 0);
        $sub = $this->billing->activeSubscriptionForCompany($companyId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['subscription' => $sub], JSON_UNESCAPED_UNICODE);
    }

    public function taxProfileLookup(): void
    {
        if (!rateb_can('billing.manage')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $companyId = (int) $this->input('company_id', 0);
        $profile = $companyId > 0
            ? (new \Rateb\App\Services\ZatcaService())->getTaxProfile($companyId)
            : [];
        $company = $companyId > 0 ? (new \Rateb\App\Models\Company())->find($companyId) : null;
        $legalName = trim((string) ($profile['legal_name_ar'] ?? $profile['legal_name_en'] ?? ''));
        if ($legalName === '' && is_array($company)) {
            $legalName = (string) ($company['name'] ?? '');
        }
        $addressParts = array_filter([
            trim((string) ($profile['street'] ?? '')),
            trim((string) ($profile['building_no'] ?? '')),
            trim((string) ($profile['city'] ?? '')),
            trim((string) ($profile['postal_code'] ?? '')),
        ]);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'profile' => [
                'vat_number' => (string) ($profile['vat_number'] ?? ''),
                'cr_number' => (string) ($profile['cr_number'] ?? ''),
                'legal_name' => $legalName,
                'address' => implode('، ', $addressParts),
                'complete' => strlen((string) ($profile['vat_number'] ?? '')) >= 15
                    && trim($legalName) !== ''
                    && implode('، ', $addressParts) !== '',
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    public function chartAccountsLookup(): void
    {
        if (!rateb_can('billing.manage')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $companyId = (int) $this->input('company_id', 0);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'accounts' => $this->billing->chartAccountOptionsForCompany($companyId),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function preview(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'print');
            return;
        }
        $company = (new \Rateb\App\Models\Company())->find((int) ($item['company_id'] ?? 0));
        $lines = \Rateb\App\Helpers\LineItems::loadInvoiceLines($id);
        $this->view('admin/invoices/print', [
            'title' => __('invoice_preview') . ' — ' . ($item['invoice_no'] ?? ''),
            'item' => $item,
            'company' => $company,
            'lines' => $lines,
        ], 'print');
    }

    /** @param array<int, array<string, mixed>> $items */
    /** @return list<array<string, string>> */
    private function buildDueAlerts(array $items): array
    {
        $alerts = [];
        $today = date('Y-m-d');
        foreach ($items as $row) {
            $status = (string) ($row['status'] ?? '');
            $due = (string) ($row['due_date'] ?? '');
            if ($due === '' || in_array($status, ['paid', 'cancelled'], true)) {
                continue;
            }
            if ($due < $today) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => __('invoice_overdue_alert', [
                        'no' => (string) ($row['invoice_no'] ?? ''),
                        'date' => $due,
                    ]),
                ];
            } elseif ($due <= date('Y-m-d', strtotime('+7 days'))) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => __('invoice_due_soon_alert', [
                        'no' => (string) ($row['invoice_no'] ?? ''),
                        'date' => $due,
                    ]),
                ];
            }
        }
        return $alerts;
    }

    /** @param array<string, mixed> $data */
    private function validateInvoiceData(array $data, ?int $excludeId = null): bool
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        if (!$this->billing->companyExists($companyId)) {
            SessionManager::flash('error', __('billing_company_required'));
            return false;
        }
        $subId = isset($data['subscription_id']) && $data['subscription_id'] !== '' ? (int) $data['subscription_id'] : null;
        if ($subId === null || $subId < 1) {
            SessionManager::flash('error', __('billing_subscription_required'));
            return false;
        }
        if (!$this->billing->subscriptionBelongsToCompany($subId, $companyId)) {
            SessionManager::flash('error', __('billing_subscription_invalid'));
            return false;
        }
        if ((float) ($data['amount'] ?? 0) <= 0) {
            SessionManager::flash('error', __('invoice_amount_required'));
            return false;
        }
        if (($data['issued_at'] ?? '') === '' || ($data['due_date'] ?? '') === '') {
            SessionManager::flash('error', __('invoice_dates_required'));
            return false;
        }
        $invoiceNo = trim((string) ($data['invoice_no'] ?? ''));
        if ($invoiceNo !== '' && $this->billing->invoiceNoExists($invoiceNo, $excludeId)) {
            SessionManager::flash('error', __('invoice_no_duplicate'));
            return false;
        }
        return true;
    }

    /** @return array<string, mixed> */
    private function collectInvoiceData(?int $excludeId = null): array
    {
        $data = [];
        $names = [
            'company_id', 'subscription_id', 'invoice_no', 'invoice_type', 'po_number',
            'buyer_legal_name', 'buyer_vat_number', 'buyer_cr_number', 'buyer_address',
            'amount', 'tax_amount', 'total_amount', 'currency', 'discount_amount', 'discount_type',
            'tax_rate', 'payment_terms_days', 'payment_method', 'supplier_account_no', 'status', 'payment_status', 'notes',
            'due_date', 'issued_at',
        ];
        foreach ($names as $name) {
            $data[$name] = trim((string) $this->input($name, ''));
        }
        $bankId = (int) $this->input('supplier_bank_account_id', 0);
        $data['supplier_bank_account_id'] = $bankId > 0 ? $bankId : null;
        if ($bankId > 0 && ($data['supplier_account_no'] ?? '') === '') {
            $bank = (new \Rateb\App\Models\BankAccount())->find($bankId);
            if ($bank) {
                $data['supplier_account_no'] = trim((string) ($bank['account_number'] ?? ''));
            }
        }
        $lines = \Rateb\App\Helpers\LineItems::collectFromRequest();
        $lineAgg = $lines !== [] ? \Rateb\App\Helpers\LineItems::aggregateTotals($lines) : null;
        $data['_lines'] = $lines;
        $data['company_id'] = (int) ($data['company_id'] ?? 0);
        $amount = max(0, (float) ($data['amount'] ?? 0));
        if ($lineAgg !== null) {
            $amount = (float) $lineAgg['subtotal'];
        }
        $taxRate = max(0, (float) ($data['tax_rate'] ?? 15));
        $discountAmount = max(0, (float) ($data['discount_amount'] ?? 0));
        $discountType = in_array((string) ($data['discount_type'] ?? 'value'), ['value', 'percent'], true)
            ? (string) $data['discount_type']
            : 'value';
        $discount = $discountType === 'percent'
            ? min($amount, round($amount * ($discountAmount / 100), 2))
            : min($amount, $discountAmount);
        $subtotal = max(0, round($amount - $discount, 2));
        if ($lineAgg !== null) {
            $lineTax = (float) $lineAgg['tax'];
            $taxAmount = $amount > 0 ? round($lineTax * ($subtotal / $amount), 2) : 0.0;
        } else {
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
        }
        $totalAmount = round($subtotal + $taxAmount, 2);

        $data['amount'] = $amount;
        $data['discount_amount'] = $discountAmount;
        $data['discount_type'] = $discountType;
        $data['tax_rate'] = $taxRate;
        $data['tax_amount'] = $taxAmount;
        $data['total_amount'] = $totalAmount;
        $data['currency'] = strtoupper(trim((string) ($data['currency'] ?? 'SAR'))) ?: 'SAR';
        $data['payment_terms_days'] = max(0, (int) ($data['payment_terms_days'] ?? 30));
        $data['payment_method'] = trim((string) ($data['payment_method'] ?? 'bank_transfer')) ?: 'bank_transfer';
        $data['invoice_type'] = trim((string) ($data['invoice_type'] ?? 'tax')) ?: 'tax';
        $data['po_number'] = trim((string) ($data['po_number'] ?? ''));
        $data['notes'] = trim((string) ($data['notes'] ?? ''));
        $data['payment_status'] = in_array((string) ($data['payment_status'] ?? 'unpaid'), ['unpaid', 'partial', 'paid'], true)
            ? (string) $data['payment_status']
            : 'unpaid';

        if (($data['invoice_no'] ?? '') === '') {
            $data['invoice_no'] = $this->billing->nextInvoiceNo();
        }
        if ($this->billing->invoiceNoExists((string) $data['invoice_no'], $excludeId)) {
            $data['invoice_no'] = $this->billing->nextInvoiceNo();
        }
        if (($data['issued_at'] ?? '') === '') {
            $data['issued_at'] = date('Y-m-d');
        }
        if (($data['due_date'] ?? '') === '') {
            $terms = (int) ($data['payment_terms_days'] ?? 30);
            $data['due_date'] = date('Y-m-d', strtotime('+' . $terms . ' days', strtotime((string) $data['issued_at'])));
        }
        if (($data['subscription_id'] ?? '') === '') {
            $data['subscription_id'] = null;
        } else {
            $data['subscription_id'] = (int) $data['subscription_id'];
        }

        $submitAction = trim((string) $this->input('submit_action', 'draft'));
        if ($submitAction === 'send' && ($data['status'] ?? 'draft') === 'draft') {
            $data['status'] = 'sent';
            $data['sent_at'] = date('Y-m-d H:i:s');
        }
        if (($data['status'] ?? '') === 'paid') {
            $data['payment_status'] = 'paid';
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function finalizeInvoiceSave(int $id, array $data): void
    {
        (new \Rateb\App\Services\DocumentBarcodeService())->ensure('invoice', $id);
        $lines = is_array($data['_lines'] ?? null) ? $data['_lines'] : [];
        if ($lines !== []) {
            \Rateb\App\Helpers\LineItems::syncInvoiceLines($id, $lines);
        }
        $this->saveInvoiceAttachments($id, $data);
        (new \Rateb\App\Services\BillingAutomationService())->recalculatePaymentStatus($id);
        $row = $this->model->find($id);
        if (!$row) {
            return;
        }
        if (($row['status'] ?? '') === 'paid') {
            (new \Rateb\App\Services\AccountingService())->postInvoice($row);
        }
        if (($row['status'] ?? '') === 'sent' && !empty($data['sent_at'])) {
            (new \Rateb\App\Services\BillingAutomationService())->sendInvoiceToCustomer($row);
        }
    }

    /** @param array<string, mixed> $data */
    private function invoiceFlashMessage(array $data): string
    {
        if (($data['status'] ?? '') === 'sent' && !empty($data['sent_at'])) {
            return __('invoice_sent_ok');
        }
        return __('save') . ' OK';
    }

    /** @param array<string, mixed> $data */
    private function saveInvoiceAttachments(int $id, array $data): void
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $upload = \Rateb\App\Helpers\EntityAttachment::handleMultipleFiles(
            'entity_attachment',
            $companyId,
            'invoice',
            $id,
            5,
            __('invoice_attachment')
        );
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
        }
    }
}

final class AuditLogsController extends Controller
{
    public function index(): void
    {
        $model = new \Rateb\App\Models\AuditLog();
        $this->view('admin/audit-logs/index', [
            'title' => __('audit_logs'),
            'items' => $model->all(50, 0),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class SettingsController extends Controller
{
    public function index(): void
    {
        $model = new \Rateb\App\Models\SystemSetting();
        $mailSvc = new \Rateb\App\Services\MailConfigService();
        $mailCfg = $mailSvc->resolve();
        $fromDomain = \Rateb\App\Helpers\Str::emailDomain((string) ($mailCfg['from_email'] ?? 'info@rateb.sa'));
        $mailDomain = $fromDomain !== '' ? $fromDomain : 'rateb.sa';
        $user = \Rateb\App\Core\Auth::user();
        $hrFlagRaw = $model->get('hr_mobile_console_enabled', '0');
        $this->view('admin/settings/index', [
            'title' => __('settings'),
            'items' => $model->all(100, 0),
            'csrf' => Csrf::token(),
            'mailCfg' => $mailCfg,
            'mailPassSet' => $mailCfg['pass'] !== '',
            'mailReady' => $mailSvc->isReady(),
            'mailLocalhost' => $mailSvc->isLocalRelayHost((string) ($mailCfg['host'] ?? '')),
            'mailRelay' => $mailSvc->isSmtpRelayHost((string) ($mailCfg['host'] ?? '')),
            'mailDnsAsync' => true,
            'mailDnsDomain' => $mailDomain,
            'mailDnsUrl' => rateb_url('admin/api/mail-dns-check') . '?domain=' . rawurlencode($mailDomain),
            'testEmailDefault' => trim((string) ($user['email'] ?? 'info@rateb.sa')) ?: 'info@rateb.sa',
            'hrMobileConsoleEnabled' => in_array(strtolower(trim((string) ($hrFlagRaw ?? '0'))), ['1', 'true', 'yes', 'on'], true),
        ], 'main');
    }

    public function mailDnsCheck(): void
    {
        if (!\Rateb\App\Core\Auth::check()) {
            \Rateb\App\Core\Response::json(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }
        $domain = trim((string) ($_GET['domain'] ?? 'rateb.sa'));
        $refresh = isset($_GET['refresh']) && (string) $_GET['refresh'] !== '0';
        $mailDns = (new \Rateb\App\Services\MailDnsCheckService())->checkCached($domain, $refresh);
        ob_start();
        \Rateb\App\Core\View::partial('admin/mail-dns-panel', ['mailDns' => $mailDns]);
        $html = (string) ob_get_clean();
        \Rateb\App\Core\Response::json(['ok' => true, 'html' => $html]);
    }

    public function save(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/settings'));
        }
        $model = new \Rateb\App\Models\SystemSetting();
        $keys = $_POST['setting_key'] ?? [];
        $values = $_POST['setting_value'] ?? [];
        $mailKeys = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'smtp_from_email', 'smtp_from_name'];
        $featureKeys = ['hr_mobile_console_enabled'];
        if (is_array($keys)) {
            foreach ($keys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '' || in_array($key, $mailKeys, true) || in_array($key, $featureKeys, true)) {
                    continue;
                }
                $existing = $model->queryOne('SELECT id FROM rateb_system_settings WHERE setting_key = :k', ['k' => $key]);
                $val = is_array($values) ? (string) ($values[$i] ?? '') : '';
                if ($existing) {
                    $model->update((int) $existing['id'], ['setting_value' => $val]);
                } else {
                    $model->create(['setting_key' => $key, 'setting_value' => $val, 'setting_group' => 'general']);
                }
            }
        }
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/settings'));
    }

    public function saveFeatures(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/settings'));
        }
        $model = new \Rateb\App\Models\SystemSetting();
        $enabled = isset($_POST['hr_mobile_console_enabled'])
            && (string) $_POST['hr_mobile_console_enabled'] === '1';
        $val = $enabled ? '1' : '0';
        $row = $model->queryOne(
            'SELECT id FROM rateb_system_settings WHERE setting_key = :k LIMIT 1',
            ['k' => 'hr_mobile_console_enabled']
        );
        if ($row) {
            $model->update((int) $row['id'], ['setting_value' => $val]);
        } else {
            $model->create([
                'setting_key' => 'hr_mobile_console_enabled',
                'setting_value' => $val,
                'setting_group' => 'features',
            ]);
        }
        if (function_exists('rateb_hr_mobile_dev_config_clear_cache')) {
            rateb_hr_mobile_dev_config_clear_cache();
        }
        SessionManager::flash('success', __('settings_features_saved'));
        Response::redirect(rateb_url('admin/settings'));
    }

    public function saveMail(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/settings'));
        }
        $model = new \Rateb\App\Models\SystemSetting();
        $host = trim((string) $this->input('smtp_host', 'localhost'));
        $port = trim((string) $this->input('smtp_port', '587'));
        $encryption = strtolower(trim((string) $this->input('smtp_encryption', 'tls')));
        if (!ctype_digit($port)) {
            SessionManager::flash('error', __('mail_error_port_invalid'));
            Response::redirect(rateb_url('admin/settings'));
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }
        $pairs = [
            'smtp_host' => $host !== '' ? $host : 'localhost',
            'smtp_port' => $port,
            'smtp_encryption' => $encryption,
            'smtp_user' => trim((string) $this->input('smtp_user', 'info@rateb.sa')),
            'smtp_from_email' => trim((string) $this->input('smtp_from_email', 'info@rateb.sa')),
            'smtp_from_name' => trim((string) $this->input('smtp_from_name', 'Rateb ERP')),
        ];
        $pass = trim((string) $this->input('smtp_pass', ''));
        if ($pass !== '') {
            $pairs['smtp_pass'] = $pass;
        }
        foreach ($pairs as $key => $val) {
            $row = $model->queryOne('SELECT id FROM rateb_system_settings WHERE setting_key = :k', ['k' => $key]);
            if ($row) {
                $model->update((int) $row['id'], ['setting_value' => $val]);
            } else {
                $model->create(['setting_key' => $key, 'setting_value' => $val, 'setting_group' => 'mail']);
            }
        }
        SessionManager::flash('success', __('mail_settings_saved'));
        Response::redirect(rateb_url('admin/settings'));
    }

    public function testMail(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect($this->mailTestRedirectUrl());
        }
        $to = trim((string) $this->input('test_to', ''));
        $outcome = (new \Rateb\App\Services\MailTestService())->sendTest($to);
        SessionManager::flash($outcome['level'], (string) $outcome['message']);
        Response::redirect($this->mailTestRedirectUrl());
    }

    private function mailTestRedirectUrl(): string
    {
        $returnTo = trim((string) $this->input('return_to', ''));
        if (preg_match('#^admin/cms/leads/\d+$#', $returnTo)) {
            return rateb_url($returnTo);
        }
        return rateb_url('admin/settings');
    }

    public function fixArabic(): void
    {
        $result = (new \Rateb\App\Services\ErpArabicRepairService())->repair();
        SessionManager::flash(
            'success',
            'إصلاح العربية: ' . $result['updated'] . ' صف؛ عينة: ' . $result['permissions_sample']
        );
        Response::redirect(rateb_url('admin/permissions'));
    }
}

final class EmailTemplatesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\EmailTemplate();
        $this->viewPrefix = 'admin/email-templates';
        $this->routePrefix = rateb_app_route('email-templates');
        $this->entityName = 'email_templates';
        $this->fields = [
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'subject', 'label' => 'Subject', 'type' => 'text'],
            ['name' => 'body_html', 'label' => 'HTML Body', 'type' => 'textarea'],
        ];
        $this->indexFields = [
            ['name' => 'slug', 'label' => 'slug', 'type' => 'slug'],
            ['name' => 'subject', 'label' => 'subject', 'type' => 'bidi_text'],
            ['name' => 'body_html', 'label' => 'body_preview', 'type' => 'html_preview'],
        ];
    }

    protected function indexViewData(int $limit, int $offset, int $page, string $search = ''): array
    {
        $data = parent::indexViewData($limit, $offset, $page, $search);
        $samples = rateb_email_template_sample_vars();

        foreach ($data['items'] as &$row) {
            $subject = (string) ($row['subject'] ?? '');
            $bodyPlain = rateb_html_preview((string) ($row['body_html'] ?? ''), 220);
            $row['slug_label'] = rateb_email_template_slug_label((string) ($row['slug'] ?? ''));
            $row['subject_display'] = rateb_bidi_cell_text(rateb_email_template_render_preview($subject, $samples));
            $row['body_display'] = rateb_bidi_cell_text(rateb_email_template_render_preview($bodyPlain, $samples));
        }
        unset($row);

        $data['fields'] = [
            ['name' => 'slug_label', 'label' => 'template_name', 'type' => 'bidi_text'],
            ['name' => 'subject_display', 'label' => 'subject', 'type' => 'bidi_text'],
            ['name' => 'body_display', 'label' => 'body_preview', 'type' => 'bidi_text'],
        ];
        $data['listHelp'] = __('email_templates_list_help');

        return $data;
    }
}

final class SmsTemplatesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SmsTemplate();
        $this->viewPrefix = 'admin/sms-templates';
        $this->routePrefix = rateb_app_route('sms-templates');
        $this->entityName = 'sms_templates';
        $this->fields = [
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'body', 'label' => 'Body', 'type' => 'textarea'],
        ];
    }
}

final class SupportTicketsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SupportTicket();
        $this->viewPrefix = 'admin/support-tickets';
        $this->routePrefix = rateb_app_route('support-tickets');
        $this->entityName = 'support_tickets';
        $this->fields = [
            ['name' => 'ticket_no', 'label' => 'Ticket No', 'type' => 'text'],
            ['name' => 'subject', 'label' => 'Subject', 'type' => 'text'],
            ['name' => 'priority', 'label' => 'Priority', 'type' => 'select', 'options' => ['low', 'medium', 'high', 'urgent']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['open', 'in_progress', 'resolved', 'closed']],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea'],
        ];
    }
}

final class ReportsController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService();
        $this->view('admin/reports/index', [
            'title' => __('reports'),
            'metrics' => $service->adminMetrics(),
            'charts' => $service->adminCharts(),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class ProcurementController extends Controller
{
    public function index(): void
    {
        $pr = new \Rateb\App\Models\PurchaseRequest();
        $po = new \Rateb\App\Models\PurchaseOrder();
        $ofs = new \Rateb\App\Services\OversightFilterService();
        $filters = $ofs->parse();
        $lookup = new \Rateb\App\Services\FormLookupService();

        $prSql = 'SELECT pr.*, c.name AS company_name
            FROM rateb_purchase_requests pr
            LEFT JOIN rateb_companies c ON c.id = pr.company_id
            WHERE 1=1';
        $prParams = [];
        $ofs->applyCompany($prSql, $prParams, 'pr.company_id', $filters);
        $ofs->applyStatus($prSql, $prParams, 'pr.status', $filters);
        $ofs->applyDateRange($prSql, $prParams, 'pr.expected_date', $filters);
        $prSql .= ' ORDER BY pr.id DESC LIMIT 50';

        $poSql = 'SELECT po.*, c.name AS company_name, s.name AS supplier_name
            FROM rateb_purchase_orders po
            LEFT JOIN rateb_companies c ON c.id = po.company_id
            LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
            WHERE 1=1';
        $poParams = [];
        $ofs->applyCompany($poSql, $poParams, 'po.company_id', $filters);
        $ofs->applyStatus($poSql, $poParams, 'po.status', $filters);
        $ofs->applyDateRange($poSql, $poParams, 'po.order_date', $filters);
        $poSql .= ' ORDER BY po.id DESC LIMIT 50';

        $prColumns = [
            ['name' => 'company_name', 'label' => 'companies'],
            ['name' => 'request_no', 'label' => 'request_no'],
            ['name' => 'title', 'label' => 'title', 'class' => 'rateb-oversight-clip'],
            ['name' => 'department', 'label' => 'department', 'class' => 'rateb-oversight-clip'],
            ['name' => 'expected_date', 'label' => 'expected_date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'total_estimated', 'label' => 'estimated_total', 'type' => 'money'],
        ];
        $poColumns = [
            ['name' => 'company_name', 'label' => 'companies'],
            ['name' => 'order_no', 'label' => 'order_no'],
            ['name' => 'supplier_name', 'label' => 'supplier', 'class' => 'rateb-oversight-clip'],
            ['name' => 'order_date', 'label' => 'order_date'],
            ['name' => 'expected_date', 'label' => 'expected_date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'total_amount', 'label' => 'total', 'type' => 'money'],
        ];

        $this->view('admin/procurement/index', [
            'title' => __('procurement'),
            'purchase_requests' => $pr->query($prSql, $prParams),
            'purchase_orders' => $po->query($poSql, $poParams),
            'prColumns' => $prColumns,
            'poColumns' => $poColumns,
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'statusOptions' => $lookup->get('pr_statuses'),
            'formAction' => rateb_url('admin/oversight/procurement'),
            'pr_stats' => $this->statusCounts('rateb_purchase_requests', $filters),
            'po_stats' => $this->statusCounts('rateb_purchase_orders', $filters),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    /**
     * @param array{company_id: int, status: string, date_from: string, date_to: string} $filters
     * @return array<string, int>
     */
    private function statusCounts(string $table, array $filters): array
    {
        $allowed = ['rateb_purchase_requests', 'rateb_purchase_orders'];
        if (!in_array($table, $allowed, true)) {
            return [];
        }
        $dateCol = $table === 'rateb_purchase_requests' ? 'expected_date' : 'order_date';
        $sql = 'SELECT status, COUNT(*) AS c FROM ' . $table . ' WHERE 1=1';
        $params = [];
        $ofs = new \Rateb\App\Services\OversightFilterService();
        $ofs->applyCompany($sql, $params, 'company_id', $filters);
        $ofs->applyStatus($sql, $params, 'status', $filters);
        $ofs->applyDateRange($sql, $params, $dateCol, $filters);
        $sql .= ' GROUP BY status';
        $rows = (new \Rateb\App\Models\Company())->query($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[(string) ($row['status'] ?? '')] = (int) ($row['c'] ?? 0);
        }
        return $out;
    }
}

final class RfqOversightController extends Controller
{
    public function index(): void
    {
        $ofs = new \Rateb\App\Services\OversightFilterService();
        $filters = $ofs->parse();
        $lookup = new \Rateb\App\Services\FormLookupService();
        $sql = 'SELECT r.*, c.name AS company_name,
            (SELECT COUNT(*) FROM rateb_supplier_quotations q WHERE q.rfq_id = r.id) AS quote_count
            FROM rateb_rfq r
            LEFT JOIN rateb_companies c ON c.id = r.company_id WHERE 1=1';
        $params = [];
        $ofs->applyCompany($sql, $params, 'r.company_id', $filters);
        $ofs->applyStatus($sql, $params, 'r.status', $filters);
        $ofs->applyDateRange($sql, $params, 'r.deadline', $filters);
        $sql .= ' ORDER BY r.id DESC LIMIT 100';
        $items = (new \Rateb\App\Models\Rfq())->query($sql, $params);
        $this->view('admin/rfq/index', [
            'title' => __('rfq'),
            'items' => $items,
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'statusOptions' => $lookup->get('rfq_statuses'),
            'formAction' => rateb_url('admin/oversight/rfq'),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class InventoryController extends Controller
{
    /** @param list<int> $allowed */
    private function oversightPaging(string $prefix, ?array $allowed = null): array
    {
        $allowed = $allowed ?? rateb_list_per_page_options();
        $default = rateb_list_default_per_page();
        $page = max(1, (int) ($_GET[$prefix . '_page'] ?? 1));
        $rawLimit = (int) ($_GET[$prefix . '_per_page'] ?? $default);
        $limit = in_array($rawLimit, $allowed, true) ? $rawLimit : $default;
        $search = trim((string) ($_GET[$prefix . '_q'] ?? ''));
        return [
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'offset' => ($page - 1) * $limit,
            'pageKey' => $prefix . '_page',
            'perPageKey' => $prefix . '_per_page',
            'searchKey' => $prefix . '_q',
            'perPageOptions' => $allowed,
        ];
    }

    /** @param array{company_id: int, status: string, date_from: string, date_to: string} $filters */
    private function oversightFilterQuery(array $filters): array
    {
        $query = [];
        if ($filters['company_id'] > 0) {
            $query['company_id'] = (string) $filters['company_id'];
        }
        if ($filters['status'] !== '') {
            $query['status'] = $filters['status'];
        }
        if ($filters['date_from'] !== '') {
            $query['date_from'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $query['date_to'] = $filters['date_to'];
        }
        return $query;
    }

    /** @param list<string> $columns */
    private function applySearchClause(string &$sql, array &$params, string $search, array $columns, string $prefix): void
    {
        if ($search === '') {
            return;
        }
        $parts = [];
        foreach ($columns as $i => $col) {
            $key = $prefix . '_s' . $i;
            $parts[] = $col . ' LIKE :' . $key;
            $params[$key] = '%' . $search . '%';
        }
        if ($parts !== []) {
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        }
    }

    /** @param array{company_id: int, status: string, date_from: string, date_to: string} $filters */
    private function inventoryOversightRows(
        \Rateb\App\Services\OversightFilterService $ofs,
        array $filters,
        int $limit,
        int $offset,
        string $search
    ): array {
        $inv = new \Rateb\App\Models\Inventory();
        $sql = 'SELECT * FROM rateb_inventory WHERE 1=1';
        $params = [];
        $ofs->applyCompany($sql, $params, 'company_id', $filters);
        $ofs->applyStatus($sql, $params, 'status', $filters);
        $ofs->applyDateRange($sql, $params, 'expiry_date', $filters);
        $this->applySearchClause($sql, $params, $search, ['item_code', 'item_name', 'sku', 'barcode'], 'inv');
        $countSql = preg_replace('/^SELECT \* FROM/', 'SELECT COUNT(*) AS c FROM', $sql) ?? $sql;
        $total = (int) ($inv->queryOne($countSql, $params)['c'] ?? 0);
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        return ['rows' => $inv->query($sql, $params), 'total' => $total];
    }

    /** @param array{company_id: int, status: string, date_from: string, date_to: string} $filters */
    private function warehouseOversightRows(
        \Rateb\App\Services\OversightFilterService $ofs,
        array $filters,
        int $limit,
        int $offset,
        string $search
    ): array {
        $wh = new \Rateb\App\Models\Warehouse();
        $sql = 'SELECT * FROM rateb_warehouses WHERE 1=1';
        $params = [];
        $ofs->applyCompany($sql, $params, 'company_id', $filters);
        $ofs->applyStatus($sql, $params, 'status', $filters);
        $this->applySearchClause($sql, $params, $search, ['name', 'code', 'location'], 'wh');
        $countSql = preg_replace('/^SELECT \* FROM/', 'SELECT COUNT(*) AS c FROM', $sql) ?? $sql;
        $total = (int) ($wh->queryOne($countSql, $params)['c'] ?? 0);
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        return ['rows' => $wh->query($sql, $params), 'total' => $total];
    }

    public function index(): void
    {
        $ofs = new \Rateb\App\Services\OversightFilterService();
        $filters = $ofs->parse();
        $lookup = new \Rateb\App\Services\FormLookupService();
        $listBaseUrl = rateb_url('admin/oversight/inventory');
        $filterQuery = $this->oversightFilterQuery($filters);

        $invPaging = $this->oversightPaging('inv');
        $whPaging = $this->oversightPaging('wh');

        $invData = $this->inventoryOversightRows(
            $ofs,
            $filters,
            $invPaging['limit'],
            $invPaging['offset'],
            $invPaging['search']
        );
        $whData = $this->warehouseOversightRows(
            $ofs,
            $filters,
            $whPaging['limit'],
            $whPaging['offset'],
            $whPaging['search']
        );

        $invPreserve = array_merge($filterQuery, [
            'wh_page' => (string) $whPaging['page'],
            'wh_per_page' => (string) $whPaging['limit'],
        ]);
        if ($whPaging['search'] !== '') {
            $invPreserve['wh_q'] = $whPaging['search'];
        }

        $whPreserve = array_merge($filterQuery, [
            'inv_page' => (string) $invPaging['page'],
            'inv_per_page' => (string) $invPaging['limit'],
        ]);
        if ($invPaging['search'] !== '') {
            $whPreserve['inv_q'] = $invPaging['search'];
        }

        $itemFields = [
            ['name' => 'item_code', 'label' => 'item_code'],
            ['name' => 'item_name', 'label' => 'item_name'],
            ['name' => 'sku', 'label' => 'sku'],
            ['name' => 'barcode', 'label' => 'document_barcode', 'type' => 'barcode'],
            ['name' => 'quantity', 'label' => 'quantity', 'type' => 'number'],
            ['name' => 'unit_cost', 'label' => 'unit_cost', 'type' => 'money'],
            ['name' => 'expiry_date', 'label' => 'expiry_date', 'type' => 'date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $warehouseFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'location', 'label' => 'location'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];

        $inv = new \Rateb\App\Models\Inventory();
        $this->view('admin/inventory/index', [
            'title' => __('inventory'),
            'items' => $invData['rows'],
            'warehouses' => $whData['rows'],
            'itemFields' => $itemFields,
            'warehouseFields' => $warehouseFields,
            'total_value' => $inv->totalValue($filters['company_id'] > 0 ? $filters['company_id'] : null),
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'statusOptions' => $lookup->get('inventory_statuses'),
            'formAction' => $listBaseUrl,
            'listBaseUrl' => $listBaseUrl,
            'invRoutePrefix' => rateb_app_route('inventory'),
            'whRoutePrefix' => rateb_app_route('warehouses'),
            'invPage' => $invPaging['page'],
            'invLimit' => $invPaging['limit'],
            'invTotal' => $invData['total'],
            'invSearch' => $invPaging['search'],
            'invPageKey' => $invPaging['pageKey'],
            'invPerPageKey' => $invPaging['perPageKey'],
            'invSearchKey' => $invPaging['searchKey'],
            'invPerPageOptions' => $invPaging['perPageOptions'],
            'invPreserveQuery' => $invPreserve,
            'invSearchClearUrl' => rateb_url_query($listBaseUrl, $invPreserve),
            'whPage' => $whPaging['page'],
            'whLimit' => $whPaging['limit'],
            'whTotal' => $whData['total'],
            'whSearch' => $whPaging['search'],
            'whPageKey' => $whPaging['pageKey'],
            'whPerPageKey' => $whPaging['perPageKey'],
            'whSearchKey' => $whPaging['searchKey'],
            'whPerPageOptions' => $whPaging['perPageOptions'],
            'whPreserveQuery' => $whPreserve,
            'whSearchClearUrl' => rateb_url_query($listBaseUrl, $whPreserve),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class SupplierEvaluationsController extends Controller
{
    public function index(): void
    {
        $ofs = new \Rateb\App\Services\OversightFilterService();
        $filters = $ofs->parse();
        $lookup = new \Rateb\App\Services\FormLookupService();
        $pendingOnly = trim((string) ($_GET['pending'] ?? '')) === '1';
        $sql = 'SELECT e.*, s.name AS supplier_name, c.name AS company_name
             FROM rateb_supplier_evaluations e
             LEFT JOIN rateb_suppliers s ON s.id = e.supplier_id
             LEFT JOIN rateb_companies c ON c.id = e.company_id
             WHERE 1=1';
        $params = [];
        $ofs->applyCompany($sql, $params, 'e.company_id', $filters);
        $ofs->applyStatus($sql, $params, 'e.status', $filters);
        $ofs->applyDateRange($sql, $params, 'e.evaluation_date', $filters);
        if ($pendingOnly) {
            $sql .= ' AND e.manager_approval = :_pending';
            $params['_pending'] = 'pending';
        }
        $sql .= ' ORDER BY e.id DESC LIMIT 100';

        $this->view('admin/supplier-evaluations/index', [
            'title' => __('supplier_evaluations_oversight'),
            'items' => (new \Rateb\App\Models\SupplierEvaluation())->query($sql, $params),
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'statusOptions' => $lookup->get('evaluation_statuses'),
            'formAction' => rateb_url('admin/oversight/supplier-evaluations'),
            'pendingOnly' => $pendingOnly,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

// LocaleController lives in Admin/LocaleController.php (WebsiteKernel + ERP autoload).
