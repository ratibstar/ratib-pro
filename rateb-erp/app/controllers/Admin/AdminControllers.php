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
        $this->view($this->viewPrefix . '/form', $this->formViewData(null), $this->layout());
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
        $this->view($this->viewPrefix . '/form', $this->formViewData($item), $this->layout());
    }

    /** @return array<string, mixed> */
    private function formViewData(?array $item): array
    {
        $selectedModules = [];
        if ($item && !empty($item['modules'])) {
            $decoded = json_decode((string) $item['modules'], true);
            $selectedModules = is_array($decoded) ? $decoded : [];
        }
        if ($selectedModules === []) {
            $selectedModules = \Rateb\App\Services\PlanLimitService::defaultModules();
        }

        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('plans'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'moduleCatalog' => \Rateb\App\Services\PlanLimitService::moduleCatalog(),
            'selectedModules' => $selectedModules,
        ];
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
            ['name' => 'module', 'label' => 'Module', 'type' => 'text'],
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
            ['name' => 'amount', 'label' => 'amount', 'type' => 'number'],
            ['name' => 'currency', 'label' => 'currency', 'type' => 'text'],
            ['name' => 'method', 'label' => 'payment_method', 'type' => 'text'],
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
        return $data;
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
        $this->view($this->viewPrefix . '/index', [
            'title' => __('invoices'),
            'items' => $this->model->withRelations($limit, $offset),
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
            'title' => __('create') . ' ' . __('invoices'),
            'item' => [
                'invoice_no' => $this->billing->nextInvoiceNo(),
                'status' => 'draft',
                'issued_at' => date('Y-m-d'),
                'tax_amount' => '0',
                'currency' => 'SAR',
            ],
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'subscriptions' => $this->billing->subscriptionOptions(),
            'csrf' => Csrf::token(),
            'multipart' => true,
            'attachment' => [
                'entityType' => 'invoice',
                'entityId' => 0,
                'companyId' => 0,
                'documentPath' => '',
                'inputName' => 'entity_attachment',
                'label' => __('invoice_attachment'),
            ],
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
        (new \Rateb\App\Services\DocumentBarcodeService())->ensure('invoice', $id);
        $this->saveInvoiceAttachment($id, $data);
        $row = $this->model->find($id);
        if ($row && ($row['status'] ?? '') === 'paid') {
            (new \Rateb\App\Services\AccountingService())->postInvoice($row);
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
            'title' => __('edit') . ' ' . __('invoices'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'companies' => $this->billing->companyOptions(),
            'subscriptions' => $this->billing->subscriptionOptions((int) ($item['company_id'] ?? 0)),
            'csrf' => Csrf::token(),
            'multipart' => true,
            'attachment' => [
                'entityType' => 'invoice',
                'entityId' => $id,
                'companyId' => (int) ($item['company_id'] ?? 0),
                'documentPath' => (string) ($item['document_path'] ?? ''),
                'inputName' => 'entity_attachment',
                'label' => __('invoice_attachment'),
            ],
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectInvoiceData();
        if (!$this->validateInvoiceData($data)) {
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        $this->saveInvoiceAttachment($id, $data);
        $row = $this->model->find($id);
        if ($row && ($row['status'] ?? '') === 'paid') {
            (new \Rateb\App\Services\AccountingService())->postInvoice($row);
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @param array<string, mixed> $data */
    private function validateInvoiceData(array $data): bool
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
    private function collectInvoiceData(): array
    {
        $data = $this->collectData();
        $data['company_id'] = (int) ($data['company_id'] ?? 0);
        $data['amount'] = (float) ($data['amount'] ?? 0);
        $data['tax_amount'] = (float) ($data['tax_amount'] ?? 0);
        $data['total_amount'] = (float) ($data['total_amount'] ?? 0);
        if ($data['total_amount'] <= 0 && $data['amount'] > 0) {
            $data['total_amount'] = $data['amount'] + $data['tax_amount'];
        }
        if ($data['amount'] <= 0 && $data['total_amount'] > 0) {
            $data['amount'] = max(0, $data['total_amount'] - $data['tax_amount']);
        }
        if (($data['invoice_no'] ?? '') === '') {
            $data['invoice_no'] = $this->billing->nextInvoiceNo();
        }
        if (($data['issued_at'] ?? '') === '') {
            $data['issued_at'] = date('Y-m-d');
        }
        if (($data['due_date'] ?? '') === '') {
            $data['due_date'] = null;
        }
        if (($data['subscription_id'] ?? '') === '') {
            $data['subscription_id'] = null;
        } else {
            $data['subscription_id'] = (int) $data['subscription_id'];
        }
        return $data;
    }

    /** @param array<string, mixed> $data */
    private function saveInvoiceAttachment(int $id, array $data): void
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $upload = \Rateb\App\Helpers\EntityAttachment::handleOptionalFile(
            'entity_attachment',
            $companyId,
            'invoice',
            $id,
            __('invoice_attachment')
        );
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
        } elseif (!empty($upload['path'])) {
            $this->model->update($id, ['document_path' => $upload['path']]);
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
        $this->view('admin/settings/index', [
            'title' => __('settings'),
            'items' => $model->all(100, 0),
            'csrf' => Csrf::token(),
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
        if (is_array($keys)) {
            foreach ($keys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '') {
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
        $companyFilter = (int) ($_GET['company_id'] ?? 0);
        $this->view('admin/procurement/index', [
            'title' => __('procurement'),
            'purchase_requests' => $pr->all(50, 0),
            'purchase_orders' => $po->all(50, 0),
            'companies' => (new \Rateb\App\Models\Company())->all(200, 0),
            'pr_stats' => $this->statusCounts('rateb_purchase_requests', $companyFilter),
            'po_stats' => $this->statusCounts('rateb_purchase_orders', $companyFilter),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    /** @return array<string, int> */
    private function statusCounts(string $table, int $companyId): array
    {
        $allowed = ['rateb_purchase_requests', 'rateb_purchase_orders'];
        if (!in_array($table, $allowed, true)) {
            return [];
        }
        $sql = 'SELECT status, COUNT(*) AS c FROM ' . $table . ' WHERE 1=1';
        $params = [];
        if ($companyId > 0) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
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
        $companyFilter = (int) ($_GET['company_id'] ?? 0);
        $sql = 'SELECT r.*, c.name AS company_name,
            (SELECT COUNT(*) FROM rateb_supplier_quotations q WHERE q.rfq_id = r.id) AS quote_count
            FROM rateb_rfq r
            LEFT JOIN rateb_companies c ON c.id = r.company_id WHERE 1=1';
        $params = [];
        if ($companyFilter > 0) {
            $sql .= ' AND r.company_id = :cid';
            $params['cid'] = $companyFilter;
        }
        $sql .= ' ORDER BY r.id DESC LIMIT 100';
        $items = (new \Rateb\App\Models\Rfq())->query($sql, $params);
        $this->view('admin/rfq/index', [
            'title' => __('rfq'),
            'items' => $items,
            'companies' => (new \Rateb\App\Models\Company())->all(200, 0),
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
        $itemFields = [
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
            'items' => $inv->all(50, 0),
            'warehouses' => $wh->all(50, 0),
            'itemFields' => $itemFields,
            'warehouseFields' => $warehouseFields,
            'total_value' => $inv->totalValue(),
            'companies' => (new \Rateb\App\Models\Company())->all(200, 0),
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
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'number'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'blacklisted']],
        ];
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
        $this->fields = [
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'number'],
            ['name' => 'asset_tag', 'label' => 'Tag', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'maintenance', 'retired', 'disposed']],
        ];
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
            ['name' => 'company_id', 'label' => 'company_id', 'type' => 'number'],
            ['name' => 'contract_no', 'label' => 'Contract No', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'supplier_id', 'label' => 'suppliers', 'type' => 'number'],
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
        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('contracts'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'suppliers' => $suppliers,
            'multipart' => true,
            'attachment' => $this->attachmentFieldData($item),
        ];
    }

    /** @param array<string, mixed>|null $item */
    private function attachmentFieldData(?array $item): ?array
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
        $model = new \Rateb\App\Models\SupplierEvaluation();
        $items = $model->query(
            'SELECT e.*, s.name AS supplier_name, c.name AS company_name
             FROM rateb_supplier_evaluations e
             LEFT JOIN rateb_suppliers s ON s.id = e.supplier_id
             LEFT JOIN rateb_companies c ON c.id = e.company_id
             ORDER BY e.id DESC LIMIT 100'
        );

        $this->view('admin/supplier-evaluations/index', [
            'title' => __('supplier_evaluations'),
            'items' => $items,
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
        }
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref === '' || strpos($ref, 'rateb-erp-app') === false) {
            $ref = rateb_url('admin');
        }
        Response::redirect($ref);
    }
}
