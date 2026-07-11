<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ApprovalCommentService;
use Rateb\App\Services\ApprovalDelegationService;
use Rateb\App\Services\ApprovalRequestService;
use Rateb\App\Services\ApprovalWorkflowService;

/**
 * Thin Approval offline replay (Phase 20B) — delegates ONLY to Phase 20A domain services.
 * Tier-1 drafts only. No approve / reject / escalate / notifications / attachments / email / SMS / payments / gov.
 * Distinct from legacy WorkflowService.
 */
final class ApprovalOfflineReplayService
{
    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'approval_request.create',
            'approval_request.update',
            'workflow.transition',
            'comment.create',
            'delegation.create',
            'note.create',
            'approval.approval_request.create',
            'approval.approval_request.update',
            'approval.workflow.transition',
            'approval.comment.create',
            'approval.delegation.create',
            'approval.note.create',
        ];
    }

    public function __construct(
        private ?ApprovalOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): ApprovalOfflineTenantGuard
    {
        return $this->guard ??= new ApprovalOfflineTenantGuard();
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

        $canonical = [
            'approval_request.create', 'approval_request.update', 'workflow.transition',
            'comment.create', 'delegation.create', 'note.create',
        ];
        if (!in_array($action, self::deferredActions(), true)
            && !in_array($this->normalizeAction($action), $canonical, true)) {
            return ['status' => 'skipped', 'error' => 'unknown_approval_action'];
        }
        $action = $this->normalizeAction($action);

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isApprovalEnabled()) {
            return ['status' => 'skipped', 'error' => 'approval_offline_disabled'];
        }
        if (in_array($action, ['approval_request.create', 'approval_request.update'], true)
            && !$flags->isApprovalRequestsEnabled()) {
            return ['status' => 'skipped', 'error' => 'approval_requests_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isApprovalWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'approval_workflow_offline_disabled'];
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
            'approval_request.create' => $this->requestCreate($scope, $inner, $idempotencyKey),
            'approval_request.update' => $this->requestUpdate($scope, $inner),
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'comment.create', 'note.create' => [
                'ok' => true,
                'comment' => (new ApprovalCommentService())->create($inner),
            ],
            'delegation.create' => [
                'ok' => true,
                'delegation' => (new ApprovalDelegationService())->create($inner),
            ],
            default => throw new \RuntimeException('unknown_approval_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function requestCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->requestExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'request_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new ApprovalRequestService())->create($inner);

        return ['ok' => true, 'request_id' => $created['id'], 'request_no' => $created['request_no']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function requestUpdate(array $scope, array $inner): array
    {
        $requestId = (int) ($inner['request_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertRequest($requestId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['request'] ?? [];
        $status = strtolower((string) ($server['workflow_status'] ?? 'draft'));
        if (isset($inner['expected_status'])) {
            $decision = $this->resolver()->resolveApproval(
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
        unset(
            $payload['request_id'],
            $payload['id'],
            $payload['expected_status'],
            $payload['server_version'],
            $payload['workflow_status']
        );
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new ApprovalRequestService())->update($requestId, $payload);

        return ['ok' => true, 'request_id' => $requestId];
    }

    /**
     * Offline workflow: submit/cancel/archive paths only.
     * Terminal decision statuses remain ONLINE ONLY.
     *
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $requestId = (int) ($inner['request_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertRequest($requestId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = strtolower(trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? '')));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        if (in_array($to, [
            ApprovalWorkflowService::APPROVED,
            ApprovalWorkflowService::REJECTED,
        ], true)) {
            throw new \RuntimeException('approval_decision_online_only');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        return (new ApprovalWorkflowService())->transition($requestId, $to, $reason, $expectedVersion);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, 'approval.')) {
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
            'request_not_found',
            'workflow_transition_denied',
            'approval_decision_online_only',
        ], true) || str_starts_with($message, 'approval_conflict');
    }
}
