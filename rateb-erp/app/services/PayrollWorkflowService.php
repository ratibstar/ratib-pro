<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\PayrollBatch;
use Rateb\App\Models\PayrollStatusHistory;

/**
 * Sole authority for payroll batch workflow_status changes.
 * Future Offline Replay (24B) must call these methods — never mutate status directly.
 */
final class PayrollWorkflowService
{
    public const ENTITY_BATCH = 'batch';

    /** @return list<string> */
    public static function entityTypes(): array
    {
        return [self::ENTITY_BATCH];
    }

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_BATCH => [
                'draft', 'prepared', 'calculated', 'reviewed', 'approved', 'posted', 'closed', 'archived',
            ],
            default => throw new \InvalidArgumentException('invalid_payroll_entity_type'),
        };
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_BATCH => [
                'draft' => ['prepared', 'archived'],
                'prepared' => ['calculated', 'draft', 'archived'],
                'calculated' => ['reviewed', 'prepared', 'archived'],
                'reviewed' => ['approved', 'calculated', 'archived'],
                'approved' => ['posted', 'reviewed', 'archived'],
                'posted' => ['closed', 'archived'],
                'closed' => ['archived'],
                'archived' => [],
            ],
            default => throw new \InvalidArgumentException('invalid_payroll_entity_type'),
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
        $companyId = PayrollSupport::requireCompanyId();
        if ($entityType !== self::ENTITY_BATCH) {
            throw new \InvalidArgumentException('invalid_payroll_entity_type');
        }

        $row = PayrollSupport::assertBatch($id, $companyId);
        PayrollSupport::assertOptimisticVersion($row, $expectedVersion);

        $from = (string) ($row['workflow_status'] ?? 'draft');
        $to = trim($toStatus);
        if (!in_array($to, self::statuses($entityType), true)) {
            throw new \InvalidArgumentException('invalid_payroll_workflow_status');
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], PayrollSupport::actorFields(false));

        if ($to === 'archived') {
            $update['status'] = 'archived';
        }

        (new PayrollBatch())->update($id, $update);

        (new PayrollStatusHistory())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => PayrollSupport::nullIfEmpty($reason),
            'status' => 'active',
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        (new PayrollTimelineService())->record(
            'workflow_transition',
            'Batch ' . ($row['code'] ?? '') . ': ' . $from . ' → ' . $to,
            $entityType,
            $id,
            $reason
        );

        return ['ok' => true, 'entity_type' => $entityType, 'entity_id' => $id, 'from' => $from, 'to' => $to];
    }
}
