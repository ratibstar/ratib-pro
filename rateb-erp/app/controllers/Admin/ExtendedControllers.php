<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\StockMovementService;
use Rateb\App\Services\WorkflowService;
use Rateb\App\Controllers\Shared\ExportController;

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
        $db = \Rateb\App\Core\Database::connection();
        $pending = $db->query('SELECT i.*, w.name AS workflow_name, c.name AS company_name
            FROM rateb_approval_instances i
            JOIN rateb_approval_workflows w ON w.id = i.workflow_id
            LEFT JOIN rateb_companies c ON c.id = i.company_id
            WHERE i.status = \'pending\' ORDER BY i.id DESC LIMIT 100')->fetchAll();
        $this->view('admin/workflows/index', [
            'title' => __('workflows'),
            'workflows' => $svc->listWorkflows(null),
            'pending' => $pending,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/oversight/workflows'));
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_approval_workflows (company_id, name, entity_type, is_active) VALUES (:cid, :name, :et, 1)'
        )->execute([
            'cid' => (int) $this->input('company_id', 0) ?: null,
            'name' => trim((string) $this->input('name', '')),
            'et' => trim((string) $this->input('entity_type', 'purchase_request')),
        ]);
        (new AuditService())->log('create', 'workflow', (int) $db->lastInsertId());
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/oversight/workflows'));
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
