<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Workflow actions via WorkflowService (approve/reject) only.
 */
final class PortalWorkflowService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function pendingForCompany(): array
    {
        TenantContext::setCompanyId($this->repo->companyId());
        try {
            return $this->repo->fetchAll(
                "SELECT id, entity_type, entity_id, status, current_step, created_at
                 FROM rateb_approval_instances
                 WHERE company_id = :cid AND status IN ('pending','in_progress')
                 ORDER BY id DESC LIMIT 50",
                ['cid' => $this->repo->companyId()]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function approve(int $instanceId): bool
    {
        return $this->decide($instanceId, 'approve');
    }

    public function reject(int $instanceId): bool
    {
        return $this->decide($instanceId, 'reject');
    }

    private function decide(int $instanceId, string $action): bool
    {
        TenantContext::setCompanyId($this->repo->companyId());
        if (!class_exists(\Rateb\App\Services\WorkflowService::class)) {
            return false;
        }
        try {
            $svc = new \Rateb\App\Services\WorkflowService();
            if ($action === 'approve') {
                $svc->approve($instanceId);
            } else {
                $svc->reject($instanceId);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('PortalWorkflowService: ' . $e->getMessage());

            return false;
        }
    }
}
