<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\ProjectTimelineEvent;

/**
 * Append-only project timeline events.
 */
final class ProjectTimelineService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(
        string $eventType,
        string $title,
        ?string $body,
        ?int $projectId,
        ?int $taskId = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?array $meta = null
    ): int {
        $companyId = ProjectSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }

        return (int) (new ProjectTimelineEvent())->create([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'task_id' => $taskId,
            'event_type' => substr($eventType, 0, 60),
            'title' => substr($title, 0, 190),
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'meta_json' => $metaJson,
            'created_by' => ProjectSupport::userId(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 20): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $safe = max(1, min(100, $limit));
        $rows = (new ProjectTimelineEvent())->query(
            'SELECT * FROM rateb_project_timeline
             WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForProject(int $projectId, int $limit = 50): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $safe = max(1, min(200, $limit));
        $rows = (new ProjectTimelineEvent())->query(
            'SELECT * FROM rateb_project_timeline
             WHERE company_id = :cid AND project_id = :pid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safe,
            ['cid' => $companyId, 'pid' => $projectId]
        );

        return is_array($rows) ? $rows : [];
    }
}
