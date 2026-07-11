<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EamAsset;
use Rateb\App\Models\EamMaintenanceRequest;
use Rateb\App\Models\EamStatusHistory;
use Rateb\App\Models\EamWorkOrder;

/**
 * Sole authority for EAM workflow_status changes (asset + maintenance request + work order).
 * Future Offline Replay (19B) must call these methods — never mutate status directly.
 */
final class AssetWorkflowService
{
    public const ASSET_DRAFT = 'draft';
    public const ASSET_REGISTERED = 'registered';
    public const ASSET_ACTIVE = 'active';
    public const ASSET_MAINTENANCE = 'maintenance';
    public const ASSET_RETIRED = 'retired';
    public const ASSET_DISPOSED = 'disposed';
    public const ASSET_ARCHIVED = 'archived';

    public const REQ_NEW = 'new';
    public const REQ_APPROVED = 'approved';
    public const REQ_SCHEDULED = 'scheduled';
    public const REQ_IN_PROGRESS = 'in_progress';
    public const REQ_COMPLETED = 'completed';
    public const REQ_CLOSED = 'closed';

    /** @return list<string> */
    public static function assetStatuses(): array
    {
        return [
            self::ASSET_DRAFT,
            self::ASSET_REGISTERED,
            self::ASSET_ACTIVE,
            self::ASSET_MAINTENANCE,
            self::ASSET_RETIRED,
            self::ASSET_DISPOSED,
            self::ASSET_ARCHIVED,
        ];
    }

    /** @return list<string> */
    public static function requestStatuses(): array
    {
        return [
            self::REQ_NEW,
            self::REQ_APPROVED,
            self::REQ_SCHEDULED,
            self::REQ_IN_PROGRESS,
            self::REQ_COMPLETED,
            self::REQ_CLOSED,
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedAssetTransitions(): array
    {
        return [
            self::ASSET_DRAFT => [self::ASSET_REGISTERED, self::ASSET_ARCHIVED],
            self::ASSET_REGISTERED => [self::ASSET_ACTIVE, self::ASSET_ARCHIVED],
            self::ASSET_ACTIVE => [self::ASSET_MAINTENANCE, self::ASSET_RETIRED, self::ASSET_DISPOSED],
            self::ASSET_MAINTENANCE => [self::ASSET_ACTIVE, self::ASSET_RETIRED],
            self::ASSET_RETIRED => [self::ASSET_DISPOSED, self::ASSET_ARCHIVED],
            self::ASSET_DISPOSED => [self::ASSET_ARCHIVED],
            self::ASSET_ARCHIVED => [],
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedRequestTransitions(): array
    {
        return [
            self::REQ_NEW => [self::REQ_APPROVED, self::REQ_CLOSED],
            self::REQ_APPROVED => [self::REQ_SCHEDULED, self::REQ_CLOSED],
            self::REQ_SCHEDULED => [self::REQ_IN_PROGRESS, self::REQ_CLOSED],
            self::REQ_IN_PROGRESS => [self::REQ_COMPLETED, self::REQ_CLOSED],
            self::REQ_COMPLETED => [self::REQ_CLOSED],
            self::REQ_CLOSED => [],
        ];
    }

    /**
     * @return array{ok: bool, asset_id: int, from: string, to: string}
     */
    public function transitionAsset(int $assetId, string $toStatus, ?string $reason = null, ?int $expectedVersion = null): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $asset = AssetSupport::assertAsset($assetId, $companyId);
        if ($expectedVersion !== null && (int) ($asset['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }
        $from = (string) ($asset['workflow_status'] ?? self::ASSET_DRAFT);
        $to = trim($toStatus);
        if (!in_array($to, self::assetStatuses(), true)) {
            throw new \InvalidArgumentException('invalid_asset_workflow_status');
        }
        $allowed = self::allowedAssetTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($asset['version'] ?? 1) + 1,
        ], AssetSupport::actorFields(false));
        if ($to === self::ASSET_ARCHIVED) {
            $update['status'] = 'archived';
        }
        (new EamAsset())->update($assetId, $update);

        (new EamStatusHistory())->create([
            'company_id' => $companyId,
            'entity_type' => 'asset',
            'entity_id' => $assetId,
            'asset_id' => $assetId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => AssetSupport::userId(),
        ]);

        (new AssetTimelineService())->record(
            'workflow',
            'Asset status: ' . $from . ' → ' . $to,
            $reason,
            $assetId,
            'asset',
            $assetId
        );

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('eam.workflow', 'eam_asset', $assetId, [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);
        }

        return ['ok' => true, 'asset_id' => $assetId, 'from' => $from, 'to' => $to];
    }

    /**
     * @return array{ok: bool, request_id: int, from: string, to: string}
     */
    public function transitionMaintenanceRequest(int $requestId, string $toStatus, ?string $reason = null, ?int $expectedVersion = null): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $req = AssetSupport::assertMaintenanceRequest($requestId, $companyId);
        if ($expectedVersion !== null && (int) ($req['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }
        $from = (string) ($req['workflow_status'] ?? self::REQ_NEW);
        $to = trim($toStatus);
        if (!in_array($to, self::requestStatuses(), true)) {
            throw new \InvalidArgumentException('invalid_request_workflow_status');
        }
        $allowed = self::allowedRequestTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($req['version'] ?? 1) + 1,
        ], AssetSupport::actorFields(false));
        if ($to === self::REQ_COMPLETED) {
            $update['completed_at'] = date('Y-m-d H:i:s');
        }
        (new EamMaintenanceRequest())->update($requestId, $update);

        $assetId = (int) ($req['asset_id'] ?? 0);
        (new EamStatusHistory())->create([
            'company_id' => $companyId,
            'entity_type' => 'maintenance_request',
            'entity_id' => $requestId,
            'asset_id' => $assetId > 0 ? $assetId : null,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => AssetSupport::userId(),
        ]);

        if ($assetId > 0) {
            (new AssetTimelineService())->record(
                'request_workflow',
                'Request status: ' . $from . ' → ' . $to,
                $reason,
                $assetId,
                'maintenance_request',
                $requestId
            );
        }

        return ['ok' => true, 'request_id' => $requestId, 'from' => $from, 'to' => $to];
    }

    /**
     * Work orders share the maintenance-request status map.
     *
     * @return array{ok: bool, work_order_id: int, from: string, to: string}
     */
    public function transitionWorkOrder(int $workOrderId, string $toStatus, ?string $reason = null, ?int $expectedVersion = null): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $wo = AssetSupport::assertWorkOrder($workOrderId, $companyId);
        if ($expectedVersion !== null && (int) ($wo['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }
        $from = (string) ($wo['workflow_status'] ?? self::REQ_NEW);
        $to = trim($toStatus);
        if (!in_array($to, self::requestStatuses(), true)) {
            throw new \InvalidArgumentException('invalid_work_order_workflow_status');
        }
        $allowed = self::allowedRequestTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($wo['version'] ?? 1) + 1,
        ], AssetSupport::actorFields(false));
        if ($to === self::REQ_IN_PROGRESS) {
            $update['started_at'] = date('Y-m-d H:i:s');
        }
        if ($to === self::REQ_COMPLETED) {
            $update['completed_at'] = date('Y-m-d H:i:s');
        }
        (new EamWorkOrder())->update($workOrderId, $update);

        $assetId = (int) ($wo['asset_id'] ?? 0);
        (new EamStatusHistory())->create([
            'company_id' => $companyId,
            'entity_type' => 'work_order',
            'entity_id' => $workOrderId,
            'asset_id' => $assetId > 0 ? $assetId : null,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => AssetSupport::userId(),
        ]);

        return ['ok' => true, 'work_order_id' => $workOrderId, 'from' => $from, 'to' => $to];
    }
}
