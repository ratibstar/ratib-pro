<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\BomService;
use Rateb\App\Services\FinishedGoodsReceiptService;
use Rateb\App\Services\ManufacturingAssignmentService;
use Rateb\App\Services\ManufacturingCommentService;
use Rateb\App\Services\ManufacturingWorkflowService;
use Rateb\App\Services\MaterialConsumptionService;
use Rateb\App\Services\MaterialReservationService;
use Rateb\App\Services\MfgWorkOrderService;
use Rateb\App\Services\ProductionCostService;
use Rateb\App\Services\ProductionOrderService;
use Rateb\App\Services\QualityCheckService;
use Rateb\App\Services\RoutingService;
use Rateb\App\Services\ScrapRecordingService;

/**
 * Thin Manufacturing offline replay (Phase 22B) — delegates ONLY to Phase 22A domain services.
 * Tier-1 drafts only. No delete / inventory posting / GL / payments / approvals / email / SMS / gov / binary.
 */
final class ManufacturingOfflineReplayService
{
    private const PREFIX = 'manufacturing.';

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $bare = [
            'bom.create',
            'bom.update',
            'routing.create',
            'routing.update',
            'production_order.create',
            'production_order.update',
            'work_order.create',
            'work_order.update',
            'workflow.transition',
            'material_reservation.create',
            'material_consumption.create',
            'finished_goods.create',
            'scrap.create',
            'quality_check.create',
            'cost.create',
            'assignment.create',
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
        private ?ManufacturingOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): ManufacturingOfflineTenantGuard
    {
        return $this->guard ??= new ManufacturingOfflineTenantGuard();
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
            return ['status' => 'skipped', 'error' => 'unknown_manufacturing_action'];
        }
        $action = $this->normalizeAction($action);

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isManufacturingEnabled()) {
            return ['status' => 'skipped', 'error' => 'manufacturing_offline_disabled'];
        }
        if (in_array($action, [
            'bom.create', 'bom.update',
            'routing.create', 'routing.update',
            'production_order.create', 'production_order.update',
            'work_order.create', 'work_order.update',
            'material_reservation.create', 'material_consumption.create',
            'finished_goods.create', 'scrap.create',
        ], true) && !$flags->isManufacturingProductionEnabled()) {
            return ['status' => 'skipped', 'error' => 'manufacturing_production_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isManufacturingWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'manufacturing_workflow_offline_disabled'];
        }
        if (in_array($action, ['quality_check.create'], true)
            && !$flags->isManufacturingQualityEnabled()) {
            return ['status' => 'skipped', 'error' => 'manufacturing_quality_offline_disabled'];
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
            'bom.create' => $this->bomCreate($scope, $inner, $idempotencyKey),
            'bom.update' => $this->bomUpdate($scope, $inner),
            'routing.create' => ['ok' => true, 'routing' => (new RoutingService())->create($inner)],
            'routing.update' => $this->routingUpdate($scope, $inner),
            'production_order.create' => $this->productionOrderCreate($scope, $inner, $idempotencyKey),
            'production_order.update' => $this->productionOrderUpdate($scope, $inner),
            'work_order.create' => $this->workOrderCreate($scope, $inner, $idempotencyKey),
            'work_order.update' => $this->workOrderUpdate($scope, $inner),
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'material_reservation.create' => [
                'ok' => true,
                'reservation' => (new MaterialReservationService())->create($inner),
            ],
            'material_consumption.create' => [
                'ok' => true,
                'consumption' => (new MaterialConsumptionService())->create($inner),
            ],
            'finished_goods.create' => [
                'ok' => true,
                'receipt' => (new FinishedGoodsReceiptService())->create($inner),
            ],
            'scrap.create' => [
                'ok' => true,
                'scrap' => (new ScrapRecordingService())->create($inner),
            ],
            'quality_check.create' => [
                'ok' => true,
                'quality_check' => (new QualityCheckService())->create($inner),
            ],
            'cost.create' => [
                'ok' => true,
                'cost' => (new ProductionCostService())->create($inner),
            ],
            'assignment.create' => [
                'ok' => true,
                'assignment' => (new ManufacturingAssignmentService())->create($inner),
            ],
            'comment.create', 'note.create' => [
                'ok' => true,
                'comment' => (new ManufacturingCommentService())->create($inner),
            ],
            default => throw new \RuntimeException('unknown_manufacturing_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function bomCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->bomExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'bom_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new BomService())->create($inner);

        return ['ok' => true, 'bom_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function bomUpdate(array $scope, array $inner): array
    {
        $bomId = (int) ($inner['bom_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertBom($bomId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['bom'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['bom_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new BomService())->update($bomId, $payload);

        return ['ok' => true, 'bom_id' => $bomId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function routingUpdate(array $scope, array $inner): array
    {
        $routingId = (int) ($inner['routing_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertRouting($routingId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['routing'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['routing_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new RoutingService())->update($routingId, $payload);

        return ['ok' => true, 'routing_id' => $routingId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function productionOrderCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->productionOrderExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'production_order_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new ProductionOrderService())->create($inner);

        return ['ok' => true, 'production_order_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function productionOrderUpdate(array $scope, array $inner): array
    {
        $poId = (int) ($inner['production_order_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertProductionOrder($poId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['production_order'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['production_order_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new ProductionOrderService())->update($poId, $payload);

        return ['ok' => true, 'production_order_id' => $poId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workOrderCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->workOrderExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'work_order_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new MfgWorkOrderService())->create($inner);

        return ['ok' => true, 'work_order_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workOrderUpdate(array $scope, array $inner): array
    {
        $woId = (int) ($inner['work_order_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertWorkOrder($woId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['work_order'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['work_order_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new MfgWorkOrderService())->update($woId, $payload);

        return ['ok' => true, 'work_order_id' => $woId];
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
        if (!in_array($entityType, ManufacturingWorkflowService::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_mfg_entity_type');
        }
        $id = (int) ($inner['entity_id'] ?? $inner['id'] ?? 0);
        $assert = match ($entityType) {
            ManufacturingWorkflowService::ENTITY_BOM => $this->guard()->assertBom($id, $scope),
            ManufacturingWorkflowService::ENTITY_ROUTING => $this->guard()->assertRouting($id, $scope),
            ManufacturingWorkflowService::ENTITY_PRODUCTION_ORDER => $this->guard()->assertProductionOrder($id, $scope),
            ManufacturingWorkflowService::ENTITY_WORK_ORDER => $this->guard()->assertWorkOrder($id, $scope),
            default => ['ok' => false, 'error' => 'invalid_mfg_entity_type'],
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

        return (new ManufacturingWorkflowService())->transition($entityType, $id, $to, $reason, $expectedVersion);
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
        $status = strtolower((string) ($server['workflow_status'] ?? 'draft'));
        $decision = $this->resolver()->resolveManufacturing(
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
            'workflow_transition_denied',
        ], true);
    }
}
