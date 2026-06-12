<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AssetDeviceWorkflowService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\ContractWorkflowService;
use Rateb\App\Services\ErpAnalyticsService;
use Rateb\App\Services\InventoryWorkflowService;
use Rateb\App\Controllers\Shared\ExportController;

final class InventoryBatchesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\InventoryBatch();
        $this->viewPrefix = 'company/inventory-batches';
        $this->routePrefix = rateb_app_route('inventory-batches');
        $this->entityName = 'inventory_batches';
        $this->fields = [
            ['name' => 'inventory_id', 'label' => 'inventory', 'type' => 'number'],
            ['name' => 'batch_no', 'label' => 'batch_no', 'type' => 'text'],
            ['name' => 'quantity', 'label' => 'quantity', 'type' => 'number'],
            ['name' => 'expiry_date', 'label' => 'expiry_date', 'type' => 'date'],
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'number'],
        ];
    }

    protected function layout(): string { return 'main'; }

    public function create(): void
    {
        rateb_require_manage('inventory-batches');
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __('inventory_batches'),
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'inventory' => (new \Rateb\App\Models\Inventory())->all(200, 0),
            'warehouses' => (new \Rateb\App\Models\Warehouse())->all(100, 0),
        ], 'main');
    }

    public function index(): void
    {
        $this->view($this->viewPrefix . '/index', [
            'title' => __('inventory_batches'),
            'items' => (new InventoryWorkflowService())->listBatches(100),
            'csrf' => Csrf::token(),
            'exportRoute' => rateb_app_url('inventory-batches/export'),
            'exportEnabled' => rateb_can_export_entity('inventory-batches'),
            'canManage' => rateb_can_manage_entity('inventory-batches'),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('inventory-batches');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $id = (new InventoryWorkflowService())->createBatch($data);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function export(): void
    {
        ExportController::send('inventory_batches', [
            ['name' => 'batch_no', 'label' => __('batch_no')],
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'expiry_date', 'label' => __('expiry_date')],
        ], (new InventoryWorkflowService())->listBatches(500), __('inventory_batches'));
    }
}

final class InventoryAuditsController extends Controller
{
    public function index(): void
    {
        $model = new \Rateb\App\Models\InventoryAudit();
        $this->view('company/inventory-audits/index', [
            'title' => __('inventory_audits'),
            'items' => $model->all(50, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('inventory-audits'),
        ], 'main');
    }

    public function create(): void
    {
        rateb_require_manage('inventory-audits');
        $this->view('company/inventory-audits/form', [
            'title' => __('create') . ' ' . __('inventory_audits'),
            'auditNo' => (new InventoryWorkflowService())->nextAuditNo(),
            'warehouses' => (new \Rateb\App\Models\Warehouse())->all(100, 0),
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
        $companyId = (int) SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId);
        $this->view('company/supplier-kpi/index', [
            'title' => __('supplier_kpi'),
            'suppliers' => (new ErpAnalyticsService())->supplierPerformance($companyId),
            'csrf' => Csrf::token(),
            'exportRoute' => rateb_app_url('supplier-kpi/export'),
            'exportEnabled' => rateb_can_export_entity('supplier-kpi'),
        ], 'main');
    }

    public function export(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        ExportController::send('supplier_kpi', [
            ['name' => 'name', 'label' => __('suppliers')],
            ['name' => 'rating', 'label' => __('rating')],
            ['name' => 'avg_eval', 'label' => __('overall_score')],
            ['name' => 'po_count', 'label' => __('purchase_orders')],
            ['name' => 'classification_name', 'label' => __('supplier_classifications')],
        ], (new ErpAnalyticsService())->supplierPerformance($companyId), __('supplier_kpi'));
    }
}

final class ContractRenewalsController extends Controller
{
    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId);
        $this->view('company/contract-renewals/index', [
            'title' => __('contract_renewals'),
            'renewals' => (new ContractWorkflowService())->listRenewals(100),
            'expiring' => (new ContractWorkflowService())->expiringContracts(60),
            'contracts' => (new \Rateb\App\Models\Contract())->all(100, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('contract-renewals'),
        ], 'main');
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
            'status' => (string) $this->input('status', 'planned'),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
        (new AuditService())->log('create', 'contract_renewal', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('contract-renewals'));
    }
}

final class AssetMaintenanceController extends Controller
{
    private AssetDeviceWorkflowService $svc;

    public function __construct() { $this->svc = new AssetDeviceWorkflowService(); }

    public function index(): void
    {
        $this->view('company/asset-maintenance/index', [
            'title' => __('asset_maintenance'),
            'items' => $this->svc->listAssetMaintenance(),
            'assets' => (new \Rateb\App\Models\Asset())->all(200, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('asset-maintenance'),
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
            'cost' => (float) $this->input('cost', 0),
            'status' => (string) $this->input('status', 'scheduled'),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
        (new AuditService())->log('create', 'asset_maintenance', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('asset-maintenance'));
    }
}

final class AssetAssignmentsController extends Controller
{
    public function index(): void
    {
        $svc = new AssetDeviceWorkflowService();
        $this->view('company/asset-assignments/index', [
            'title' => __('asset_assignments'),
            'items' => $svc->listAssignments(),
            'assets' => (new \Rateb\App\Models\Asset())->all(200, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('asset-assignments'),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('asset-assignments');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('asset-assignments'));
        }
        $id = (new AssetDeviceWorkflowService())->createAssignment([
            'asset_id' => (int) $this->input('asset_id', 0),
            'assigned_to' => trim((string) $this->input('assigned_to', '')),
            'department' => trim((string) $this->input('department', '')),
            'assigned_at' => (string) $this->input('assigned_at', date('Y-m-d')),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
        (new AuditService())->log('create', 'asset_assignment', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('asset-assignments'));
    }
}

final class AssetDepreciationController extends Controller
{
    public function index(): void
    {
        $this->view('company/asset-depreciation/index', [
            'title' => __('asset_depreciation'),
            'items' => (new AssetDeviceWorkflowService())->listDepreciation(),
            'assets' => (new \Rateb\App\Models\Asset())->all(200, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('asset-depreciation'),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_manage('asset-depreciation');
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('asset-depreciation'));
        }
        $id = (new AssetDeviceWorkflowService())->recordDepreciation([
            'asset_id' => (int) $this->input('asset_id', 0),
            'period_date' => (string) $this->input('period_date', date('Y-m-d')),
            'amount' => (float) $this->input('amount', 0),
            'book_value' => (float) $this->input('book_value', 0),
        ]);
        (new AuditService())->log('create', 'asset_depreciation', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('asset-depreciation'));
    }
}

final class DeviceMaintenanceController extends Controller
{
    public function index(): void
    {
        $this->view('company/device-maintenance/index', [
            'title' => __('device_maintenance'),
            'items' => (new AssetDeviceWorkflowService())->listDeviceService(),
            'devices' => (new \Rateb\App\Models\MedicalDevice())->all(200, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('device-maintenance'),
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
}

final class DeviceSparePartsController extends Controller
{
    public function index(): void
    {
        $this->view('company/device-spare-parts/index', [
            'title' => __('device_spare_parts'),
            'items' => (new AssetDeviceWorkflowService())->listSpareParts(),
            'devices' => (new \Rateb\App\Models\MedicalDevice())->all(200, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('device-spare-parts'),
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
}

final class DeviceWarrantyController extends Controller
{
    public function index(): void
    {
        $this->view('company/device-warranty/index', [
            'title' => __('device_warranty'),
            'devices' => (new \Rateb\App\Models\MedicalDevice())->all(200, 0),
            'due' => (new AssetDeviceWorkflowService())->devicesWarrantyDue(90),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('device-warranty'),
        ], 'main');
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
        $id = (int) SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($id);
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
        ], $d['po_monthly'] ?? [], __('procurement_analytics'));
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
        ], $rows, __('company_kpi'));
    }

    public function exportCost(): void
    {
        $d = (new ErpAnalyticsService())->costAnalysis($this->companyId());
        ExportController::send('cost_analysis', [
            ['name' => 'supplier_name', 'label' => __('suppliers')],
            ['name' => 'total', 'label' => __('total')],
        ], $d['po_by_supplier'] ?? [], __('cost_analysis'));
    }

    public function exportSupplierPerformance(): void
    {
        ExportController::send('supplier_performance', [
            ['name' => 'name', 'label' => __('suppliers')],
            ['name' => 'rating', 'label' => __('rating')],
            ['name' => 'avg_eval', 'label' => __('overall_score')],
            ['name' => 'po_count', 'label' => __('purchase_orders')],
        ], (new ErpAnalyticsService())->supplierPerformance($this->companyId()), __('supplier_performance_report'));
    }

    public function exportInventoryValuation(): void
    {
        $val = (new InventoryWorkflowService())->valuationReport();
        ExportController::send('inventory_valuation', [
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'unit_cost', 'label' => __('unit_price')],
            ['name' => 'line_value', 'label' => __('value')],
        ], $val['rows'], __('inventory_valuation_report'));
    }
}
