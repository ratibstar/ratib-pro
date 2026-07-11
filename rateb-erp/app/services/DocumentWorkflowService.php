<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\DmsDocument;
use Rateb\App\Models\DmsStatusHistory;

/**
 * Sole authority for DMS document workflow_status changes.
 * Future Offline Replay (26B) must call these methods — never mutate status directly.
 */
final class DocumentWorkflowService
{
    public const ENTITY_DOCUMENT = 'document';

    /** @return list<string> */
    public static function entityTypes(): array
    {
        return [self::ENTITY_DOCUMENT];
    }

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        if ($entityType !== self::ENTITY_DOCUMENT) {
            throw new \InvalidArgumentException('invalid_dms_entity_type');
        }

        return ['draft', 'checked_in', 'review', 'approved', 'published', 'archived'];
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(string $entityType): array
    {
        if ($entityType !== self::ENTITY_DOCUMENT) {
            throw new \InvalidArgumentException('invalid_dms_entity_type');
        }

        return [
            'draft' => ['checked_in', 'archived'],
            'checked_in' => ['review', 'draft', 'archived'],
            'review' => ['approved', 'checked_in', 'archived'],
            'approved' => ['published', 'review', 'archived'],
            'published' => ['archived'],
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
        $companyId = DocumentManagementSupport::requireCompanyId();
        if ($entityType !== self::ENTITY_DOCUMENT) {
            throw new \InvalidArgumentException('invalid_dms_entity_type');
        }

        $row = DocumentManagementSupport::assertDocument($id, $companyId);
        DocumentManagementSupport::assertOptimisticVersion($row, $expectedVersion);

        $from = (string) ($row['workflow_status'] ?? 'draft');
        $to = trim($toStatus);
        if (!in_array($to, self::statuses($entityType), true)) {
            throw new \InvalidArgumentException('invalid_dms_workflow_status');
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], DocumentManagementSupport::actorFields(false));

        if ($to === 'archived') {
            $update['status'] = 'archived';
        }
        if ($to === 'published' && empty($row['published_at'])) {
            $update['published_at'] = date('Y-m-d H:i:s');
        }
        if ($to === 'checked_in') {
            $update['checked_out_by'] = null;
            $update['checked_out_at'] = null;
        }

        (new DmsDocument())->update($id, $update);

        (new DmsStatusHistory())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => DocumentManagementSupport::nullIfEmpty($reason),
            'status' => 'active',
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DocumentTimelineService())->record(
            'workflow_transition',
            ($row['code'] ?? $entityType) . ': ' . $from . ' → ' . $to,
            $entityType,
            $id,
            $reason
        );

        return ['ok' => true, 'entity_type' => $entityType, 'entity_id' => $id, 'from' => $from, 'to' => $to];
    }
}
