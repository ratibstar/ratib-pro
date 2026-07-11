<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EprocCollaboration;
use Rateb\App\Models\EprocContract;
use Rateb\App\Models\EprocStatusHistory;
use Rateb\App\Models\EprocSupplierProfile;
use Rateb\App\Models\EprocSupplierQualification;
use Rateb\App\Models\EprocTender;

/**
 * Sole authority for EPROC workflow_status changes.
 * Future Offline Replay (21B) must call these methods — never mutate status directly.
 * Distinct from legacy ProcurementService / WorkflowService.
 */
final class ProcurementWorkflowService
{
    public const ENTITY_SUPPLIER_PROFILE = 'supplier_profile';
    public const ENTITY_TENDER = 'tender';
    public const ENTITY_CONTRACT = 'contract';
    public const ENTITY_QUALIFICATION = 'qualification';
    public const ENTITY_COLLABORATION = 'collaboration';

    /** @return list<string> */
    public static function entityTypes(): array
    {
        return [
            self::ENTITY_SUPPLIER_PROFILE,
            self::ENTITY_TENDER,
            self::ENTITY_CONTRACT,
            self::ENTITY_QUALIFICATION,
            self::ENTITY_COLLABORATION,
        ];
    }

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_SUPPLIER_PROFILE => [
                'draft', 'qualified', 'active', 'suspended', 'blacklisted', 'cancelled', 'archived',
            ],
            self::ENTITY_TENDER => [
                'draft', 'published', 'bidding', 'evaluation', 'awarded', 'closed', 'cancelled', 'archived',
            ],
            self::ENTITY_CONTRACT => [
                'draft', 'negotiation', 'active', 'expired', 'renewed', 'terminated', 'archived',
            ],
            self::ENTITY_QUALIFICATION => [
                'draft', 'submitted', 'under_review', 'approved', 'rejected', 'archived',
            ],
            self::ENTITY_COLLABORATION => [
                'open', 'in_progress', 'resolved', 'closed', 'archived',
            ],
            default => throw new \InvalidArgumentException('invalid_eproc_entity_type'),
        };
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_SUPPLIER_PROFILE => [
                'draft' => ['qualified', 'cancelled', 'archived'],
                'qualified' => ['active', 'suspended', 'archived'],
                'active' => ['suspended', 'blacklisted', 'archived'],
                'suspended' => ['active', 'blacklisted', 'archived'],
                'blacklisted' => ['archived'],
                'cancelled' => ['archived'],
                'archived' => [],
            ],
            self::ENTITY_TENDER => [
                'draft' => ['published', 'cancelled', 'archived'],
                'published' => ['bidding', 'cancelled', 'archived'],
                'bidding' => ['evaluation', 'cancelled', 'closed'],
                'evaluation' => ['awarded', 'cancelled', 'closed'],
                'awarded' => ['closed', 'archived'],
                'closed' => ['archived'],
                'cancelled' => ['archived'],
                'archived' => [],
            ],
            self::ENTITY_CONTRACT => [
                'draft' => ['negotiation', 'archived'],
                'negotiation' => ['active', 'terminated', 'archived'],
                'active' => ['expired', 'renewed', 'terminated', 'archived'],
                'expired' => ['renewed', 'archived'],
                'renewed' => ['active', 'expired', 'terminated', 'archived'],
                'terminated' => ['archived'],
                'archived' => [],
            ],
            self::ENTITY_QUALIFICATION => [
                'draft' => ['submitted', 'archived'],
                'submitted' => ['under_review', 'archived'],
                'under_review' => ['approved', 'rejected'],
                'approved' => ['archived'],
                'rejected' => ['archived', 'draft'],
                'archived' => [],
            ],
            self::ENTITY_COLLABORATION => [
                'open' => ['in_progress', 'closed', 'archived'],
                'in_progress' => ['resolved', 'closed', 'archived'],
                'resolved' => ['closed', 'archived'],
                'closed' => ['archived'],
                'archived' => [],
            ],
            default => throw new \InvalidArgumentException('invalid_eproc_entity_type'),
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
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        if (!in_array($entityType, self::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_eproc_entity_type');
        }

        $row = $this->loadEntity($entityType, $id, $companyId);
        if ($expectedVersion !== null && (int) ($row['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }

        $defaultFrom = $entityType === self::ENTITY_COLLABORATION ? 'open' : 'draft';
        $from = (string) ($row['workflow_status'] ?? $defaultFrom);
        $to = trim($toStatus);
        if (!in_array($to, self::statuses($entityType), true)) {
            throw new \InvalidArgumentException('invalid_eproc_workflow_status');
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], ProcurementEnterpriseSupport::actorFields(false));

        if ($to === 'archived') {
            $update['status'] = 'archived';
        }
        if ($entityType === self::ENTITY_QUALIFICATION && in_array($to, ['approved', 'rejected'], true)) {
            $update['decided_at'] = date('Y-m-d H:i:s');
        }
        if ($entityType === self::ENTITY_SUPPLIER_PROFILE && $to === 'qualified') {
            $update['qualification_status'] = 'qualified';
        }
        if ($entityType === self::ENTITY_SUPPLIER_PROFILE && $to === 'blacklisted') {
            $update['qualification_status'] = 'blacklisted';
        }

        $this->modelFor($entityType)->update($id, $update);

        (new EprocStatusHistory())->create([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => ProcurementEnterpriseSupport::userId(),
        ]);

        if (class_exists(ProcurementTimelineService::class)) {
            (new ProcurementTimelineService())->record(
                'workflow',
                $entityType . ' status: ' . $from . ' → ' . $to,
                $entityType,
                $id,
                ['from' => $from, 'to' => $to, 'reason' => $reason]
            );
        }

        if (class_exists(ProcurementAuditService::class)) {
            (new ProcurementAuditService())->log(
                'workflow_transition',
                'Status ' . $from . ' → ' . $to,
                $entityType,
                $id,
                ['from' => $from, 'to' => $to, 'reason' => $reason]
            );
        }

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
            self::ENTITY_SUPPLIER_PROFILE => ProcurementEnterpriseSupport::assertProfile($id, $companyId),
            self::ENTITY_TENDER => ProcurementEnterpriseSupport::assertTender($id, $companyId),
            self::ENTITY_CONTRACT => ProcurementEnterpriseSupport::assertContract($id, $companyId),
            self::ENTITY_QUALIFICATION => $this->assertQualification($id, $companyId),
            self::ENTITY_COLLABORATION => $this->assertCollaboration($id, $companyId),
            default => throw new \InvalidArgumentException('invalid_eproc_entity_type'),
        };
    }

    private function modelFor(string $entityType): EprocSupplierProfile|EprocTender|EprocContract|EprocSupplierQualification|EprocCollaboration
    {
        return match ($entityType) {
            self::ENTITY_SUPPLIER_PROFILE => new EprocSupplierProfile(),
            self::ENTITY_TENDER => new EprocTender(),
            self::ENTITY_CONTRACT => new EprocContract(),
            self::ENTITY_QUALIFICATION => new EprocSupplierQualification(),
            self::ENTITY_COLLABORATION => new EprocCollaboration(),
            default => throw new \InvalidArgumentException('invalid_eproc_entity_type'),
        };
    }

    /** @return array<string, mixed> */
    private function assertQualification(int $id, int $companyId): array
    {
        $row = (new EprocSupplierQualification())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_qualification
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('qualification_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function assertCollaboration(int $id, int $companyId): array
    {
        $row = (new EprocCollaboration())->queryOne(
            'SELECT * FROM rateb_eproc_collaboration
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('collaboration_not_found');
        }

        return $row;
    }
}
