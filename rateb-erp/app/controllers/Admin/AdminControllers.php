<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\RateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\User;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DashboardService;
use Rateb\App\Services\LoginActivityService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('admin/auth/login', [
            'title' => __('login'),
            'csrf' => Csrf::token(),
        ], 'auth');
    }

    public function login(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/login'));
        }

        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');

        if (!RateLimiter::attempt('admin_login_' . md5($email), 5, 300)) {
            SessionManager::flash('error', __('too_many_attempts'));
            Response::redirect(rateb_url('admin/login'));
        }

        $user = Auth::attempt($email, $password, 'admin');
        (new LoginActivityService())->record($user ? (int) $user['id'] : null, $email, $user !== null);

        if (!$user) {
            SessionManager::flash('error', __('invalid_credentials'));
            Response::redirect(rateb_url('admin/login'));
        }

        if (!empty($user['locale']) && in_array($user['locale'], RATEB_SUPPORTED_LOCALES, true)) {
            $_SESSION['rateb_locale'] = $user['locale'];
        }

        (new User())->updateLastLogin((int) $user['id']);
        (new AuditService())->log('login', 'user', (int) $user['id']);
        Response::redirect(rateb_url('admin'));
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect(rateb_url('admin/login'));
    }
}

final class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService();
        $this->view('admin/dashboard', [
            'title' => __('dashboard'),
            'metrics' => $service->adminMetrics(),
            'charts' => $service->adminCharts(),
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
                    $data['modules'] = $plan['modules'] ?? '[]';
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
}

final class SubscriptionsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Subscription();
        $this->viewPrefix = 'admin/subscriptions';
        $this->routePrefix = 'admin/subscriptions';
        $this->entityName = 'subscriptions';
        $this->fields = [
            ['name' => 'company_id', 'label' => 'Company ID', 'type' => 'number'],
            ['name' => 'plan_id', 'label' => 'Plan ID', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['trial', 'active', 'cancelled', 'expired']],
            ['name' => 'billing_cycle', 'label' => 'Billing', 'type' => 'select', 'options' => ['monthly', 'yearly']],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['name' => 'starts_at', 'label' => 'Starts', 'type' => 'date'],
            ['name' => 'ends_at', 'label' => 'Ends', 'type' => 'date'],
        ];
    }

    public function index(): void
    {
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
            'csrf' => Csrf::token(),
        ], 'main');
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
}

final class UsersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new User();
        $this->viewPrefix = 'admin/users';
        $this->routePrefix = 'admin/users';
        $this->entityName = 'users';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'company_id', 'label' => 'Company ID', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'suspended']],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $password = (string) $this->input('password', '');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        return $data;
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
        ], $this->layout());
    }
}

final class PaymentsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Payment();
        $this->viewPrefix = 'admin/payments';
        $this->routePrefix = 'admin/payments';
        $this->entityName = 'payments';
        $this->fields = [
            ['name' => 'company_id', 'label' => 'Company ID', 'type' => 'number'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['pending', 'completed', 'failed', 'refunded']],
        ];
    }
}

final class InvoicesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Invoice();
        $this->viewPrefix = 'admin/invoices';
        $this->routePrefix = 'admin/invoices';
        $this->entityName = 'invoices';
        $this->fields = [
            ['name' => 'company_id', 'label' => 'Company ID', 'type' => 'number'],
            ['name' => 'invoice_no', 'label' => 'Invoice No', 'type' => 'text'],
            ['name' => 'total_amount', 'label' => 'Total', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'sent', 'paid', 'overdue', 'cancelled']],
        ];
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
        $this->view('admin/procurement/index', [
            'title' => __('procurement'),
            'purchase_requests' => $pr->all(20, 0),
            'purchase_orders' => $po->all(20, 0),
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
        $this->view('admin/inventory/index', [
            'title' => __('inventory'),
            'items' => $inv->all(50, 0),
            'warehouses' => $wh->all(50, 0),
            'total_value' => $inv->totalValue(),
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
        $this->fields = [
            ['name' => 'contract_no', 'label' => 'Contract No', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'start_date', 'label' => 'Start', 'type' => 'date'],
            ['name' => 'end_date', 'label' => 'End', 'type' => 'date'],
            ['name' => 'value', 'label' => 'Value', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'active', 'expired', 'terminated']],
        ];
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
