<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmCall;
use Rateb\App\Models\CrmMeeting;
use Rateb\App\Models\CrmTask;

/** Phase 17A — CRM activities, meetings, calls, tasks (ONLINE). */

final class ActivityService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new CrmActivity())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_activities WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new CrmActivity())->query(
            'SELECT * FROM rateb_crm_activities WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY COALESCE(activity_at, created_at) DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            throw new \InvalidArgumentException('subject_required');
        }
        $type = (string) ($input['activity_type'] ?? 'other');
        if (!in_array($type, ['note', 'follow_up', 'other'], true)) {
            $type = 'other';
        }
        $id = (new CrmActivity())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'activity_type' => $type,
            'subject' => substr($subject, 0, 190),
            'body' => CrmSupport::nullIfEmpty($input['body'] ?? null),
            'related_type' => CrmSupport::nullIfEmpty($input['related_type'] ?? null),
            'related_id' => CrmSupport::intOrNull($input['related_id'] ?? null),
            'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
            'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'activity_at' => CrmSupport::nullIfEmpty($input['activity_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'status' => 'open',
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'activity',
            $subject,
            CrmSupport::nullIfEmpty($input['body'] ?? null),
            'activity',
            (int) $id,
            [
                'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
                'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            ]
        );

        return ['id' => (int) $id];
    }
}

final class MeetingService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new CrmMeeting())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_meetings WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new CrmMeeting())->query(
            'SELECT * FROM rateb_crm_meetings WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY starts_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $subject = trim((string) ($input['subject'] ?? ''));
        $starts = trim((string) ($input['starts_at'] ?? ''));
        if ($subject === '' || $starts === '') {
            throw new \InvalidArgumentException('meeting_fields_required');
        }
        $id = (new CrmMeeting())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'subject' => substr($subject, 0, 190),
            'location' => CrmSupport::nullIfEmpty($input['location'] ?? null),
            'starts_at' => $starts,
            'ends_at' => CrmSupport::nullIfEmpty($input['ends_at'] ?? null),
            'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
            'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'status' => 'scheduled',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'meeting',
            'Meeting: ' . $subject,
            null,
            'meeting',
            (int) $id,
            ['lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null)]
        );

        return ['id' => (int) $id];
    }
}

final class CallService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new CrmCall())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_calls WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new CrmCall())->query(
            'SELECT * FROM rateb_crm_calls WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY called_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $subject = trim((string) ($input['subject'] ?? ''));
        $calledAt = trim((string) ($input['called_at'] ?? date('Y-m-d H:i:s')));
        if ($subject === '') {
            throw new \InvalidArgumentException('subject_required');
        }
        $direction = (string) ($input['direction'] ?? 'outbound');
        if (!in_array($direction, ['inbound', 'outbound'], true)) {
            $direction = 'outbound';
        }
        $id = (new CrmCall())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'subject' => substr($subject, 0, 190),
            'direction' => $direction,
            'called_at' => $calledAt,
            'duration_sec' => CrmSupport::intOrNull($input['duration_sec'] ?? null),
            'phone' => CrmSupport::nullIfEmpty($input['phone'] ?? null),
            'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
            'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'outcome' => CrmSupport::nullIfEmpty($input['outcome'] ?? null),
            'status' => 'logged',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'call',
            'Call: ' . $subject,
            null,
            'call',
            (int) $id,
            ['lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null)]
        );

        return ['id' => (int) $id];
    }
}

final class TaskService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND status = :st';
            $params['st'] = $status;
        }
        $totalRow = (new CrmTask())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_tasks WHERE ' . $where,
            $params
        );
        $items = (new CrmTask())->query(
            'SELECT * FROM rateb_crm_tasks WHERE ' . $where
            . ' ORDER BY COALESCE(due_at, created_at) ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            throw new \InvalidArgumentException('subject_required');
        }
        $priority = (string) ($input['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }
        $id = (new CrmTask())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'subject' => substr($subject, 0, 190),
            'due_at' => CrmSupport::nullIfEmpty($input['due_at'] ?? null),
            'priority' => $priority,
            'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
            'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'reminder_at' => CrmSupport::nullIfEmpty($input['reminder_at'] ?? null),
            'status' => 'open',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'task',
            'Task: ' . $subject,
            null,
            'task',
            (int) $id,
            ['lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null)]
        );

        return ['id' => (int) $id];
    }

    public function complete(int $id): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmTask())->queryOne(
            'SELECT * FROM rateb_crm_tasks WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if ($row === null) {
            throw new \RuntimeException('task_not_found');
        }
        (new CrmTask())->update($id, array_merge([
            'status' => 'done',
            'completed_at' => date('Y-m-d H:i:s'),
        ], CrmSupport::actorFields(false)));
        (new CrmTimelineService())->record(
            'task_done',
            'Task completed: ' . (string) ($row['subject'] ?? $id),
            null,
            'task',
            $id,
            ['lead_id' => CrmSupport::intOrNull($row['lead_id'] ?? null)]
        );
    }
}
