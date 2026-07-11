<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\QmsCorrectiveAction;
use Rateb\App\Models\QmsInspection;
use Rateb\App\Models\QmsPreventiveAction;
use Rateb\App\Models\QmsStatusHistory;

/**
 * Sole authority for QMS workflow_status changes.
 * Future Offline Replay (25B) must call these methods — never mutate status directly.
 */
final class QualityWorkflowService
{
    public const ENTITY_INSPECTION = 'inspection';
    public const ENTITY_CORRECTIVE = 'corrective_action';
    public const ENTITY_PREVENTIVE = 'preventive_action';
    public const ENTITY_AUDIT = 'audit';

    /** @return list<string> */
    public static function entityTypes(): array
    {
        return [
            self::ENTITY_INSPECTION,
            self::ENTITY_CORRECTIVE,
            self::ENTITY_PREVENTIVE,
            self::ENTITY_AUDIT,
        ];
    }

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_INSPECTION, self::ENTITY_AUDIT => [
                'planned', 'scheduled', 'in_progress', 'completed', 'approved', 'archived',
            ],
            self::ENTITY_CORRECTIVE, self::ENTITY_PREVENTIVE => [
                'draft', 'assigned', 'in_progress', 'verified', 'closed', 'archived',
            ],
            default => throw new \InvalidArgumentException('invalid_qms_entity_type'),
        };
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_INSPECTION, self::ENTITY_AUDIT => [
                'planned' => ['scheduled', 'archived'],
                'scheduled' => ['in_progress', 'planned', 'archived'],
                'in_progress' => ['completed', 'scheduled', 'archived'],
                'completed' => ['approved', 'in_progress', 'archived'],
                'approved' => ['archived'],
                'archived' => [],
            ],
            self::ENTITY_CORRECTIVE, self::ENTITY_PREVENTIVE => [
                'draft' => ['assigned', 'archived'],
                'assigned' => ['in_progress', 'draft', 'archived'],
                'in_progress' => ['verified', 'assigned', 'archived'],
                'verified' => ['closed', 'in_progress', 'archived'],
                'closed' => ['archived'],
                'archived' => [],
            ],
            default => throw new \InvalidArgumentException('invalid_qms_entity_type'),
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
        $companyId = QualitySupport::requireCompanyId();
        if (!in_array($entityType, self::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_qms_entity_type');
        }

        $row = $this->loadEntity($entityType, $id, $companyId);
        QualitySupport::assertOptimisticVersion($row, $expectedVersion);

        $from = (string) ($row['workflow_status'] ?? (
            in_array($entityType, [self::ENTITY_CORRECTIVE, self::ENTITY_PREVENTIVE], true) ? 'draft' : 'planned'
        ));
        $to = trim($toStatus);
        if (!in_array($to, self::statuses($entityType), true)) {
            throw new \InvalidArgumentException('invalid_qms_workflow_status');
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], QualitySupport::actorFields(false));

        if ($to === 'archived') {
            $update['status'] = 'archived';
        }
        if ($entityType === self::ENTITY_INSPECTION && $to === 'in_progress' && empty($row['started_at'])) {
            $update['started_at'] = date('Y-m-d H:i:s');
        }
        if ($entityType === self::ENTITY_INSPECTION && $to === 'completed' && empty($row['completed_at'])) {
            $update['completed_at'] = date('Y-m-d H:i:s');
        }
        if ($entityType === self::ENTITY_CORRECTIVE && $to === 'verified' && empty($row['verified_at'])) {
            $update['verified_at'] = date('Y-m-d H:i:s');
        }

        $this->persist($entityType, $id, $update);

        (new QmsStatusHistory())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => QualitySupport::nullIfEmpty($reason),
            'status' => 'active',
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        (new QualityTimelineService())->record(
            'workflow_transition',
            ($row['code'] ?? $entityType) . ': ' . $from . ' → ' . $to,
            $entityType,
            $id,
            $reason
        );

        return ['ok' => true, 'entity_type' => $entityType, 'entity_id' => $id, 'from' => $from, 'to' => $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadEntity(string $entityType, int $id, int $companyId): array
    {
        return match ($entityType) {
            self::ENTITY_INSPECTION => QualitySupport::assertInspection($id, $companyId),
            self::ENTITY_CORRECTIVE => QualitySupport::assertCorrectiveAction($id, $companyId),
            self::ENTITY_PREVENTIVE => $this->assertPreventive($id, $companyId),
            self::ENTITY_AUDIT => $this->assertAudit($id, $companyId),
            default => throw new \InvalidArgumentException('invalid_qms_entity_type'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function assertPreventive(int $id, int $companyId): array
    {
        $row = (new QmsPreventiveAction())->queryOne(
            'SELECT * FROM rateb_qms_preventive_actions WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('preventive_action_not_found');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function assertAudit(int $id, int $companyId): array
    {
        $row = (new \Rateb\App\Models\QmsAudit())->queryOne(
            'SELECT * FROM rateb_qms_audits WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('audit_not_found');
        }

        return $row;
    }

    /** @param array<string, mixed> $update */
    private function persist(string $entityType, int $id, array $update): void
    {
        match ($entityType) {
            self::ENTITY_INSPECTION => (new QmsInspection())->update($id, $update),
            self::ENTITY_CORRECTIVE => (new QmsCorrectiveAction())->update($id, $update),
            self::ENTITY_PREVENTIVE => (new QmsPreventiveAction())->update($id, $update),
            self::ENTITY_AUDIT => (new \Rateb\App\Models\QmsAudit())->update($id, $update),
            default => throw new \InvalidArgumentException('invalid_qms_entity_type'),
        };
    }
}
