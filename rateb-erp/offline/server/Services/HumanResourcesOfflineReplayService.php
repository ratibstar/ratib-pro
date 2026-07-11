<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\CompetencyService;
use Rateb\App\Services\DepartmentService;
use Rateb\App\Services\EmployeeCommentService;
use Rateb\App\Services\EmployeeProfileService;
use Rateb\App\Services\GoalService;
use Rateb\App\Services\HrmAssignmentService;
use Rateb\App\Services\HumanResourcesWorkflowService;
use Rateb\App\Services\OrganizationService;
use Rateb\App\Services\PerformanceReviewService;
use Rateb\App\Services\PositionService;
use Rateb\App\Services\PromotionService;
use Rateb\App\Services\TrainingService;
use Rateb\App\Services\TransferService;

/**
 * Thin Enterprise HRMS offline replay (Phase 23B) — delegates ONLY to Phase 23A domain services.
 * Additive to Phase 4 HrOfflineReplayService (attendance/leave). Module remains `hr`.
 * No delete / attendance / leave / payroll / payments / approvals / email / SMS / gov / binary.
 */
final class HumanResourcesOfflineReplayService
{
    private const PREFIX = 'hr.';

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $bare = [
            'employee.create',
            'employee.update',
            'department.create',
            'position.create',
            'organization.create',
            'training.create',
            'performance.create',
            'goal.create',
            'competency.create',
            'promotion.create',
            'transfer.create',
            'assignment.create',
            'workflow.transition',
            'comment.create',
            'note.create',
        ];
        $out = $bare;
        foreach ($bare as $a) {
            $out[] = self::PREFIX . $a;
        }

        return $out;
    }

    /** True when action belongs to Phase 23B enterprise HRMS (not Phase 4 attendance/leave). */
    public static function isEnterpriseAction(string $action): bool
    {
        $action = trim($action);
        if (in_array($action, self::deferredActions(), true)) {
            return true;
        }
        $aliases = [
            'create_employee', 'update_employee', 'create_department', 'create_position',
            'create_organization', 'create_org_unit', 'create_training', 'create_performance',
            'create_goal', 'create_competency', 'create_promotion', 'create_transfer',
            'create_assignment', 'create_comment', 'create_note', 'transition_workflow',
        ];

        return in_array($action, $aliases, true);
    }

    public function __construct(
        private ?HumanResourcesOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): HumanResourcesOfflineTenantGuard
    {
        return $this->guard ??= new HumanResourcesOfflineTenantGuard();
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
            && !in_array($this->normalizeAction($action), self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_hrm_action'];
        }
        $action = $this->normalizeAction($action);

        if ($this->isRejectedAction($action)) {
            return ['status' => 'skipped', 'error' => 'hrm_action_not_allowed'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isHumanResourcesEnabled()) {
            return ['status' => 'skipped', 'error' => 'hrm_offline_disabled'];
        }
        if (in_array($action, [
            'employee.create', 'employee.update',
            'department.create', 'position.create', 'organization.create',
        ], true) && !$flags->isHumanResourcesEmployeeEnabled()) {
            return ['status' => 'skipped', 'error' => 'hrm_employee_offline_disabled'];
        }
        if (in_array($action, ['training.create'], true)
            && !$flags->isHumanResourcesTrainingEnabled()) {
            return ['status' => 'skipped', 'error' => 'hrm_training_offline_disabled'];
        }
        if (in_array($action, ['performance.create', 'goal.create', 'competency.create'], true)
            && !$flags->isHumanResourcesPerformanceEnabled()) {
            return ['status' => 'skipped', 'error' => 'hrm_performance_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isHumanResourcesWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'hrm_workflow_offline_disabled'];
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
            'employee.create' => $this->employeeCreate($scope, $inner, $idempotencyKey),
            'employee.update' => $this->employeeUpdate($scope, $inner),
            'department.create' => $this->departmentCreate($scope, $inner, $idempotencyKey),
            'position.create' => [
                'ok' => true,
                'position' => (new PositionService())->create($inner),
            ],
            'organization.create' => $this->organizationCreate($inner),
            'training.create' => [
                'ok' => true,
                'training' => (new TrainingService())->create($inner),
            ],
            'performance.create' => [
                'ok' => true,
                'performance' => (new PerformanceReviewService())->create($inner),
            ],
            'goal.create' => [
                'ok' => true,
                'goal' => (new GoalService())->create($inner),
            ],
            'competency.create' => [
                'ok' => true,
                'competency' => (new CompetencyService())->create($inner),
            ],
            'promotion.create' => [
                'ok' => true,
                'promotion' => (new PromotionService())->create($inner),
            ],
            'transfer.create' => [
                'ok' => true,
                'transfer' => (new TransferService())->create($inner),
            ],
            'assignment.create' => [
                'ok' => true,
                'assignment' => (new HrmAssignmentService())->create($inner),
            ],
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'comment.create' => [
                'ok' => true,
                'comment' => (new EmployeeCommentService())->create($inner),
            ],
            'note.create' => [
                'ok' => true,
                'note' => (new EmployeeCommentService())->createNote($inner),
            ],
            default => throw new \RuntimeException('unknown_hrm_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function employeeCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->profileExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'employee_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new EmployeeProfileService())->create($inner);

        return ['ok' => true, 'employee_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function employeeUpdate(array $scope, array $inner): array
    {
        $profileId = (int) ($inner['employee_id'] ?? $inner['profile_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertProfile($profileId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['profile'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset(
            $payload['employee_id'],
            $payload['profile_id'],
            $payload['id'],
            $payload['expected_status'],
            $payload['server_version'],
            $payload['workflow_status']
        );
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new EmployeeProfileService())->update($profileId, $payload);

        return ['ok' => true, 'employee_id' => $profileId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function departmentCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->departmentExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'department_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new DepartmentService())->create($inner);

        return ['ok' => true, 'department_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function organizationCreate(array $inner): array
    {
        $type = strtolower(trim((string) ($inner['type'] ?? $inner['entity_type'] ?? 'org_unit')));
        $payload = $inner;
        unset($payload['type'], $payload['entity_type']);
        $org = new OrganizationService();
        if (in_array($type, ['location', 'locations'], true)) {
            $created = $org->createLocation($payload);

            return ['ok' => true, 'location_id' => $created['id'], 'code' => $created['code'], 'type' => 'location'];
        }
        $created = $org->createOrgUnit($payload);

        return ['ok' => true, 'org_unit_id' => $created['id'], 'code' => $created['code'], 'type' => 'org_unit'];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? '')));
        if ($entityType === '') {
            throw new \InvalidArgumentException('entity_type_required');
        }
        if (!in_array($entityType, HumanResourcesWorkflowService::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_hrm_entity_type');
        }
        $id = (int) ($inner['entity_id'] ?? $inner['id'] ?? 0);
        $assert = match ($entityType) {
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE => $this->guard()->assertProfile($id, $scope),
            HumanResourcesWorkflowService::ENTITY_TRAINING => $this->guard()->assertTraining($id, $scope),
            HumanResourcesWorkflowService::ENTITY_PERFORMANCE => $this->guard()->assertPerformance($id, $scope),
            default => ['ok' => false, 'error' => 'invalid_hrm_entity_type'],
        };
        if (!($assert['ok'] ?? false)) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        return (new HumanResourcesWorkflowService())->transition($entityType, $id, $to, $reason, $expectedVersion);
    }

    /**
     * @param array<string, mixed> $inner
     * @param array<string, mixed> $server
     */
    private function maybeConflict(array $inner, array $server): void
    {
        if (!isset($inner['expected_status'])) {
            return;
        }
        $status = strtolower((string) ($server['workflow_status'] ?? $server['status'] ?? 'draft'));
        $decision = $this->resolver()->resolveHumanResources(
            [
                'version' => (int) ($inner['version'] ?? $inner['expected_version'] ?? 1),
                'expected_status' => $inner['expected_status'],
            ],
            [
                'version' => (int) ($server['version'] ?? $inner['server_version'] ?? 0),
                'status' => $status,
                'workflow_status' => $status,
            ]
        );
        if (($decision['action'] ?? '') === 'reject_client') {
            throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
        }
    }

    private function isRejectedAction(string $action): bool
    {
        $lower = strtolower($action);
        foreach ([
            'delete', 'attendance', 'leave', 'payroll', 'payment', 'approval',
            'email', 'sms', 'gov', 'binary', 'upload', 'attachment',
        ] as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, self::PREFIX)) {
            $bare = substr($action, strlen(self::PREFIX));
            // Keep Phase 4 attendance/leave under hr.* out of this service's bare set.
            if (in_array($bare, [
                'employee.create', 'employee.update', 'department.create', 'position.create',
                'organization.create', 'training.create', 'performance.create', 'goal.create',
                'competency.create', 'promotion.create', 'transfer.create', 'assignment.create',
                'workflow.transition', 'comment.create', 'note.create',
            ], true)) {
                return $bare;
            }
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
            'workflow_transition_denied',
        ], true);
    }
}
