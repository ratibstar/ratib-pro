<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\Project;
use Rateb\App\Models\ProjectTask;

/**
 * Tenant + branch isolation for Projects offline replay (Phase 18B).
 * Additive — does not alter Projects Online domain services.
 */
final class ProjectOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, project?: array<string, mixed>}
     */
    public function assertProject(int $projectId, array $scope): array
    {
        if ($projectId < 1) {
            return ['ok' => false, 'error' => 'invalid_project_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new Project())->queryOne(
            'SELECT * FROM rateb_projects WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $projectId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'project_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'project' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, task?: array<string, mixed>}
     */
    public function assertTask(int $taskId, array $scope): array
    {
        if ($taskId < 1) {
            return ['ok' => false, 'error' => 'invalid_task_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new ProjectTask())->queryOne(
            'SELECT * FROM rateb_project_tasks WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $taskId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'task_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'task' => $row];
    }

    public function projectExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = (new Project())->queryOne(
            'SELECT id FROM rateb_projects
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }
}
