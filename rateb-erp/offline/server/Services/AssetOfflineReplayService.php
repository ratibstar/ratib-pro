<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AssetActivityService;
use Rateb\App\Services\AssetAssignmentService;
use Rateb\App\Services\AssetCommentService;
use Rateb\App\Services\AssetService;
use Rateb\App\Services\AssetTransferService;
use Rateb\App\Services\AssetWorkflowService;
use Rateb\App\Services\ChecklistService;
use Rateb\App\Services\InspectionService;
use Rateb\App\Services\MaintenancePlanService;
use Rateb\App\Services\MaintenanceRequestService;
use Rateb\App\Services\MeterReadingService;
use Rateb\App\Services\WorkOrderService;

/**
 * Thin Assets offline replay (Phase 19B) — delegates ONLY to Phase 19A domain services.
 * Tier-1 drafts only. No delete / payments / approvals / email / SMS / attachments / gov.
 */
final class AssetOfflineReplayService
{
    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'asset.create',
            'asset.update',
            'workflow.transition',
            'assignment.create',
            'transfer.create',
            'maintenance_request.create',
            'maintenance_plan.create',
            'work_order.create',
            'inspection.create',
            'checklist.create',
            'meter_reading.create',
            'comment.create',
            'activity.create',
            'note.create',
            'assets.asset.create',
            'assets.asset.update',
            'assets.workflow.transition',
            'assets.assignment.create',
            'assets.transfer.create',
            'assets.maintenance_request.create',
            'assets.work_order.create',
            'assets.inspection.create',
        ];
    }

    public function __construct(
        private ?AssetOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): AssetOfflineTenantGuard
    {
        return $this->guard ??= new AssetOfflineTenantGuard();
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
            'asset.create', 'asset.update', 'workflow.transition', 'assignment.create', 'transfer.create',
            'maintenance_request.create', 'maintenance_plan.create', 'work_order.create',
            'inspection.create', 'checklist.create', 'meter_reading.create',
            'comment.create', 'activity.create', 'note.create',
        ];
        if (!in_array($action, self::deferredActions(), true)
            && !in_array($this->normalizeAction($action), $canonical, true)) {
            return ['status' => 'skipped', 'error' => 'unknown_assets_action'];
        }
        $action = $this->normalizeAction($action);

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isAssetsEnabled()) {
            return ['status' => 'skipped', 'error' => 'assets_offline_disabled'];
        }
        if (in_array($action, [
            'maintenance_request.create', 'maintenance_plan.create', 'work_order.create',
        ], true) && !$flags->isAssetsMaintenanceEnabled()) {
            return ['status' => 'skipped', 'error' => 'assets_maintenance_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isAssetsWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'assets_workflow_offline_disabled'];
        }
        if (in_array($action, ['inspection.create', 'checklist.create', 'meter_reading.create'], true)
            && !$flags->isAssetsInspectionsEnabled()) {
            return ['status' => 'skipped', 'error' => 'assets_inspections_offline_disabled'];
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
            'asset.create' => $this->assetCreate($scope, $inner, $idempotencyKey),
            'asset.update' => $this->assetUpdate($scope, $inner),
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'assignment.create' => ['ok' => true, 'assignment' => (new AssetAssignmentService())->assign($inner)],
            'transfer.create' => ['ok' => true, 'transfer' => (new AssetTransferService())->create($inner)],
            'maintenance_request.create' => [
                'ok' => true,
                'request' => (new MaintenanceRequestService())->create($inner),
            ],
            'maintenance_plan.create' => [
                'ok' => true,
                'plan' => (new MaintenancePlanService())->create($inner),
            ],
            'work_order.create' => ['ok' => true, 'work_order' => (new WorkOrderService())->create($inner)],
            'inspection.create' => ['ok' => true, 'inspection' => (new InspectionService())->create($inner)],
            'checklist.create' => ['ok' => true, 'checklist' => (new ChecklistService())->create($inner)],
            'meter_reading.create' => [
                'ok' => true,
                'meter_reading' => (new MeterReadingService())->create($inner),
            ],
            'comment.create', 'note.create' => [
                'ok' => true,
                'comment' => (new AssetCommentService())->create($inner),
            ],
            'activity.create' => ['ok' => true, 'activity' => (new AssetActivityService())->create($inner)],
            default => throw new \RuntimeException('unknown_assets_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function assetCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->assetExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'asset_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new AssetService())->create($inner);

        return ['ok' => true, 'asset_id' => $created['id'], 'asset_no' => $created['asset_no']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function assetUpdate(array $scope, array $inner): array
    {
        $assetId = (int) ($inner['asset_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertAsset($assetId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['asset'] ?? [];
        $status = strtolower((string) ($server['workflow_status'] ?? 'draft'));
        if (isset($inner['expected_status'])) {
            $decision = $this->resolver()->resolveAssets(
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
        unset($payload['asset_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new AssetService())->update($assetId, $payload);

        return ['ok' => true, 'asset_id' => $assetId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? 'asset')));
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;
        $wf = new AssetWorkflowService();

        if ($entityType === 'maintenance_request' || $entityType === 'request') {
            $requestId = (int) ($inner['request_id'] ?? $inner['id'] ?? 0);
            $assert = $this->guard()->assertMaintenanceRequest($requestId, $scope);
            if (!$assert['ok']) {
                throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
            }

            return $wf->transitionMaintenanceRequest($requestId, $to, $reason, $expectedVersion);
        }

        if ($entityType === 'work_order' || $entityType === 'wo') {
            $woId = (int) ($inner['work_order_id'] ?? $inner['id'] ?? 0);
            $assert = $this->guard()->assertWorkOrder($woId, $scope);
            if (!$assert['ok']) {
                throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
            }

            return $wf->transitionWorkOrder($woId, $to, $reason, $expectedVersion);
        }

        $assetId = (int) ($inner['asset_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertAsset($assetId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }

        return $wf->transitionAsset($assetId, $to, $reason, $expectedVersion);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, 'assets.')) {
            $action = substr($action, 7);
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
            'asset_not_found',
            'maintenance_request_not_found',
            'work_order_not_found',
            'workflow_transition_denied',
        ], true) || str_starts_with($message, 'assets_conflict');
    }
}
