<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DocumentService;
use Rateb\App\Services\StockMovementService;
use Rateb\App\Services\WorkflowService;
use Rateb\App\Controllers\Shared\ExportController;

final class StockMovementsController extends Controller
{
    public function index(): void
    {
        $service = new StockMovementService();
        $this->view('company/stock-movements/index', [
            'title' => __('stock_movements'),
            'items' => $service->listRecent(100),
            'inventory' => (new \Rateb\App\Models\Inventory())->all(200, 0),
            'warehouses' => (new \Rateb\App\Models\Warehouse())->all(100, 0),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('stock-movements'),
            'exportEnabled' => rateb_can_export_entity('stock-movements'),
        ], 'main');
    }

    public function store(): void
    {
        if (!rateb_can_manage_entity('stock-movements')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        try {
            $payload = [
                'inventory_id' => (int) $this->input('inventory_id', 0),
                'warehouse_id' => (int) $this->input('warehouse_id', 0) ?: null,
                'movement_type' => (string) $this->input('movement_type', 'in'),
                'quantity' => (float) $this->input('quantity', 0),
                'notes' => trim((string) $this->input('notes', '')),
            ];
            \Rateb\App\Services\TenantFkValidator::validate(
                ['inventory_id' => $payload['inventory_id'], 'warehouse_id' => $payload['warehouse_id'] ?? 0],
                ['inventory_id', 'warehouse_id']
            );
            $id = (new StockMovementService())->record($payload);
            (new AuditService())->log('create', 'stock_movement', $id);
            SessionManager::flash('success', __('save') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('stock-movements'));
    }

    public function export(): void
    {
        $items = (new StockMovementService())->listRecent(500);
        ExportController::send('stock_movements', [
            ['name' => 'movement_no', 'label' => __('movement_no')],
            ['name' => 'movement_type', 'label' => __('movement_type')],
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'created_at', 'label' => __('created_at')],
        ], $items, __('stock_movements'), 'stock-movements');
    }

    public function bulkDestroy(): void
    {
        if (!rateb_can_manage_entity('stock-movements')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        $raw = $this->input('ids', []);
        $ids = is_array($raw)
            ? array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)))
            : [];
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        $deleted = (new \Rateb\App\Models\StockMovement())->deleteMany($ids);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', 'stock_movement', $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        $this->redirect(rateb_app_url('stock-movements'));
    }
}

final class DocumentsController extends Controller
{
    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare('SELECT * FROM rateb_documents WHERE company_id = :cid ORDER BY id DESC LIMIT 100');
        $stmt->execute(['cid' => $companyId]);
        $this->view('company/documents/index', [
            'title' => __('documents'),
            'items' => $stmt->fetchAll(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('documents'),
        ], 'main');
    }

    public function store(): void
    {
        if (!rateb_can_manage_entity('documents')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('documents'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('documents'));
        }
        $result = (new DocumentService())->storeUpload(
            $_FILES['document'] ?? [],
            (string) $this->input('entity_type', 'general'),
            (int) $this->input('entity_id', 0),
            trim((string) $this->input('title', ''))
        );
        if ($result['success']) {
            SessionManager::flash('success', __('save') . ' OK');
        } else {
            SessionManager::flash('error', $result['error'] ?? __('invalid_request'));
        }
        $this->redirect(rateb_app_url('documents'));
    }
}

final class WorkflowsController extends Controller
{
    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        $svc = new WorkflowService();
        $db = \Rateb\App\Core\Database::connection();
        $pending = $db->prepare(
            'SELECT i.*, w.name AS workflow_name FROM rateb_approval_instances i
             JOIN rateb_approval_workflows w ON w.id = i.workflow_id
             WHERE i.company_id = :cid AND i.status = :st ORDER BY i.id DESC LIMIT 50'
        );
        $pending->execute(['cid' => $companyId, 'st' => 'pending']);
        $this->view('company/workflows/index', [
            'title' => __('workflows'),
            'workflows' => $svc->listWorkflows($companyId),
            'pending' => $pending->fetchAll(),
            'csrf' => Csrf::token(),
            'canApprove' => rateb_can('workflows.approve'),
        ], 'main');
    }

    public function approve(array $params): void
    {
        if (!rateb_can('workflows.approve')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('workflows'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('workflows'));
        }
        $id = (int) ($params['id'] ?? 0);
        (new WorkflowService())->approve($id, trim((string) $this->input('comment', '')));
        (new AuditService())->log('approve', 'workflow_instance', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('workflows'));
    }

    public function reject(array $params): void
    {
        if (!rateb_can('workflows.approve')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('workflows'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('workflows'));
        }
        $id = (int) ($params['id'] ?? 0);
        (new WorkflowService())->reject($id, trim((string) $this->input('comment', '')));
        (new AuditService())->log('reject', 'workflow_instance', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('workflows'));
    }
}

final class ProductCategoriesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\ProductCategory();
        $this->viewPrefix = 'company/product-categories';
        $this->routePrefix = rateb_app_route('product-categories');
        $this->entityName = 'product_categories';
        $this->fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text'],
            ['name' => 'is_active', 'label' => 'active', 'type' => 'select', 'options' => ['1', '0']],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}
