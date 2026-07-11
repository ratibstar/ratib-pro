<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\HrmEmployeeProfile;
use Rateb\App\Models\HrmPerformanceReview;
use Rateb\App\Models\HrmStatusHistory;
use Rateb\App\Models\HrmTraining;

/**
 * Sole authority for HRMS workflow_status changes.
 * Future Offline Replay (23B) must call these methods — never mutate status directly.
 */
final class HumanResourcesWorkflowService
{
    public const ENTITY_EMPLOYEE = 'employee';
    public const ENTITY_TRAINING = 'training';
    public const ENTITY_PERFORMANCE = 'performance';

    /** @return list<string> */
    public static function entityTypes(): array
    {
        return [
            self::ENTITY_EMPLOYEE,
            self::ENTITY_TRAINING,
            self::ENTITY_PERFORMANCE,
        ];
    }

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_EMPLOYEE => [
                'draft', 'registered', 'active', 'on_leave', 'suspended', 'terminated', 'archived',
            ],
            self::ENTITY_TRAINING => [
                'planned', 'scheduled', 'in_progress', 'completed', 'cancelled', 'archived',
            ],
            self::ENTITY_PERFORMANCE => [
                'draft', 'submitted', 'approved', 'closed', 'archived',
            ],
            default => throw new \InvalidArgumentException('invalid_hrm_entity_type'),
        };
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_EMPLOYEE => [
                'draft' => ['registered', 'archived'],
                'registered' => ['active', 'archived'],
                'active' => ['on_leave', 'suspended', 'terminated', 'archived'],
                'on_leave' => ['active', 'suspended', 'terminated', 'archived'],
                'suspended' => ['active', 'terminated', 'archived'],
                'terminated' => ['archived'],
                'archived' => [],
            ],
            self::ENTITY_TRAINING => [
                'planned' => ['scheduled', 'cancelled', 'archived'],
                'scheduled' => ['in_progress', 'cancelled', 'archived'],
                'in_progress' => ['completed', 'cancelled'],
                'completed' => ['archived'],
                'cancelled' => ['archived'],
                'archived' => [],
            ],
            self::ENTITY_PERFORMANCE => [
                'draft' => ['submitted', 'archived'],
                'submitted' => ['approved', 'draft', 'archived'],
                'approved' => ['closed', 'archived'],
                'closed' => ['archived'],
                'archived' => [],
            ],
            default => throw new \InvalidArgumentException('invalid_hrm_entity_type'),
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
        $companyId = HumanResourcesSupport::requireCompanyId();
        if (!in_array($entityType, self::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_hrm_entity_type');
        }

        $row = $this->loadEntity($entityType, $id, $companyId);
        HumanResourcesSupport::assertOptimisticVersion($row, $expectedVersion);

        $from = (string) ($row['workflow_status'] ?? ($entityType === self::ENTITY_TRAINING ? 'planned' : 'draft'));
        $to = trim($toStatus);
        if (!in_array($to, self::statuses($entityType), true)) {
            throw new \InvalidArgumentException('invalid_hrm_workflow_status');
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], HumanResourcesSupport::actorFields(false));

        if ($to === 'archived') {
            $update['status'] = 'archived';
        }
        if ($entityType === self::ENTITY_EMPLOYEE && $to === 'terminated' && empty($row['termination_date'])) {
            $update['termination_date'] = date('Y-m-d');
        }

        $this->persistUpdate($entityType, $id, $update);

        (new HrmStatusHistory())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null ? substr($reason, 0, 255) : null,
            'status' => 'active',
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            $entityType,
            $id,
            'workflow.transition',
            $entityType . ' ' . $from . ' → ' . $to,
            $reason,
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
            self::ENTITY_EMPLOYEE => HumanResourcesSupport::assertProfile($id, $companyId),
            self::ENTITY_TRAINING => HumanResourcesSupport::assertTraining($id, $companyId),
            self::ENTITY_PERFORMANCE => HumanResourcesSupport::assertPerformanceReview($id, $companyId),
            default => throw new \InvalidArgumentException('invalid_hrm_entity_type'),
        };
    }

    /** @param array<string, mixed> $update */
    private function persistUpdate(string $entityType, int $id, array $update): void
    {
        match ($entityType) {
            self::ENTITY_EMPLOYEE => (new HrmEmployeeProfile())->update($id, $update),
            self::ENTITY_TRAINING => (new HrmTraining())->update($id, $update),
            self::ENTITY_PERFORMANCE => (new HrmPerformanceReview())->update($id, $update),
            default => throw new \InvalidArgumentException('invalid_hrm_entity_type'),
        };
    }
}
