<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

final class WorkflowSlaService
{
    public function setDueDate(int $instanceId, int $workflowId, int $stepOrder): void
    {
        $db = Database::connection();
        $step = $db->prepare(
            'SELECT sla_hours FROM rateb_approval_workflow_steps WHERE workflow_id = :wid AND step_order = :st LIMIT 1'
        );
        $step->execute(['wid' => $workflowId, 'st' => $stepOrder]);
        $row = $step->fetch();
        $hours = (int) ($row['sla_hours'] ?? 48);
        if ($hours < 1) {
            $hours = 48;
        }
        $due = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $db->prepare('UPDATE rateb_approval_instances SET due_at = :due WHERE id = :id')
            ->execute(['due' => $due, 'id' => $instanceId]);
    }

    public function processEscalations(): int
    {
        $db = Database::connection();
        $stmt = $db->query(
            "SELECT i.*, w.name AS workflow_name FROM rateb_approval_instances i
             JOIN rateb_approval_workflows w ON w.id = i.workflow_id
             WHERE i.status = 'pending' AND i.due_at IS NOT NULL AND i.due_at < NOW()"
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        $count = 0;
        foreach ($rows as $row) {
            $instanceId = (int) $row['id'];
            $companyId = (int) $row['company_id'];
            $step = (int) $row['current_step'];
            $workflowId = (int) $row['workflow_id'];
            $entityType = (string) $row['entity_type'];
            $entityId = (int) $row['entity_id'];
            TenantContext::setCompanyId($companyId);
            (new WorkflowSubmissionService())->notifyStepApprovers($companyId, $workflowId, $step, $entityType, $entityId);
            $db->prepare(
                'UPDATE rateb_approval_instances SET escalated_at = NOW(), escalation_count = escalation_count + 1,
                 due_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = :id'
            )->execute(['id' => $instanceId]);
            (new NotificationService())->notifyCompany(
                $companyId,
                __('workflow_overdue'),
                __('workflow_overdue_message', ['type' => $entityType, 'id' => (string) $entityId]),
                'warning',
                'workflow_overdue',
                $entityType,
                $entityId
            );
            (new EmailAlertService())->sendWorkflowOverdue($companyId, $entityType, $entityId);
            $count++;
        }
        return $count;
    }
}
