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
        $db = Database::connection();
        $inst = $db->prepare('SELECT * FROM rateb_approval_instances WHERE id = :id LIMIT 1');
        $inst->execute(['id' => $instanceId]);
        $row = $inst->fetch();
        if (!$row || (string) $row['status'] !== 'pending') {
            return false;
        }

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

        if ($action === 'reject') {
            $db->prepare('UPDATE rateb_approval_instances SET status = :st WHERE id = :id')->execute(['st' => 'rejected', 'id' => $instanceId]);
            return true;
        }

        $steps = $db->prepare('SELECT COUNT(*) AS c FROM rateb_approval_workflow_steps WHERE workflow_id = :wid');
        $steps->execute(['wid' => (int) $row['workflow_id']]);
        $totalSteps = (int) ($steps->fetch()['c'] ?? 1);

        if ($step >= $totalSteps) {
            $db->prepare('UPDATE rateb_approval_instances SET status = :st WHERE id = :id')->execute(['st' => 'approved', 'id' => $instanceId]);
        } else {
            $db->prepare('UPDATE rateb_approval_instances SET current_step = current_step + 1 WHERE id = :id')->execute(['id' => $instanceId]);
        }

        return true;
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
