<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AssetDeviceWorkflowService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\FormLookupService;
use Rateb\App\Services\ContractWorkflowService;
use Rateb\App\Services\WorkflowRecordService;
use Rateb\App\Services\ErpAnalyticsService;
use Rateb\App\Services\InventoryWorkflowService;
use Rateb\App\Controllers\Shared\ExportController;

final class InventoryBatchesController extends \Rateb\App\Controllers\CrudController
{
    protected string $documentEntityType = 'inventory_batch';

    public function __construct()
    {
        $this->model = new \Rateb\App\Models\InventoryBatch();
        $this->viewPrefix = 'company/inventory-batches';
        $this->routePrefix = rateb_app_route('inventory-batches');
        $this->entityName = 'inventory_batches';
        $this->tenantForeignKeys = ['warehouse_id', 'inventory_id'];
        $this->indexFields = [
            ['name' => 'item_code', 'label' => 'item_code'],
            ['name' => 'item_name', 'label' => 'item_name'],
            ['name' => 'quantity', 'label' => 'quantity'],
            ['name' => 'production_date', 'label' => 'production_date'],
            ['name' => 'expiry_date', 'label' => 'expiry_date'],
            ['name' => 'warehouse_name', 'label' => 'warehouses'],
        ];
        $this->fields = [
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'inventory_id', 'label' => 'inventory', 'type' => 'fk', 'lookup' => 'inventory', 'required' => true, 'col' => 'col-md-4'],
            [
                'name' => 'item_code',
                'label' => 'item_code',
                'type' => 'text',
                'display_only' => true,
                'readonly' => true,
                'col' => 'col-md-4',
                'attrs' => [
                    'data-batch-item-code-display' => '1',
                    'class' => 'form-control rateb-form-control rateb-ltr-num',
                ],
                'hint' => 'item_code_from_inventory_hint',
            ],
            ['name' => 'quantity', 'label' => 'quantity', 'type' => 'number', 'step' => '0.001', 'min' => '0', 'col' => 'col-md-4'],
            ['name' => 'production_date', 'label' => 'production_date', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'expiry_date', 'label' => 'expiry_date', 'type' => 'date', 'col' => 'col-md-4'],
        ];
    }

    protected function layout(): string { return 'main'; }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        $data = parent::formViewData($extra);
        $item = $data['item'] ?? null;
        if (is_array($item) && !empty($item['inventory_id'])) {
            $inv = (new \Rateb\App\Models\Inventory())->find((int) $item['inventory_id']);
            if ($inv) {
                $item['item_code'] = (string) ($inv['item_code'] ?? '');
                $data['item'] = $item;
            }
        }
        return $data;
    }

    public function create(): void
    {
        rateb_bootstrap_ops_tenant();
        rateb_require_manage('inventory-batches');
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __('inventory_batches'),
            'item' => null,
        ]), 'main');
    }

    public function documents(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        parent::documents($params);
    }

    public function documentsPanel(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        parent::documentsPanel($params);
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $items = $this->enrichItemsWithDocumentCounts((new InventoryWorkflowService())->listBatches(100));
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __('inventory_batches'),
            'items' => $items,
            'total' => count((new InventoryWorkflowService())->listBatches(500)),
            'page' => 1,
            'limit' => 100,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->indexFields,
            'csrf' => Csrf::token(),
            'exportRoute' => rateb_app_url('inventory-batches/export'),
            'exportEnabled' => rateb_can_export_entity('inventory-batches'),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
            'documentEntityType' => $this->resolveDocumentEntityType(),
        ]), 'main');
    }

    public function store(): void
    {
        rateb_bootstrap_ops_tenant();
        rateb_require_manage('inventory-batches');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        try {
            $id = (new InventoryWorkflowService())->createBatch($data);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $batch = $this->model->find($id);
        $attachmentOk = $this->saveEntityAttachment($id, is_array($batch) ? $batch : null);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        if ($attachmentOk) {
            SessionManager::flash('success', __('save') . ' OK');
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function edit(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        rateb_require_manage('inventory-batches');
        parent::edit($params);
    }

    public function update(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        rateb_require_manage('inventory-batches');
        parent::update($params);
    }

    public function export(): void
    {
        ExportController::send('inventory_batches', [
            ['name' => 'item_code', 'label' => __('item_code')],
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'production_date', 'label' => __('production_date')],
            ['name' => 'expiry_date', 'label' => __('expiry_date')],
        ], (new InventoryWorkflowService())->listBatches(500), __('inventory_batches'), 'inventory-batches');
    }
}

final class InventoryAuditsController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $model = new \Rateb\App\Models\InventoryAudit();
        $this->view('company/inventory-audits/index', [
            'title' => __('inventory_audits'),
            'items' => $model->all(50, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('inventory-audits'),
            'exportRoute' => rateb_app_url('inventory-audits/export'),
            'exportEnabled' => rateb_can_export_entity('inventory-audits'),
        ], 'main');
    }

    public function create(): void
    {
        rateb_bootstrap_ops_tenant();
        rateb_require_manage('inventory-audits');
        $this->view('company/inventory-audits/form', [
            'title' => __('create') . ' ' . __('inventory_audits'),
            'auditNo' => (new InventoryWorkflowService())->nextAuditNo(),
            'inventory' => (new \Rateb\App\Models\Inventory())->all(200, 0),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('inventory-audits');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('inventory-audits'));
        }
        $svc = new InventoryWorkflowService();
        $auditId = $svc->createAudit(
            trim((string) $this->input('audit_no', '')),
            (int) $this->input('warehouse_id', 0) ?: null,
            trim((string) $this->input('notes', ''))
        );
        $lines = [];
        $ids = (array) $this->input('inventory_id', []);
        $qtys = (array) $this->input('counted_qty', []);
        foreach (array_keys($ids) as $i) {
            $lines[] = ['inventory_id' => (int) ($ids[$i] ?? 0), 'counted_qty' => (float) ($qtys[$i] ?? 0)];
        }
        $svc->saveAuditLines($auditId, $lines);
        (new AuditService())->log('create', 'inventory_audit', $auditId);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('inventory-audits/' . $auditId));
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $audit = (new \Rateb\App\Models\InventoryAudit())->find($id);
        if (!$audit) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $this->view('company/inventory-audits/show', [
            'title' => __('inventory_audits'),
            'audit' => $audit,
            'lines' => (new InventoryWorkflowService())->auditLines($id),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('inventory-audits'),
        ], 'main');
    }

    public function reconcile(array $params): void
    {
        rateb_require_manage('inventory-audits');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('inventory-audits'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $n = (new InventoryWorkflowService())->completeAudit($id);
            (new AuditService())->log('reconcile', 'inventory_audit', $id, ['adjusted' => $n]);
            SessionManager::flash('success', __('reconcile_done', ['count' => $n]));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('inventory-audits/' . $id));
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $items = (new \Rateb\App\Models\InventoryAudit())->all(500, 0);
        ExportController::send('inventory_audits', [
            ['name' => 'audit_no', 'label' => __('record_id')],
            ['name' => 'audit_date', 'label' => __('audit_date')],
            ['name' => 'status', 'label' => __('status')],
            ['name' => 'notes', 'label' => __('notes')],
            ['name' => 'created_at', 'label' => __('created_at')],
        ], $items, __('inventory_audits'), 'inventory-audits');
    }
}

final class InventoryCodesController extends Controller
{
    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = (new \Rateb\App\Models\Inventory())->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        (new \Rateb\App\Services\DocumentBarcodeService())->ensure('inventory', $id);
        $item = (new \Rateb\App\Models\Inventory())->find($id);
        $this->view('company/inventory-codes/show', [
            'title' => __('barcode_qr'),
            'item' => $item,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function generate(array $params): void
    {
        rateb_require_manage('inventory-codes');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('inventory'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new InventoryWorkflowService())->generateCodes($id);
            (new AuditService())->log('generate_codes', 'inventory', $id);
            SessionManager::flash('success', __('codes_generated'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('inventory/' . $id . '/codes'));
    }
}

final class SupplierClassificationsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SupplierClassification();
        $this->viewPrefix = 'company/supplier-classifications';
        $this->routePrefix = rateb_app_route('supplier-classifications');
        $this->entityName = 'supplier_classifications';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['name' => 'color', 'label' => 'color', 'type' => 'text'],
        ];
    }

    protected function layout(): string { return 'main'; }
}

final class SupplierKpiController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = function_exists('rateb_resolve_ops_company_id')
            ? rateb_resolve_ops_company_id()
            : (int) SessionManager::get('rateb_company_id');
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $this->view('company/supplier-kpi/index', [
            'title' => __('supplier_kpi'),
            'suppliers' => (new ErpAnalyticsService())->supplierPerformance($companyId > 0 ? $companyId : null),
            'csrf' => Csrf::token(),
            'exportRoute' => rateb_app_url('supplier-kpi/export'),
            'exportEnabled' => rateb_can_export_entity('supplier-kpi'),
        ], 'main');
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = function_exists('rateb_resolve_ops_company_id')
            ? rateb_resolve_ops_company_id()
            : (int) SessionManager::get('rateb_company_id');
        ExportController::send('supplier_kpi', [
            ['name' => 'code', 'label' => __('record_id')],
            ['name' => 'name', 'label' => __('suppliers')],
            ['name' => 'classification_name', 'label' => __('supplier_classifications')],
            ['name' => 'rating', 'label' => __('rating')],
            ['name' => 'avg_eval', 'label' => __('overall_score')],
            ['name' => 'po_count', 'label' => __('purchase_orders')],
            ['name' => 'performance_kpi', 'label' => __('performance_kpi')],
        ], (new ErpAnalyticsService())->supplierPerformance($companyId > 0 ? $companyId : null), __('supplier_kpi'), 'supplier-kpi');
    }
}

final class ContractRenewalsController extends Controller
{
    use WorkflowOpsTrait;

    protected function workflowSlug(): string { return 'contract-renewals'; }
    protected function workflowRedirect(): void { $this->redirect(rateb_app_url('contract-renewals')); }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $renewals = (new ContractWorkflowService())->listRenewals(100);
        $this->view('company/contract-renewals/index', [
            'title' => __('contract_renewals'),
            'renewals' => $renewals,
            'expiring' => (new ContractWorkflowService())->expiringContracts(60),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('contract-renewals'),
            'canApprove' => rateb_can('contracts.manage'),
            'exportRoute' => rateb_app_url('contract-renewals/export'),
            'exportEnabled' => rateb_can_export_entity('contract-renewals'),
        ], 'main');
    }

    public function edit(array $params): void
    {
        rateb_require_manage('contract-renewals');
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = (new ContractWorkflowService())->findRenewal($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        if ((string) ($item['manager_approval_raw'] ?? $item['manager_approval'] ?? '') === 'approved') {
            SessionManager::flash('error', __('contract_renewal_already_processed'));
            $this->redirect(rateb_app_url('contract-renewals'));
        }
        $fields = FormLookupService::contractRenewalFormFields();
        $lookups = (new FormLookupService())->forFields($fields);
        $this->view('company/contract-renewals/form', [
            'title' => __('edit') . ' — ' . __('contract_renewals'),
            'item' => $item,
            'formFields' => $fields,
            'lookups' => $lookups,
            'csrf' => Csrf::token(),
            'canApprove' => rateb_can('contracts.manage'),
        ], 'main');
    }

    public function update(array $params): void
    {
        rateb_require_manage('contract-renewals');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('contract-renewals'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ContractWorkflowService())->updateRenewal($id, [
                'contract_id' => (int) $this->input('contract_id', 0),
                'renewal_date' => (string) $this->input('renewal_date', date('Y-m-d')),
                'new_end_date' => (string) $this->input('new_end_date', ''),
                'new_value' => (float) $this->input('new_value', 0),
                'notes' => trim((string) $this->input('notes', '')),
            ]);
            (new AuditService())->log('update', 'contract_renewal', $id);
            SessionManager::flash('success', __('save') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('contract-renewals/' . $id . '/edit'));
    }

    public function approve(array $params): void
    {
        if (!rateb_can('contracts.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('contract-renewals'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('contract-renewals'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ContractWorkflowService())->approveRenewal($id, (int) SessionManager::get('rateb_user_id'));
            (new AuditService())->log('approve', 'contract_renewal', $id);
            SessionManager::flash('success', __('contract_renewal_approved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('contract-renewals'));
    }

    public function reject(array $params): void
    {
        if (!rateb_can('contracts.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('contract-renewals'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('contract-renewals'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ContractWorkflowService())->rejectRenewal($id, (int) SessionManager::get('rateb_user_id'));
            (new AuditService())->log('reject', 'contract_renewal', $id);
            SessionManager::flash('success', __('contract_renewal_rejected'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('contract-renewals'));
    }

    public function show(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = (new ContractWorkflowService())->findRenewal($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $this->view('company/contract-renewals/show', [
            'title' => __('contract_renewals') . ' — ' . ($item['renewal_no'] ?? ''),
            'item' => $item,
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('contract-renewals'),
            'canApprove' => rateb_can('contracts.manage'),
            'exportEnabled' => rateb_can_export_entity('contract-renewals'),
        ], 'main');
    }

    public function print(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = (new ContractWorkflowService())->findRenewal($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view('company/contract-renewals/print', [
            'title' => __('print') . ' — ' . __('contract_renewals'),
            'item' => $item,
        ], 'print');
    }

    public function download(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = (new ContractWorkflowService())->findRenewal($id);
        if (!$item) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $svc = new ContractWorkflowService();
        ExportController::send(
            'contract_renewal_' . ($item['renewal_no'] ?? $id),
            $svc->exportColumns(),
            [$item],
            __('contract_renewals') . ' ' . ($item['renewal_no'] ?? ''),
            'contract-renewals'
        );
    }

    public function store(): void
    {
        rateb_require_manage('contract-renewals');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('contract-renewals'));
        }
        $id = (new ContractWorkflowService())->createRenewal([
            'contract_id' => (int) $this->input('contract_id', 0),
            'renewal_date' => (string) $this->input('renewal_date', date('Y-m-d')),
            'new_end_date' => (string) $this->input('new_end_date', ''),
            'new_value' => (float) $this->input('new_value', 0),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
        (new AuditService())->log('create', 'contract_renewal', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('contract-renewals'));
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new ContractWorkflowService();
        ExportController::send(
            'contract_renewals',
            $svc->exportColumns(),
            $svc->listRenewalsForExport(500),
            __('contract_renewals'),
            'contract-renewals'
        );
    }
}

final class AssetMaintenanceController extends Controller
{
    use WorkflowOpsTrait;
    use WorkflowDocumentTrait;

    private AssetDeviceWorkflowService $svc;

    public function __construct() { $this->svc = new AssetDeviceWorkflowService(); }

    protected function workflowSlug(): string { return 'asset-maintenance'; }
    protected function workflowRedirect(): void { $this->redirect(rateb_app_url('asset-maintenance')); }
    protected function workflowTitle(): string { return __('asset_maintenance'); }

    /** @return array<int, array{name:string,label:string,type?:string}> */
    protected function workflowColumns(): array
    {
        return [
            ['name' => 'maintenance_no', 'label' => 'record_id', 'type' => 'id'],
            ['name' => 'asset_name', 'label' => 'assets'],
            ['name' => 'maintenance_type', 'label' => 'maintenance_type'],
            ['name' => 'scheduled_date', 'label' => 'scheduled_date'],
            ['name' => 'completed_date', 'label' => 'completed_date'],
            ['name' => 'cost', 'label' => 'cost', 'type' => 'money'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'notes'],
        ];
    }

    /** @return array<string, mixed>|null */
    protected function workflowFind(int $id): ?array
    {
        return $this->svc->findMaintenance($id);
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $this->view('company/asset-maintenance/index', [
            'title' => __('asset_maintenance'),
            'items' => $this->svc->listAssetMaintenance(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('asset-maintenance'),
            'canApprove' => rateb_can_manage_entity('asset-maintenance'),
            'exportRoute' => rateb_app_url('asset-maintenance/export'),
            'exportEnabled' => rateb_can_export_entity('asset-maintenance'),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('asset-maintenance');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('asset-maintenance'));
        }
        $id = $this->svc->createMaintenance([
            'asset_id' => (int) $this->input('asset_id', 0),
            'maintenance_type' => (string) $this->input('maintenance_type', 'general'),
            'scheduled_date' => (string) $this->input('scheduled_date', ''),
            'completed_date' => (string) $this->input('completed_date', ''),
            'cost' => (float) $this->input('cost', 0),
            'status' => (string) $this->input('status', 'scheduled'),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
        (new AuditService())->log('create', 'asset_maintenance', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('asset-maintenance'));
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new WorkflowRecordService();
        $cols = $svc->exportColumns($this->workflowColumns());
        ExportController::send(
            'asset_maintenance',
            $cols,
            $svc->rowsForExport((new AssetDeviceWorkflowService())->listAssetMaintenance(), $cols),
            __('asset_maintenance'),
            'asset-maintenance'
        );
    }
}

final class AssetAssignmentsController extends Controller
{
    use WorkflowOpsTrait;
    use WorkflowDocumentTrait;

    protected function workflowSlug(): string { return 'asset-assignments'; }
    protected function workflowRedirect(): void { $this->redirect(rateb_app_url('asset-assignments')); }
    protected function workflowTitle(): string { return __('asset_assignments'); }

    /** @return array<int, array{name:string,label:string,type?:string}> */
    protected function workflowColumns(): array
    {
        return [
            ['name' => 'assignment_no', 'label' => 'record_id', 'type' => 'id'],
            ['name' => 'asset_name', 'label' => 'assets'],
            ['name' => 'assigned_to', 'label' => 'assigned_to'],
            ['name' => 'department', 'label' => 'department'],
            ['name' => 'assigned_at', 'label' => 'assigned_at'],
            ['name' => 'returned_at', 'label' => 'returned_at'],
            ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'notes'],
        ];
    }

    /** @return array<string, mixed>|null */
    protected function workflowFind(int $id): ?array
    {
        return (new AssetDeviceWorkflowService())->findAssignment($id);
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new AssetDeviceWorkflowService();
        $this->view('company/asset-assignments/index', [
            'title' => __('asset_assignments'),
            'items' => $svc->listAssignments(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('asset-assignments'),
            'canApprove' => rateb_can_manage_entity('asset-assignments'),
            'exportRoute' => rateb_app_url('asset-assignments/export'),
            'exportEnabled' => rateb_can_export_entity('asset-assignments'),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('asset-assignments');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('asset-assignments'));
        }
        $assignedTo = trim((string) $this->input('assigned_to', ''));
        if ($assignedTo !== '' && ctype_digit($assignedTo)) {
            $user = (new \Rateb\App\Models\User())->find((int) $assignedTo);
            if ($user) {
                $assignedTo = trim((string) ($user['name'] ?? '')) ?: $assignedTo;
            }
        }
        $id = (new AssetDeviceWorkflowService())->createAssignment([
            'asset_id' => (int) $this->input('asset_id', 0),
            'assigned_to' => $assignedTo,
            'department' => trim((string) $this->input('department', '')),
            'assigned_at' => (string) $this->input('assigned_at', date('Y-m-d')),
            'returned_at' => trim((string) $this->input('returned_at', '')),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
        (new AuditService())->log('create', 'asset_assignment', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('asset-assignments'));
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new WorkflowRecordService();
        $cols = $svc->exportColumns($this->workflowColumns());
        ExportController::send(
            'asset_assignments',
            $cols,
            $svc->rowsForExport((new AssetDeviceWorkflowService())->listAssignments(), $cols),
            __('asset_assignments'),
            'asset-assignments'
        );
    }
}

final class AssetDepreciationController extends Controller
{
    use WorkflowOpsTrait;

    protected function workflowSlug(): string { return 'asset-depreciation'; }
    protected function workflowRedirect(): void { $this->redirect(rateb_app_url('asset-depreciation')); }

    /** @return array<string, mixed> */
    private function depreciationFilters(): array
    {
        return [
            'asset_id' => (int) $this->input('asset_id', 0),
            'status' => trim((string) $this->input('status', '')),
            'date_from' => trim((string) $this->input('date_from', '')),
            'date_to' => trim((string) $this->input('date_to', '')),
            'company_id' => (int) $this->input('company_id', 0),
        ];
    }

    private function ensureOpsCompany(): int
    {
        rateb_bootstrap_ops_tenant();
        $id = function_exists('rateb_resolve_ops_company_id')
            ? rateb_resolve_ops_company_id()
            : (int) SessionManager::get('rateb_company_id', 0);
        if ($id > 0) {
            TenantContext::setCompanyId($id);
        }
        return $id;
    }

    private function requireOpsCompanyForWrite(): int
    {
        $id = $this->ensureOpsCompany();
        if ($id < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        return $id;
    }

    /** @return array<int, array{name:string,label:string,type?:string,header_label?:string}> */
    private function depreciationColumns(): array
    {
        return [
            ['name' => 'depreciation_no', 'label' => 'depreciation_no', 'type' => 'id'],
            ['name' => 'asset_name', 'label' => 'assets'],
            ['name' => 'period_date', 'label' => 'depreciation_date'],
            ['name' => 'amount', 'label' => 'depreciation_amount', 'type' => 'money'],
            ['name' => 'book_value_before', 'label' => 'book_value_before', 'header_label' => 'depreciation_book_before', 'type' => 'money'],
            ['name' => 'book_value', 'label' => 'book_value_after', 'header_label' => 'depreciation_book_after', 'type' => 'money'],
            ['name' => 'accumulated_total', 'label' => 'accumulated_depreciation', 'header_label' => 'accumulated_depreciation_short', 'type' => 'money'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
    }

    /** @return array<string, mixed> */
    private function depreciationInput(): array
    {
        return [
            'asset_id' => (int) $this->input('asset_id', 0),
            'period_date' => (string) $this->input('period_date', date('Y-m-d')),
            'amount' => (float) $this->input('amount', 0),
            'depreciation_type' => (string) $this->input('depreciation_type', 'monthly'),
            'depreciation_rate' => $this->input('depreciation_rate', ''),
            'useful_life_months' => (int) $this->input('useful_life_months', 0),
            'residual_value' => (float) $this->input('residual_value', 0),
            'cost_center_id' => (int) $this->input('cost_center_id', 0),
            'notes' => trim((string) $this->input('notes', '')),
        ];
    }

    private function saveDepreciationAttachment(int $companyId, int $depreciationId): bool
    {
        if ($companyId < 1 || $depreciationId < 1) {
            return true;
        }
        $upload = \Rateb\App\Helpers\EntityAttachment::handleOptionalFile(
            'entity_attachment',
            $companyId,
            'asset_depreciation',
            $depreciationId,
            __('attach_document')
        );
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
            return false;
        }
        return true;
    }

    public function index(): void
    {
        $this->ensureOpsCompany();
        $filters = $this->depreciationFilters();
        $lookup = new FormLookupService();
        $svc = new AssetDeviceWorkflowService();
        $companyId = $this->ensureOpsCompany();
        $this->view('company/asset-depreciation/index', [
            'title' => __('asset_depreciation'),
            'items' => $svc->listDepreciation($filters),
            'filters' => $filters,
            'summary' => $svc->depreciationSummary($companyId > 0 ? $companyId : null),
            'assetOptions' => $lookup->forFields([['lookup' => 'assets']])['assets'] ?? [],
            'costCenterOptions' => $lookup->forFields([['lookup' => 'cost_centers']])['cost_centers'] ?? [],
            'assetBookValues' => $svc->assetBookValueMap(),
            'assetAccumulated' => $svc->assetAccumulatedMap($companyId > 0 ? $companyId : null),
            'statusOptions' => [
                ['value' => 'draft', 'label' => __('depreciation_status_draft')],
                ['value' => 'approved', 'label' => __('depreciation_status_approved')],
            ],
            'depreciationTypes' => [
                ['value' => 'monthly', 'label' => __('depreciation_type_monthly')],
                ['value' => 'annual', 'label' => __('depreciation_type_annual')],
                ['value' => 'straight_line', 'label' => __('depreciation_type_straight_line')],
            ],
            'columns' => $this->depreciationColumns(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('asset-depreciation'),
            'exportRoute' => rateb_app_url('asset-depreciation/export'),
            'exportEnabled' => rateb_can_export_entity('asset-depreciation'),
            'assetJs' => rateb_asset('js/asset-depreciation.js'),
            'assetCss' => rateb_asset('css/asset-depreciation.css'),
        ], 'main');
    }

    public function show(array $params): void
    {
        $this->ensureOpsCompany();
        $id = (int) ($params['id'] ?? 0);
        $item = (new AssetDeviceWorkflowService())->findDepreciation($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $this->view('company/asset-depreciation/show', [
            'title' => __('asset_depreciation') . ' — ' . ($item['depreciation_no'] ?? ''),
            'item' => $item,
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('asset-depreciation'),
        ], 'main');
    }

    public function edit(array $params): void
    {
        rateb_require_manage('asset-depreciation');
        $this->ensureOpsCompany();
        $id = (int) ($params['id'] ?? 0);
        $item = (new AssetDeviceWorkflowService())->findDepreciation($id);
        if (!$item || (string) ($item['status'] ?? '') !== 'draft') {
            SessionManager::flash('error', __('depreciation_edit_denied'));
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        $lookup = new FormLookupService();
        $svc = new AssetDeviceWorkflowService();
        $companyId = $this->ensureOpsCompany();
        $this->view('company/asset-depreciation/form', [
            'title' => __('edit') . ' ' . __('asset_depreciation'),
            'item' => $item,
            'formFields' => FormLookupService::assetDepreciationFormFields(true),
            'lookups' => $lookup->forFields(FormLookupService::assetDepreciationFormFields(true)),
            'assetBookValues' => $svc->assetBookValueMap(),
            'companyId' => $companyId,
            'assetJs' => rateb_asset('js/asset-depreciation.js'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('asset-depreciation');
        $companyId = $this->requireOpsCompanyForWrite();
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        $amount = (float) $this->input('amount', 0);
        if ($amount <= 0) {
            SessionManager::flash('error', __('depreciation_amount_required'));
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        $id = (new AssetDeviceWorkflowService())->recordDepreciation($this->depreciationInput());
        if (!$this->saveDepreciationAttachment($companyId, $id)) {
            $this->redirect(rateb_app_url('asset-depreciation/' . $id . '/edit'));
        }
        (new AuditService())->log('create', 'asset_depreciation', $id);
        SessionManager::flash('success', __('depreciation_saved_draft'));
        $this->redirect(rateb_app_url('asset-depreciation'));
    }

    public function update(array $params): void
    {
        rateb_require_manage('asset-depreciation');
        $companyId = $this->requireOpsCompanyForWrite();
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        $id = (int) ($params['id'] ?? 0);
        $amount = (float) $this->input('amount', 0);
        if ($amount <= 0) {
            SessionManager::flash('error', __('depreciation_amount_required'));
            $this->redirect(rateb_app_url('asset-depreciation/' . $id . '/edit'));
        }
        $ok = (new AssetDeviceWorkflowService())->updateDepreciation($id, $this->depreciationInput());
        if (!$ok) {
            SessionManager::flash('error', __('depreciation_edit_denied'));
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        if (!$this->saveDepreciationAttachment($companyId, $id)) {
            $this->redirect(rateb_app_url('asset-depreciation/' . $id . '/edit'));
        }
        (new AuditService())->log('update', 'asset_depreciation', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('asset-depreciation'));
    }

    public function approve(array $params): void
    {
        rateb_require_manage('asset-depreciation');
        $this->requireOpsCompanyForWrite();
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        $id = (int) ($params['id'] ?? 0);
        if (!(new AssetDeviceWorkflowService())->approveDepreciation($id)) {
            SessionManager::flash('error', __('depreciation_approve_denied'));
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        (new AuditService())->log('approve', 'asset_depreciation', $id);
        SessionManager::flash('success', __('depreciation_approved_ok'));
        $this->redirect(rateb_app_url('asset-depreciation'));
    }

    public function export(): void
    {
        $this->ensureOpsCompany();
        $filters = $this->depreciationFilters();
        $rows = (new AssetDeviceWorkflowService())->listDepreciation($filters);
        foreach ($rows as &$row) {
            $row['book_value_after'] = $row['book_value'] ?? 0;
            $row['status'] = __((string) ($row['status'] ?? 'draft'));
        }
        unset($row);
        ExportController::send('asset_depreciation', [
            ['name' => 'depreciation_no', 'label' => __('depreciation_no')],
            ['name' => 'asset_name', 'label' => __('assets')],
            ['name' => 'period_date', 'label' => __('depreciation_date')],
            ['name' => 'amount', 'label' => __('depreciation_amount'), 'type' => 'money'],
            ['name' => 'book_value_before', 'label' => __('book_value_before'), 'type' => 'money'],
            ['name' => 'book_value_after', 'label' => __('book_value_after'), 'type' => 'money'],
            ['name' => 'accumulated_total', 'label' => __('accumulated_depreciation'), 'type' => 'money'],
            ['name' => 'status', 'label' => __('status')],
        ], $rows, __('asset_depreciation'), 'asset-depreciation');
    }
}

final class DeviceMaintenanceController extends Controller
{
    use WorkflowOpsTrait;
    use WorkflowDocumentTrait;

    protected function workflowSlug(): string { return 'device-maintenance'; }
    protected function workflowRedirect(): void { $this->redirect(rateb_app_url('device-maintenance')); }
    protected function workflowTitle(): string { return __('device_maintenance'); }

    /** @return array<int, array{name:string,label:string,type?:string}> */
    protected function workflowColumns(): array
    {
        return [
            ['name' => 'service_no', 'label' => 'record_id', 'type' => 'id'],
            ['name' => 'device_name', 'label' => 'medical_devices'],
            ['name' => 'service_date', 'label' => 'service_date'],
            ['name' => 'service_type', 'label' => 'service_type'],
            ['name' => 'provider', 'label' => 'provider'],
            ['name' => 'cost', 'label' => 'cost', 'type' => 'money'],
            ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'notes'],
        ];
    }

    /** @return array<string, mixed>|null */
    protected function workflowFind(int $id): ?array
    {
        return (new AssetDeviceWorkflowService())->findDeviceService($id);
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $this->view('company/device-maintenance/index', [
            'title' => __('device_maintenance'),
            'items' => (new AssetDeviceWorkflowService())->listDeviceService(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('device-maintenance'),
            'canApprove' => rateb_can_manage_entity('device-maintenance'),
            'exportRoute' => rateb_app_url('device-maintenance/export'),
            'exportEnabled' => rateb_can_export_entity('device-maintenance'),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('device-maintenance');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('device-maintenance'));
        }
        (new AssetDeviceWorkflowService())->createMaintenance([
            'device_id' => (int) $this->input('device_id', 0),
            'service_date' => (string) $this->input('service_date', date('Y-m-d')),
            'service_type' => (string) $this->input('service_type', 'maintenance'),
            'provider' => trim((string) $this->input('provider', '')),
            'cost' => (float) $this->input('cost', 0),
            'notes' => trim((string) $this->input('notes', '')),
        ], 'rateb_device_service_history');
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('device-maintenance'));
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new WorkflowRecordService();
        $cols = $svc->exportColumns($this->workflowColumns());
        ExportController::send(
            'device_maintenance',
            $cols,
            $svc->rowsForExport((new AssetDeviceWorkflowService())->listDeviceService(), $cols),
            __('device_maintenance'),
            'device-maintenance'
        );
    }
}

final class DeviceSparePartsController extends Controller
{
    use WorkflowOpsTrait;
    use WorkflowDocumentTrait;

    protected function workflowSlug(): string { return 'device-spare-parts'; }
    protected function workflowRedirect(): void { $this->redirect(rateb_app_url('device-spare-parts')); }
    protected function workflowTitle(): string { return __('device_spare_parts'); }

    /** @return array<int, array{name:string,label:string,type?:string}> */
    protected function workflowColumns(): array
    {
        return [
            ['name' => 'part_no', 'label' => 'record_id', 'type' => 'id'],
            ['name' => 'device_name', 'label' => 'medical_devices'],
            ['name' => 'part_name', 'label' => 'part_name'],
            ['name' => 'quantity', 'label' => 'quantity'],
            ['name' => 'reorder_level', 'label' => 'reorder_level'],
            ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
        ];
    }

    /** @return array<string, mixed>|null */
    protected function workflowFind(int $id): ?array
    {
        return (new AssetDeviceWorkflowService())->findSparePart($id);
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $this->view('company/device-spare-parts/index', [
            'title' => __('device_spare_parts'),
            'items' => (new AssetDeviceWorkflowService())->listSpareParts(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('device-spare-parts'),
            'canApprove' => rateb_can_manage_entity('device-spare-parts'),
            'exportRoute' => rateb_app_url('device-spare-parts/export'),
            'exportEnabled' => rateb_can_export_entity('device-spare-parts'),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('device-spare-parts');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('device-spare-parts'));
        }
        (new AssetDeviceWorkflowService())->createSparePart([
            'device_id' => (int) $this->input('device_id', 0),
            'part_name' => trim((string) $this->input('part_name', '')),
            'part_no' => trim((string) $this->input('part_no', '')),
            'quantity' => (float) $this->input('quantity', 0),
            'reorder_level' => (float) $this->input('reorder_level', 0),
        ]);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('device-spare-parts'));
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new WorkflowRecordService();
        $cols = $svc->exportColumns($this->workflowColumns());
        ExportController::send(
            'device_spare_parts',
            $cols,
            $svc->rowsForExport((new AssetDeviceWorkflowService())->listSpareParts(), $cols),
            __('device_spare_parts'),
            'device-spare-parts'
        );
    }
}

final class DeviceWarrantyController extends Controller
{
    use WorkflowDocumentTrait;

    protected function workflowSlug(): string { return 'device-warranty'; }
    protected function workflowRedirect(): void { $this->redirect(rateb_app_url('device-warranty')); }
    protected function workflowTitle(): string { return __('device_warranty'); }
    protected function workflowCanApprove(): bool { return false; }

    /** @return array<int, array{name:string,label:string,type?:string}> */
    protected function workflowColumns(): array
    {
        return [
            ['name' => 'device_name', 'label' => 'medical_devices'],
            ['name' => 'serial_no', 'label' => 'serial_no'],
            ['name' => 'manufacturer', 'label' => 'manufacturer'],
            ['name' => 'warranty_expiry', 'label' => 'warranty_expiry'],
            ['name' => 'maintenance_due', 'label' => 'maintenance_due'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
    }

    /** @return array<string, mixed>|null */
    protected function workflowFind(int $id): ?array
    {
        return (new AssetDeviceWorkflowService())->findMedicalDevice($id);
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new AssetDeviceWorkflowService();
        $devices = (new \Rateb\App\Models\MedicalDevice())->all(200, 0);
        $this->view('company/device-warranty/index', [
            'title' => __('device_warranty'),
            'devices' => $devices,
            'items' => $devices,
            'due' => $svc->devicesWarrantyDue(90),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('device-warranty'),
            'exportRoute' => rateb_app_url('device-warranty/export'),
            'exportEnabled' => rateb_can_export_entity('device-warranty'),
        ], 'main');
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $svc = new WorkflowRecordService();
        $cols = $svc->exportColumns($this->workflowColumns());
        ExportController::send(
            'device_warranty',
            $cols,
            $svc->rowsForExport((new \Rateb\App\Models\MedicalDevice())->all(500, 0), $cols),
            __('device_warranty'),
            'device-warranty'
        );
    }

    public function update(array $params): void
    {
        rateb_require_manage('device-warranty');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('device-warranty'));
        }
        $id = (int) ($params['id'] ?? 0);
        (new AssetDeviceWorkflowService())->updateDeviceWarranty($id, (string) $this->input('warranty_expiry', ''));
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('device-warranty'));
    }
}

final class AnalyticsReportsController extends Controller
{
    private function companyId(): int
    {
        rateb_bootstrap_ops_tenant();
        $id = function_exists('rateb_resolve_ops_company_id')
            ? rateb_resolve_ops_company_id()
            : (int) SessionManager::get('rateb_company_id');
        if ($id > 0) {
            TenantContext::setCompanyId($id);
        }
        return $id;
    }

    public function procurement(): void
    {
        $cid = $this->companyId();
        $this->view('company/reports/procurement', [
            'title' => __('procurement_analytics'),
            'data' => (new ErpAnalyticsService())->procurementDashboard($cid),
            'exportRoute' => rateb_app_url('reports/procurement/export'),
            'exportEnabled' => rateb_can_export_entity('reports/procurement'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function kpi(): void
    {
        $cid = $this->companyId();
        $this->view('company/reports/kpi', [
            'title' => __('company_kpi'),
            'data' => (new ErpAnalyticsService())->companyKpi($cid),
            'exportRoute' => rateb_app_url('reports/kpi/export'),
            'exportEnabled' => rateb_can_export_entity('reports/kpi'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function costAnalysis(): void
    {
        $cid = $this->companyId();
        $this->view('company/reports/cost-analysis', [
            'title' => __('cost_analysis'),
            'data' => (new ErpAnalyticsService())->costAnalysis($cid),
            'exportRoute' => rateb_app_url('reports/cost-analysis/export'),
            'exportEnabled' => rateb_can_export_entity('reports/cost-analysis'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function supplierPerformance(): void
    {
        $cid = $this->companyId();
        $this->view('company/reports/supplier-performance', [
            'title' => __('supplier_performance_report'),
            'rows' => (new ErpAnalyticsService())->supplierPerformance($cid),
            'exportRoute' => rateb_app_url('reports/supplier-performance/export'),
            'exportEnabled' => rateb_can_export_entity('reports/supplier-performance'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function inventoryValuation(): void
    {
        $this->companyId();
        $val = (new InventoryWorkflowService())->valuationReport();
        $this->view('company/reports/inventory-valuation', [
            'title' => __('inventory_valuation_report'),
            'rows' => $val['rows'],
            'total_value' => $val['total_value'],
            'exportRoute' => rateb_app_url('reports/inventory-valuation/export'),
            'exportEnabled' => rateb_can_export_entity('reports/inventory-valuation'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function exportProcurement(): void
    {
        $d = (new ErpAnalyticsService())->procurementDashboard($this->companyId());
        ExportController::send('procurement_analytics', [
            ['name' => 'month', 'label' => __('month')],
            ['name' => 'c', 'label' => __('count')],
            ['name' => 'total', 'label' => __('total')],
        ], $d['po_monthly'] ?? [], __('procurement_analytics'), 'reports/procurement');
    }

    public function exportKpi(): void
    {
        $d = (new ErpAnalyticsService())->companyKpi($this->companyId());
        $rows = [];
        foreach ($d as $k => $v) {
            if (!is_array($v)) {
                $rows[] = ['metric' => $k, 'value' => $v];
            }
        }
        ExportController::send('company_kpi', [
            ['name' => 'metric', 'label' => __('metric')],
            ['name' => 'value', 'label' => __('value')],
        ], $rows, __('company_kpi'), 'reports/kpi');
    }

    public function exportCost(): void
    {
        $d = (new ErpAnalyticsService())->costAnalysis($this->companyId());
        ExportController::send('cost_analysis', [
            ['name' => 'supplier_name', 'label' => __('suppliers')],
            ['name' => 'total', 'label' => __('total')],
        ], $d['po_by_supplier'] ?? [], __('cost_analysis'), 'reports/cost-analysis');
    }

    public function exportSupplierPerformance(): void
    {
        ExportController::send('supplier_performance', [
            ['name' => 'code', 'label' => __('record_id')],
            ['name' => 'name', 'label' => __('suppliers')],
            ['name' => 'classification_name', 'label' => __('supplier_classifications')],
            ['name' => 'rating', 'label' => __('rating')],
            ['name' => 'avg_eval', 'label' => __('overall_score')],
            ['name' => 'po_count', 'label' => __('purchase_orders')],
            ['name' => 'performance_kpi', 'label' => __('performance_kpi')],
        ], (new ErpAnalyticsService())->supplierPerformance($this->companyId()), __('supplier_performance_report'), 'reports/supplier-performance');
    }

    public function exportInventoryValuation(): void
    {
        $val = (new InventoryWorkflowService())->valuationReport();
        ExportController::send('inventory_valuation', [
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'unit_cost', 'label' => __('unit_price')],
            ['name' => 'line_value', 'label' => __('value')],
        ], $val['rows'], __('inventory_valuation_report'), 'reports/inventory-valuation');
    }
}

final class WarehouseTransfersController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $cid = function_exists('rateb_resolve_ops_company_id')
            ? rateb_resolve_ops_company_id()
            : (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
        if ($cid < 1) {
            $cid = (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
        }
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT t.*, i.item_name, sw.name AS source_name, dw.name AS dest_name
             FROM rateb_warehouse_transfers t
             JOIN rateb_inventory i ON i.id = t.inventory_id
             JOIN rateb_warehouses sw ON sw.id = t.source_warehouse_id
             JOIN rateb_warehouses dw ON dw.id = t.destination_warehouse_id
             WHERE t.company_id = :cid ORDER BY ' . rateb_list_order_sql('t') . ' LIMIT 100'
        );
        $stmt->execute(['cid' => $cid]);
        $this->view('company/warehouse-transfers/index', [
            'title' => __('warehouse_transfers'),
            'items' => $stmt->fetchAll(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('warehouse-transfers'),
            'exportRoute' => rateb_app_url('warehouse-transfers/export'),
            'exportEnabled' => rateb_can_export_entity('warehouse-transfers'),
        ], 'main');
    }

    public function create(): void
    {
        rateb_bootstrap_ops_tenant();
        $formFields = \Rateb\App\Services\FormLookupService::warehouseTransferFormFields();
        $lookups = (new \Rateb\App\Services\FormLookupService())->forFields($formFields);
        $this->view('company/warehouse-transfers/form', [
            'title' => __('create') . ' ' . __('warehouse_transfers'),
            'formFields' => $formFields,
            'lookups' => $lookups,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('warehouse-transfers');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('warehouse-transfers'));
        }
        try {
            $id = (new InventoryWorkflowService())->createTransfer([
                'source_warehouse_id' => (int) $this->input('source_warehouse_id', 0),
                'destination_warehouse_id' => (int) $this->input('destination_warehouse_id', 0),
                'inventory_id' => (int) $this->input('inventory_id', 0),
                'quantity' => (float) $this->input('quantity', 0),
                'notes' => trim((string) $this->input('notes', '')),
            ]);
            (new AuditService())->log('create', 'warehouse_transfer', $id);
            SessionManager::flash('success', __('save') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('warehouse-transfers'));
    }

    public function approve(array $params): void
    {
        rateb_require_manage('warehouse-transfers');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('warehouse-transfers'));
        }
        $id = (int) ($params['id'] ?? 0);
        if (!(new InventoryWorkflowService())->approveTransfer($id)) {
            SessionManager::flash('error', __('invalid_request'));
        } else {
            SessionManager::flash('success', __('transfer_completed'));
        }
        $this->redirect(rateb_app_url('warehouse-transfers'));
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $cid = function_exists('rateb_resolve_ops_company_id')
            ? rateb_resolve_ops_company_id()
            : (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT t.transfer_no, i.item_name, sw.name AS source_name, dw.name AS dest_name,
                    t.quantity, t.status, t.notes, t.created_at
             FROM rateb_warehouse_transfers t
             JOIN rateb_inventory i ON i.id = t.inventory_id
             JOIN rateb_warehouses sw ON sw.id = t.source_warehouse_id
             JOIN rateb_warehouses dw ON dw.id = t.destination_warehouse_id
             WHERE t.company_id = :cid ORDER BY ' . rateb_list_order_sql('t') . ' LIMIT 500'
        );
        $stmt->execute(['cid' => $cid]);
        ExportController::send('warehouse_transfers', [
            ['name' => 'transfer_no', 'label' => __('record_id')],
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'source_name', 'label' => __('from')],
            ['name' => 'dest_name', 'label' => __('to')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'status', 'label' => __('status')],
            ['name' => 'notes', 'label' => __('notes')],
            ['name' => 'created_at', 'label' => __('created_at')],
        ], $stmt->fetchAll(), __('warehouse_transfers'), 'warehouse-transfers');
    }
}

final class InventoryForecastController extends Controller
{
    public function index(): void
    {
        $rows = (new InventoryWorkflowService())->reorderSuggestions();
        $this->view('company/inventory-forecast/index', [
            'title' => __('inventory_forecast'),
            'rows' => $rows,
        ], 'main');
    }
}

final class SupplierCommsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\SupplierCommunication();
        $this->viewPrefix = 'company/supplier-comms';
        $this->routePrefix = rateb_app_route('supplier-comms');
        $this->entityName = 'supplier_comms';
        $this->permissionResource = 'supplier-comms';
        $this->documentEntityType = 'supplier_communication';
        $this->indexFields = [
            ['name' => 'supplier_name', 'label' => 'suppliers'],
            ['name' => 'channel', 'label' => 'comm_channel', 'type' => 'channel'],
            ['name' => 'subject', 'label' => 'subject'],
            ['name' => 'comm_date_display', 'label' => 'comm_date', 'type' => 'clip'],
            ['name' => 'comm_status', 'label' => 'comm_status', 'type' => 'status'],
            ['name' => 'follow_up_date', 'label' => 'follow_up_date', 'type' => 'clip'],
        ];
        $this->fields = [
            ['name' => 'supplier_id', 'label' => 'suppliers', 'type' => 'fk', 'lookup' => 'suppliers', 'required' => true, 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'channel', 'label' => 'comm_channel', 'type' => 'select', 'lookup' => 'communication_types', 'required' => true, 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'comm_date', 'label' => 'comm_date', 'type' => 'date', 'default' => date('Y-m-d'), 'required' => true, 'col' => 'col-xl-2 col-md-4'],
            ['name' => 'comm_time', 'label' => 'comm_time', 'type' => 'time', 'col' => 'col-xl-2 col-md-4'],
            ['name' => 'comm_status', 'label' => 'comm_status', 'type' => 'select', 'lookup' => 'comm_statuses', 'default' => 'new', 'col' => 'col-xl-2 col-md-4'],
            ['name' => 'subject', 'label' => 'subject', 'type' => 'text', 'required' => true, 'col' => 'col-xl-6 col-md-8'],
            ['name' => 'details', 'label' => 'comm_details', 'type' => 'text', 'col' => 'col-xl-6 col-md-4'],
            ['name' => 'body', 'label' => 'comm_message', 'type' => 'textarea', 'required' => true, 'col' => 'col-12', 'rows' => 3],
            ['name' => 'responsible_name', 'label' => 'comm_responsible', 'type' => 'text', 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'supplier_contact', 'label' => 'comm_supplier_contact', 'type' => 'text', 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'supplier_phone', 'label' => 'comm_supplier_phone', 'type' => 'text', 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'supplier_email', 'label' => 'comm_supplier_email', 'type' => 'text', 'col' => 'col-xl-3 col-md-6', 'attrs' => ['placeholder' => 'supplier@company.com', 'inputmode' => 'email', 'autocomplete' => 'email']],
            ['name' => 'follow_up_date', 'label' => 'follow_up_date', 'type' => 'date', 'col' => 'col-xl-3 col-md-4'],
            ['name' => 'follow_up_priority', 'label' => 'follow_up_priority', 'type' => 'select', 'lookup' => 'follow_up_priorities', 'default' => 'medium', 'col' => 'col-xl-3 col-md-4'],
            ['name' => 'purchase_order_id', 'label' => 'link_purchase_order', 'type' => 'fk', 'lookup' => 'purchase_orders', 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'rfq_id', 'label' => 'link_rfq', 'type' => 'fk', 'lookup' => 'rfq', 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'response_rating', 'label' => 'comm_response_rating', 'type' => 'select', 'lookup' => 'comm_response_ratings', 'col' => 'col-xl-3 col-md-6'],
            ['name' => 'response_notes', 'label' => 'comm_response_notes', 'type' => 'textarea', 'col' => 'col-xl-9 col-md-12', 'rows' => 2],
        ];
        $this->tenantForeignKeys = ['supplier_id', 'purchase_order_id', 'rfq_id'];
    }

    protected function layout(): string
    {
        return 'main';
    }

    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));
        $filters = [
            'supplier_id' => max(0, (int) $this->input('supplier_id', 0)),
            'channel' => trim((string) $this->input('channel', '')),
            'comm_status' => trim((string) $this->input('comm_status', '')),
            'date_from' => trim((string) $this->input('date_from', '')),
            'date_to' => trim((string) $this->input('date_to', '')),
            'show_archived' => (int) $this->input('show_archived', 0) === 1,
            'q' => $search,
        ];
        $companyId = rateb_resolve_ops_company_id();
        TenantContext::setCompanyId($companyId);
        $svc = new \Rateb\App\Services\SupplierCommService();
        $lookups = (new \Rateb\App\Services\FormLookupService())->forFields($this->fields);
        $supplierOptions = $lookups['suppliers'] ?? [];
        $channelOptions = $lookups['communication_types'] ?? [];
        $statusOptions = $lookups['comm_statuses'] ?? [];

        $sql = 'SELECT c.*, s.name AS supplier_name,
                       po.order_no AS po_no, r.rfq_no
                FROM rateb_supplier_communications c
                LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
                LEFT JOIN rateb_purchase_orders po ON po.id = c.purchase_order_id
                LEFT JOIN rateb_rfq r ON r.id = c.rfq_id
                WHERE c.company_id = :cid';
        $params = ['cid' => $companyId];
        $countSql = 'SELECT COUNT(*) AS c FROM rateb_supplier_communications c
                     LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
                     WHERE c.company_id = :cid';
        $countParams = ['cid' => $companyId];

        if (!$filters['show_archived']) {
            $sql .= ' AND c.is_archived = 0';
            $countSql .= ' AND c.is_archived = 0';
        }
        if ($search !== '') {
            $sql .= ' AND (s.name LIKE :q OR c.subject LIKE :q OR c.body LIKE :q OR c.details LIKE :q OR c.channel LIKE :q)';
            $countSql .= ' AND (s.name LIKE :q OR c.subject LIKE :q OR c.body LIKE :q OR c.details LIKE :q OR c.channel LIKE :q)';
            $params['q'] = '%' . $search . '%';
            $countParams['q'] = '%' . $search . '%';
        }
        if ($filters['supplier_id'] > 0) {
            $sql .= ' AND c.supplier_id = :sid';
            $countSql .= ' AND c.supplier_id = :sid';
            $params['sid'] = $filters['supplier_id'];
            $countParams['sid'] = $filters['supplier_id'];
        }
        if ($filters['channel'] !== '') {
            $sql .= ' AND c.channel = :ch';
            $countSql .= ' AND c.channel = :ch';
            $params['ch'] = $filters['channel'];
            $countParams['ch'] = $filters['channel'];
        }
        if ($filters['comm_status'] !== '') {
            $sql .= ' AND c.comm_status = :cst';
            $countSql .= ' AND c.comm_status = :cst';
            $params['cst'] = $filters['comm_status'];
            $countParams['cst'] = $filters['comm_status'];
        }
        if ($filters['date_from'] !== '') {
            $sql .= ' AND COALESCE(c.comm_date, DATE(c.created_at)) >= :df';
            $countSql .= ' AND COALESCE(c.comm_date, DATE(c.created_at)) >= :df';
            $params['df'] = $filters['date_from'];
            $countParams['df'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $sql .= ' AND COALESCE(c.comm_date, DATE(c.created_at)) <= :dt';
            $countSql .= ' AND COALESCE(c.comm_date, DATE(c.created_at)) <= :dt';
            $params['dt'] = $filters['date_to'];
            $countParams['dt'] = $filters['date_to'];
        }

        $total = (int) (($this->model->queryOne($countSql, $countParams)['c'] ?? 0));
        $orderSql = function_exists('rateb_list_order_sql') ? rateb_list_order_sql('c') : 'c.id DESC';
        $sql .= ' ORDER BY ' . $orderSql . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        $items = $this->model->query($sql, $params);
        foreach ($items as &$row) {
            $row['comm_date_display'] = (string) ($row['comm_date'] ?? substr((string) ($row['created_at'] ?? ''), 0, 10));
            $status = (string) ($row['comm_status'] ?? 'new');
            $row['comm_status'] = 'comm_status_' . $status;
        }
        unset($row);

        $supplierFilterId = (int) $filters['supplier_id'];
        $extraLookups = (new \Rateb\App\Services\FormLookupService())->forFields([
            ['lookup' => 'comm_attachment_types'],
        ]);
        $lookups = array_merge($lookups, $extraLookups);
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __('supplier_comms'),
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'filters' => $filters,
            'supplierOptions' => $supplierOptions,
            'channelOptions' => $channelOptions,
            'statusOptions' => $statusOptions,
            'lookups' => $lookups,
            'fields' => $this->fields,
            'columns' => $this->indexFields,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
            'moduleCss' => rateb_asset('css/supplier-comms.css'),
            'moduleJs' => rateb_asset('js/supplier-comm-form.js'),
            'historyUrl' => rateb_app_url('supplier-comms/history'),
            'supplierProfileUrl' => rateb_app_url('supplier-comms/supplier-profile'),
            'responsibleDefault' => (string) (\Rateb\App\Core\Auth::user()['name'] ?? ''),
            'stats' => $svc->companyStats($companyId, $supplierFilterId),
            'upcomingFollowUps' => $svc->upcomingFollowUps($companyId),
            'topSuppliers' => $svc->topSuppliersByComms($companyId),
            'supplierHistory' => $supplierFilterId > 0
                ? $svc->historyForSupplier($companyId, $supplierFilterId)
                : [],
            'commSvc' => $svc,
        ]), $this->layout());
    }

    public function supplierHistory(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = rateb_resolve_ops_company_id();
        $supplierId = (int) $this->input('supplier_id', 0);
        $excludeId = (int) $this->input('exclude_id', 0);
        $rows = (new \Rateb\App\Services\SupplierCommService())->historyForSupplier($companyId, $supplierId, $excludeId);
        $out = [];
        foreach ($rows as $row) {
            $status = (string) ($row['comm_status'] ?? 'new');
            $row['comm_status_label'] = __('comm_status_' . $status);
            $out[] = $row;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['rows' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function print(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/print', [
            'title' => __('print') . ' — ' . __('supplier_comms'),
            'item' => $item,
        ], 'print');
    }

    public function archive(array $params): void
    {
        $this->guardManage();
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
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
        $archived = (int) ($item['is_archived'] ?? 0) === 1;
        $this->model->update($id, [
            'is_archived' => $archived ? 0 : 1,
            'archived_at' => $archived ? null : date('Y-m-d H:i:s'),
        ]);
        SessionManager::flash('success', $archived ? __('comm_unarchived') : __('comm_archived'));
        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            $data['company_id'] = $companyId;
        }
        $uid = (int) SessionManager::get('rateb_user_id', 0);
        if ($uid > 0 && empty($data['created_by'])) {
            $data['created_by'] = $uid;
        }
        if (empty($data['responsible_name'])) {
            $user = \Rateb\App\Core\Auth::user();
            $data['responsible_name'] = trim((string) ($user['name'] ?? ''));
        }
        $data['comm_date'] = trim((string) ($data['comm_date'] ?? '')) ?: date('Y-m-d');
        $commTime = trim((string) ($data['comm_time'] ?? ''));
        $data['comm_time'] = $commTime !== '' ? $commTime : null;
        $followUp = trim((string) ($data['follow_up_date'] ?? ''));
        $data['follow_up_date'] = $followUp !== '' ? $followUp : null;
        $data['purchase_order_id'] = (int) ($data['purchase_order_id'] ?? 0) ?: null;
        $data['rfq_id'] = (int) ($data['rfq_id'] ?? 0) ?: null;
        $data['comm_status'] = trim((string) ($data['comm_status'] ?? 'new')) ?: 'new';
        $data['follow_up_priority'] = trim((string) ($data['follow_up_priority'] ?? 'medium')) ?: 'medium';
        $responseRating = trim((string) ($data['response_rating'] ?? ''));
        $data['response_rating'] = $responseRating !== '' ? $responseRating : null;
        $responseNotes = trim((string) ($data['response_notes'] ?? ''));
        $data['response_notes'] = $responseNotes !== '' ? $responseNotes : null;
        if (trim((string) ($data['body'] ?? '')) === '') {
            throw new \RuntimeException(__('comm_message_required'));
        }
        return $data;
    }

    public function store(): void
    {
        $this->guardManage();
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            $data = $this->collectData();
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix));
        }
        $formAction = trim((string) $this->input('form_action', 'save'));
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->ensureTenantCompanyForWrite($data);
        try {
            $id = $this->model->create($data);
            $companyId = (int) ($data['company_id'] ?? 0);
            $this->persistAttachments($id, $companyId);
            $svc = new \Rateb\App\Services\SupplierCommService();
            $svc->logTimeline($id, $companyId, 'created', __('comm_timeline_created'), (string) ($data['subject'] ?? ''));
            if ($formAction === 'save_send') {
                $this->handleSendAction($id, $data, $svc);
            }
            (new AuditService())->log('create', $this->entityName, $id, $data);
            SessionManager::flash('success', __('comm_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $failUrl = rateb_url($this->routePrefix . '/' . $id . '/edit');
        $old = $this->loadRecordForWrite($id);
        if (!$old) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        try {
            $data = $this->collectData();
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect($failUrl);
        }
        $this->inheritTenantFromRecord($data, $old);
        $this->ensureTenantCompanyForWrite($data, $failUrl);
        unset($data['created_by']);
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $formAction = trim((string) $this->input('form_action', 'save'));
        $companyId = (int) ($data['company_id'] ?? TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        try {
            $this->model->update($id, $data);
            $this->persistAttachments($id, $companyId);
            $svc = new \Rateb\App\Services\SupplierCommService();
            $svc->logTimeline($id, $companyId, 'updated', __('comm_timeline_updated'), (string) ($data['subject'] ?? ''));
            if ($formAction === 'save_send') {
                $this->handleSendAction($id, $data, $svc);
            }
            (new AuditService())->log('update', $this->entityName, $id, $data);
            SessionManager::flash('success', __('comm_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
    }

    public function create(): void
    {
        $this->guardManage();
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
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
            'title' => __('create') . ' ' . __('supplier_comms'),
            'item' => null,
        ]), $this->layout());
    }

    protected function formViewData(array $extra = []): array
    {
        $data = parent::formViewData($extra);
        $item = $data['item'] ?? null;
        $companyId = is_array($item) && (int) ($item['company_id'] ?? 0) > 0
            ? (int) $item['company_id']
            : rateb_resolve_ops_company_id();
        $supplierId = is_array($item) ? (int) ($item['supplier_id'] ?? 0) : 0;
        $commId = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
        $svc = new \Rateb\App\Services\SupplierCommService();
        $data['multipart'] = true;
        $data['moduleCss'] = rateb_asset('css/supplier-comms.css');
        $data['moduleJs'] = rateb_asset('js/supplier-comm-form.js');
        $data['historyUrl'] = rateb_app_url('supplier-comms/history');
        $data['responsibleDefault'] = is_array($item) && !empty($item['responsible_name'])
            ? (string) $item['responsible_name']
            : (string) (\Rateb\App\Core\Auth::user()['name'] ?? '');
        $data['supplierHistory'] = $supplierId > 0
            ? $svc->historyForSupplier($companyId, $supplierId, $commId)
            : [];
        $data['commTimeline'] = $commId > 0 ? $svc->timelineForComm($commId, $companyId) : [];
        $data['supplierProfileUrl'] = rateb_app_url('supplier-comms/supplier-profile');
        $data['commSvc'] = $svc;
        $extraLookups = (new \Rateb\App\Services\FormLookupService())->forFields([
            ['lookup' => 'comm_attachment_types'],
        ]);
        $data['lookups'] = array_merge($data['lookups'] ?? [], $extraLookups);
        if (is_array($item) && !empty($item['id'])) {
            $data['existingDocuments'] = (new \Rateb\App\Services\DocumentService())
                ->listForEntity('supplier_communication', (int) $item['id'], $companyId);
        } else {
            $data['existingDocuments'] = [];
        }
        return $data;
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->loadRecordForWrite($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __('supplier_comms'),
            'item' => $item,
        ]), $this->layout());
    }

    public function supplierProfile(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = rateb_resolve_ops_company_id();
        $supplierId = (int) $this->input('supplier_id', 0);
        header('Content-Type: application/json; charset=utf-8');
        $svc = new \Rateb\App\Services\SupplierCommService();
        $profile = $svc->supplierContactProfile($companyId, $supplierId);
        if (!$profile) {
            echo json_encode(['profile' => null], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $poModel = new \Rateb\App\Models\PurchaseOrder();
        $pos = $poModel->query(
            'SELECT id, order_no FROM rateb_purchase_orders WHERE company_id = :cid AND supplier_id = :sid ORDER BY id DESC LIMIT 30',
            ['cid' => $companyId, 'sid' => $supplierId]
        );
        $rfqs = (new \Rateb\App\Models\Rfq())->query(
            'SELECT id, rfq_no FROM rateb_rfq WHERE company_id = :cid ORDER BY id DESC LIMIT 30',
            ['cid' => $companyId]
        );
        echo json_encode([
            'profile' => [
                'name' => (string) ($profile['name'] ?? ''),
                'email' => (string) ($profile['email'] ?? ''),
                'phone' => (string) ($profile['phone'] ?? ''),
                'address' => (string) ($profile['address'] ?? ''),
            ],
            'purchase_orders' => $pos,
            'rfqs' => $rfqs,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @param array<string, mixed> $data */
    private function handleSendAction(int $commId, array $data, \Rateb\App\Services\SupplierCommService $svc): void
    {
        $companyId = (int) ($data['company_id'] ?? rateb_resolve_ops_company_id());
        $channel = (string) ($data['channel'] ?? '');
        $supplierEmail = trim((string) ($data['supplier_email'] ?? ''));
        $hasEmail = $supplierEmail !== '' && \Rateb\App\Helpers\Str::isValidEmail($supplierEmail);
        $user = \Rateb\App\Core\Auth::user();
        $userEmail = trim((string) ($user['email'] ?? ''));

        if ($hasEmail) {
            $result = $svc->sendViaChannel($data, $userEmail, $userEmail);
            $status = (string) ($result['status'] ?? 'failed');
            $this->model->update($commId, [
                'send_status' => $status,
                'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            ]);
            $msg = (string) ($result['message'] ?? '');
            $recipient = (string) ($result['recipient'] ?? $supplierEmail);
            $svc->logTimeline($commId, $companyId, 'email_send', $msg, $recipient);
            if ($result['success'] ?? false) {
                SessionManager::flash('info', $msg);
            } elseif (!empty($result['smtp_config_required'])) {
                $this->model->update($commId, ['send_status' => 'failed']);
                SessionManager::flash('error', $msg);
            } else {
                $this->model->update($commId, ['send_status' => 'failed']);
                SessionManager::flash('error', $msg);
            }
            return;
        }
        if ($channel === 'whatsapp') {
            $phone = preg_replace('/\D+/', '', (string) ($data['supplier_phone'] ?? ''));
            if ($phone !== '') {
                $text = rawurlencode((string) ($data['body'] ?? ''));
                SessionManager::set('rateb_comm_external_url', 'https://wa.me/' . $phone . '?text=' . $text);
                $this->model->update($commId, ['send_status' => 'mailto', 'sent_at' => date('Y-m-d H:i:s')]);
                $svc->logTimeline($commId, $companyId, 'whatsapp', __('comm_whatsapp_opened'), $phone);
                SessionManager::flash('info', __('comm_whatsapp_open'));
            }
            return;
        }
        if ($channel === 'sms') {
            $phone = trim((string) ($data['supplier_phone'] ?? ''));
            if ($phone !== '') {
                $body = rawurlencode((string) ($data['body'] ?? ''));
                SessionManager::set('rateb_comm_external_url', 'sms:' . rawurlencode($phone) . '?body=' . $body);
                $this->model->update($commId, ['send_status' => 'mailto', 'sent_at' => date('Y-m-d H:i:s')]);
                $svc->logTimeline($commId, $companyId, 'sms', __('comm_sms_opened'), $phone);
                SessionManager::flash('info', __('comm_sms_open'));
            }
            return;
        }
        SessionManager::flash('warning', __('comm_save_send_no_email'));
    }

    /** @param array<string, mixed> $data */
    private function buildMailtoUrl(array $data): string
    {
        $email = trim((string) ($data['supplier_email'] ?? ''));
        if ($email === '' || !\Rateb\App\Helpers\Str::isValidEmail($email)) {
            return '';
        }
        $subject = rawurlencode((string) ($data['subject'] ?? ''));
        $body = rawurlencode((string) ($data['body'] ?? ''));
        return 'mailto:' . $email . '?subject=' . $subject . '&body=' . $body;
    }

    private function persistAttachments(int $commId, int $companyId): void
    {
        if ($commId < 1 || $companyId < 1) {
            return;
        }
        $category = trim((string) $this->input('attachment_category', 'other'));
        $categoryLabel = __('comm_attach_' . ($category !== '' ? $category : 'other'));
        if (str_starts_with($categoryLabel, 'comm_attach_')) {
            $categoryLabel = __('comm_attachments');
        }
        $result = \Rateb\App\Helpers\EntityAttachment::handleMultipleFiles(
            'comm_attachments',
            $companyId,
            'supplier_communication',
            $commId,
            5,
            $categoryLabel
        );
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? __('upload_failed')));
        }
        if (($result['uploaded'] ?? 0) > 0) {
            (new \Rateb\App\Services\SupplierCommService())->logTimeline(
                $commId,
                $companyId,
                'attachment',
                __('comm_timeline_attachment', ['count' => (string) ($result['uploaded'] ?? 0)]),
                $categoryLabel
            );
        }
    }
}

