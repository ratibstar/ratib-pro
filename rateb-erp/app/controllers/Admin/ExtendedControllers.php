<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Controllers\Shared\ExportController;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
use Rateb\App\Services\ApprovalOversightService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\BillingService;
use Rateb\App\Services\OversightFilterService;
use Rateb\App\Services\StockMovementService;
use Rateb\App\Services\WorkflowService;

final class AdminStockMovementsController extends Controller
{
    public function index(): void
    {
        $this->view('admin/stock-movements/index', [
            'title' => __('stock_movements'),
            'items' => (new StockMovementService())->listRecent(100),
            'companies' => (new Company())->all(200, 0),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function export(): void
    {
        $items = (new StockMovementService())->listRecent(500);
        ExportController::send('admin_stock_movements', [
            ['name' => 'movement_no', 'label' => __('movement_no')],
            ['name' => 'movement_type', 'label' => __('movement_type')],
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'created_at', 'label' => __('created_at')],
        ], $items, __('stock_movements'));
    }
}

final class AdminWorkflowsController extends Controller
{
    public function index(): void
    {
        $svc = new WorkflowService();
        $ofs = new OversightFilterService();
        $filters = $ofs->parse();
        $companyFilter = $filters['company_id'] > 0 ? $filters['company_id'] : null;
        $this->view('admin/workflows/index', [
            'title' => __('workflow_definitions'),
            'workflows' => $svc->listWorkflowsDetailed($companyFilter),
            'pending' => $svc->listPending($companyFilter),
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'entityTypes' => WorkflowService::entityTypeOptions(),
            'formAction' => rateb_url('admin/oversight/workflows'),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can('workflows.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!rateb_can('workflows.manage') || !$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $name = trim((string) $this->input('name', ''));
        $entityType = trim((string) $this->input('entity_type', 'purchase_request'));
        $companyId = (int) $this->input('company_id', 0);
        if ($name === '') {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_approval_workflows (company_id, name, entity_type, is_active) VALUES (:cid, :name, :et, 1)'
        )->execute([
            'cid' => $companyId > 0 ? $companyId : null,
            'name' => $name,
            'et' => $entityType,
        ]);
        $id = (int) $db->lastInsertId();
        $defaultLabel = match ($entityType) {
            'purchase_order' => 'اعتماد أمر الشراء',
            'contract' => 'اعتماد العقد',
            'supplier' => 'اعتماد المورد',
            default => 'اعتماد طلب الشراء',
        };
        (new WorkflowService())->addStep($id, $defaultLabel);
        (new AuditService())->log('create', 'workflow', $id);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/oversight/workflows/' . $id . '/edit'));
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $svc = new WorkflowService();
        $workflow = $svc->findWorkflow($id);
        if (!$workflow) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $this->view('admin/workflows/edit', [
            'title' => __('edit') . ' — ' . (string) ($workflow['name'] ?? ''),
            'workflow' => $workflow,
            'steps' => $svc->listSteps($id),
            'companies' => (new BillingService())->companyOptions(),
            'entityTypes' => WorkflowService::entityTypeOptions(),
            'roles' => $this->roleOptions(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can('workflows.manage'),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!rateb_can('workflows.manage') || !$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $id = (int) ($params['id'] ?? 0);
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/oversight/workflows/' . $id . '/edit'));
        }
        (new WorkflowService())->updateWorkflow(
            $id,
            $name,
            (int) $this->input('company_id', 0),
            trim((string) $this->input('entity_type', 'purchase_request')),
            (string) $this->input('is_active', '1') === '1'
        );
        (new AuditService())->log('update', 'workflow', $id);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/oversight/workflows/' . $id . '/edit'));
    }

    public function destroy(array $params): void
    {
        if (!rateb_can('workflows.manage') || !$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $id = (int) ($params['id'] ?? 0);
        if (!(new WorkflowService())->deleteWorkflow($id)) {
            SessionManager::flash('error', __('workflow_delete_blocked_pending'));
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        (new AuditService())->log('delete', 'workflow', $id);
        SessionManager::flash('success', __('deleted'));
        Response::redirect(rateb_url('admin/oversight/workflows'));
    }

    public function toggle(array $params): void
    {
        if (!rateb_can('workflows.manage') || !$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $id = (int) ($params['id'] ?? 0);
        (new WorkflowService())->toggleWorkflow($id);
        (new AuditService())->log('toggle', 'workflow', $id);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/oversight/workflows'));
    }

    public function storeStep(array $params): void
    {
        if (!rateb_can('workflows.manage') || !$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $workflowId = (int) ($params['id'] ?? 0);
        $label = trim((string) $this->input('label', ''));
        if ($label === '') {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/oversight/workflows/' . $workflowId . '/edit'));
        }
        (new WorkflowService())->addStep($workflowId, $label, (int) $this->input('role_id', 0));
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/oversight/workflows/' . $workflowId . '/edit'));
    }

    public function destroyStep(array $params): void
    {
        if (!rateb_can('workflows.manage') || !$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $workflowId = (int) ($params['id'] ?? 0);
        $stepId = (int) ($params['stepId'] ?? 0);
        (new WorkflowService())->deleteStep($stepId);
        SessionManager::flash('success', __('deleted'));
        Response::redirect(rateb_url('admin/oversight/workflows/' . $workflowId . '/edit'));
    }

    /** @return array<int, array{id:int,name:string}> */
    private function roleOptions(): array
    {
        $rows = (new Company())->query(
            'SELECT id, name FROM rateb_roles ORDER BY name LIMIT 200'
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['id' => (int) ($row['id'] ?? 0), 'name' => (string) ($row['name'] ?? '')];
        }
        return $out;
    }
}

final class AdminApprovalsController extends Controller
{
    public function index(): void
    {
        $ofs = new OversightFilterService();
        $filters = $ofs->parse();
        $companyFilter = $filters['company_id'] > 0 ? $filters['company_id'] : null;
        $typeFilter = trim((string) ($_GET['type'] ?? ''));
        $svc = new ApprovalOversightService();
        $this->view('admin/approvals/index', [
            'title' => __('approvals_oversight'),
            'items' => $svc->listPending($companyFilter, $typeFilter !== '' ? $typeFilter : null),
            'summary' => $svc->summary($companyFilter),
            'typeOptions' => ApprovalOversightService::typeOptions(),
            'typeFilter' => $typeFilter,
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'formAction' => rateb_url('admin/oversight/approvals'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function approve(): void
    {
        $this->decide('approve');
    }

    public function reject(): void
    {
        $this->decide('reject');
    }

    private function decide(string $action): void
    {
        if (!rateb_is_super_admin()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_url('admin'));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/oversight/approvals'));
        }
        $sourceKey = trim((string) $this->input('source_key', ''));
        $recordId = (int) $this->input('record_id', 0);
        $companyId = (int) $this->input('company_id', 0);
        try {
            (new ApprovalOversightService())->process($sourceKey, $recordId, $companyId, $action);
            (new AuditService())->log($action, 'approval_oversight', $recordId, [
                'source' => $sourceKey,
                'company_id' => $companyId,
            ]);
            SessionManager::flash('success', $action === 'approve' ? __('approved') : __('rejected'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $qs = [];
        if ($companyId > 0) {
            $qs['company_id'] = $companyId;
        }
        $type = trim((string) $this->input('type_filter', ''));
        if ($type !== '') {
            $qs['type'] = $type;
        }
        $url = rateb_url('admin/oversight/approvals' . ($qs !== [] ? '?' . http_build_query($qs) : ''));
        Response::redirect($url);
    }
}

final class AdminMedicalDevicesController extends Controller
{
    public function index(): void
    {
        $db = \Rateb\App\Core\Database::connection();
        $filter = (int) ($_GET['company_id'] ?? 0);
        $sql = 'SELECT d.*, c.name AS company_name FROM rateb_medical_devices d LEFT JOIN rateb_companies c ON c.id = d.company_id WHERE 1=1';
        $params = [];
        if ($filter > 0) {
            $sql .= ' AND d.company_id = :cid';
            $params['cid'] = $filter;
        }
        $sql .= ' ORDER BY d.id DESC LIMIT 100';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $this->view('admin/medical-devices/index', [
            'title' => __('medical_devices'),
            'items' => $stmt->fetchAll(),
            'companies' => (new Company())->all(200, 0),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}
