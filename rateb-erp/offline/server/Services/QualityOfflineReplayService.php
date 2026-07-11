<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\QualityAssignmentService;
use Rateb\App\Services\QualityAuditService;
use Rateb\App\Services\QualityChecklistService;
use Rateb\App\Services\QualityCommentService;
use Rateb\App\Services\QualityComplaintService;
use Rateb\App\Services\QualityDefectService;
use Rateb\App\Services\QualityInspectionService;
use Rateb\App\Services\QualityNonconformityService;
use Rateb\App\Services\QualityTimelineService;
use Rateb\App\Services\QualityWorkflowService;
use Rateb\App\Services\QmsCorrectiveActionService;
use Rateb\App\Services\QmsPreventiveActionService;
use Rateb\App\Services\SupplierQualityService;

/**
 * Thin Quality offline replay (Phase 25B) — delegates ONLY to Phase 25A domain services.
 * Tier-1 drafts only. No delete / attachments / binary / notifications / email / SMS /
 * payments / government / approvals / inventory posting / GL posting.
 *
 * Corrective/Preventive map to QmsCorrectiveActionService / QmsPreventiveActionService (25A names).
 */
final class QualityOfflineReplayService
{
    private const PREFIX = 'quality.';

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $bare = [
            'inspection.create',
            'inspection.update',
            'checklist.create',
            'audit.create',
            'defect.create',
            'nonconformity.create',
            'corrective_action.create',
            'preventive_action.create',
            'supplier_quality.create',
            'complaint.create',
            'assignment.create',
            'comment.create',
            'workflow.transition',
            'note.create',
        ];
        $out = $bare;
        foreach ($bare as $a) {
            $out[] = self::PREFIX . $a;
        }

        return $out;
    }

    public function __construct(
        private ?QualityOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): QualityOfflineTenantGuard
    {
        return $this->guard ??= new QualityOfflineTenantGuard();
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
            return ['status' => 'skipped', 'error' => 'unknown_quality_action'];
        }
        $action = $this->normalizeAction($action);

        if (in_array($action, [
            'delete', 'attachment.create', 'upload', 'payment.create', 'bank_transfer',
            'accounting.post', 'inventory.post', 'notification.send', 'email.send', 'sms.send',
            'government.submit', 'approval.decide',
        ], true)) {
            return ['status' => 'skipped', 'error' => 'quality_action_rejected'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isQualityEnabled()) {
            return ['status' => 'skipped', 'error' => 'quality_offline_disabled'];
        }
        if (in_array($action, ['inspection.create', 'inspection.update', 'checklist.create'], true)
            && !$flags->isQualityInspectionsEnabled()) {
            return ['status' => 'skipped', 'error' => 'quality_inspections_offline_disabled'];
        }
        if ($action === 'audit.create' && !$flags->isQualityAuditEnabled()) {
            return ['status' => 'skipped', 'error' => 'quality_audit_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isQualityWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'quality_workflow_offline_disabled'];
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
            'inspection.create' => $this->inspectionCreate($scope, $inner, $idempotencyKey),
            'inspection.update' => $this->inspectionUpdate($scope, $inner),
            'checklist.create' => $this->checklistCreate($scope, $inner, $idempotencyKey),
            'audit.create' => ['ok' => true, 'audit' => (new QualityAuditService())->create($this->stampNotes($inner, $idempotencyKey))],
            'defect.create' => ['ok' => true, 'defect' => (new QualityDefectService())->create($this->stampNotes($inner, $idempotencyKey))],
            'nonconformity.create' => ['ok' => true, 'nonconformity' => (new QualityNonconformityService())->create($this->stampNotes($inner, $idempotencyKey))],
            'corrective_action.create' => ['ok' => true, 'corrective_action' => (new QmsCorrectiveActionService())->create($this->stampNotes($inner, $idempotencyKey))],
            'preventive_action.create' => ['ok' => true, 'preventive_action' => (new QmsPreventiveActionService())->create($this->stampNotes($inner, $idempotencyKey))],
            'supplier_quality.create' => ['ok' => true, 'supplier_quality' => (new SupplierQualityService())->create($this->stampNotes($inner, $idempotencyKey))],
            'complaint.create' => ['ok' => true, 'complaint' => (new QualityComplaintService())->create($this->stampNotes($inner, $idempotencyKey))],
            'assignment.create' => ['ok' => true, 'assignment' => (new QualityAssignmentService())->create($this->stampNotes($inner, $idempotencyKey))],
            'comment.create' => ['ok' => true, 'comment' => (new QualityCommentService())->create($inner)],
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'note.create' => $this->noteCreate($inner),
            default => throw new \RuntimeException('unknown_quality_action'),
        };
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function stampNotes(array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey === '') {
            return $inner;
        }
        $notes = trim((string) ($inner['notes'] ?? ''));
        $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');

        return $inner;
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function inspectionCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->inspectionExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'inspection_id' => $existing, 'duplicate_replay' => true];
            }
            $inner = $this->stampNotes($inner, $idempotencyKey);
        }
        $created = (new QualityInspectionService())->create($inner);

        return ['ok' => true, 'inspection_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function inspectionUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['inspection_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertInspection($id, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['inspection'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['inspection_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new QualityInspectionService())->update($id, $payload);

        return ['ok' => true, 'inspection_id' => $id];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function checklistCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->checklistExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'checklist_id' => $existing, 'duplicate_replay' => true];
            }
            $inner = $this->stampNotes($inner, $idempotencyKey);
        }
        $created = (new QualityChecklistService())->create($inner);

        return ['ok' => true, 'checklist_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? QualityWorkflowService::ENTITY_INSPECTION)));
        if ($entityType === '') {
            $entityType = QualityWorkflowService::ENTITY_INSPECTION;
        }
        if (!in_array($entityType, QualityWorkflowService::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_qms_entity_type');
        }
        $id = (int) ($inner['entity_id'] ?? $inner['inspection_id'] ?? $inner['id'] ?? 0);
        if ($entityType === QualityWorkflowService::ENTITY_INSPECTION) {
            $assert = $this->guard()->assertInspection($id, $scope);
        } elseif ($entityType === QualityWorkflowService::ENTITY_CORRECTIVE) {
            $assert = $this->guard()->assertCorrectiveAction($id, $scope);
        } else {
            $assert = ['ok' => $id > 0];
        }
        if (!($assert['ok'] ?? false)) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        // Offline may only advance early statuses (never approve/complete/verify/close).
        $allowedOffline = match ($entityType) {
            QualityWorkflowService::ENTITY_INSPECTION, QualityWorkflowService::ENTITY_AUDIT => [
                'planned', 'scheduled', 'archived',
            ],
            QualityWorkflowService::ENTITY_CORRECTIVE, QualityWorkflowService::ENTITY_PREVENTIVE => [
                'draft', 'assigned', 'archived',
            ],
            default => [],
        };
        if (!in_array($to, $allowedOffline, true)) {
            throw new \RuntimeException('quality_workflow_offline_denied');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        return (new QualityWorkflowService())->transition($entityType, $id, $to, $reason, $expectedVersion);
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
        (new QualityTimelineService())->record(
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
        $status = strtolower((string) ($server['workflow_status'] ?? $server['status'] ?? 'planned'));
        $decision = $this->resolver()->resolveQuality(
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
            'inspection_not_editable',
        ], true);
    }
}
