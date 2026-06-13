<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\User;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DashboardService;

final class PurchaseRequestsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\PurchaseRequest();
        $this->viewPrefix = 'company/purchase-requests';
        $this->routePrefix = rateb_app_route('purchase-requests');
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
        return 'main';
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (empty($data['request_no'])) {
            $data['request_no'] = $this->model->generateRequestNo();
        }
        return $data;
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
            'lineItems' => [],
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }
        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
            'lineItems' => \Rateb\App\Helpers\LineItems::loadPurchaseRequestItems($id),
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $lines = \Rateb\App\Helpers\LineItems::collectFromRequest();
        if ($lines !== []) {
            $data['total_estimated'] = array_sum(array_column($lines, 'total_price'));
        }
        $id = $this->model->create($data);
        if ($lines !== []) {
            \Rateb\App\Helpers\LineItems::syncPurchaseRequestItems($id, $lines);
        }
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseRequestStatus(
            $id,
            (string) ($data['status'] ?? 'draft')
        );
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
        $old = $this->model->find($id);
        $data = $this->collectData();
        $lines = \Rateb\App\Helpers\LineItems::collectFromRequest();
        if ($lines !== []) {
            $data['total_estimated'] = array_sum(array_column($lines, 'total_price'));
        }
        $this->model->update($id, $data);
        \Rateb\App\Helpers\LineItems::syncPurchaseRequestItems($id, $lines);
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseRequestStatus(
            $id,
            (string) ($data['status'] ?? ''),
            $old ? (string) ($old['status'] ?? '') : null
        );
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function export(): void
    {
        $items = $this->model->all(500, 0);
        $columns = [
            ['name' => 'request_no', 'label' => __('request_no')],
            ['name' => 'title', 'label' => __('title')],
            ['name' => 'status', 'label' => __('status')],
            ['name' => 'total_estimated', 'label' => __('total')],
        ];
        \Rateb\App\Controllers\Shared\ExportController::send('purchase_requests', $columns, $items, __('purchase_requests'), 'purchase-requests');
    }
}

final class PurchaseOrdersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\PurchaseOrder();
        $this->viewPrefix = 'company/purchase-orders';
        $this->routePrefix = rateb_app_route('purchase-orders');
        $this->entityName = 'purchase_orders';
        $this->tenantForeignKeys = ['supplier_id'];
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
        return 'main';
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (empty($data['order_no'])) {
            $data['order_no'] = $this->model->generateOrderNo();
        }
        if (empty($data['order_date'])) {
            $data['order_date'] = date('Y-m-d');
        }
        return $data;
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
            'lineItems' => [],
            'suppliers' => (new \Rateb\App\Models\Supplier())->all(200, 0),
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }
        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
            'lineItems' => \Rateb\App\Helpers\LineItems::loadPurchaseOrderItems($id),
            'suppliers' => (new \Rateb\App\Models\Supplier())->all(200, 0),
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $lines = \Rateb\App\Helpers\LineItems::collectFromRequest();
        $id = $this->model->create($data);
        $total = \Rateb\App\Helpers\LineItems::syncPurchaseOrderItems($id, $lines);
        if ($total > 0) {
            $this->model->update($id, ['total_amount' => $total, 'subtotal' => $total]);
        }
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseOrderStatus(
            $id,
            (string) ($data['status'] ?? 'draft')
        );
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
        $old = $this->model->find($id);
        $data = $this->collectData();
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $lines = \Rateb\App\Helpers\LineItems::collectFromRequest();
        $total = \Rateb\App\Helpers\LineItems::syncPurchaseOrderItems($id, $lines);
        if ($total > 0) {
            $data['total_amount'] = $total;
            $data['subtotal'] = $total;
        }
        $this->model->update($id, $data);
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseOrderStatus(
            $id,
            (string) ($data['status'] ?? ''),
            $old ? (string) ($old['status'] ?? '') : null
        );
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function export(): void
    {
        $items = $this->model->all(500, 0);
        $columns = [
            ['name' => 'order_no', 'label' => __('order_no')],
            ['name' => 'status', 'label' => __('status')],
            ['name' => 'order_date', 'label' => __('order_date')],
            ['name' => 'total_amount', 'label' => __('total')],
        ];
        \Rateb\App\Controllers\Shared\ExportController::send('purchase_orders', $columns, $items, __('purchase_orders'), 'purchase-orders');
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $items = \Rateb\App\Helpers\LineItems::loadPurchaseOrderItems($id);
        $this->view('company/purchase-orders/show', [
            'title' => __('purchase_orders'),
            'order' => $item,
            'items' => $items,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class RfqController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Rfq();
        $this->viewPrefix = 'company/rfq';
        $this->routePrefix = rateb_app_route('rfq');
        $this->entityName = 'rfq';
        $this->fields = [
            ['name' => 'rfq_no', 'label' => 'RFQ No', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'published', 'closed', 'awarded', 'cancelled']],
            ['name' => 'deadline', 'label' => 'Deadline', 'type' => 'date'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ];
    }

    public function compare(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $rfq = $this->model->find($id);
        if (!$rfq) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $quotations = (new \Rateb\App\Models\SupplierQuotation())->query(
            'SELECT q.*, s.name AS supplier_name
             FROM rateb_supplier_quotations q
             LEFT JOIN rateb_suppliers s ON s.id = q.supplier_id
             WHERE q.rfq_id = :rid
             ORDER BY q.amount ASC',
            ['rid' => $id]
        );
        $this->view('company/rfq/compare', [
            'title' => __('quotation_compare'),
            'rfq' => $rfq,
            'quotations' => $quotations,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $this->model->all($limit, $offset),
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
        ]), $this->layout());
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class QuotationsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SupplierQuotation();
        $this->viewPrefix = 'company/quotations';
        $this->routePrefix = rateb_app_route('quotations');
        $this->entityName = 'quotations';
        $this->tenantForeignKeys = ['rfq_id', 'supplier_id'];
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
        return 'main';
    }
}

final class SuppliersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Supplier();
        $this->viewPrefix = 'company/suppliers';
        $this->routePrefix = rateb_app_route('suppliers');
        $this->entityName = 'suppliers';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'code', 'label' => 'Code', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'classification_id', 'label' => 'supplier_classifications', 'type' => 'number'],
            ['name' => 'performance_kpi', 'label' => 'performance_kpi', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'blacklisted']],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class InventoryController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Inventory();
        $this->viewPrefix = 'company/inventory';
        $this->routePrefix = rateb_app_route('inventory');
        $this->entityName = 'inventory';
        $this->tenantForeignKeys = ['warehouse_id'];
        $this->indexFields = [
            ['name' => 'item_name', 'label' => 'item_name'],
            ['name' => 'sku', 'label' => 'sku'],
            ['name' => 'barcode', 'label' => 'document_barcode', 'type' => 'barcode'],
            ['name' => 'quantity', 'label' => 'quantity'],
            ['name' => 'expiry_date', 'label' => 'expiry_date'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'warehouse_id', 'label' => 'Warehouse ID', 'type' => 'number'],
            ['name' => 'item_name', 'label' => 'Item', 'type' => 'text'],
            ['name' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number'],
            ['name' => 'unit_cost', 'label' => 'Unit Cost', 'type' => 'number'],
            ['name' => 'reorder_level', 'label' => 'reorder_level', 'type' => 'number'],
            ['name' => 'expiry_date', 'label' => 'expiry_date', 'type' => 'date'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'expired']],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }

    public function create(): void
    {
        $this->guardManage();
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'multipart' => true,
            'attachment' => $this->attachmentFieldData(null),
        ], $this->layout());
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }

        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'multipart' => true,
            'attachment' => $this->attachmentFieldData($item),
        ], $this->layout());
    }

    public function store(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $data = $this->collectData();
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        (new \Rateb\App\Services\DocumentBarcodeService())->ensure('inventory', $id);
        $this->saveInventoryAttachment($id);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        $this->saveInventoryAttachment($id);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @param array<string, mixed>|null $item */
    private function attachmentFieldData(?array $item): array
    {
        $companyId = (int) TenantContext::companyId();
        if (!$item || (int) ($item['id'] ?? 0) < 1) {
            return [
                'entityType' => 'inventory',
                'entityId' => 0,
                'companyId' => $companyId,
                'documentPath' => '',
                'inputName' => 'entity_attachment',
                'label' => __('inventory_attachment'),
            ];
        }
        return [
            'entityType' => 'inventory',
            'entityId' => (int) $item['id'],
            'companyId' => $companyId,
            'documentPath' => (string) ($item['document_path'] ?? ''),
            'inputName' => 'entity_attachment',
            'label' => __('inventory_attachment'),
        ];
    }

    private function saveInventoryAttachment(int $id): void
    {
        $companyId = (int) TenantContext::companyId();
        $upload = \Rateb\App\Helpers\EntityAttachment::handleOptionalFile(
            'entity_attachment',
            $companyId,
            'inventory',
            $id,
            __('inventory_attachment')
        );
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
        } elseif (!empty($upload['path'])) {
            $this->model->update($id, ['document_path' => $upload['path']]);
        }
    }
}

final class WarehousesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Warehouse();
        $this->viewPrefix = 'company/warehouses';
        $this->routePrefix = rateb_app_route('warehouses');
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
        return 'main';
    }
}

final class AssetsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Asset();
        $this->viewPrefix = 'company/assets';
        $this->routePrefix = rateb_app_route('assets');
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
        return 'main';
    }
}

final class MedicalDevicesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\MedicalDevice();
        $this->viewPrefix = 'company/medical-devices';
        $this->routePrefix = rateb_app_route('medical-devices');
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
        return 'main';
    }
}

final class ContractsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Contract();
        $this->viewPrefix = 'company/contracts';
        $this->routePrefix = rateb_app_route('contracts');
        $this->entityName = 'contracts';
        $this->tenantForeignKeys = ['supplier_id'];
        $this->indexFields = [
            ['name' => 'contract_no', 'label' => 'contract_no'],
            ['name' => 'title', 'label' => 'title'],
            ['name' => 'barcode', 'label' => 'document_barcode', 'type' => 'barcode'],
            ['name' => 'start_date', 'label' => 'start_date'],
            ['name' => 'end_date', 'label' => 'end_date'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
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
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->contractFormData($item), $this->layout());
    }

    /** @return array<string, mixed> */
    private function contractFormData(?array $item): array
    {
        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('contracts'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'suppliers' => (new \Rateb\App\Models\Supplier())->all(200, 0),
            'multipart' => true,
            'attachment' => $this->attachmentFieldData($item),
        ];
    }

    /** @param array<string, mixed>|null $item */
    private function attachmentFieldData(?array $item): array
    {
        $companyId = (int) TenantContext::companyId();
        if (!$item || (int) ($item['id'] ?? 0) < 1) {
            return [
                'entityType' => 'contract',
                'entityId' => 0,
                'companyId' => $companyId,
                'documentPath' => '',
                'inputName' => 'contract_file',
                'label' => __('contract_attachment'),
            ];
        }
        return [
            'entityType' => 'contract',
            'entityId' => (int) $item['id'],
            'companyId' => $companyId,
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
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        $companyId = (int) TenantContext::companyId();
        $upload = \Rateb\App\Helpers\ContractUpload::handleOptionalFile($companyId, $id);
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
        } elseif (!empty($upload['path'])) {
            $this->model->update($id, ['document_path' => $upload['path']]);
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
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        $companyId = (int) TenantContext::companyId();
        $upload = \Rateb\App\Helpers\ContractUpload::handleOptionalFile($companyId, $id);
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
        } elseif (!empty($upload['path'])) {
            $this->model->update($id, ['document_path' => $upload['path']]);
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class TendersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Tender();
        $this->viewPrefix = 'company/tenders';
        $this->routePrefix = rateb_app_route('tenders');
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
        return 'main';
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
            'exportRoute' => rateb_app_url('reports/export'),
            'exportEnabled' => rateb_can_export_entity('reports'),
        ], 'main');
    }

    public function export(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId);
        $m = (new DashboardService())->companyMetrics($companyId);
        \Rateb\App\Controllers\Shared\ExportController::send('company_reports', [
            ['name' => 'metric', 'label' => __('metric')],
            ['name' => 'value', 'label' => __('value')],
        ], [
            ['metric' => __('purchase_requests'), 'value' => $m['purchase_requests']],
            ['metric' => __('purchase_orders'), 'value' => $m['purchase_orders']],
            ['metric' => __('inventory_value'), 'value' => $m['inventory_value']],
            ['metric' => __('suppliers'), 'value' => $m['suppliers']],
        ], __('reports'), 'reports');
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
        ], 'main');
    }

    public function markRead(array $params): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('notifications'));
        }
        $id = (int) ($params['id'] ?? 0);
        (new \Rateb\App\Services\NotificationService())->markRead($id, (int) SessionManager::get('rateb_user_id'));
        Response::redirect(rateb_app_url('notifications'));
    }
}

final class SupplierEvaluationsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SupplierEvaluation();
        $this->viewPrefix = 'company/supplier-evaluations';
        $this->routePrefix = rateb_app_route('supplier-evaluations');
        $this->entityName = 'supplier_evaluations';
        $this->tenantForeignKeys = ['supplier_id'];
        $this->fields = [
            ['name' => 'supplier_id', 'label' => 'supplier_id', 'type' => 'number'],
            ['name' => 'evaluation_date', 'label' => 'evaluation_date', 'type' => 'date'],
            ['name' => 'quality_score', 'label' => 'quality_score', 'type' => 'number'],
            ['name' => 'delivery_score', 'label' => 'delivery_score', 'type' => 'number'],
            ['name' => 'price_score', 'label' => 'price_score', 'type' => 'number'],
            ['name' => 'service_score', 'label' => 'service_score', 'type' => 'number'],
            ['name' => 'comments', 'label' => 'comments', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft', 'published', 'archived']],
        ];
    }

    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $items = $this->model->query(
            'SELECT e.*, s.name AS supplier_name FROM rateb_supplier_evaluations e
             LEFT JOIN rateb_suppliers s ON s.id = e.supplier_id
             WHERE e.company_id = :cid ORDER BY e.id DESC LIMIT 100',
            ['cid' => $companyId]
        );

        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $items,
            'total' => count($items),
            'page' => 1,
            'limit' => 100,
            'routePrefix' => $this->routePrefix,
            'fields' => [
                ['name' => 'supplier_name', 'label' => 'suppliers'],
                ['name' => 'evaluation_date', 'label' => 'evaluation_date'],
                ['name' => 'overall_score', 'label' => 'overall_score'],
                ['name' => 'quality_score', 'label' => 'quality_score'],
                ['name' => 'status', 'label' => 'status'],
            ],
            'csrf' => Csrf::token(),
        ]), $this->layout());
    }

    public function create(): void
    {
        $this->guardManage();
        $this->view($this->viewPrefix . '/form', $this->evalFormData(null), $this->layout());
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->evalFormData($item), $this->layout());
    }

    /** @return array<string, mixed> */
    private function evalFormData(?array $item): array
    {
        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('supplier_evaluations'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'suppliers' => (new \Rateb\App\Models\Supplier())->all(200, 0),
        ];
    }

    public function store(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        $this->model->updateSupplierRating((int) ($data['supplier_id'] ?? 0));
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        $this->model->updateSupplierRating((int) ($data['supplier_id'] ?? 0));
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $evalModel = new \Rateb\App\Models\SupplierEvaluation();
        $data['overall_score'] = $evalModel->recalculateOverall([
            (int) ($data['quality_score'] ?? 0),
            (int) ($data['delivery_score'] ?? 0),
            (int) ($data['price_score'] ?? 0),
            (int) ($data['service_score'] ?? 0),
        ]);
        $data['evaluated_by'] = SessionManager::get('rateb_user_id');
        return $data;
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class ProfileController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $barcodeSvc = new \Rateb\App\Services\BarcodeLoginService();
        $barcode = $userId > 0 ? $barcodeSvc->ensureUserBarcode($userId) : null;
        $badgeLoginUrl = $barcode ? $barcodeSvc->badgeLoginUrl($barcode) : '';
        $this->view('company/profile/index', [
            'title' => __('profile'),
            'user' => $user,
            'csrf' => Csrf::token(),
            'loginBarcode' => $barcode,
            'badgeScanQrUrl' => $barcode ? $barcodeSvc->badgeScanQrUrl($barcode) : '',
            'badgeLoginUrl' => $badgeLoginUrl,
            'badgeRegenerateAction' => rateb_app_url('profile/regenerate-barcode'),
        ], 'main');
    }

    public function regenerateBarcode(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('profile'));
        }
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('login'));
        }
        $userId = (int) $user['id'];
        $svc = new \Rateb\App\Services\BarcodeLoginService();
        $newCode = $svc->generateBarcodeValue($userId, bin2hex(random_bytes(4)));
        (new User())->update($userId, ['login_barcode' => $newCode]);
        (new AuditService())->log('regenerate', 'login_barcode', $userId);
        SessionManager::flash('success', __('barcode_regenerated'));
        Response::redirect(rateb_app_url('profile'));
    }

    public function update(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('profile'));
        }
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('login'));
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
        Response::redirect(rateb_app_url('profile'));
    }
}
