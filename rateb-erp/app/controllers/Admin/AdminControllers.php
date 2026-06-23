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
        Response::redirect(rateb_url('login'));
    }
}

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (SessionManager::get('rateb_is_super_admin')) {
            $service = new DashboardService();
            $this->view('admin/dashboard', [
                'title' => __('dashboard'),
                'metrics' => $service->adminMetrics(),
                'charts' => $service->adminCharts(),
                'csrf' => Csrf::token(),
            ], 'main');
            return;
        }

        $companyId = (int) SessionManager::get('rateb_company_id');
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
            ['name' => 'storage_limit_mb', 'label' => 'storage_limit_mb', 'type' => 'number'],
        ];
    }

    public function create(): void
    {
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

        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('companies'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'plans' => (new \Rateb\App\Models\Plan())->all(100, 0),
            'moduleCatalog' => \Rateb\App\Services\PlanLimitService::moduleCatalog(),
            'selectedModules' => $selectedModules,
            'limits' => $item ? (new \Rateb\App\Services\PlanLimitService())->getLimits((int) $item['id']) : null,
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $modules = $this->input('modules', []);
        if (is_array($modules)) {
            $data['modules'] = json_encode(array_values(array_filter(array_map('strval', $modules))), JSON_UNESCAPED_UNICODE);
        }
        if (!empty($data['plan_id']) && (int) $data['plan_id'] > 0) {
            $plan = (new \Rateb\App\Models\Plan())->find((int) $data['plan_id']);
            if ($plan) {
                if (empty($data['user_limit'])) {
                    $data['user_limit'] = (int) ($plan['max_users'] ?? 10);
                }
                if (empty($data['storage_limit_mb'])) {
                    $data['storage_limit_mb'] = (int) ($plan['max_storage_mb'] ?? 1024);
                }
                if (empty($data['modules']) || $data['modules'] === '[]') {
                    $data['modules'] = $plan['modules'] ?? json_encode(
                        \Rateb\App\Services\PlanLimitService::defaultModules(),
                        JSON_UNESCAPED_UNICODE
                    );
                }
            }
        }
        return $data;
    }

    public function suspend(array $params): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $id = (int) ($params['id'] ?? 0);
        $this->model->suspend($id);
        (new AuditService())->log('suspend', 'company', $id);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/companies'));
    }

    public function activate(array $params): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $id = (int) ($params['id'] ?? 0);
        $this->model->activate($id);
        (new AuditService())->log('activate', 'company', $id);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/companies'));
    }

    public function bulkSuspend(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/companies'));
        }
        $count = 0;
        foreach ($this->parseBulkIds() as $id) {
            $this->model->suspend($id);
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
        $count = 0;
        foreach ($this->parseBulkIds() as $id) {
            $this->model->activate($id);
            (new AuditService())->log('bulk_activate', 'company', $id);
            $count++;
        }
        SessionManager::flash('success', __('bulk_activated', ['count' => $count]));
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
        $limit = 20;
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
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'price_monthly', 'label' => 'Monthly', 'type' => 'number'],
            ['name' => 'price_yearly', 'label' => 'Yearly', 'type' => 'number'],
            ['name' => 'max_users', 'label' => 'Max Users', 'type' => 'number'],
            ['name' => 'max_storage_mb', 'label' => 'Storage MB', 'type' => 'number'],
        ];
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __('plans'),
            'item' => null,
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
        if ($selectedModules === []) {
            $selectedModules = \Rateb\App\Services\PlanLimitService::defaultModules();
        }

        return array_merge(parent::formViewData($extra), [
            'moduleCatalog' => \Rateb\App\Services\PlanLimitService::moduleCatalog(),
            'selectedModules' => $selectedModules,
        ]);
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $modules = $this->input('modules', []);
        if (is_array($modules)) {
            $data['modules'] = json_encode(array_values(array_filter(array_map('strval', $modules))), JSON_UNESCAPED_UNICODE);
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
        $this->routePrefix = 'admin/users';
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
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $authz = new \Rateb\App\Services\AuthorizationService();
        $items = $this->model->all($limit, $offset);
        foreach ($items as &$row) {
            $row['roles_list'] = $authz->getUserRoleNames((int) $row['id']);
            if (!empty($row['is_super_admin'])) {
                $row['roles_list'] = __('super_admin') . ($row['roles_list'] !== '' ? ' · ' . $row['roles_list'] : '');
            }
        }
        unset($row);

        $displayFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'email', 'label' => 'email'],
            ['name' => 'phone', 'label' => 'phone'],
            ['name' => 'company_id', 'label' => 'company_id'],
            ['name' => 'roles_list', 'label' => 'roles'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'locale', 'label' => 'language'],
        ];

        $this->view($this->viewPrefix . '/index', [
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $displayFields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
        ], $this->layout());
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', $this->userFormData(null), $this->layout());
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
        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('users'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'roles' => $authz->allRoles(),
            'companies' => (new \Rateb\App\Models\Company())->all(200, 0),
            'selectedRoles' => $userId > 0 ? $authz->getUserRoleIds($userId) : [],
            'isSuperAdmin' => !empty($item['is_super_admin']),
            'loginBarcode' => $barcode,
            'badgeScanQrUrl' => $badgeScanQrUrl,
            'badgeLoginUrl' => $badgeLoginUrl,
            'badgeRegenerateAction' => $userId > 0 ? rateb_url($this->routePrefix . '/' . $userId . '/regenerate-barcode') : '',
        ];
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $roleIds = array_map('intval', (array) $this->input('role_ids', []));
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0 && !(new \Rateb\App\Services\PlanLimitService())->canAddUser($companyId)) {
            SessionManager::flash('error', __('user_limit_reached'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        (new \Rateb\App\Services\AuthorizationService())->syncUserRoles($id, $roleIds);
        if ((string) ($data['status'] ?? '') === 'active') {
            (new \Rateb\App\Services\BarcodeLoginService())->ensureUserBarcode($id);
        }
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        $roleIds = array_map('intval', (array) $this->input('role_ids', []));
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0) {
            $existing = $this->model->find($id);
            $wasOtherCompany = $existing && (int) ($existing['company_id'] ?? 0) !== $companyId;
            if ($wasOtherCompany || !$existing) {
                try {
                    (new \Rateb\App\Services\PlanLimitService())->assertCanAddUser($companyId);
                } catch (\RuntimeException $e) {
                    SessionManager::flash('error', $e->getMessage());
                    $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
                }
            }
        }
        $this->model->update($id, $data);
        (new \Rateb\App\Services\AuthorizationService())->syncUserRoles($id, $roleIds);
        if ((string) ($data['status'] ?? '') === 'active') {
            (new \Rateb\App\Services\BarcodeLoginService())->ensureUserBarcode($id);
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $password = (string) $this->input('password', '');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $data['is_super_admin'] = $this->input('is_super_admin') ? 1 : 0;
        if ($data['company_id'] === '' || $data['company_id'] === '0' || $data['company_id'] === null) {
            $data['company_id'] = null;
        } else {
            $companyId = (int) $data['company_id'];
            $company = (new \Rateb\App\Models\Company())->find($companyId);
            $data['company_id'] = $company ? $companyId : null;
        }
        return $data;
    }

    public function regenerateBarcode(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $user = $this->model->find($id);
        if (!$user) {
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
}

final class RolesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Role();
        $this->viewPrefix = 'admin/roles';
        $this->routePrefix = 'admin/roles';
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
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $authz = new \Rateb\App\Services\AuthorizationService();
        $authz->dedupeDuplicateRoles();
        $items = $this->model->all($limit, $offset);
        $seenSlugs = [];
        $items = array_values(array_filter($items, static function (array $row) use (&$seenSlugs): bool {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || isset($seenSlugs[$slug])) {
                return false;
            }
            $seenSlugs[$slug] = true;
            return true;
        }));
        foreach ($items as &$row) {
            $row['permission_count'] = $authz->getRolePermissionCount((int) $row['id']);
        }
        unset($row);

        $displayFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'slug', 'label' => 'slug'],
            ['name' => 'description', 'label' => 'description'],
            ['name' => 'permission_count', 'label' => 'permissions_count'],
        ];

        $this->view($this->viewPrefix . '/index', [
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $displayFields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
        ], $this->layout());
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
        $permIds = array_map('intval', (array) $this->input('permission_ids', []));
        $id = $this->model->create($data);
        (new \Rateb\App\Services\AuthorizationService())->syncRolePermissions($id, $permIds);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        $permIds = array_map('intval', (array) $this->input('permission_ids', []));
        $this->model->update($id, $data);
        (new \Rateb\App\Services\AuthorizationService())->syncRolePermissions($id, $permIds);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }
}

final class PermissionsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Permission();
        $this->viewPrefix = 'admin/permissions';
        $this->routePrefix = 'admin/permissions';
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
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $items = $this->model->all($limit, $offset);

        foreach ($items as &$row) {
            $row['name'] = rateb_permission_label($row);
            if (rateb_locale() === 'ar' && !empty($row['description_ar'])) {
                $row['description'] = $row['description_ar'];
            }
        }
        unset($row);

        $displayFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'slug', 'label' => 'slug'],
            ['name' => 'module', 'label' => 'module'],
        ];

        $this->view($this->viewPrefix . '/index', [
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $displayFields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
        ], $this->layout());
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
        $limit = 20;
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
        $limit = 20;
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
                'complete' => strlen((string) ($profile['vat_number'] ?? '')) >= 15,
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
        $mailDns = (new \Rateb\App\Services\MailDnsCheckService())->check($fromDomain !== '' ? $fromDomain : 'rateb.sa');
        $user = \Rateb\App\Core\Auth::user();
        $this->view('admin/settings/index', [
            'title' => __('settings'),
            'items' => $model->all(100, 0),
            'csrf' => Csrf::token(),
            'mailCfg' => $mailCfg,
            'mailPassSet' => $mailCfg['pass'] !== '',
            'mailReady' => $mailSvc->isReady(),
            'mailLocalhost' => $mailSvc->isLocalRelayHost((string) ($mailCfg['host'] ?? '')),
            'mailRelay' => $mailSvc->isSmtpRelayHost((string) ($mailCfg['host'] ?? '')),
            'mailDns' => $mailDns,
            'testEmailDefault' => trim((string) ($user['email'] ?? 'info@rateb.sa')) ?: 'info@rateb.sa',
        ], 'main');
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
        if (is_array($keys)) {
            foreach ($keys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '' || in_array($key, $mailKeys, true)) {
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
            Response::redirect(rateb_url('admin/settings'));
        }
        $to = trim((string) $this->input('test_to', ''));
        if ($to === '' || !\Rateb\App\Helpers\Str::isValidEmail($to)) {
            SessionManager::flash('error', __('mail_test_invalid'));
            Response::redirect(rateb_url('admin/settings'));
        }
        if (!(new \Rateb\App\Services\MailConfigService())->isReady()) {
            SessionManager::flash('error', __('mail_password_env_hint'));
            Response::redirect(rateb_url('admin/settings'));
        }
        $mail = new \Rateb\App\Services\MailService();
        $cfg = (new \Rateb\App\Services\MailConfigService())->resolve();
        $from = trim((string) ($cfg['from_email'] ?? ''));
        $fromDomain = \Rateb\App\Helpers\Str::emailDomain($from);
        $toDomain = \Rateb\App\Helpers\Str::emailDomain($to);
        $isExternalTest = $toDomain !== '' && $fromDomain !== '' && strcasecmp($toDomain, $fromDomain) !== 0;
        $bcc = (!$isExternalTest && $from !== '' && \Rateb\App\Helpers\Str::isValidEmail($from) && strcasecmp($from, $to) !== 0) ? $from : null;
        $result = $mail->sendDetailed(
            $to,
            __('mail_test_subject'),
            '<div dir="auto" style="font-family:Tajawal,sans-serif"><p>' . htmlspecialchars(__('mail_test_body'), ENT_QUOTES, 'UTF-8') . '</p></div>',
            null,
            null,
            $bcc
        );
        $sent = (bool) ($result['success'] ?? false);
        $failMsg = (string) ($result['error'] ?? __('mail_test_failed'));
        if ($sent && $isExternalTest) {
            $dns = (new \Rateb\App\Services\MailDnsCheckService())->check($fromDomain);
            if (!$dns['ready_for_external']) {
                SessionManager::flash('warning', __('mail_test_external_dns_warn', ['email' => $to]));
                Response::redirect(rateb_url('admin/settings'));
            }
        }
        SessionManager::flash($sent ? 'success' : 'error', $sent
            ? __('mail_test_ok', ['email' => $to, 'host' => (string) ($result['smtp_host'] ?? $mail->lastSmtpHost() ?? 'mail.rateb.sa')])
            : $failMsg);
        Response::redirect(rateb_url('admin/settings'));
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
        $this->routePrefix = 'admin/email-templates';
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
        $this->routePrefix = 'admin/sms-templates';
        $this->entityName = 'sms_templates';
        $this->fields = [
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'body', 'label' => 'Body', 'type' => 'textarea'],
        ];
    }
}

final class NotificationsController extends Controller
{
    public function index(): void
    {
        $model = new \Rateb\App\Models\Notification();
        $this->view('admin/notifications/index', [
            'title' => __('notifications'),
            'items' => $model->all(50, 0),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class SupportTicketsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SupportTicket();
        $this->viewPrefix = 'admin/support-tickets';
        $this->routePrefix = 'admin/support-tickets';
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

        $prSql = 'SELECT * FROM rateb_purchase_requests WHERE 1=1';
        $prParams = [];
        $ofs->applyCompany($prSql, $prParams, 'company_id', $filters);
        $ofs->applyStatus($prSql, $prParams, 'status', $filters);
        $ofs->applyDateRange($prSql, $prParams, 'expected_date', $filters);
        $prSql .= ' ORDER BY id DESC LIMIT 50';

        $poSql = 'SELECT * FROM rateb_purchase_orders WHERE 1=1';
        $poParams = [];
        $ofs->applyCompany($poSql, $poParams, 'company_id', $filters);
        $ofs->applyStatus($poSql, $poParams, 'status', $filters);
        $ofs->applyDateRange($poSql, $poParams, 'order_date', $filters);
        $poSql .= ' ORDER BY id DESC LIMIT 50';

        $prFields = [
            ['name' => 'company_id', 'label' => 'companies', 'type' => 'fk', 'lookup' => 'companies'],
            ['name' => 'request_no', 'label' => 'request_no'],
            ['name' => 'title', 'label' => 'title', 'type' => 'clip'],
            ['name' => 'department', 'label' => 'department', 'type' => 'clip'],
            ['name' => 'expected_date', 'label' => 'expected_date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'total_estimated', 'label' => 'estimated_total', 'type' => 'money'],
        ];
        $poFields = [
            ['name' => 'company_id', 'label' => 'companies', 'type' => 'fk', 'lookup' => 'companies'],
            ['name' => 'order_no', 'label' => 'order_no'],
            ['name' => 'supplier_id', 'label' => 'supplier', 'type' => 'fk', 'lookup' => 'suppliers'],
            ['name' => 'order_date', 'label' => 'order_date'],
            ['name' => 'expected_date', 'label' => 'expected_date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'total_amount', 'label' => 'total', 'type' => 'money'],
        ];

        $this->view('admin/procurement/index', [
            'title' => __('procurement'),
            'purchase_requests' => $pr->query($prSql, $prParams),
            'purchase_orders' => $po->query($poSql, $poParams),
            'prFields' => $prFields,
            'poFields' => $poFields,
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
            'formAction' => rateb_url('admin/rfq'),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class InventoryController extends Controller
{
    public function index(): void
    {
        $inv = new \Rateb\App\Models\Inventory();
        $wh = new \Rateb\App\Models\Warehouse();
        $ofs = new \Rateb\App\Services\OversightFilterService();
        $filters = $ofs->parse();
        $lookup = new \Rateb\App\Services\FormLookupService();

        $invSql = 'SELECT * FROM rateb_inventory WHERE 1=1';
        $invParams = [];
        $ofs->applyCompany($invSql, $invParams, 'company_id', $filters);
        $ofs->applyStatus($invSql, $invParams, 'status', $filters);
        $ofs->applyDateRange($invSql, $invParams, 'expiry_date', $filters);
        $invSql .= ' ORDER BY id DESC LIMIT 50';

        $whSql = 'SELECT * FROM rateb_warehouses WHERE 1=1';
        $whParams = [];
        $ofs->applyCompany($whSql, $whParams, 'company_id', $filters);
        $ofs->applyStatus($whSql, $whParams, 'status', $filters);
        $whSql .= ' ORDER BY id DESC LIMIT 50';

        $itemFields = [
            ['name' => 'item_code', 'label' => 'item_code'],
            ['name' => 'item_name', 'label' => 'item_name'],
            ['name' => 'sku', 'label' => 'sku'],
            ['name' => 'barcode', 'label' => 'document_barcode', 'type' => 'barcode'],
            ['name' => 'quantity', 'label' => 'quantity'],
            ['name' => 'unit_cost', 'label' => 'unit_cost'],
            ['name' => 'expiry_date', 'label' => 'expiry_date'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $warehouseFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'location', 'label' => 'location'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->view('admin/inventory/index', [
            'title' => __('inventory'),
            'items' => $inv->query($invSql, $invParams),
            'warehouses' => $wh->query($whSql, $whParams),
            'itemFields' => $itemFields,
            'warehouseFields' => $warehouseFields,
            'total_value' => $inv->totalValue($filters['company_id'] > 0 ? $filters['company_id'] : null),
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'statusOptions' => $lookup->get('inventory_statuses'),
            'formAction' => rateb_url('admin/inventory'),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class SuppliersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Supplier();
        $this->viewPrefix = 'admin/suppliers';
        $this->routePrefix = 'admin/suppliers';
        $this->entityName = 'suppliers';
        $this->indexFields = [
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'email', 'label' => 'email'],
            ['name' => 'phone', 'label' => 'phone'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'fk', 'lookup' => 'companies', 'required' => true],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'blacklisted']],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (!empty($data['company_id'])) {
            TenantContext::setCompanyId((int) $data['company_id']);
        }
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_SUPPLIER, 'code');
        return $data;
    }
}

final class AssetsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Asset();
        $this->viewPrefix = 'admin/assets';
        $this->routePrefix = 'admin/assets';
        $this->entityName = 'assets';
        $this->indexFields = [
            ['name' => 'asset_tag', 'label' => 'asset_tag'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'category', 'label' => 'category', 'type' => 'fk', 'lookup' => 'asset_categories'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'fk', 'lookup' => 'companies', 'required' => true],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'fk', 'lookup' => 'asset_categories'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'maintenance', 'retired', 'disposed']],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (!empty($data['company_id'])) {
            TenantContext::setCompanyId((int) $data['company_id']);
        }
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_ASSET, 'asset_tag');
        return $data;
    }
}

final class ContractsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Contract();
        $this->viewPrefix = 'admin/contracts';
        $this->routePrefix = 'admin/contracts';
        $this->entityName = 'contracts';
        $this->indexFields = [
            ['name' => 'contract_no', 'label' => 'contract_no'],
            ['name' => 'title', 'label' => 'title'],
            ['name' => 'barcode', 'label' => 'document_barcode', 'type' => 'barcode'],
            ['name' => 'start_date', 'label' => 'start_date'],
            ['name' => 'end_date', 'label' => 'end_date'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'fk', 'lookup' => 'companies', 'required' => true],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'supplier_id', 'label' => 'suppliers', 'type' => 'fk', 'lookup' => 'suppliers'],
            ['name' => 'contract_type', 'label' => 'contract_type', 'type' => 'select', 'lookup' => 'contract_types'],
            ['name' => 'approval_status', 'label' => 'approval_status', 'type' => 'select', 'lookup' => 'approval_statuses'],
            ['name' => 'start_date', 'label' => 'Start', 'type' => 'date'],
            ['name' => 'end_date', 'label' => 'End', 'type' => 'date'],
            ['name' => 'renewal_date', 'label' => 'renewal_date', 'type' => 'date'],
            ['name' => 'value', 'label' => 'Value', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'active', 'expired', 'terminated']],
        ];
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', $this->contractFormData(null), $this->layout());
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
        $this->view($this->viewPrefix . '/form', $this->contractFormData($item), $this->layout());
    }

    /** @return array<string, mixed> */
    private function contractFormData(?array $item): array
    {
        $companyId = $item ? (int) ($item['company_id'] ?? 0) : 0;
        $suppliers = [];
        if ($companyId > 0) {
            $suppliers = (new \Rateb\App\Models\Supplier())->query(
                'SELECT * FROM rateb_suppliers WHERE company_id = :cid ORDER BY name LIMIT 200',
                ['cid' => $companyId]
            );
        }
        return array_merge($this->formViewData([
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('contracts'),
            'item' => $item,
            'suppliers' => $suppliers,
            'multipart' => true,
            'attachment' => $this->attachmentFieldData($item),
        ]), []);
    }

    /** @param array<string, mixed>|null $item */
    protected function attachmentFieldData(?array $item): array
    {
        if (!$item || (int) ($item['id'] ?? 0) < 1) {
            return [
                'entityType' => 'contract',
                'entityId' => 0,
                'companyId' => (int) ($item['company_id'] ?? 0),
                'documentPath' => '',
                'inputName' => 'contract_file',
                'label' => __('contract_attachment'),
            ];
        }
        return [
            'entityType' => 'contract',
            'entityId' => (int) $item['id'],
            'companyId' => (int) ($item['company_id'] ?? 0),
            'documentPath' => (string) ($item['document_path'] ?? ''),
            'inputName' => 'contract_file',
            'label' => __('contract_attachment'),
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (!empty($data['company_id'])) {
            TenantContext::setCompanyId((int) $data['company_id']);
        }
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_CONTRACT, 'contract_no');
        return $data;
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $companyId = (int) ($data['company_id'] ?? 0);
        $id = $this->model->create($data);
        if ($companyId > 0) {
            $upload = \Rateb\App\Helpers\ContractUpload::handleOptionalFile($companyId, $id);
            if (!($upload['success'] ?? false)) {
                SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
            } elseif (!empty($upload['path'])) {
                $this->model->update($id, ['document_path' => $upload['path']]);
            }
        }
        (new \Rateb\App\Services\DocumentBarcodeService())->ensure('contract', $id);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        $companyId = (int) ($data['company_id'] ?? 0);
        $this->model->update($id, $data);
        if ($companyId > 0) {
            $upload = \Rateb\App\Helpers\ContractUpload::handleOptionalFile($companyId, $id);
            if (!($upload['success'] ?? false)) {
                SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
            } elseif (!empty($upload['path'])) {
                $this->model->update($id, ['document_path' => $upload['path']]);
            }
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }
}

final class SupplierEvaluationsController extends Controller
{
    public function index(): void
    {
        $ofs = new \Rateb\App\Services\OversightFilterService();
        $filters = $ofs->parse();
        $lookup = new \Rateb\App\Services\FormLookupService();
        $sql = 'SELECT e.*, s.name AS supplier_name, c.name AS company_name
             FROM rateb_supplier_evaluations e
             LEFT JOIN rateb_suppliers s ON s.id = e.supplier_id
             LEFT JOIN rateb_companies c ON c.id = e.company_id
             WHERE 1=1';
        $params = [];
        $ofs->applyCompany($sql, $params, 'e.company_id', $filters);
        $ofs->applyStatus($sql, $params, 'e.status', $filters);
        $ofs->applyDateRange($sql, $params, 'e.evaluation_date', $filters);
        $sql .= ' ORDER BY e.id DESC LIMIT 100';

        $this->view('admin/supplier-evaluations/index', [
            'title' => __('supplier_evaluations'),
            'items' => (new \Rateb\App\Models\SupplierEvaluation())->query($sql, $params),
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'statusOptions' => $lookup->get('evaluation_statuses'),
            'formAction' => rateb_url('admin/supplier-evaluations'),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class LocaleController extends Controller
{
    public function switch(array $params): void
    {
        $locale = $params['locale'] ?? 'en';
        if (in_array($locale, RATEB_SUPPORTED_LOCALES, true)) {
            $_SESSION['rateb_locale'] = $locale;
            if (function_exists('rateb_set_locale_cookie')) {
                rateb_set_locale_cookie($locale);
            }
        }
        Response::redirect($this->localeRedirectTarget());
    }

    private function localeRedirectTarget(): string
    {
        $next = trim((string) ($_GET['next'] ?? ''));
        if ($next !== '' && $this->isSafeInternalPath($next)) {
            return rateb_url($next);
        }

        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '' && $this->isSameSiteUrl($ref) && strpos($ref, '/locale/') === false) {
            return $ref;
        }

        if (Auth::check()) {
            return rateb_url(Auth::homePath());
        }

        return rateb_url('site');
    }

    private function isSafeInternalPath(string $path): bool
    {
        if ($path === '' || strpos($path, '://') !== false || strpos($path, '//') === 0) {
            return false;
        }
        $path = ltrim($path, '/');
        return $path !== '' && strpos($path, 'locale/') !== 0;
    }

    private function isSameSiteUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $refHost = (string) ($parsed['host'] ?? '');
        if ($refHost === '' || strcasecmp($refHost, $host) !== 0) {
            return false;
        }
        $path = (string) ($parsed['path'] ?? '');
        $base = defined('RATEB_BASE_URL') ? rtrim((string) RATEB_BASE_URL, '/') : '/rateb-erp/public';
        return strpos($path, $base) !== false;
    }
}
