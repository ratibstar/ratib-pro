<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;

final class WorkflowService
{
    /** @return array<int, array<string, mixed>> */
    public function listWorkflows(?int $companyId = null): array
    {
        $sql = 'SELECT * FROM rateb_approval_workflows WHERE 1=1';
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND (company_id IS NULL OR company_id = :cid)';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY name';
        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<int, string> */
    public static function entityTypeOptions(): array
    {
        return [
            'purchase_request' => 'purchase_requests',
            'purchase_order' => 'purchase_orders',
            'contract' => 'contracts',
            'supplier' => 'suppliers',
            'supplier_evaluation' => 'supplier_evaluations',
            'contract_renewal' => 'contract_renewals',
            'journal_entry' => 'journal_entries',
            'cash_voucher' => 'cash_vouchers',
            'warehouse_transfer' => 'warehouse_transfers',
            'asset_depreciation' => 'asset_depreciation',
            'asset_maintenance' => 'asset_maintenance',
            'asset_assignment' => 'asset_assignments',
            'device_maintenance' => 'device_maintenance',
            'device_spare_part' => 'device_spare_parts',
            'inventory_audit' => 'inventory_audits',
            'hr_leave' => 'hr_leaves',
            'hr_permission_request' => 'hr_permission_requests',
            'hr_employee_request' => 'hr_employee_requests',
            'hr_payroll' => 'hr_payroll',
        ];
    }

    public static function entityTypeLabel(string $entityType): string
    {
        $key = self::entityTypeOptions()[$entityType] ?? $entityType;
        return __($key);
    }

    public static function entityDocumentUrl(string $entityType, int $entityId, int $companyId): string
    {
        $routes = [
            'purchase_request' => 'purchase-requests',
            'purchase_order' => 'purchase-orders',
            'contract' => 'contracts',
            'supplier' => 'suppliers',
            'supplier_evaluation' => 'supplier-evaluations',
            'contract_renewal' => 'contract-renewals',
            'journal_entry' => 'journal-entries',
            'cash_voucher' => 'cash-vouchers',
            'warehouse_transfer' => 'warehouse-transfers',
            'asset_depreciation' => 'asset-depreciation',
            'asset_maintenance' => 'asset-maintenance',
            'asset_assignment' => 'asset-assignments',
            'device_maintenance' => 'device-maintenance',
            'device_spare_part' => 'device-spare-parts',
            'inventory_audit' => 'inventory-audits',
            'hr_leave' => 'hr/leaves',
            'hr_permission_request' => 'hr/permission-requests',
            'hr_employee_request' => 'hr/requests',
            'hr_payroll' => 'hr/payroll',
        ];
        $slug = $routes[$entityType] ?? '';
        if ($slug === '' || $entityId < 1) {
            return '';
        }
        $url = rateb_url(rateb_app_route($slug . '/' . $entityId));
        if ($companyId > 0) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'company_id=' . $companyId;
        }
        return $url;
    }

    /** @return array<int, array<string, mixed>> */
    public function listWorkflowsDetailed(?int $companyFilter = null): array
    {
        $sql = 'SELECT w.*, c.name AS company_name,
            (SELECT COUNT(*) FROM rateb_approval_workflow_steps s WHERE s.workflow_id = w.id) AS step_count
            FROM rateb_approval_workflows w
            LEFT JOIN rateb_companies c ON c.id = w.company_id
            WHERE 1=1';
        $params = [];
        if ($companyFilter !== null && $companyFilter > 0) {
            $sql .= ' AND (w.company_id IS NULL OR w.company_id = :cid)';
            $params['cid'] = $companyFilter;
        }
        $sql .= ' ORDER BY w.name';
        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findWorkflow(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT w.*, c.name AS company_name
             FROM rateb_approval_workflows w
             LEFT JOIN rateb_companies c ON c.id = w.company_id
             WHERE w.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listSteps(int $workflowId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT s.*, r.name AS role_name
             FROM rateb_approval_workflow_steps s
             LEFT JOIN rateb_roles r ON r.id = s.role_id
             WHERE s.workflow_id = :wid
             ORDER BY s.step_order ASC'
        );
        $stmt->execute(['wid' => $workflowId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function listPending(?int $companyFilter = null, int $limit = 100): array
    {
        $sql = 'SELECT i.*, w.name AS workflow_name, c.name AS company_name
            FROM rateb_approval_instances i
            JOIN rateb_approval_workflows w ON w.id = i.workflow_id
            LEFT JOIN rateb_companies c ON c.id = i.company_id
            WHERE i.status = \'pending\'';
        $params = [];
        if ($companyFilter !== null && $companyFilter > 0) {
            $sql .= ' AND i.company_id = :cid';
            $params['cid'] = $companyFilter;
        }
        $sql .= ' ORDER BY i.id DESC LIMIT ' . max(1, min(500, $limit));
        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateWorkflow(int $id, string $name, ?int $companyId, string $entityType, bool $isActive): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_approval_workflows
             SET name = :name, company_id = :cid, entity_type = :et, is_active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'cid' => $companyId > 0 ? $companyId : null,
            'et' => $entityType,
            'active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteWorkflow(int $id): bool
    {
        $db = Database::connection();
        $pending = $db->prepare('SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE workflow_id = :id AND status = \'pending\'');
        $pending->execute(['id' => $id]);
        if ((int) ($pending->fetch()['c'] ?? 0) > 0) {
            return false;
        }
        $stmt = $db->prepare('DELETE FROM rateb_approval_workflows WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function toggleWorkflow(int $id): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE rateb_approval_workflows SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function addStep(int $workflowId, string $label, ?int $roleId = null): int
    {
        $db = Database::connection();
        $orderStmt = $db->prepare('SELECT COALESCE(MAX(step_order), 0) + 1 AS n FROM rateb_approval_workflow_steps WHERE workflow_id = :wid');
        $orderStmt->execute(['wid' => $workflowId]);
        $stepOrder = (int) ($orderStmt->fetch()['n'] ?? 1);
        $db->prepare(
            'INSERT INTO rateb_approval_workflow_steps (workflow_id, step_order, role_id, label) VALUES (:wid, :ord, :rid, :label)'
        )->execute([
            'wid' => $workflowId,
            'ord' => $stepOrder,
            'rid' => $roleId > 0 ? $roleId : null,
            'label' => $label,
        ]);
        return (int) $db->lastInsertId();
    }

    public function deleteStep(int $stepId): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM rateb_approval_workflow_steps WHERE id = :id');
        $stmt->execute(['id' => $stepId]);
        return $stmt->rowCount() > 0;
    }

    public function submit(string $entityType, int $entityId, int $companyId, int $workflowId): int
    {
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_approval_instances (company_id, workflow_id, entity_type, entity_id, status, submitted_by, current_step)
             VALUES (:cid, :wid, :et, :eid, :st, :uid, 1)'
        )->execute([
            'cid' => $companyId,
            'wid' => $workflowId,
            'et' => $entityType,
            'eid' => $entityId,
            'st' => 'pending',
            'uid' => SessionManager::get('rateb_user_id'),
        ]);
        $instanceId = (int) $db->lastInsertId();

        $db->prepare(
            'INSERT INTO rateb_approval_actions (instance_id, step_order, user_id, action) VALUES (:iid, 1, :uid, :act)'
        )->execute([
            'iid' => $instanceId,
            'uid' => SessionManager::get('rateb_user_id'),
            'act' => 'submit',
        ]);

        return $instanceId;
    }

    public function approve(int $instanceId, ?string $comment = null): bool
    {
        return $this->decide($instanceId, 'approve', $comment);
    }

    public function reject(int $instanceId, ?string $comment = null): bool
    {
        return $this->decide($instanceId, 'reject', $comment);
    }

    private function decide(int $instanceId, string $action, ?string $comment): bool
    {
        try {
            $row = TenantGuard::assertApprovalInstance($instanceId);
        } catch (\Throwable $e) {
            return false;
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            return false;
        }

        $userId = (int) SessionManager::get('rateb_user_id', 0);
        $authz = new AuthorizationService();
        if (!$authz->companyUserCan($userId, 'workflows.approve', 'workflows')) {
            return false;
        }

        $db = Database::connection();
        $step = (int) $row['current_step'];
        $db->prepare(
            'INSERT INTO rateb_approval_actions (instance_id, step_order, user_id, action, comment) VALUES (:iid, :st, :uid, :act, :c)'
        )->execute([
            'iid' => $instanceId,
            'st' => $step,
            'uid' => SessionManager::get('rateb_user_id'),
            'act' => $action,
            'c' => $comment,
        ]);

        $entityType = (string) ($row['entity_type'] ?? '');
        $entityId = (int) ($row['entity_id'] ?? 0);
        $companyId = (int) ($row['company_id'] ?? 0);
        $workflowId = (int) ($row['workflow_id'] ?? 0);

        if ($action === 'reject') {
            $db->prepare('UPDATE rateb_approval_instances SET status = :st WHERE id = :id')->execute(['st' => 'rejected', 'id' => $instanceId]);
            $this->syncEntityStatus($entityType, $entityId, 'rejected');
            (new EmailAlertService())->sendApprovalResult($companyId, $entityType, $entityId, 'rejected');
            return true;
        }

        $steps = $db->prepare('SELECT COUNT(*) AS c FROM rateb_approval_workflow_steps WHERE workflow_id = :wid');
        $steps->execute(['wid' => $workflowId]);
        $totalSteps = (int) ($steps->fetch()['c'] ?? 1);

        if ($step >= $totalSteps) {
            $db->prepare('UPDATE rateb_approval_instances SET status = :st WHERE id = :id')->execute(['st' => 'approved', 'id' => $instanceId]);
            $this->syncEntityStatus($entityType, $entityId, 'approved');
            (new EmailAlertService())->sendApprovalResult($companyId, $entityType, $entityId, 'approved');
        } else {
            $db->prepare('UPDATE rateb_approval_instances SET current_step = current_step + 1 WHERE id = :id')->execute(['id' => $instanceId]);
            (new WorkflowSubmissionService())->notifyStepApprovers($companyId, $workflowId, $step + 1, $entityType, $entityId);
            (new WorkflowSlaService())->setDueDate($instanceId, $workflowId, $step + 1);
        }

        return true;
    }

    private function syncEntityStatus(string $entityType, int $entityId, string $instanceStatus): void
    {
        if ($entityId < 1) {
            return;
        }
        $db = Database::connection();
        if ($entityType === 'purchase_request') {
            $status = $instanceStatus === 'approved' ? 'approved' : 'rejected';
            $db->prepare('UPDATE rateb_purchase_requests SET status = :st WHERE id = :id')->execute(['st' => $status, 'id' => $entityId]);
            return;
        }
        if ($entityType === 'purchase_order') {
            $status = $instanceStatus === 'approved' ? 'confirmed' : 'cancelled';
            $db->prepare('UPDATE rateb_purchase_orders SET status = :st WHERE id = :id')->execute(['st' => $status, 'id' => $entityId]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $instanceId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT a.*, u.name AS user_name FROM rateb_approval_actions a
             LEFT JOIN rateb_users u ON u.id = a.user_id
             WHERE a.instance_id = :iid ORDER BY a.id ASC'
        );
        $stmt->execute(['iid' => $instanceId]);
        return $stmt->fetchAll();
    }
}
