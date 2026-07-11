<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Project;
use Rateb\App\Models\ProjectStatusHistory;
use Rateb\App\Models\ProjectTask;

/**
 * Project + task lifecycle transitions — sole authority for workflow_status changes.
 * Future Offline Replay (18B) must call transitionProject() / transitionTask() — never mutate status directly.
 */
final class ProjectWorkflowService
{
    public const PROJECT_DRAFT = 'draft';
    public const PROJECT_PLANNED = 'planned';
    public const PROJECT_ACTIVE = 'active';
    public const PROJECT_ON_HOLD = 'on_hold';
    public const PROJECT_COMPLETED = 'completed';
    public const PROJECT_CANCELLED = 'cancelled';
    public const PROJECT_ARCHIVED = 'archived';

    public const TASK_NEW = 'new';
    public const TASK_ASSIGNED = 'assigned';
    public const TASK_IN_PROGRESS = 'in_progress';
    public const TASK_REVIEW = 'review';
    public const TASK_DONE = 'done';
    public const TASK_CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function projectStatuses(): array
    {
        return [
            self::PROJECT_DRAFT,
            self::PROJECT_PLANNED,
            self::PROJECT_ACTIVE,
            self::PROJECT_ON_HOLD,
            self::PROJECT_COMPLETED,
            self::PROJECT_CANCELLED,
            self::PROJECT_ARCHIVED,
        ];
    }

    /** @return list<string> */
    public static function taskStatuses(): array
    {
        return [
            self::TASK_NEW,
            self::TASK_ASSIGNED,
            self::TASK_IN_PROGRESS,
            self::TASK_REVIEW,
            self::TASK_DONE,
            self::TASK_CANCELLED,
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedProjectTransitions(): array
    {
        return [
            self::PROJECT_DRAFT => [self::PROJECT_PLANNED, self::PROJECT_CANCELLED, self::PROJECT_ARCHIVED],
            self::PROJECT_PLANNED => [self::PROJECT_ACTIVE, self::PROJECT_ON_HOLD, self::PROJECT_CANCELLED, self::PROJECT_ARCHIVED],
            self::PROJECT_ACTIVE => [self::PROJECT_ON_HOLD, self::PROJECT_COMPLETED, self::PROJECT_CANCELLED],
            self::PROJECT_ON_HOLD => [self::PROJECT_ACTIVE, self::PROJECT_CANCELLED, self::PROJECT_ARCHIVED],
            self::PROJECT_COMPLETED => [self::PROJECT_ARCHIVED],
            self::PROJECT_CANCELLED => [self::PROJECT_ARCHIVED, self::PROJECT_DRAFT],
            self::PROJECT_ARCHIVED => [],
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedTaskTransitions(): array
    {
        return [
            self::TASK_NEW => [self::TASK_ASSIGNED, self::TASK_IN_PROGRESS, self::TASK_CANCELLED],
            self::TASK_ASSIGNED => [self::TASK_IN_PROGRESS, self::TASK_CANCELLED],
            self::TASK_IN_PROGRESS => [self::TASK_REVIEW, self::TASK_DONE, self::TASK_CANCELLED],
            self::TASK_REVIEW => [self::TASK_IN_PROGRESS, self::TASK_DONE, self::TASK_CANCELLED],
            self::TASK_DONE => [],
            self::TASK_CANCELLED => [self::TASK_NEW],
        ];
    }

    /**
     * @return array{ok: bool, project_id: int, from: string, to: string}
     */
    public function transitionProject(int $projectId, string $toStatus, ?string $reason = null, ?int $expectedVersion = null): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $project = ProjectSupport::assertProject($projectId, $companyId);
        if ($expectedVersion !== null && (int) ($project['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }
        $from = (string) ($project['workflow_status'] ?? self::PROJECT_DRAFT);
        $to = trim($toStatus);
        if (!in_array($to, self::projectStatuses(), true)) {
            throw new \InvalidArgumentException('invalid_project_workflow_status');
        }
        $allowed = self::allowedProjectTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($project['version'] ?? 1) + 1,
        ], ProjectSupport::actorFields(false));
        if ($to === self::PROJECT_ARCHIVED) {
            $update['status'] = 'archived';
        }

        (new Project())->update($projectId, $update);

        (new ProjectStatusHistory())->create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'task_id' => null,
            'entity_type' => 'project',
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => ProjectSupport::userId(),
        ]);

        (new ProjectTimelineService())->record(
            'workflow',
            'Project status: ' . $from . ' → ' . $to,
            $reason,
            $projectId,
            null,
            'project',
            $projectId,
            ['project_id' => $projectId]
        );

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('projects.workflow', 'project', $projectId, [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);
        }

        return ['ok' => true, 'project_id' => $projectId, 'from' => $from, 'to' => $to];
    }

    /**
     * @return array{ok: bool, task_id: int, project_id: int, from: string, to: string}
     */
    public function transitionTask(int $taskId, string $toStatus, ?string $reason = null, ?int $expectedVersion = null): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $task = ProjectSupport::assertTask($taskId, $companyId);
        if ($expectedVersion !== null && (int) ($task['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }
        $from = (string) ($task['workflow_status'] ?? self::TASK_NEW);
        $to = trim($toStatus);
        if (!in_array($to, self::taskStatuses(), true)) {
            throw new \InvalidArgumentException('invalid_task_workflow_status');
        }
        $allowed = self::allowedTaskTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($task['version'] ?? 1) + 1,
        ], ProjectSupport::actorFields(false));
        if ($to === self::TASK_DONE) {
            $update['percent_complete'] = 100.00;
        }

        (new ProjectTask())->update($taskId, $update);
        $projectId = (int) $task['project_id'];

        (new ProjectStatusHistory())->create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'entity_type' => 'task',
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => ProjectSupport::userId(),
        ]);

        (new ProjectTimelineService())->record(
            'task_workflow',
            'Task status: ' . $from . ' → ' . $to,
            $reason,
            $projectId,
            $taskId,
            'task',
            $taskId,
            ['project_id' => $projectId, 'task_id' => $taskId]
        );

        return [
            'ok' => true,
            'task_id' => $taskId,
            'project_id' => $projectId,
            'from' => $from,
            'to' => $to,
        ];
    }
}
