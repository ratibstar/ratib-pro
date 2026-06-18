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
        $this->indexFields = [
            ['name' => 'request_no', 'label' => 'request_no'],
            ['name' => 'title', 'label' => 'title'],
            ['name' => 'department', 'label' => 'department'],
            ['name' => 'priority', 'label' => 'priority'],
            ['name' => 'expected_date', 'label' => 'expected_date'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'currency', 'label' => 'currency'],
            ['name' => 'total_estimated', 'label' => 'estimated_total'],
        ];
        $this->fields = [
            ['name' => 'title', 'label' => 'title', 'type' => 'text'],
            ['name' => 'department', 'label' => 'department', 'type' => 'hybrid', 'lookup' => 'departments'],
            ['name' => 'priority', 'label' => 'priority', 'type' => 'select', 'lookup' => 'priority_levels'],
            ['name' => 'expected_date', 'label' => 'expected_date', 'type' => 'date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'pr_statuses'],
            ['name' => 'currency', 'label' => 'currency', 'type' => 'select', 'lookup' => 'currencies'],
            ['name' => 'total_estimated', 'label' => 'estimated_total', 'type' => 'number', 'readonly' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea'],
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
            $data['request_no'] = $this->model->generateDocumentCode(
                \Rateb\App\Services\DocumentCodeService::PREFIX_PURCHASE_REQUEST,
                'request_no'
            );
        }
        (new \Rateb\App\Services\ProcurementService())->stampRequestedBy($data);
        if (($data['currency'] ?? '') === '') {
            $data['currency'] = 'SAR';
        }
        return $data;
    }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        $proc = new \Rateb\App\Services\ProcurementService();
        return array_merge([
            'departments' => $proc->departmentOptions(),
            'inventoryItems' => (new \Rateb\App\Models\Inventory())->all(300, 0),
        ], $extra);
    }

    public function create(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
            'lineItems' => [],
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ]), $this->layout());
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
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
            'lineItems' => \Rateb\App\Helpers\LineItems::loadPurchaseRequestItems($id),
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ]), $this->layout());
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }
        $this->view('company/purchase-requests/show', [
            'title' => __('purchase_requests'),
            'request' => $item,
            'items' => \Rateb\App\Helpers\LineItems::loadPurchaseRequestItems($id),
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function convertToPo(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $poId = (new \Rateb\App\Services\ProcurementService())->convertRequestToOrder($id);
            SessionManager::flash('success', __('po_created_from_pr'));
            $this->redirect(rateb_url(rateb_app_route('purchase-orders') . '/' . $poId));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id));
        }
    }

    public function submit(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $old = $this->model->find($id);
        if (!$old) {
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->model->update($id, ['status' => 'submitted']);
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseRequestStatus(
            $id,
            'submitted',
            (string) ($old['status'] ?? '')
        );
        SessionManager::flash('success', __('submitted_for_approval'));
        $this->redirect(rateb_url($this->routePrefix . '/' . $id));
    }

    public function store(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            $data = $this->collectData();
            $lines = \Rateb\App\Helpers\LineItems::collectFromRequest();
            $this->applyLineTotals($data, $lines);
            $this->ensureTenantCompanyForWrite($data);
            $id = $this->model->create($data);
            if ($lines !== []) {
                \Rateb\App\Helpers\LineItems::syncPurchaseRequestItems($id, $lines);
            }
            $this->saveQuoteAttachment($id);
            (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseRequestStatus(
                $id,
                (string) ($data['status'] ?? 'draft')
            );
            (new AuditService())->log('create', $this->entityName, $id, $data);
            SessionManager::flash('success', __('save') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
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
        $this->applyLineTotals($data, $lines);
        if ($old) {
            $this->applyNotesHistory($data, $old);
        }
        $this->model->update($id, $data);
        \Rateb\App\Helpers\LineItems::syncPurchaseRequestItems($id, $lines);
        $this->saveQuoteAttachment($id);
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

    /** @param array<string, mixed> $data */
    protected function applyLineTotals(array &$data, array $lines): void
    {
        if ($lines === []) {
            return;
        }
        $agg = \Rateb\App\Helpers\LineItems::aggregateTotals($lines);
        $data['total_estimated'] = $agg['total'];
    }

    /** @param array<string, mixed> $data */
    protected function applyNotesHistory(array &$data, ?array $old): void
    {
        $history = \Rateb\App\Helpers\ProcurementNotes::decodeHistory(
            isset($old['notes_history']) ? (string) $old['notes_history'] : null
        );
        $data['notes_history'] = \Rateb\App\Helpers\ProcurementNotes::encodeHistory(
            \Rateb\App\Helpers\ProcurementNotes::appendHistory(
                $history,
                (string) ($old['notes'] ?? ''),
                (string) ($data['notes'] ?? '')
            )
        );
    }

    protected function saveQuoteAttachment(int $id): void
    {
        (new \Rateb\App\Services\ProcurementService())->saveQuoteAttachments('purchase_request', $id);
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
        $this->indexFields = [
            ['name' => 'order_no', 'label' => 'order_no'],
            ['name' => 'supplier_id', 'label' => 'supplier', 'type' => 'fk', 'lookup' => 'suppliers'],
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses'],
            ['name' => 'order_date', 'label' => 'order_date'],
            ['name' => 'expected_date', 'label' => 'expected_date'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'currency', 'label' => 'currency'],
            ['name' => 'total_amount', 'label' => 'total'],
        ];
        $this->fields = [
            ['name' => 'supplier_id', 'label' => 'supplier', 'type' => 'fk', 'lookup' => 'suppliers'],
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses'],
            ['name' => 'cost_center_id', 'label' => 'cost_center', 'type' => 'fk', 'lookup' => 'cost_centers'],
            ['name' => 'currency', 'label' => 'currency', 'type' => 'select', 'lookup' => 'currencies'],
            ['name' => 'order_date', 'label' => 'order_date', 'type' => 'date'],
            ['name' => 'expected_date', 'label' => 'expected_date', 'type' => 'date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'po_statuses'],
            ['name' => 'discount_amount', 'label' => 'discount', 'type' => 'number'],
            ['name' => 'shipping_amount', 'label' => 'shipping', 'type' => 'number'],
            ['name' => 'total_amount', 'label' => 'total', 'type' => 'number'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea'],
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
            $data['order_no'] = $this->model->generateDocumentCode(
                \Rateb\App\Services\DocumentCodeService::PREFIX_PURCHASE_ORDER,
                'order_no'
            );
        }
        if (empty($data['order_date'])) {
            $data['order_date'] = date('Y-m-d');
        }
        if (($data['currency'] ?? '') === '') {
            $data['currency'] = 'SAR';
        }
        return $data;
    }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        $proc = new \Rateb\App\Services\ProcurementService();
        return array_merge([
            'departments' => $proc->departmentOptions(),
            'inventoryItems' => (new \Rateb\App\Models\Inventory())->all(300, 0),
            'warehouses' => (new \Rateb\App\Models\Warehouse())->all(200, 0),
        ], $extra);
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
            'lineItems' => [],
            'suppliers' => (new \Rateb\App\Models\Supplier())->all(200, 0),
            'costCenters' => $this->loadCostCenters(),
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'defaultVat15' => true,
        ]), $this->layout());
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
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
            'lineItems' => \Rateb\App\Helpers\LineItems::loadPurchaseOrderItems($id),
            'suppliers' => (new \Rateb\App\Models\Supplier())->all(200, 0),
            'costCenters' => $this->loadCostCenters(),
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'defaultVat15' => true,
            'workflow' => (new \Rateb\App\Services\WorkflowSubmissionService())->instanceForEntity(
                'purchase_order',
                $id,
                (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0)
            ),
        ]), $this->layout());
    }

    public function createFromQuotation(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('rfq'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $poId = (new \Rateb\App\Services\ProcurementService())->createOrderFromQuotation($id);
            SessionManager::flash('success', __('po_created_from_quote'));
            $this->redirect(rateb_url(rateb_app_route('purchase-orders') . '/' . $poId . '/edit'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_app_url('rfq'));
        }
    }

    public function submit(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $old = $this->model->find($id);
        if (!$old) {
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->model->update($id, ['status' => 'sent']);
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseOrderStatus(
            $id,
            'sent',
            (string) ($old['status'] ?? '')
        );
        SessionManager::flash('success', __('po_sent'));
        $this->redirect(rateb_url($this->routePrefix . '/' . $id));
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
        $this->applyLineTotals($data, $lines);
        $id = $this->model->create($data);
        \Rateb\App\Helpers\LineItems::syncPurchaseOrderItems($id, $lines);
        $this->saveQuoteAttachment($id);
        (new \Rateb\App\Services\DocumentBarcodeService())->ensure('purchase_order', $id);
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseOrderStatus(
            $id,
            (string) ($data['status'] ?? 'draft')
        );
        $this->tryAutoPostPurchaseOrder($id, (string) ($data['status'] ?? ''));
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
        $this->applyLineTotals($data, $lines);
        if ($old) {
            $this->applyNotesHistory($data, $old);
        }
        $this->model->update($id, $data);
        \Rateb\App\Helpers\LineItems::syncPurchaseOrderItems($id, $lines);
        $this->saveQuoteAttachment($id);
        (new \Rateb\App\Services\WorkflowSubmissionService())->handlePurchaseOrderStatus(
            $id,
            (string) ($data['status'] ?? ''),
            $old ? (string) ($old['status'] ?? '') : null
        );
        $this->tryAutoPostPurchaseOrder($id, (string) ($data['status'] ?? ''));
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
        $docBarcode = (new \Rateb\App\Services\DocumentBarcodeService())->labelData('purchase_order', $id);
        $supplierName = '';
        if (!empty($item['supplier_id'])) {
            $sup = (new \Rateb\App\Models\Supplier())->find((int) $item['supplier_id']);
            $supplierName = (string) ($sup['name'] ?? '');
        }
        $this->view('company/purchase-orders/show', [
            'title' => __('purchase_orders'),
            'order' => $item,
            'items' => $items,
            'supplierName' => $supplierName,
            'warehouses' => (new \Rateb\App\Models\Warehouse())->all(200, 0),
            'docBarcode' => $docBarcode,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function print(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $items = \Rateb\App\Helpers\LineItems::loadPurchaseOrderItems($id);
        $docBarcode = (new \Rateb\App\Services\DocumentBarcodeService())->labelData('purchase_order', $id);
        $supplierName = '';
        if (!empty($item['supplier_id'])) {
            $sup = (new \Rateb\App\Models\Supplier())->find((int) $item['supplier_id']);
            $supplierName = (string) ($sup['name'] ?? '');
        }
        $this->view('company/purchase-orders/print', [
            'title' => __('purchase_orders'),
            'order' => $item,
            'items' => $items,
            'supplierName' => $supplierName,
            'docBarcode' => $docBarcode,
        ], 'print');
    }

    public function receive(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $receiveQtys = $_POST['receive_qty'] ?? [];
        $warehouseId = (int) ($_POST['warehouse_id'] ?? 0) ?: null;
        try {
            (new \Rateb\App\Services\ProcurementService())->receiveOrder($id, is_array($receiveQtys) ? $receiveQtys : [], $warehouseId);
            SessionManager::flash('success', __('grn_received'));
            $this->redirect(rateb_url($this->routePrefix . '/' . $id));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id));
        }
    }

    private function tryAutoPostPurchaseOrder(int $id, string $status): void
    {
        if (!in_array($status, ['received', 'confirmed'], true)) {
            return;
        }
        (new \Rateb\App\Services\AccountingService())->autoPostPurchaseOrder($id);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadCostCenters(): array
    {
        $companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
        if ($companyId < 1) {
            return [];
        }
        return (new \Rateb\App\Models\CostCenter())->query(
            'SELECT id, code, name, name_ar FROM rateb_cost_centers WHERE company_id = :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
    }

    /** @param array<string, mixed> $data */
    protected function applyLineTotals(array &$data, array $lines): void
    {
        (new \Rateb\App\Services\ProcurementService())->applyOrderTotals($data, $lines);
    }

    /** @param array<string, mixed> $data */
    protected function applyNotesHistory(array &$data, ?array $old): void
    {
        $history = \Rateb\App\Helpers\ProcurementNotes::decodeHistory(
            isset($old['notes_history']) ? (string) $old['notes_history'] : null
        );
        $data['notes_history'] = \Rateb\App\Helpers\ProcurementNotes::encodeHistory(
            \Rateb\App\Helpers\ProcurementNotes::appendHistory(
                $history,
                (string) ($old['notes'] ?? ''),
                (string) ($data['notes'] ?? '')
            )
        );
    }

    protected function saveQuoteAttachment(int $id): void
    {
        (new \Rateb\App\Services\ProcurementService())->saveQuoteAttachments('purchase_order', $id);
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
        $this->indexFields = [
            ['name' => 'rfq_no', 'label' => 'rfq_no'],
            ['name' => 'title', 'label' => 'title'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'deadline', 'label' => 'deadline'],
        ];
        $this->fields = [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'rfq_statuses'],
            ['name' => 'deadline', 'label' => 'Deadline', 'type' => 'date'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_RFQ, 'rfq_no');
        return $data;
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
        $this->indexFields = [
            ['name' => 'quotation_no', 'label' => 'quotation_no'],
            ['name' => 'rfq_id', 'label' => 'rfq', 'type' => 'fk', 'lookup' => 'rfq'],
            ['name' => 'supplier_id', 'label' => 'supplier', 'type' => 'fk', 'lookup' => 'suppliers'],
            ['name' => 'amount', 'label' => 'amount'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'rfq_id', 'label' => 'rfq', 'type' => 'fk', 'lookup' => 'rfq'],
            ['name' => 'supplier_id', 'label' => 'supplier', 'type' => 'fk', 'lookup' => 'suppliers'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'quotation_statuses'],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_QUOTATION, 'quotation_no');
        return $data;
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
        $this->indexFields = [
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'email', 'label' => 'email'],
            ['name' => 'phone', 'label' => 'phone'],
            ['name' => 'classification_id', 'label' => 'supplier_classifications', 'type' => 'fk', 'lookup' => 'supplier_classifications'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'classification_id', 'label' => 'supplier_classifications', 'type' => 'fk', 'lookup' => 'supplier_classifications'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'supplier_statuses'],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_SUPPLIER, 'code');
        $classId = (int) ($data['classification_id'] ?? 0);
        $data['classification_id'] = $classId > 0 ? $classId : null;
        return $data;
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
            ['name' => 'item_code', 'label' => 'item_code'],
            ['name' => 'item_name', 'label' => 'item_name'],
            ['name' => 'sku', 'label' => 'sku'],
            ['name' => 'barcode', 'label' => 'document_barcode', 'type' => 'barcode'],
            ['name' => 'quantity', 'label' => 'quantity'],
            ['name' => 'production_date', 'label' => 'production_date'],
            ['name' => 'expiry_date', 'label' => 'expiry_date'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses', 'required' => true],
            ['name' => 'category_id', 'label' => 'product_categories', 'type' => 'fk', 'lookup' => 'product_categories'],
            ['name' => 'item_name', 'label' => 'Item', 'type' => 'text', 'required' => true],
            ['name' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'step' => '0.001', 'min' => '0'],
            ['name' => 'unit', 'label' => 'unit_of_measure', 'type' => 'select', 'lookup' => 'units', 'translate_options' => true],
            ['name' => 'unit_cost', 'label' => 'unit_price', 'type' => 'number', 'step' => 'any', 'min' => '0', 'attrs' => ['class' => 'form-control rateb-form-control rateb-ltr-num']],
            ['name' => 'reorder_level', 'label' => 'reorder_level', 'type' => 'number', 'step' => '0.001', 'min' => '0'],
            ['name' => 'max_stock', 'label' => 'max_stock', 'type' => 'number', 'step' => '0.001', 'min' => '0'],
            ['name' => 'production_date', 'label' => 'production_date', 'type' => 'date'],
            ['name' => 'expiry_date', 'label' => 'expiry_date', 'type' => 'date'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'inventory_statuses'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3],
        ];
    }

    public function warehouseItemsJson(): void
    {
        $warehouseId = (int) ($this->input('warehouse_id', 0));
        $rows = (new \Rateb\App\Services\FormLookupService())->inventoryRowsByWarehouse($warehouseId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['items' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function layout(): string
    {
        return 'main';
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_INVENTORY, 'item_code');
        return $data;
    }

    /** @param array<string, mixed> $extra */
    /** @return array<string, mixed> */
    private function inventoryFormData(array $extra): array
    {
        $lookupSvc = new \Rateb\App\Services\FormLookupService();
        return $this->formViewData(array_merge($extra, [
            'lookups' => $lookupSvc->forFields(array_merge($this->fields, [
                ['lookup' => 'inventory_movement_types'],
            ])),
            'warehouseItemsUrl' => rateb_url($this->routePrefix . '/warehouse-items'),
            'assetJs' => rateb_asset('js/inventory-form.js'),
        ]));
    }

    private function validateMaxStock(float $quantity, float $maxStock): void
    {
        if ($maxStock > 0 && $quantity > $maxStock) {
            throw new \RuntimeException(__('max_stock_exceeded'));
        }
    }

    public function create(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        $this->view($this->viewPrefix . '/form', $this->inventoryFormData([
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
            'multipart' => true,
            'attachment' => $this->attachmentFieldData(null),
        ]), $this->layout());
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

        $this->view($this->viewPrefix . '/form', $this->inventoryFormData([
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
            'multipart' => true,
            'attachment' => $this->attachmentFieldData($item),
        ]), $this->layout());
    }

    public function store(): void
    {
        $this->guardManage();
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $existingId = (int) $this->input('existing_inventory_id', 0);
        $movementType = trim((string) $this->input('movement_type', 'in'));
        $movementQty = (float) str_replace(',', '.', (string) $this->input('quantity', '0'));
        $notes = trim((string) $this->input('notes', ''));

        if ($existingId > 0) {
            $this->applyExistingItemMovement($existingId, $movementType, $movementQty, $notes);
            return;
        }

        if ($movementType === 'out') {
            SessionManager::flash('error', __('movement_out_requires_existing'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }

        $data = $this->collectData();
        $data['notes'] = $notes;
        $data['quantity'] = 0;

        try {
            $this->ensureTenantCompanyForWrite($data);
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
            $targetQty = $movementQty;
            $this->validateMaxStock($targetQty, (float) ($data['max_stock'] ?? 0));
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }

        $id = $this->model->create($data);
        (new \Rateb\App\Services\DocumentBarcodeService())->ensure('inventory', $id);
        $attachmentOk = $this->saveInventoryAttachment($id);

        if ($movementQty > 0) {
            try {
                (new \Rateb\App\Services\StockMovementService())->record([
                    'inventory_id' => $id,
                    'warehouse_id' => (int) ($data['warehouse_id'] ?? 0),
                    'movement_type' => $movementType === 'adjustment' ? 'adjustment' : 'in',
                    'quantity' => $movementQty,
                    'notes' => $notes,
                    'reference_type' => 'inventory_create',
                    'reference_id' => $id,
                ]);
            } catch (\Throwable $e) {
                SessionManager::flash('error', $e->getMessage());
                $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
            }
        }

        (new AuditService())->log('create', $this->entityName, $id, $data);
        if ($attachmentOk) {
            SessionManager::flash('success', __('save') . ' OK');
        } else {
            SessionManager::flash('error', __('save_ok_attachment_failed'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $id = (int) ($params['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $itemCompanyId = (int) ($existing['company_id'] ?? 0);
        if ($itemCompanyId > 0) {
            \Rateb\App\Core\TenantContext::setCompanyId($itemCompanyId);
        }

        $movementType = trim((string) $this->input('movement_type', ''));
        $movementQty = (float) str_replace(',', '.', (string) $this->input('quantity', '0'));
        $notes = trim((string) $this->input('notes', ''));

        $data = $this->collectData();
        $data['notes'] = $notes;

        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }

        if ($movementType !== '') {
            unset($data['quantity']);
            try {
                (new \Rateb\App\Services\StockMovementService())->record([
                    'inventory_id' => $id,
                    'warehouse_id' => (int) ($data['warehouse_id'] ?? 0),
                    'movement_type' => $movementType,
                    'quantity' => $movementQty,
                    'notes' => $notes,
                    'reference_type' => 'inventory_edit',
                    'reference_id' => $id,
                ]);
            } catch (\Throwable $e) {
                SessionManager::flash('error', $e->getMessage());
                $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
            }
        } else {
            try {
                $this->validateMaxStock((float) ($data['quantity'] ?? 0), (float) ($data['max_stock'] ?? 0));
            } catch (\RuntimeException $e) {
                SessionManager::flash('error', $e->getMessage());
                $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
            }
        }

        $this->model->update($id, $data);
        $attachmentOk = $this->saveInventoryAttachment($id);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        if ($attachmentOk) {
            SessionManager::flash('success', __('save') . ' OK');
        } else {
            SessionManager::flash('error', __('save_ok_attachment_failed'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    private function applyExistingItemMovement(int $existingId, string $movementType, float $movementQty, string $notes): void
    {
        $item = $this->model->find($existingId);
        if (!$item) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }

        if ($movementQty <= 0) {
            SessionManager::flash('error', __('quantity_required'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }

        $meta = [
            'reorder_level' => trim((string) $this->input('reorder_level', '')),
            'max_stock' => trim((string) $this->input('max_stock', '')),
            'unit_cost' => trim((string) $this->input('unit_cost', '')),
            'expiry_date' => trim((string) $this->input('expiry_date', '')),
            'status' => trim((string) $this->input('status', '')),
            'notes' => $notes,
        ];
        $meta = array_filter($meta, static fn ($v) => $v !== '');

        try {
            if ($meta !== []) {
                $this->model->update($existingId, $meta);
            }
            (new \Rateb\App\Services\StockMovementService())->record([
                'inventory_id' => $existingId,
                'warehouse_id' => (int) ($item['warehouse_id'] ?? 0),
                'movement_type' => $movementType,
                'quantity' => $movementQty,
                'notes' => $notes,
                'reference_type' => 'inventory_create',
                'reference_id' => $existingId,
            ]);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }

        (new AuditService())->log('stock_movement', $this->entityName, $existingId, [
            'movement_type' => $movementType,
            'quantity' => $movementQty,
        ]);
        SessionManager::flash('success', __('movement_recorded'));
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

    private function saveInventoryAttachment(int $id): bool
    {
        $item = $this->model->find($id);
        $companyId = (int) ($item['company_id'] ?? \Rateb\App\Core\TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        $upload = \Rateb\App\Helpers\EntityAttachment::handleOptionalFile(
            'entity_attachment',
            $companyId,
            'inventory',
            $id,
            __('inventory_attachment')
        );
        if (!($upload['success'] ?? false)) {
            return false;
        }
        if (!empty($upload['path'])) {
            $this->model->update($id, ['document_path' => $upload['path']]);
        }
        return true;
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
        $this->indexFields = [
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'location', 'label' => 'location'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'location', 'label' => 'location', 'type' => 'hybrid', 'lookup' => 'warehouse_locations'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'warehouse_statuses'],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_WAREHOUSE, 'code');
        return $data;
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
        $this->indexFields = [
            ['name' => 'asset_tag', 'label' => 'asset_tag'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'category', 'label' => 'category', 'type' => 'fk', 'lookup' => 'asset_categories'],
            ['name' => 'location', 'label' => 'location'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'type' => 'hybrid', 'lookup' => 'asset_categories'],
            ['name' => 'location', 'label' => 'location', 'type' => 'hybrid', 'lookup' => 'asset_locations'],
            ['name' => 'purchase_date', 'label' => 'purchase_date', 'type' => 'date'],
            ['name' => 'purchase_cost', 'label' => 'purchase_cost', 'type' => 'number'],
            ['name' => 'current_value', 'label' => 'Value', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'asset_statuses'],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_ASSET, 'asset_tag');
        return $data;
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
            ['name' => 'asset_id', 'label' => 'assets', 'type' => 'fk', 'lookup' => 'assets'],
            ['name' => 'device_name', 'label' => 'Device', 'type' => 'text', 'required' => true],
            ['name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'hybrid', 'lookup' => 'manufacturers'],
            ['name' => 'model_no', 'label' => 'Model', 'type' => 'hybrid', 'lookup' => 'model_numbers'],
            ['name' => 'serial_no', 'label' => 'Serial', 'type' => 'text'],
            ['name' => 'calibration_due', 'label' => 'Calibration Due', 'type' => 'date'],
            ['name' => 'regulatory_status', 'label' => 'regulatory_status', 'type' => 'select', 'lookup' => 'regulatory_statuses'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'medical_device_statuses'],
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
            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
            ['name' => 'supplier_id', 'label' => 'suppliers', 'type' => 'fk', 'lookup' => 'suppliers'],
            ['name' => 'contract_type', 'label' => 'contract_type', 'type' => 'select', 'lookup' => 'contract_types'],
            ['name' => 'approval_status', 'label' => 'approval_status', 'type' => 'select', 'lookup' => 'approval_statuses'],
            ['name' => 'start_date', 'label' => 'Start', 'type' => 'date'],
            ['name' => 'end_date', 'label' => 'End', 'type' => 'date'],
            ['name' => 'renewal_date', 'label' => 'renewal_date', 'type' => 'date'],
            ['name' => 'value', 'label' => 'Value', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'contract_statuses'],
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
        return $this->formViewData([
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('contracts'),
            'item' => $item,
            'multipart' => true,
            'attachment' => $this->attachmentFieldData($item),
        ]);
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_CONTRACT, 'contract_no');
        return $data;
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
        $this->indexFields = [
            ['name' => 'tender_no', 'label' => 'tender_no'],
            ['name' => 'title', 'label' => 'title'],
            ['name' => 'publish_date', 'label' => 'publish_date'],
            ['name' => 'closing_date', 'label' => 'closing_date'],
            ['name' => 'estimated_value', 'label' => 'estimated_value'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'publish_date', 'label' => 'Publish', 'type' => 'date'],
            ['name' => 'closing_date', 'label' => 'Closing', 'type' => 'date'],
            ['name' => 'estimated_value', 'label' => 'Value', 'type' => 'number'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'lookup' => 'tender_statuses'],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_TENDER, 'tender_no');
        return $data;
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
        $this->permissionResource = 'supplier-evaluations';
        $this->tenantForeignKeys = ['supplier_id'];
        $this->fields = [
            ['name' => 'supplier_id', 'label' => 'suppliers', 'type' => 'fk', 'lookup' => 'suppliers', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'evaluation_date', 'label' => 'evaluation_date', 'type' => 'date', 'default' => date('Y-m-d'), 'col' => 'col-md-6'],
            ['name' => 'period_start', 'label' => 'evaluation_period_start', 'type' => 'date', 'col' => 'col-md-6'],
            ['name' => 'period_end', 'label' => 'evaluation_period_end', 'type' => 'date', 'col' => 'col-md-6'],
            ['name' => 'quality_score', 'label' => 'quality_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'delivery_score', 'label' => 'delivery_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'price_score', 'label' => 'price_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'service_score', 'label' => 'service_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'evaluation_statuses', 'default' => 'published', 'col' => 'col-md-6'],
            ['name' => 'comments', 'label' => 'comments', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 4],
        ];
        $this->indexFields = [
            ['name' => 'evaluation_no', 'label' => 'evaluation_no'],
            ['name' => 'supplier_name', 'label' => 'suppliers'],
            ['name' => 'evaluation_date', 'label' => 'evaluation_date'],
            ['name' => 'overall_score', 'label' => 'overall_score'],
            ['name' => 'score_percent', 'label' => 'evaluation_percent'],
            ['name' => 'rating_tier', 'label' => 'supplier_rating_tier', 'type' => 'status'],
            ['name' => 'evaluator_name', 'label' => 'evaluator_name'],
            ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $items = $this->model->query(
            'SELECT e.*, s.name AS supplier_name FROM rateb_supplier_evaluations e
             LEFT JOIN rateb_suppliers s ON s.id = e.supplier_id
             WHERE e.company_id = :cid ORDER BY e.id DESC LIMIT 100',
            ['cid' => $companyId]
        );
        foreach ($items as &$row) {
            $tier = (string) ($row['rating_tier'] ?? '');
            if ($tier !== '') {
                $row['rating_tier'] = 'eval_tier_' . $tier;
            }
            $approval = (string) ($row['manager_approval'] ?? 'pending');
            $row['manager_approval'] = 'manager_approval_' . $approval;
            $row['score_percent'] = number_format((float) ($row['score_percent'] ?? 0), 1) . '%';
        }
        unset($row);

        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $items,
            'total' => count($items),
            'page' => 1,
            'limit' => 100,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->indexFields,
            'documentEntityType' => $this->resolveDocumentEntityType(),
            'csrf' => \Rateb\App\Core\Csrf::token(),
        ]), $this->layout());
    }

    public function approvals(): void
    {
        rateb_bootstrap_ops_tenant();
        $this->guardManage();
        $companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $svc = new \Rateb\App\Services\SupplierEvaluationService();
        $items = $svc->pendingApprovals($companyId);
        $this->view($this->viewPrefix . '/approvals', [
            'title' => __('evaluation_approvals'),
            'items' => $items,
            'pendingCount' => count($items),
            'routePrefix' => $this->routePrefix,
            'csrf' => \Rateb\App\Core\Csrf::token(),
        ], $this->layout());
    }

    protected function formViewData(array $extra = []): array
    {
        $data = parent::formViewData($extra);
        $item = $data['item'] ?? null;
        $companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $svc = new \Rateb\App\Services\SupplierEvaluationService();
        $user = \Rateb\App\Core\Auth::user();
        $data['multipart'] = true;
        $data['evaluatorName'] = is_array($item) && !empty($item['evaluator_name'])
            ? (string) $item['evaluator_name']
            : (string) ($user['name'] ?? '');
        $data['evaluationMetrics'] = $svc->computeMetrics(
            (int) (is_array($item) ? ($item['quality_score'] ?? 0) : 0),
            (int) (is_array($item) ? ($item['delivery_score'] ?? 0) : 0),
            (int) (is_array($item) ? ($item['price_score'] ?? 0) : 0),
            (int) (is_array($item) ? ($item['service_score'] ?? 0) : 0)
        );
        $data['tierLabels'] = [
            'excellent' => $svc->tierLabel('excellent'),
            'very_good' => $svc->tierLabel('very_good'),
            'good' => $svc->tierLabel('good'),
            'weak' => $svc->tierLabel('weak'),
        ];
        $supplierId = is_array($item) ? (int) ($item['supplier_id'] ?? 0) : 0;
        $evalId = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
        $data['supplierHistory'] = $supplierId > 0
            ? $svc->historyForSupplier($companyId, $supplierId, $evalId)
            : [];
        $data['historyUrl'] = rateb_app_url('supplier-evaluations/history');
        $data['evaluationFormJs'] = rateb_asset('js/supplier-evaluation-form.js');
        if (is_array($item) && !empty($item['id'])) {
            $data['existingDocuments'] = (new \Rateb\App\Services\DocumentService())
                ->listForEntity('supplier_evaluation', (int) $item['id'], $companyId);
        } else {
            $data['existingDocuments'] = [];
        }
        return $data;
    }

    public function create(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        TenantContext::setCompanyId($companyId);
        if ((new \Rateb\App\Models\Supplier())->count() < 1) {
            SessionManager::flash('error', __('supplier_comms_need_supplier'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __('supplier_evaluations'),
            'item' => null,
        ]), $this->layout());
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __('supplier_evaluations'),
            'item' => $item,
        ]), $this->layout());
    }

    public function store(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            $data = $this->collectData();
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $this->ensureTenantCompanyForWrite($data);
        $data['manager_approval'] = 'pending';
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
            $id = $this->model->create($data);
            $this->persistAttachments($id, (int) ($data['company_id'] ?? 0));
            (new \Rateb\App\Services\SupplierEvaluationService())->refreshSupplierRating((int) ($data['supplier_id'] ?? 0));
            (new AuditService())->log('create', $this->entityName, $id, $data);
            SessionManager::flash('success', __('evaluation_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            $data = $this->collectData();
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        unset($data['evaluated_by'], $data['evaluator_name']);
        if (!empty($existing['evaluation_no'])) {
            $data['evaluation_no'] = (string) $existing['evaluation_no'];
        }
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $needsReapproval = $this->evaluationNeedsReapproval($existing, $data);
        if ($needsReapproval) {
            $data['manager_approval'] = 'pending';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
        }
        $this->model->update($id, $data);
        try {
            $this->persistAttachments($id, (int) (\Rateb\App\Core\TenantContext::companyId() ?? rateb_resolve_ops_company_id()));
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $svc = new \Rateb\App\Services\SupplierEvaluationService();
        $oldSupplierId = (int) ($existing['supplier_id'] ?? 0);
        $newSupplierId = (int) ($data['supplier_id'] ?? 0);
        $svc->refreshSupplierRating($newSupplierId);
        if ($oldSupplierId > 0 && $oldSupplierId !== $newSupplierId) {
            $svc->refreshSupplierRating($oldSupplierId);
        }
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', $needsReapproval ? __('evaluation_resubmitted_for_approval') : __('evaluation_saved'));
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function supplierHistory(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $supplierId = (int) $this->input('supplier_id', 0);
        $excludeId = (int) $this->input('exclude_id', 0);
        header('Content-Type: application/json; charset=utf-8');
        try {
            $svc = new \Rateb\App\Services\SupplierEvaluationService();
            $rows = $svc->historyForSupplier($companyId, $supplierId, $excludeId);
            $out = [];
            foreach ($rows as $row) {
                $approval = (string) ($row['manager_approval'] ?? 'pending');
                $row['manager_approval_label'] = __('manager_approval_' . $approval);
                $out[] = $row;
            }
            echo json_encode(['rows' => $out], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'rows' => [],
                'error' => \Rateb\App\Services\DatabaseErrorService::userMessage($e),
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function approve(array $params): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix . '/approvals'));
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix . '/approvals'));
        }
        if ((string) ($item['manager_approval'] ?? '') !== 'pending') {
            SessionManager::flash('error', __('evaluation_already_processed'));
            $this->redirect(rateb_url($this->routePrefix . '/approvals'));
        }
        $this->model->update($id, [
            'manager_approval' => 'approved',
            'approved_by' => (int) SessionManager::get('rateb_user_id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        (new \Rateb\App\Services\SupplierEvaluationService())->refreshSupplierRating((int) ($item['supplier_id'] ?? 0));
        (new AuditService())->log('approve', $this->entityName, $id);
        SessionManager::flash('success', __('evaluation_approved'));
        $this->redirect(rateb_url($this->routePrefix . '/approvals'));
    }

    public function reject(array $params): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix . '/approvals'));
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix . '/approvals'));
        }
        if ((string) ($item['manager_approval'] ?? '') !== 'pending') {
            SessionManager::flash('error', __('evaluation_already_processed'));
            $this->redirect(rateb_url($this->routePrefix . '/approvals'));
        }
        $this->model->update($id, [
            'manager_approval' => 'rejected',
            'approved_by' => (int) SessionManager::get('rateb_user_id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        (new \Rateb\App\Services\SupplierEvaluationService())->refreshSupplierRating((int) ($item['supplier_id'] ?? 0));
        (new AuditService())->log('reject', $this->entityName, $id);
        SessionManager::flash('success', __('evaluation_rejected'));
        $this->redirect(rateb_url($this->routePrefix . '/approvals'));
    }

    public function destroy(array $params): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $supplierId = (int) ($item['supplier_id'] ?? 0);
        $this->model->delete($id);
        if ($supplierId > 0) {
            (new \Rateb\App\Services\SupplierEvaluationService())->refreshSupplierRating($supplierId);
        }
        (new AuditService())->log('delete', $this->entityName, $id);
        SessionManager::flash('success', __('delete') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function bulkDestroy(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
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
        $supplierIds = [];
        foreach ($ids as $id) {
            $item = $this->model->find($id);
            if ($item) {
                $sid = (int) ($item['supplier_id'] ?? 0);
                if ($sid > 0) {
                    $supplierIds[$sid] = true;
                }
            }
        }
        $deleted = $this->model->deleteMany($ids);
        $svc = new \Rateb\App\Services\SupplierEvaluationService();
        foreach (array_keys($supplierIds) as $supplierId) {
            $svc->refreshSupplierRating($supplierId);
        }
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', $this->entityName, $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $svc = new \Rateb\App\Services\SupplierEvaluationService();
        $metrics = $svc->computeMetrics(
            (int) ($data['quality_score'] ?? 0),
            (int) ($data['delivery_score'] ?? 0),
            (int) ($data['price_score'] ?? 0),
            (int) ($data['service_score'] ?? 0)
        );
        $data['overall_score'] = $metrics['overall'];
        $data['score_percent'] = $metrics['percent'];
        $data['rating_tier'] = $metrics['tier'];
        $data['evaluated_by'] = SessionManager::get('rateb_user_id');
        $user = \Rateb\App\Core\Auth::user();
        $data['evaluator_name'] = trim((string) ($user['name'] ?? ''));
        if ($data['evaluator_name'] === '') {
            $data['evaluator_name'] = trim((string) ($user['email'] ?? ''));
        }
        $periodStart = trim((string) ($data['period_start'] ?? ''));
        $periodEnd = trim((string) ($data['period_end'] ?? ''));
        $data['period_start'] = $periodStart !== '' ? $periodStart : null;
        $data['period_end'] = $periodEnd !== '' ? $periodEnd : null;
        if ($data['period_start'] && $data['period_end'] && $data['period_end'] < $data['period_start']) {
            throw new \RuntimeException(__('evaluation_period_invalid'));
        }
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_EVALUATION, 'evaluation_no');
        return $data;
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $data */
    private function evaluationNeedsReapproval(array $existing, array $data): bool
    {
        $prev = (string) ($existing['manager_approval'] ?? 'pending');
        if ($prev === 'pending') {
            return false;
        }
        $watch = [
            'supplier_id', 'evaluation_date', 'period_start', 'period_end', 'status',
            'quality_score', 'delivery_score', 'price_score', 'service_score',
        ];
        foreach ($watch as $key) {
            $old = $existing[$key] ?? null;
            $new = $data[$key] ?? null;
            if ($old === null && ($new === null || $new === '')) {
                continue;
            }
            if ((string) $old !== (string) $new) {
                return true;
            }
        }
        return false;
    }

    private function persistAttachments(int $evaluationId, int $companyId): void
    {
        if ($evaluationId < 1 || $companyId < 1) {
            return;
        }
        $result = \Rateb\App\Helpers\EntityAttachment::handleMultipleFiles(
            'evaluation_attachments',
            $companyId,
            'supplier_evaluation',
            $evaluationId,
            5,
            __('evaluation_attachments')
        );
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? __('upload_failed')));
        }
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
