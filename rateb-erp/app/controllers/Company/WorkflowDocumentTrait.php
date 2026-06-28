<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Controllers\Shared\ExportController;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\WorkflowRecordService;
use Rateb\App\Services\WorkflowTableService;

/** View / print / download / manager approval for workflow-index modules. */
trait WorkflowDocumentTrait
{
    abstract protected function workflowSlug(): string;

    abstract protected function workflowRedirect(): void;

    /** @return array<int, array{name:string,label:string,type?:string}> */
    abstract protected function workflowColumns(): array;

    /** @return array<string, mixed>|null */
    abstract protected function workflowFind(int $id): ?array;

    protected function workflowTitle(): string
    {
        return __('record');
    }

    protected function workflowExportSlug(): string
    {
        return $this->workflowSlug();
    }

    protected function workflowCanApprove(): bool
    {
        return false;
    }

    public function show(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->workflowFind($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'main');
            return;
        }
        $this->view('components/workflow-record-show', [
            'title' => $this->workflowTitle() . ' — ' . $this->workflowRecordLabel($item),
            'item' => $item,
            'columns' => $this->workflowColumns(),
            'routePrefix' => rateb_app_route($this->workflowSlug()),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity($this->workflowSlug()),
            'canApprove' => $this->workflowCanApprove(),
            'exportEnabled' => rateb_can_export_entity($this->workflowSlug()),
        ], 'main');
    }

    public function print(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->workflowFind($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view('components/workflow-record-print', [
            'title' => __('print') . ' — ' . $this->workflowTitle(),
            'item' => $item,
            'columns' => $this->workflowColumns(),
        ], 'print');
    }

    public function download(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->workflowFind($id);
        if (!$item) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $svc = new WorkflowRecordService();
        $cols = $svc->exportColumns($this->workflowColumns());
        ExportController::send(
            $this->workflowExportSlug() . '_' . $id,
            $cols,
            $svc->rowsForExport([$item], $cols),
            $this->workflowTitle() . ' ' . $this->workflowRecordLabel($item),
            $this->workflowSlug()
        );
    }

    public function approve(array $params): void
    {
        if (function_exists('rateb_oversight_approve_only') && rateb_oversight_approve_only()) {
            SessionManager::flash('error', __('approvals_admin_only'));
            Response::redirect(rateb_url('admin/oversight/approvals'));
            return;
        }
        if (!$this->workflowCanApprove()) {
            SessionManager::flash('error', __('access_denied'));
            $this->workflowRedirect();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->workflowRedirect();
        }
        $id = (int) ($params['id'] ?? 0);
        $cfg = WorkflowTableService::config($this->workflowSlug());
        try {
            (new WorkflowRecordService())->approve($this->workflowSlug(), $id);
            if ($cfg !== null) {
                (new AuditService())->log('approve', (string) $cfg['entity'], $id);
            }
            SessionManager::flash('success', __('manager_approval_approved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->workflowRedirect();
    }

    public function reject(array $params): void
    {
        if (function_exists('rateb_oversight_approve_only') && rateb_oversight_approve_only()) {
            SessionManager::flash('error', __('approvals_admin_only'));
            Response::redirect(rateb_url('admin/oversight/approvals'));
            return;
        }
        if (!$this->workflowCanApprove()) {
            SessionManager::flash('error', __('access_denied'));
            $this->workflowRedirect();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->workflowRedirect();
        }
        $id = (int) ($params['id'] ?? 0);
        $cfg = WorkflowTableService::config($this->workflowSlug());
        try {
            (new WorkflowRecordService())->reject($this->workflowSlug(), $id);
            if ($cfg !== null) {
                (new AuditService())->log('reject', (string) $cfg['entity'], $id);
            }
            SessionManager::flash('success', __('manager_approval_rejected'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->workflowRedirect();
    }

    /** @param array<string, mixed> $item */
    protected function workflowRecordLabel(array $item): string
    {
        foreach (['maintenance_no', 'assignment_no', 'service_no', 'part_no', 'renewal_no', 'depreciation_no', 'device_name', 'audit_no'] as $key) {
            if (!empty($item[$key])) {
                return (string) $item[$key];
            }
        }
        return '#' . (int) ($item['id'] ?? 0);
    }
}
