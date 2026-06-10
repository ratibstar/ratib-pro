<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

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
    public function showLogin(): void
    {
        $this->view('company/auth/login', [
            'title' => __('login'),
            'csrf' => Csrf::token(),
        ], 'auth');
    }

    public function login(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid request');
            Response::redirect(rateb_url('company/login'));
        }

        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');

        if (!RateLimiter::attempt('company_login_' . md5($email), 5, 300)) {
            SessionManager::flash('error', 'Too many attempts');
            Response::redirect(rateb_url('company/login'));
        }

        $user = Auth::attempt($email, $password, 'company');
        (new LoginActivityService())->record($user ? (int) $user['id'] : null, $email, $user !== null);

        if (!$user) {
            SessionManager::flash('error', 'Invalid credentials');
            Response::redirect(rateb_url('company/login'));
        }

        (new User())->updateLastLogin((int) $user['id']);
        (new AuditService())->log('login', 'user', (int) $user['id']);
        Response::redirect(rateb_url('company'));
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect(rateb_url('company/login'));
    }
}

final class DashboardController extends Controller
{
    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId);
        $service = new DashboardService();
        $this->view('company/dashboard', [
            'title' => __('dashboard'),
            'metrics' => $service->companyMetrics($companyId),
            'csrf' => Csrf::token(),
        ], 'company');
    }
}

final class PurchaseRequestsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\PurchaseRequest();
        $this->viewPrefix = 'company/purchase-requests';
        $this->routePrefix = 'company/purchase-requests';
        $this->entityName = 'purchase_requests';
        $this->fields = [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'department', 'label' => 'Department', 'type' => 'text'],
            ['name' => 'priority', 'label' => 'Priority', 'type' => 'select', 'options' => ['low', 'medium', 'high', 'urgent']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'submitted', 'approved', 'rejected', 'cancelled']],
            ['name' => 'total_estimated', 'label' => 'Estimated Total', 'type' => 'number'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (empty($data['request_no'])) {
            $data['request_no'] = $this->model->generateRequestNo();
        }
        return $data;
    }
}

final class PurchaseOrdersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\PurchaseOrder();
        $this->viewPrefix = 'company/purchase-orders';
        $this->routePrefix = 'company/purchase-orders';
        $this->entityName = 'purchase_orders';
        $this->fields = [
            ['name' => 'supplier_id', 'label' => 'Supplier ID', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'sent', 'confirmed', 'partial', 'received', 'cancelled']],
            ['name' => 'order_date', 'label' => 'Order Date', 'type' => 'date'],
            ['name' => 'expected_date', 'label' => 'Expected Date', 'type' => 'date'],
            ['name' => 'total_amount', 'label' => 'Total', 'type' => 'number'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (empty($data['order_no'])) {
            $data['order_no'] = $this->model->generateOrderNo();
        }
        return $data;
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'company');
            return;
        }
        $items = (new \Rateb\App\Models\PurchaseItem())->all(100, 0, ['purchase_order_id' => $id]);
        $this->view('company/purchase-orders/show', [
            'title' => __('purchase_orders'),
            'order' => $item,
            'items' => $items,
            'csrf' => Csrf::token(),
        ], 'company');
    }
}

final class RfqController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Rfq();
        $this->viewPrefix = 'company/rfq';
        $this->routePrefix = 'company/rfq';
        $this->entityName = 'rfq';
        $this->fields = [
            ['name' => 'rfq_no', 'label' => 'RFQ No', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'published', 'closed', 'awarded', 'cancelled']],
            ['name' => 'deadline', 'label' => 'Deadline', 'type' => 'date'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class QuotationsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SupplierQuotation();
        $this->viewPrefix = 'company/quotations';
        $this->routePrefix = 'company/quotations';
        $this->entityName = 'quotations';
        $this->fields = [
            ['name' => 'rfq_id', 'label' => 'RFQ ID', 'type' => 'number'],
            ['name' => 'supplier_id', 'label' => 'Supplier ID', 'type' => 'number'],
            ['name' => 'quotation_no', 'label' => 'Quotation No', 'type' => 'text'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['submitted', 'under_review', 'accepted', 'rejected']],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class SuppliersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Supplier();
        $this->viewPrefix = 'company/suppliers';
        $this->routePrefix = 'company/suppliers';
        $this->entityName = 'suppliers';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'code', 'label' => 'Code', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'blacklisted']],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class InventoryController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Inventory();
        $this->viewPrefix = 'company/inventory';
        $this->routePrefix = 'company/inventory';
        $this->entityName = 'inventory';
        $this->fields = [
            ['name' => 'warehouse_id', 'label' => 'Warehouse ID', 'type' => 'number'],
            ['name' => 'item_name', 'label' => 'Item', 'type' => 'text'],
            ['name' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number'],
            ['name' => 'unit_cost', 'label' => 'Unit Cost', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'expired']],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class WarehousesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Warehouse();
        $this->viewPrefix = 'company/warehouses';
        $this->routePrefix = 'company/warehouses';
        $this->entityName = 'warehouses';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'code', 'label' => 'Code', 'type' => 'text'],
            ['name' => 'location', 'label' => 'Location', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive']],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class AssetsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Asset();
        $this->viewPrefix = 'company/assets';
        $this->routePrefix = 'company/assets';
        $this->entityName = 'assets';
        $this->fields = [
            ['name' => 'asset_tag', 'label' => 'Tag', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'text'],
            ['name' => 'current_value', 'label' => 'Value', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'maintenance', 'retired', 'disposed']],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class MedicalDevicesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\MedicalDevice();
        $this->viewPrefix = 'company/medical-devices';
        $this->routePrefix = 'company/medical-devices';
        $this->entityName = 'medical_devices';
        $this->fields = [
            ['name' => 'device_name', 'label' => 'Device', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'text'],
            ['name' => 'model_no', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serial_no', 'label' => 'Serial', 'type' => 'text'],
            ['name' => 'calibration_due', 'label' => 'Calibration Due', 'type' => 'date'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['operational', 'maintenance', 'out_of_service']],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class ContractsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Contract();
        $this->viewPrefix = 'company/contracts';
        $this->routePrefix = 'company/contracts';
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

    protected function layout(): string
    {
        return 'company';
    }
}

final class TendersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Tender();
        $this->viewPrefix = 'company/tenders';
        $this->routePrefix = 'company/tenders';
        $this->entityName = 'tenders';
        $this->fields = [
            ['name' => 'tender_no', 'label' => 'Tender No', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'publish_date', 'label' => 'Publish', 'type' => 'date'],
            ['name' => 'closing_date', 'label' => 'Closing', 'type' => 'date'],
            ['name' => 'estimated_value', 'label' => 'Value', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'open', 'closed', 'awarded', 'cancelled']],
        ];
    }

    protected function layout(): string
    {
        return 'company';
    }
}

final class ReportsController extends Controller
{
    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId);
        $service = new DashboardService();
        $this->view('company/reports/index', [
            'title' => __('reports'),
            'metrics' => $service->companyMetrics($companyId),
            'csrf' => Csrf::token(),
        ], 'company');
    }
}

final class NotificationsController extends Controller
{
    public function index(): void
    {
        $userId = (int) SessionManager::get('rateb_user_id');
        $model = new \Rateb\App\Models\Notification();
        $items = $model->query(
            'SELECT * FROM rateb_notifications WHERE user_id = :uid OR company_id = :cid ORDER BY id DESC LIMIT 50',
            ['uid' => $userId, 'cid' => (int) SessionManager::get('rateb_company_id')]
        );
        $this->view('company/notifications/index', [
            'title' => __('notifications'),
            'items' => $items,
            'csrf' => Csrf::token(),
        ], 'company');
    }
}

final class ProfileController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $this->view('company/profile/index', [
            'title' => __('profile'),
            'user' => $user,
            'csrf' => Csrf::token(),
        ], 'company');
    }

    public function update(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('company/profile'));
        }
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('company/login'));
        }
        $data = [
            'name' => trim((string) $this->input('name', '')),
            'phone' => trim((string) $this->input('phone', '')),
            'locale' => trim((string) $this->input('locale', 'en')),
        ];
        $password = (string) $this->input('password', '');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        (new User())->update((int) $user['id'], $data);
        $_SESSION['rateb_locale'] = $data['locale'];
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('company/profile'));
    }
}
