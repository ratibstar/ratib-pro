<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\ProjectActivity;
use Rateb\App\Models\ProjectTimesheet;

/**
 * Phase 18A — Project activities + timesheets (ONLINE).
 * Named Project* to avoid CRM ActivityService collision.
 */

final class ProjectActivityService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId, int $limit = 50): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $safe = max(1, min(200, $limit));
        $rows = (new ProjectActivity())->query(
            'SELECT * FROM rateb_project_activities
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY activity_at DESC, id DESC
             LIMIT ' . $safe,
            ['cid' => $companyId, 'pid' => $projectId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $projectId = (int) ($input['project_id'] ?? 0);
        ProjectSupport::assertProject($projectId, $companyId);
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            throw new \InvalidArgumentException('subject_required');
        }
        $id = (new ProjectActivity())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'task_id' => ProjectSupport::intOrNull($input['task_id'] ?? null),
            'activity_type' => substr(trim((string) ($input['activity_type'] ?? 'note')), 0, 40) ?: 'note',
            'subject' => substr($subject, 0, 190),
            'body' => ProjectSupport::nullIfEmpty($input['body'] ?? null),
            'activity_at' => ProjectSupport::nullIfEmpty($input['activity_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'owner_user_id' => ProjectSupport::intOrNull($input['owner_user_id'] ?? null) ?? ProjectSupport::userId(),
            'status' => 'active',
        ], ProjectSupport::actorFields(true)));

        (new ProjectTimelineService())->record(
            'activity',
            $subject,
            ProjectSupport::nullIfEmpty($input['body'] ?? null),
            $projectId,
            ProjectSupport::intOrNull($input['task_id'] ?? null),
            'activity',
            (int) $id,
            ['project_id' => $projectId]
        );

        return ['id' => (int) $id];
    }
}

final class ProjectTimesheetService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(?int $projectId = null, int $limit = 50, int $offset = 0): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $safeLimit = max(1, min(200, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($projectId !== null) {
            ProjectSupport::assertProject($projectId, $companyId);
            $where .= ' AND project_id = :pid';
            $params['pid'] = $projectId;
        }
        $totalRow = (new ProjectTimesheet())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_project_timesheets WHERE ' . $where,
            $params
        );
        $items = (new ProjectTimesheet())->query(
            'SELECT * FROM rateb_project_timesheets WHERE ' . $where
            . ' ORDER BY work_date DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * Draft timesheet only — approvals remain online product process (not Offline 18B).
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $projectId = (int) ($input['project_id'] ?? 0);
        ProjectSupport::assertProject($projectId, $companyId);
        $hours = (float) ($input['hours'] ?? 0);
        if ($hours <= 0) {
            throw new \InvalidArgumentException('hours_required');
        }
        $workDate = trim((string) ($input['work_date'] ?? ''));
        if ($workDate === '') {
            $workDate = date('Y-m-d');
        }
        $userId = ProjectSupport::intOrNull($input['user_id'] ?? null) ?? ProjectSupport::userId();
        if ($userId === null || $userId < 1) {
            throw new \InvalidArgumentException('user_required');
        }
        $id = (new ProjectTimesheet())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'task_id' => ProjectSupport::intOrNull($input['task_id'] ?? null),
            'user_id' => $userId,
            'work_date' => $workDate,
            'hours' => $hours,
            'description' => ProjectSupport::nullIfEmpty($input['description'] ?? null),
            'status' => 'draft',
        ], ProjectSupport::actorFields(true)));

        (new ProjectTimelineService())->record(
            'timesheet',
            'Timesheet logged: ' . $hours . 'h',
            ProjectSupport::nullIfEmpty($input['description'] ?? null),
            $projectId,
            ProjectSupport::intOrNull($input['task_id'] ?? null),
            'timesheet',
            (int) $id,
            ['project_id' => $projectId, 'hours' => $hours]
        );

        return ['id' => (int) $id];
    }
}
