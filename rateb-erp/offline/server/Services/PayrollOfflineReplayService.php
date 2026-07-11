<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AdvanceService;
use Rateb\App\Services\BonusService;
use Rateb\App\Services\EmployeeSalaryService;
use Rateb\App\Services\LoanService;
use Rateb\App\Services\OvertimeService;
use Rateb\App\Services\PayrollBatchService;
use Rateb\App\Services\PayrollCommentService;
use Rateb\App\Services\PayrollStructureService;
use Rateb\App\Services\PayrollTimelineService;
use Rateb\App\Services\PayrollWorkflowService;
use Rateb\App\Services\SettlementService;

/**
 * Thin Payroll offline replay (Phase 24B) — delegates ONLY to Phase 24A domain services.
 * Tier-1 drafts only. No delete / calculate / approve / post / payments / GL / attendance import / leave / notifications / binary.
 */
final class PayrollOfflineReplayService
{
    private const PREFIX = 'payroll.';

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $bare = [
            'salary_structure.create',
            'salary_structure.update',
            'employee_salary.create',
            'employee_salary.update',
            'payroll_batch.create',
            'payroll_batch.update',
            'workflow.transition',
            'loan.create',
            'advance.create',
            'bonus.create',
            'overtime.create',
            'settlement.create',
            'comment.create',
            'note.create',
        ];
        $out = $bare;
        foreach ($bare as $a) {
            $out[] = self::PREFIX . $a;
        }

        return $out;
    }

    public function __construct(
        private ?PayrollOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): PayrollOfflineTenantGuard
    {
        return $this->guard ??= new PayrollOfflineTenantGuard();
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
            return ['status' => 'skipped', 'error' => 'unknown_payroll_action'];
        }
        $action = $this->normalizeAction($action);

        // Explicit reject list — never calculate/approve/post inside replay.
        if (in_array($action, [
            'delete', 'calculate', 'approve', 'post', 'payment.create', 'bank_transfer',
            'accounting.post', 'attendance.import', 'leave.approve',
        ], true)) {
            return ['status' => 'skipped', 'error' => 'payroll_action_rejected'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isPayrollEnabled()) {
            return ['status' => 'skipped', 'error' => 'payroll_offline_disabled'];
        }
        if (in_array($action, [
            'salary_structure.create', 'salary_structure.update',
            'employee_salary.create', 'employee_salary.update',
        ], true) && !$flags->isPayrollEmployeeEnabled()) {
            return ['status' => 'skipped', 'error' => 'payroll_employee_offline_disabled'];
        }
        if (in_array($action, ['payroll_batch.create', 'payroll_batch.update'], true)
            && !$flags->isPayrollBatchEnabled()) {
            return ['status' => 'skipped', 'error' => 'payroll_batch_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isPayrollWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'payroll_workflow_offline_disabled'];
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
            'salary_structure.create' => $this->structureCreate($scope, $inner, $idempotencyKey),
            'salary_structure.update' => $this->structureUpdate($scope, $inner),
            'employee_salary.create' => $this->employeeSalaryCreate($scope, $inner, $idempotencyKey),
            'employee_salary.update' => $this->employeeSalaryUpdate($scope, $inner),
            'payroll_batch.create' => $this->batchCreate($scope, $inner, $idempotencyKey),
            'payroll_batch.update' => $this->batchUpdate($scope, $inner),
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'loan.create' => ['ok' => true, 'loan' => (new LoanService())->create($inner)],
            'advance.create' => ['ok' => true, 'advance' => (new AdvanceService())->create($inner)],
            'bonus.create' => ['ok' => true, 'bonus' => (new BonusService())->create($inner)],
            'overtime.create' => ['ok' => true, 'overtime' => (new OvertimeService())->create($inner)],
            'settlement.create' => ['ok' => true, 'settlement' => (new SettlementService())->create($inner)],
            'comment.create' => ['ok' => true, 'comment' => (new PayrollCommentService())->create($inner)],
            'note.create' => $this->noteCreate($inner),
            default => throw new \RuntimeException('unknown_payroll_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function structureCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->structureExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'structure_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new PayrollStructureService())->create($inner);

        return ['ok' => true, 'structure_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function structureUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['structure_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertStructure($id, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['structure'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['structure_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new PayrollStructureService())->update($id, $payload);

        return ['ok' => true, 'structure_id' => $id];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function employeeSalaryCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->employeeSalaryExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'employee_salary_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new EmployeeSalaryService())->create($inner);

        return ['ok' => true, 'employee_salary_id' => $created['id']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function employeeSalaryUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['employee_salary_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertEmployeeSalary($id, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['employee_salary'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['employee_salary_id'], $payload['id'], $payload['expected_status'], $payload['server_version']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new EmployeeSalaryService())->update($id, $payload);

        return ['ok' => true, 'employee_salary_id' => $id];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function batchCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->batchExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'batch_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new PayrollBatchService())->create($inner);

        return ['ok' => true, 'batch_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function batchUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['batch_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertBatch($id, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['batch'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['batch_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new PayrollBatchService())->update($id, $payload);

        return ['ok' => true, 'batch_id' => $id];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? PayrollWorkflowService::ENTITY_BATCH)));
        if ($entityType === '') {
            $entityType = PayrollWorkflowService::ENTITY_BATCH;
        }
        if (!in_array($entityType, PayrollWorkflowService::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_payroll_entity_type');
        }
        $id = (int) ($inner['entity_id'] ?? $inner['batch_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertBatch($id, $scope);
        if (!($assert['ok'] ?? false)) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        // Offline may only advance draft → prepared (never approve/post/calculate).
        if (!in_array($to, ['prepared', 'draft', 'archived'], true)) {
            throw new \RuntimeException('payroll_workflow_offline_denied');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        return (new PayrollWorkflowService())->transition($entityType, $id, $to, $reason, $expectedVersion);
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function noteCreate(array $inner): array
    {
        $title = trim((string) ($inner['title'] ?? $inner['event_type'] ?? 'Offline note'));
        $body = isset($inner['body']) ? (string) $inner['body'] : (isset($inner['notes']) ? (string) $inner['notes'] : null);
        $entityType = isset($inner['entity_type']) ? (string) $inner['entity_type'] : null;
        $entityId = isset($inner['entity_id']) ? (int) $inner['entity_id'] : null;
        (new PayrollTimelineService())->record(
            'offline_note',
            $title !== '' ? $title : 'Offline note',
            $entityType,
            $entityId > 0 ? $entityId : null,
            $body
        );

        return ['ok' => true, 'note' => true];
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
        $decision = $this->resolver()->resolvePayroll(
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

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, self::PREFIX)) {
            $action = substr($action, strlen(self::PREFIX));
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
            'batch_not_editable',
        ], true);
    }
}
