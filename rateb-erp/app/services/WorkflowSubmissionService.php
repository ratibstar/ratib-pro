<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

final class WorkflowSubmissionService
{
    public function submitEntity(string $entityType, int $entityId, int $companyId): void
    {
        if ($entityId < 1 || $companyId < 1) {
            return;
        }

        $workflow = $this->findWorkflow($companyId, $entityType);
        if ($workflow === null) {
            return;
        }

        $db = Database::connection();
        $existing = $db->prepare(
            'SELECT id FROM rateb_approval_instances
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND status IN (\'pending\', \'approved\')
             LIMIT 1'
        );
        $existing->execute(['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]);
        if ($existing->fetch()) {
            return;
        }

        $workflowId = (int) $workflow['id'];
        $instanceId = (new WorkflowService())->submit($entityType, $entityId, $companyId, $workflowId);
        (new WorkflowSlaService())->setDueDate($instanceId, $workflowId, 1);
        $this->notifyStepApprovers($companyId, $workflowId, 1, $entityType, $entityId);
        ApprovalOversightService::notifyPendingSubmission($companyId, $entityType, $entityType . ' #' . $entityId, $entityId);
    }

    /** @return array<string, mixed>|null */
    private function findWorkflow(int $companyId, string $entityType): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_approval_workflows
             WHERE entity_type = :et AND is_active = 1
               AND (company_id IS NULL OR company_id = :cid)
             ORDER BY company_id DESC, id ASC LIMIT 1'
        );
        $stmt->execute(['et' => $entityType, 'cid' => $companyId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function notifyStepApprovers(int $companyId, int $workflowId, int $stepOrder, string $entityType, int $entityId): void
    {
        $db = Database::connection();
        $step = $db->prepare(
            'SELECT * FROM rateb_approval_workflow_steps WHERE workflow_id = :wid AND step_order = :st LIMIT 1'
        );
        $step->execute(['wid' => $workflowId, 'st' => $stepOrder]);
        $first = $step->fetch();
        if (!$first) {
            return;
        }

        $notifier = new NotificationService();
        $approverId = (int) ($first['approver_user_id'] ?? 0);
        if ($approverId > 0) {
            $notifier->triggerApproval($approverId, $companyId, $entityType, $entityId);
            return;
        }

        $roleId = (int) ($first['role_id'] ?? 0);
        if ($roleId < 1) {
            return;
        }

        $users = $db->prepare(
            'SELECT DISTINCT ur.user_id FROM rateb_user_roles ur
             JOIN rateb_users u ON u.id = ur.user_id
             WHERE ur.role_id = :rid AND (u.company_id = :cid OR u.is_super_admin = 1) AND u.status = \'active\''
        );
        $users->execute(['rid' => $roleId, 'cid' => $companyId]);
        while ($row = $users->fetch()) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $notifier->triggerApproval($uid, $companyId, $entityType, $entityId);
            }
        }
    }

    public function handlePurchaseRequestStatus(int $entityId, string $newStatus, ?string $oldStatus = null): void
    {
        if ($newStatus !== 'submitted' || $oldStatus === 'submitted') {
            return;
        }
        $cid = TenantContext::companyId() ?? 0;
        if ($cid < 1) {
            return;
        }
        $this->submitEntity('purchase_request', $entityId, $cid);
    }

    public function handlePurchaseOrderStatus(int $entityId, string $newStatus, ?string $oldStatus = null): void
    {
        if ($newStatus !== 'sent' || $oldStatus === 'sent') {
            return;
        }
        $cid = TenantContext::companyId() ?? 0;
        if ($cid < 1) {
            return;
        }
        $this->submitEntity('purchase_order', $entityId, $cid);
    }

    /** @return array<string, mixed>|null */
    public function instanceForEntity(string $entityType, int $entityId, int $companyId): ?array
    {
        if ($entityId < 1 || $companyId < 1) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT status, current_step FROM rateb_approval_instances
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
