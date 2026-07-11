<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\MfgBom;
use Rateb\App\Models\MfgBomVersion;
use Rateb\App\Models\MfgProductionOrder;
use Rateb\App\Models\MfgProduct;
use Rateb\App\Models\MfgRouting;
use Rateb\App\Models\MfgStatusHistory;
use Rateb\App\Models\MfgWorkOrder;

/**
 * Sole authority for MFG workflow_status changes.
 * Future Offline Replay (22B) must call these methods — never mutate status directly.
 */
final class ManufacturingWorkflowService
{
    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_BOM = 'bom';
    public const ENTITY_BOM_VERSION = 'bom_version';
    public const ENTITY_ROUTING = 'routing';
    public const ENTITY_PRODUCTION_ORDER = 'production_order';
    public const ENTITY_WORK_ORDER = 'work_order';

    /** @return list<string> */
    public static function entityTypes(): array
    {
        return [
            self::ENTITY_PRODUCT,
            self::ENTITY_BOM,
            self::ENTITY_BOM_VERSION,
            self::ENTITY_ROUTING,
            self::ENTITY_PRODUCTION_ORDER,
            self::ENTITY_WORK_ORDER,
        ];
    }

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_PRODUCT => [
                'draft', 'active', 'obsolete', 'cancelled', 'archived',
            ],
            self::ENTITY_BOM, self::ENTITY_BOM_VERSION => [
                'draft', 'active', 'obsolete', 'cancelled', 'archived',
            ],
            self::ENTITY_ROUTING => [
                'draft', 'active', 'obsolete', 'cancelled', 'archived',
            ],
            self::ENTITY_PRODUCTION_ORDER, self::ENTITY_WORK_ORDER => [
                'draft', 'planned', 'released', 'in_progress', 'quality_check',
                'completed', 'closed', 'cancelled', 'archived',
            ],
            default => throw new \InvalidArgumentException('invalid_mfg_entity_type'),
        };
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_PRODUCT, self::ENTITY_BOM, self::ENTITY_BOM_VERSION, self::ENTITY_ROUTING => [
                'draft' => ['active', 'cancelled', 'archived'],
                'active' => ['obsolete', 'cancelled', 'archived'],
                'obsolete' => ['archived', 'active'],
                'cancelled' => ['archived'],
                'archived' => [],
            ],
            self::ENTITY_PRODUCTION_ORDER, self::ENTITY_WORK_ORDER => [
                'draft' => ['planned', 'cancelled', 'archived'],
                'planned' => ['released', 'cancelled', 'archived'],
                'released' => ['in_progress', 'cancelled', 'archived'],
                'in_progress' => ['quality_check', 'completed', 'cancelled'],
                'quality_check' => ['completed', 'in_progress', 'cancelled'],
                'completed' => ['closed', 'archived'],
                'closed' => ['archived'],
                'cancelled' => ['archived'],
                'archived' => [],
            ],
            default => throw new \InvalidArgumentException('invalid_mfg_entity_type'),
        };
    }

    /**
     * @return array{ok: bool, entity_type: string, entity_id: int, from: string, to: string}
     */
    public function transition(
        string $entityType,
        int $id,
        string $toStatus,
        ?string $reason = null,
        ?int $expectedVersion = null
    ): array {
        $companyId = ManufacturingSupport::requireCompanyId();
        if (!in_array($entityType, self::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_mfg_entity_type');
        }

        $row = $this->loadEntity($entityType, $id, $companyId);
        if ($expectedVersion !== null && (int) ($row['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }

        $from = (string) ($row['workflow_status'] ?? 'draft');
        $to = trim($toStatus);
        if (!in_array($to, self::statuses($entityType), true)) {
            throw new \InvalidArgumentException('invalid_mfg_workflow_status');
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], ManufacturingSupport::actorFields(false));

        if ($to === 'archived') {
            $update['status'] = 'archived';
        }
        if ($entityType === self::ENTITY_PRODUCTION_ORDER) {
            if ($to === 'in_progress' && empty($row['actual_start'])) {
                $update['actual_start'] = date('Y-m-d H:i:s');
            }
            if (in_array($to, ['completed', 'closed'], true) && empty($row['actual_end'])) {
                $update['actual_end'] = date('Y-m-d H:i:s');
            }
        }
        if ($entityType === self::ENTITY_WORK_ORDER) {
            if ($to === 'in_progress' && empty($row['actual_start'])) {
                $update['actual_start'] = date('Y-m-d H:i:s');
            }
            if (in_array($to, ['completed', 'closed'], true) && empty($row['actual_end'])) {
                $update['actual_end'] = date('Y-m-d H:i:s');
            }
        }

        $this->persistUpdate($entityType, $id, $update);

        (new MfgStatusHistory())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null ? substr($reason, 0, 255) : null,
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'workflow.transition',
            $entityType . ' ' . $from . ' → ' . $to,
            $entityType,
            $id,
            ['from' => $from, 'to' => $to, 'reason' => $reason]
        );

        return [
            'ok' => true,
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from' => $from,
            'to' => $to,
        ];
    }

    /** @return array<string, mixed> */
    private function loadEntity(string $entityType, int $id, int $companyId): array
    {
        return match ($entityType) {
            self::ENTITY_PRODUCT => ManufacturingSupport::assertProduct($id, $companyId),
            self::ENTITY_BOM => ManufacturingSupport::assertBom($id, $companyId),
            self::ENTITY_BOM_VERSION => $this->assertBomVersion($id, $companyId),
            self::ENTITY_ROUTING => $this->assertRouting($id, $companyId),
            self::ENTITY_PRODUCTION_ORDER => ManufacturingSupport::assertProductionOrder($id, $companyId),
            self::ENTITY_WORK_ORDER => ManufacturingSupport::assertWorkOrder($id, $companyId),
            default => throw new \InvalidArgumentException('invalid_mfg_entity_type'),
        };
    }

    /** @return array<string, mixed> */
    private function assertBomVersion(int $id, int $companyId): array
    {
        $row = (new MfgBomVersion())->queryOne(
            'SELECT * FROM rateb_mfg_bom_versions WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('bom_version_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function assertRouting(int $id, int $companyId): array
    {
        $row = (new MfgRouting())->queryOne(
            'SELECT * FROM rateb_mfg_routings WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('routing_not_found');
        }

        return $row;
    }

    /** @param array<string, mixed> $update */
    private function persistUpdate(string $entityType, int $id, array $update): void
    {
        match ($entityType) {
            self::ENTITY_PRODUCT => (new MfgProduct())->update($id, $update),
            self::ENTITY_BOM => (new MfgBom())->update($id, $update),
            self::ENTITY_BOM_VERSION => (new MfgBomVersion())->update($id, $update),
            self::ENTITY_ROUTING => (new MfgRouting())->update($id, $update),
            self::ENTITY_PRODUCTION_ORDER => (new MfgProductionOrder())->update($id, $update),
            self::ENTITY_WORK_ORDER => (new MfgWorkOrder())->update($id, $update),
            default => throw new \InvalidArgumentException('invalid_mfg_entity_type'),
        };
    }
}
