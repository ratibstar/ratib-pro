<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\WorkflowTableService;

trait WorkflowOpsTrait
{
    abstract protected function workflowSlug(): string;

    abstract protected function workflowRedirect(): void;

    public function destroy(array $params): void
    {
        rateb_require_manage($this->workflowSlug());
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->workflowRedirect();
        }
        $cfg = WorkflowTableService::config($this->workflowSlug());
        $id = (int) ($params['id'] ?? 0);
        if ($cfg === null || $id < 1) {
            SessionManager::flash('error', __('no_records'));
            $this->workflowRedirect();
        }
        (new WorkflowTableService())->deleteOne($this->workflowSlug(), $id);
        (new AuditService())->log('delete', (string) $cfg['entity'], $id);
        SessionManager::flash('success', __('delete') . ' OK');
        $this->workflowRedirect();
    }

    public function bulkDestroy(): void
    {
        rateb_require_manage($this->workflowSlug());
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->workflowRedirect();
        }
        $cfg = WorkflowTableService::config($this->workflowSlug());
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
        if ($cfg === null || $ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->workflowRedirect();
        }
        $deleted = (new WorkflowTableService())->bulkDelete($this->workflowSlug(), $ids);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', (string) $cfg['entity'], $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        $this->workflowRedirect();
    }
}
