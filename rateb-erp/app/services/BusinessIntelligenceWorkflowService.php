<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\BiDashboard;
use Rateb\App\Models\BiKpi;
use Rateb\App\Models\BiReport;
use Rateb\App\Models\BiStatusHistory;

/**
 * Sole authority for BI workflow_status changes.
 * Future Offline Replay (27B) must call these methods — never mutate status directly.
 */
final class BusinessIntelligenceWorkflowService
{
    public const ENTITY_DASHBOARD = 'dashboard';
    public const ENTITY_REPORT = 'report';
    public const ENTITY_KPI = 'kpi';

    /** @return list<string> */
    public static function entityTypes(): array
    {
        return [self::ENTITY_DASHBOARD, self::ENTITY_REPORT, self::ENTITY_KPI];
    }

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        if (!in_array($entityType, self::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_bi_entity_type');
        }

        return ['draft', 'published', 'archived'];
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(string $entityType): array
    {
        if (!in_array($entityType, self::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_bi_entity_type');
        }

        return [
            'draft' => ['published', 'archived'],
            'published' => ['draft', 'archived'],
            'archived' => [],
        ];
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
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        if (!in_array($entityType, self::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_bi_entity_type');
        }

        $row = $this->loadEntity($entityType, $id, $companyId);
        BusinessIntelligenceSupport::assertOptimisticVersion($row, $expectedVersion);

        $from = (string) ($row['workflow_status'] ?? 'draft');
        $to = trim($toStatus);
        if (!in_array($to, self::statuses($entityType), true)) {
            throw new \InvalidArgumentException('invalid_bi_workflow_status');
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], BusinessIntelligenceSupport::actorFields(false));

        if ($to === 'archived') {
            $update['status'] = 'archived';
        }
        if ($to === 'published' && empty($row['published_at'])
            && in_array($entityType, [self::ENTITY_DASHBOARD, self::ENTITY_REPORT], true)) {
            $update['published_at'] = date('Y-m-d H:i:s');
        }

        $this->persist($entityType, $id, $update);

        (new BiStatusHistory())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => BusinessIntelligenceSupport::nullIfEmpty($reason),
            'status' => 'active',
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record(
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
            self::ENTITY_DASHBOARD => BusinessIntelligenceSupport::assertDashboard($id, $companyId),
            self::ENTITY_REPORT => BusinessIntelligenceSupport::assertReport($id, $companyId),
            self::ENTITY_KPI => BusinessIntelligenceSupport::assertKpi($id, $companyId),
            default => throw new \InvalidArgumentException('invalid_bi_entity_type'),
        };
    }

    /** @param array<string, mixed> $update */
    private function persist(string $entityType, int $id, array $update): void
    {
        match ($entityType) {
            self::ENTITY_DASHBOARD => (new BiDashboard())->update($id, $update),
            self::ENTITY_REPORT => (new BiReport())->update($id, $update),
            self::ENTITY_KPI => (new BiKpi())->update($id, $update),
            default => throw new \InvalidArgumentException('invalid_bi_entity_type'),
        };
    }
}
