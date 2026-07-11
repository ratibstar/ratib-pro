<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ProjectActivityService;
use Rateb\App\Services\ProjectAssignmentService;
use Rateb\App\Services\ProjectBudgetService;
use Rateb\App\Services\ProjectCommentService;
use Rateb\App\Services\ProjectIssueService;
use Rateb\App\Services\ProjectMilestoneService;
use Rateb\App\Services\ProjectPhaseService;
use Rateb\App\Services\ProjectRiskService;
use Rateb\App\Services\ProjectService;
use Rateb\App\Services\ProjectTaskService;
use Rateb\App\Services\ProjectTimesheetService;
use Rateb\App\Services\ProjectWorkflowService;

/**
 * Thin Projects offline replay (Phase 18B) — delegates ONLY to Phase 18A Project* domain services.
 * Tier-1 drafts only. No delete / payments / approvals / email / SMS / attachments / gov.
 */
final class ProjectOfflineReplayService
{
    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'project.create',
            'project.update',
            'task.create',
            'task.update',
            'workflow.transition',
            'milestone.create',
            'phase.create',
            'comment.create',
            'assignment.create',
            'timesheet.create',
            'issue.create',
            'risk.create',
            'budget.create',
            'activity.create',
            'projects.project.create',
            'projects.project.update',
            'projects.workflow.transition',
            'projects.task.create',
            'projects.task.update',
        ];
    }

    public function __construct(
        private ?ProjectOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): ProjectOfflineTenantGuard
    {
        return $this->guard ??= new ProjectOfflineTenantGuard();
    }

    private function resolver(): OfflineConflictResolverService
    {
        return $this->resolver ??= new OfflineConflictResolverService();
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array{status: string, error?: string, result?: array<string, mixed>, reason?: string}
     */
    public function replayFromQueueRow(array $queueRow): array
    {
        $decoded = $this->decodePayload($queueRow);
        $action = $this->normalizeAction(
            (string) ($decoded['action'] ?? $queueRow['action'] ?? '')
        );
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        unset($inner['branch_id'], $inner['company_id'], $inner['user_id'], $inner['device_id']);
        $idempotencyKey = substr(trim((string) (
            $queueRow['idempotency_key']
            ?? $decoded['client_id']
            ?? $decoded['idempotency_key']
            ?? ''
        )), 0, 64);

        if (!in_array($action, self::deferredActions(), true)
            && !in_array($this->normalizeAction($action), [
                'project.create', 'project.update', 'task.create', 'task.update', 'workflow.transition',
                'milestone.create', 'phase.create', 'comment.create', 'assignment.create',
                'timesheet.create', 'issue.create', 'risk.create', 'budget.create', 'activity.create',
            ], true)) {
            return ['status' => 'skipped', 'error' => 'unknown_projects_action'];
        }
        $action = $this->normalizeAction($action);

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isProjectsEnabled()) {
            return ['status' => 'skipped', 'error' => 'projects_offline_disabled'];
        }
        if (in_array($action, ['task.create', 'task.update'], true) && !$flags->isProjectsTasksEnabled()) {
            return ['status' => 'skipped', 'error' => 'projects_tasks_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isProjectsWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'projects_workflow_offline_disabled'];
        }
        if ($action === 'timesheet.create' && !$flags->isProjectsTimesheetsEnabled()) {
            return ['status' => 'skipped', 'error' => 'projects_timesheets_offline_disabled'];
        }

        try {
            $scope = (new OfflineReplayScopeService())->fromQueueRow($queueRow);
            if ($scope['company_id'] < 1) {
                return ['status' => 'failed', 'error' => 'company_required'];
            }

            TenantContext::setCompanyId($scope['company_id']);
            if ($scope['user_id'] > 0) {
                SessionManager::set('rateb_user_id', $scope['user_id']);
            }

            $result = $this->replay($action, $scope, $inner, $idempotencyKey);

            return ['status' => 'synced', 'result' => $result];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($this->isConflictError($message)) {
                return ['status' => 'conflict', 'error' => $message, 'reason' => $message];
            }

            return ['status' => 'failed', 'error' => $message];
        }
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function replay(string $action, array $scope, array $inner, string $idempotencyKey): array
    {
        return match ($action) {
            'project.create' => $this->projectCreate($scope, $inner, $idempotencyKey),
            'project.update' => $this->projectUpdate($scope, $inner),
            'task.create' => $this->taskCreate($scope, $inner),
            'task.update' => $this->taskUpdate($scope, $inner),
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'milestone.create' => ['ok' => true, 'milestone' => (new ProjectMilestoneService())->create($inner)],
            'phase.create' => ['ok' => true, 'phase' => (new ProjectPhaseService())->create($inner)],
            'comment.create' => ['ok' => true, 'comment' => (new ProjectCommentService())->create($inner)],
            'assignment.create' => ['ok' => true, 'assignment' => (new ProjectAssignmentService())->assign($inner)],
            'timesheet.create' => ['ok' => true, 'timesheet' => (new ProjectTimesheetService())->create($inner)],
            'issue.create' => ['ok' => true, 'issue' => (new ProjectIssueService())->create($inner)],
            'risk.create' => ['ok' => true, 'risk' => (new ProjectRiskService())->create($inner)],
            'budget.create' => ['ok' => true, 'budget' => (new ProjectBudgetService())->create($inner)],
            'activity.create' => ['ok' => true, 'activity' => (new ProjectActivityService())->create($inner)],
            default => throw new \RuntimeException('unknown_projects_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function projectCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->projectExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'project_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new ProjectService())->create($inner);

        return ['ok' => true, 'project_id' => $created['id'], 'project_no' => $created['project_no']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function projectUpdate(array $scope, array $inner): array
    {
        $projectId = (int) ($inner['project_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertProject($projectId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['project'] ?? [];
        $status = strtolower((string) ($server['workflow_status'] ?? 'draft'));
        if (isset($inner['expected_status'])) {
            $decision = $this->resolver()->resolveProjects(
                [
                    'version' => (int) ($inner['version'] ?? $inner['expected_version'] ?? 1),
                    'expected_status' => $inner['expected_status'],
                ],
                [
                    'version' => (int) ($server['version'] ?? $inner['server_version'] ?? 0),
                    'status' => $status,
                ]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
            }
        }
        $payload = $inner;
        unset($payload['project_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new ProjectService())->update($projectId, $payload);

        return ['ok' => true, 'project_id' => $projectId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function taskCreate(array $scope, array $inner): array
    {
        $projectId = (int) ($inner['project_id'] ?? 0);
        $assert = $this->guard()->assertProject($projectId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $created = (new ProjectTaskService())->create($inner);

        return ['ok' => true, 'task_id' => $created['id'], 'task_no' => $created['task_no']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function taskUpdate(array $scope, array $inner): array
    {
        $taskId = (int) ($inner['task_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertTask($taskId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['task'] ?? [];
        $status = strtolower((string) ($server['workflow_status'] ?? 'new'));
        if (isset($inner['expected_status'])) {
            $decision = $this->resolver()->resolveProjects(
                [
                    'version' => (int) ($inner['version'] ?? $inner['expected_version'] ?? 1),
                    'expected_status' => $inner['expected_status'],
                ],
                [
                    'version' => (int) ($server['version'] ?? $inner['server_version'] ?? 0),
                    'status' => $status,
                ]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
            }
        }
        $payload = $inner;
        unset($payload['task_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new ProjectTaskService())->update($taskId, $payload);

        return ['ok' => true, 'task_id' => $taskId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? 'project')));
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        if ($entityType === 'task') {
            $taskId = (int) ($inner['task_id'] ?? $inner['id'] ?? 0);
            $assert = $this->guard()->assertTask($taskId, $scope);
            if (!$assert['ok']) {
                throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
            }

            return (new ProjectWorkflowService())->transitionTask($taskId, $to, $reason, $expectedVersion);
        }

        $projectId = (int) ($inner['project_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertProject($projectId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }

        return (new ProjectWorkflowService())->transitionProject($projectId, $to, $reason, $expectedVersion);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, 'projects.')) {
            $action = substr($action, 9);
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $payload = $queueRow['payload'] ?? null;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'status_changed',
            'server_newer',
            'version_conflict',
            'tenant_mismatch',
            'branch_mismatch',
            'project_not_found',
            'task_not_found',
            'workflow_transition_denied',
        ], true) || str_starts_with($message, 'projects_conflict');
    }
}
