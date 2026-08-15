<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
use Rateb\App\Services\ApprovalOversightService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\BillingService;
use Rateb\App\Services\DatabaseErrorService;
use Rateb\App\Services\OversightFilterService;
use Rateb\App\Services\WorkflowService;

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
    public function companiesApprovals(): void
    {
        $_GET['type'] = 'companies';
        $this->index();
    }

    public function hrApprovals(): void
    {
        $_GET['type'] = 'hr';
        $this->index();
    }

    public function index(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        $ofs = new OversightFilterService();
        $filters = $ofs->parse();
        $companyFilter = $filters['company_id'] > 0 ? $filters['company_id'] : null;
        $typeFilter = trim((string) ($_GET['type'] ?? ''));
        $hrType = trim((string) ($_GET['hr_type'] ?? ''));
        $hrSource = $typeFilter === 'hr' ? ApprovalOversightService::hrSourceKeyForType($hrType) : null;
        $svc = new ApprovalOversightService();
        $summary = $svc->summary($companyFilter);
        SessionManager::set('rateb_oversight_approvals_seen', (int) ($summary['total'] ?? 0));
        // Warm nav badges from this page's summary — avoid a second COUNT storm in the layout.
        try {
            SessionManager::set('rateb_oversight_menu_counts_v2', [
                'exp' => time() + 300,
                'data' => $svc->menuCountsFromSummary($summary),
            ]);
        } catch (\Throwable $e) {
            // Best-effort badge warm.
        }
        $formPath = match ($typeFilter) {
            'companies' => 'admin/oversight/companies-approvals',
            'hr' => 'admin/oversight/hr-approvals',
            default => 'admin/oversight/approvals',
        };
        $this->view('admin/approvals/index', [
            'title' => $typeFilter === 'hr' ? __('hr_approvals_oversight') : __('approvals_oversight'),
            'items' => $svc->listPending(
                $companyFilter,
                $typeFilter !== '' ? $typeFilter : null,
                200,
                $hrSource
            ),
            'summary' => $summary,
            'typeOptions' => ApprovalOversightService::typeOptions(),
            'typeFilter' => $typeFilter,
            'hrType' => $hrType,
            'hrTypeOptions' => ApprovalOversightService::hrTypeOptions(),
            'companies' => $ofs->companies(),
            'filters' => $filters,
            'formAction' => rateb_url($formPath),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function count(): void
    {
        if (!rateb_is_super_admin()) {
            Response::json(['total' => 0], 403);
        }
        $companyFilter = (int) ($_GET['company_id'] ?? 0);
        $svc = new ApprovalOversightService();
        $total = (int) ($svc->summary($companyFilter > 0 ? $companyFilter : null)['total'] ?? 0);
        Response::json(['total' => $total]);
    }

    public function detail(): void
    {
        if (!rateb_is_super_admin()) {
            Response::json(['ok' => false, 'message' => __('access_denied')], 403);
        }
        $sourceKey = trim((string) ($_GET['source_key'] ?? ''));
        $recordId = (int) ($_GET['record_id'] ?? 0);
        $companyId = (int) ($_GET['company_id'] ?? 0);
        try {
            $data = (new ApprovalOversightService())->detail($sourceKey, $recordId, $companyId);
            Response::json(['ok' => true, 'detail' => $data]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function approve(): void
    {
        $this->decide('approve');
    }

    public function reject(): void
    {
        $this->decide('reject');
    }

    public function decideAction(): void
    {
        $action = trim((string) $this->input('decision', 'approve'));
        if ($action === 'undo') {
            $this->undo();
            return;
        }
        $this->decide($action === 'reject' ? 'reject' : 'approve');
    }

    public function bulkDecide(): void
    {
        if (!rateb_is_super_admin()) {
            Response::json(['ok' => false, 'message' => __('access_denied')], 403);
            return;
        }
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false, 'message' => __('invalid_request')], 400);
            return;
        }
        $action = trim((string) $this->input('decision', 'approve'));
        $action = $action === 'reject' ? 'reject' : 'approve';
        $items = $this->parseBulkOversightItems();
        if ($items === []) {
            Response::json(['ok' => false, 'message' => __('bulk_none_selected')], 400);
            return;
        }
        if (count($items) > 50) {
            $items = array_slice($items, 0, 50);
        }

        $svc = new ApprovalOversightService();
        $processed = [];
        $failed = [];
        foreach ($items as $item) {
            $sourceKey = (string) ($item['source_key'] ?? '');
            $recordId = (int) ($item['record_id'] ?? 0);
            $companyId = (int) ($item['company_id'] ?? 0);
            $rowKey = (string) ($item['row_key'] ?? '');
            if ($action === 'reject' && !ApprovalOversightService::canReject($sourceKey)) {
                $failed[] = [
                    'row_key' => $rowKey,
                    'message' => __('invalid_request'),
                ];
                continue;
            }
            try {
                $svc->process($sourceKey, $recordId, $companyId, $action);
                try {
                    (new AuditService())->log($action, 'approval_oversight', $recordId, [
                        'source' => $sourceKey,
                        'company_id' => $companyId,
                        'bulk' => true,
                    ]);
                } catch (\Throwable $e) {
                    // Do not block bulk if audit log insert fails.
                }
                $processed[] = $rowKey !== '' ? $rowKey : ($sourceKey . '-' . $recordId);
            } catch (\Throwable $e) {
                $failed[] = [
                    'row_key' => $rowKey !== '' ? $rowKey : ($sourceKey . '-' . $recordId),
                    'message' => DatabaseErrorService::userMessage($e),
                ];
            }
        }

        $this->clearOversightCountCache();

        $okCount = count($processed);
        if ($okCount < 1) {
            $firstMsg = (string) ($failed[0]['message'] ?? __('system_error_generic'));
            Response::json([
                'ok' => false,
                'message' => $firstMsg,
                'failed' => $failed,
            ], 400);
            return;
        }

        $msg = $action === 'approve'
            ? __('bulk_approved', ['count' => $okCount])
            : __('bulk_rejected', ['count' => $okCount]);
        if ($failed !== []) {
            $msg .= ' — ' . __('failed') . ': ' . count($failed);
        }

        $filterCompany = null;
        $postedCompany = (int) $this->input('company_id', 0);
        if ($postedCompany > 0) {
            $filterCompany = $postedCompany;
        }
        $payload = [
            'ok' => true,
            'message' => $msg,
            'processed' => $processed,
            'failed' => $failed,
        ];
        try {
            $payload['summary'] = $svc->summary($filterCompany, true);
            $payload['menu_counts'] = $svc->menuCountsFromSummary($payload['summary']);
            SessionManager::set('rateb_oversight_menu_counts_v2', [
                'exp' => time() + 300,
                'data' => $payload['menu_counts'],
            ]);
        } catch (\Throwable $e) {
            // Counts are progressive enhancement.
        }
        Response::json($payload);
    }

    public function undo(): void
    {
        if (!rateb_is_super_admin()) {
            $this->respondDecision(false, __('access_denied'));
        }
        if (!$this->validateCsrf()) {
            $this->respondDecision(false, __('invalid_request'));
        }
        $sourceKey = trim((string) $this->input('source_key', ''));
        $recordId = (int) $this->input('record_id', 0);
        $companyId = (int) $this->input('company_id', 0);
        try {
            $svc = new ApprovalOversightService();
            $svc->undo($sourceKey, $recordId, $companyId);
            try {
                (new AuditService())->log('undo', 'approval_oversight', $recordId, [
                    'source' => $sourceKey,
                    'company_id' => $companyId,
                ]);
            } catch (\Throwable $e) {
                // Do not block undo if audit log insert fails.
            }
            $detail = $svc->detail($sourceKey, $recordId, $companyId);
            $this->clearOversightCountCache();
            $this->respondDecision(true, __('approval_undone'), $detail, null, $svc, $companyId);
        } catch (\Throwable $e) {
            $this->respondDecision(false, DatabaseErrorService::userMessage($e));
        }
    }

    private function decide(string $action): void
    {
        if (!rateb_is_super_admin()) {
            $this->respondDecision(false, __('access_denied'));
        }
        if (!$this->validateCsrf()) {
            $this->respondDecision(false, __('invalid_request'));
        }
        $sourceKey = trim((string) $this->input('source_key', ''));
        $recordId = (int) $this->input('record_id', 0);
        $companyId = (int) $this->input('company_id', 0);
        if ($sourceKey === '' || $recordId < 1) {
            $this->respondDecision(false, __('invalid_request'));
        }
        $svc = new ApprovalOversightService();
        try {
            $svc->process($sourceKey, $recordId, $companyId, $action);
            try {
                (new AuditService())->log($action, 'approval_oversight', $recordId, [
                    'source' => $sourceKey,
                    'company_id' => $companyId,
                ]);
            } catch (\Throwable $e) {
                // Do not block approval if audit log insert fails.
            }
            $msg = $action === 'approve' ? __('approved') : __('rejected');
            $detail = null;
            try {
                $detail = $svc->detail($sourceKey, $recordId, $companyId);
            } catch (\Throwable $e) {
                // Approval saved; detail panel is optional.
            }
            if ($detail === null) {
                $detail = [
                    'can_approve' => false,
                    'can_reject' => false,
                    'can_undo' => ApprovalOversightService::canUndo($sourceKey),
                    'status_label' => $action === 'approve' ? __('approved') : __('rejected'),
                ];
            }
            $this->clearOversightCountCache();
            $this->respondDecision(true, $msg, $detail, null, $svc, $companyId);
        } catch (\Throwable $e) {
            $this->respondDecision(false, DatabaseErrorService::userMessage($e), null, $e);
        }
    }

    private function clearOversightCountCache(): void
    {
        try {
            SessionManager::forget('rateb_oversight_menu_counts');
            SessionManager::forget('rateb_oversight_menu_counts_v2');
            SessionManager::forget('rateb_oversight_approvals_seen');
            SessionManager::forget('rateb_approval_summary_v1_0');
            $cid = (int) $this->input('company_id', 0);
            if ($cid > 0) {
                SessionManager::forget('rateb_approval_summary_v1_' . $cid);
            }
        } catch (\Throwable $e) {
            // Ignore cache clear failures.
        }
    }

    /**
     * @return list<array{source_key: string, record_id: int, company_id: int, row_key: string}>
     */
    private function parseBulkOversightItems(): array
    {
        $raw = $this->input('items', []);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sourceKey = trim((string) ($item['source_key'] ?? ''));
            $recordId = (int) ($item['record_id'] ?? 0);
            if ($sourceKey === '' || $recordId < 1) {
                continue;
            }
            $out[] = [
                'source_key' => $sourceKey,
                'record_id' => $recordId,
                'company_id' => (int) ($item['company_id'] ?? 0),
                'row_key' => trim((string) ($item['row_key'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $detail
     * @param ApprovalOversightService|null $svc
     */
    private function respondDecision(
        bool $ok,
        string $message,
        ?array $detail = null,
        ?\Throwable $error = null,
        ?ApprovalOversightService $svc = null,
        int $companyId = 0
    ): void {
        if ($this->wantsJson()) {
            $payload = ['ok' => $ok, 'message' => $message];
            if ($detail !== null) {
                $payload['detail'] = $detail;
            }
            if ($ok && $svc !== null) {
                try {
                    $filterCompany = $companyId > 0 ? $companyId : null;
                    $payload['summary'] = $svc->summary($filterCompany, true);
                    $payload['menu_counts'] = $svc->menuCountsFromSummary($payload['summary']);
                    SessionManager::set('rateb_oversight_menu_counts_v2', [
                        'exp' => time() + 300,
                        'data' => $payload['menu_counts'],
                    ]);
                } catch (\Throwable $e) {
                    // Counts are progressive enhancement for live UI refresh.
                }
            }
            if (!$ok && $error !== null && rateb_is_super_admin()) {
                $sqlError = DatabaseErrorService::technicalDetail($error);
                if ($sqlError !== '') {
                    $payload['sql_error'] = $sqlError;
                }
            }
            Response::json($payload, $ok ? 200 : 400);
        }
        if ($ok) {
            SessionManager::flash('success', $message);
        } else {
            SessionManager::flash('error', $message);
        }
        $qs = [];
        $companyId = (int) $this->input('company_id', 0);
        if ($companyId > 0) {
            $qs['company_id'] = $companyId;
        }
        $type = trim((string) $this->input('type_filter', ''));
        if ($type !== '') {
            $qs['type'] = $type;
        }
        $path = $type === 'hr' ? 'admin/oversight/hr-approvals' : 'admin/oversight/approvals';
        $hrType = trim((string) $this->input('hr_type', ''));
        if ($type === 'hr' && $hrType !== '' && ApprovalOversightService::hrSourceKeyForType($hrType) !== null) {
            $qs['hr_type'] = $hrType;
        }
        $url = rateb_url($path . ($qs !== [] ? '?' . http_build_query($qs) : ''));
        Response::redirect($url);
    }

    private function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }
}
