<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Project;
use Rateb\App\Models\ProjectAssignment;
use Rateb\App\Models\ProjectBudget;
use Rateb\App\Models\ProjectComment;
use Rateb\App\Models\ProjectCost;
use Rateb\App\Models\ProjectIssue;
use Rateb\App\Models\ProjectMember;
use Rateb\App\Models\ProjectMilestone;
use Rateb\App\Models\ProjectPhase;
use Rateb\App\Models\ProjectResource;
use Rateb\App\Models\ProjectRisk;
use Rateb\App\Models\ProjectTag;
use Rateb\App\Models\ProjectTask;

/**
 * Phase 18A — Projects core domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Named Project* to avoid collisions with CRM TaskService / Recruitment AssignmentService.
 */

final class ProjectService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR project_no LIKE :q2 OR name_ar LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new Project())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_projects WHERE ' . $where,
            $params
        );
        $items = (new Project())->query(
            'SELECT * FROM rateb_projects WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return ProjectSupport::findProject($id, ProjectSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, project_no: string}
     */
    public function create(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $projectNo = trim((string) ($input['project_no'] ?? ''));
        if ($projectNo === '') {
            $projectNo = ProjectSupport::nextProjectNo($companyId);
        }
        $id = (new Project())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::intOrNull($input['branch_id'] ?? null) ?? ProjectSupport::branchId(),
            'project_no' => $projectNo,
            'name' => substr($name, 0, 190),
            'name_ar' => ProjectSupport::nullIfEmpty($input['name_ar'] ?? null),
            'description' => ProjectSupport::nullIfEmpty($input['description'] ?? null),
            'customer_id' => ProjectSupport::intOrNull($input['customer_id'] ?? null),
            'owner_user_id' => ProjectSupport::intOrNull($input['owner_user_id'] ?? null) ?? ProjectSupport::userId(),
            'workflow_status' => ProjectWorkflowService::PROJECT_DRAFT,
            'priority' => in_array(($input['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                ? $input['priority'] : 'normal',
            'start_date' => ProjectSupport::nullIfEmpty($input['start_date'] ?? null),
            'end_date' => ProjectSupport::nullIfEmpty($input['end_date'] ?? null),
            'planned_start' => ProjectSupport::nullIfEmpty($input['planned_start'] ?? null),
            'planned_end' => ProjectSupport::nullIfEmpty($input['planned_end'] ?? null),
            'percent_complete' => 0,
            'currency_code' => ProjectSupport::nullIfEmpty($input['currency_code'] ?? null),
            'budget_amount' => ProjectSupport::floatOrNull($input['budget_amount'] ?? null),
            'cost_center_id' => ProjectSupport::intOrNull($input['cost_center_id'] ?? null),
            'version' => 1,
            'status' => 'active',
            'notes' => ProjectSupport::nullIfEmpty($input['notes'] ?? null),
        ], ProjectSupport::actorFields(true)));

        (new ProjectTimelineService())->record(
            'project_created',
            'Project created: ' . $name,
            null,
            (int) $id,
            null,
            'project',
            (int) $id,
            ['project_id' => (int) $id]
        );

        return ['id' => (int) $id, 'project_no' => $projectNo];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProjectSupport::requireCompanyId();
        $project = ProjectSupport::assertProject($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($project['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ProjectSupport::actorFields(false);
        foreach (['name', 'name_ar', 'description', 'notes', 'currency_code', 'start_date', 'end_date', 'planned_start', 'planned_end', 'priority'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProjectSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['customer_id', 'owner_user_id', 'branch_id', 'cost_center_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProjectSupport::intOrNull($input[$f]);
            }
        }
        if (array_key_exists('budget_amount', $input)) {
            $patch['budget_amount'] = ProjectSupport::floatOrNull($input['budget_amount']);
        }
        if (array_key_exists('percent_complete', $input)) {
            $patch['percent_complete'] = max(0, min(100, (float) $input['percent_complete']));
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($project['version'] ?? 1) + 1;
        (new Project())->update($id, $patch);
        (new ProjectTimelineService())->record('project_updated', 'Project updated', null, $id, null, 'project', $id, ['project_id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($id, $companyId);
        (new Project())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], ProjectSupport::actorFields(false)));
        (new ProjectTimelineService())->record('project_deleted', 'Project soft-deleted', null, $id, null, 'project', $id, ['project_id' => $id]);
    }

    /** @return array<string, int> */
    public function boardCounts(): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $out = [];
        foreach (ProjectWorkflowService::projectStatuses() as $st) {
            $row = (new Project())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_projects
                 WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                ['cid' => $companyId, 'st' => $st]
            );
            $out[$st] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }
}

final class ProjectTaskService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(?int $projectId = null, int $limit = 50, int $offset = 0, ?string $status = null, ?int $parentTaskId = null): array
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
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($parentTaskId === 0) {
            $where .= ' AND parent_task_id IS NULL';
        } elseif ($parentTaskId !== null) {
            $where .= ' AND parent_task_id = :parent';
            $params['parent'] = $parentTaskId;
        }
        $totalRow = (new ProjectTask())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_project_tasks WHERE ' . $where,
            $params
        );
        $items = (new ProjectTask())->query(
            'SELECT * FROM rateb_project_tasks WHERE ' . $where
            . ' ORDER BY sort_order ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return ProjectSupport::findTask($id, ProjectSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, task_no: string}
     */
    public function create(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $projectId = (int) ($input['project_id'] ?? 0);
        ProjectSupport::assertProject($projectId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $parentId = ProjectSupport::intOrNull($input['parent_task_id'] ?? null);
        if ($parentId !== null) {
            $parent = ProjectSupport::assertTask($parentId, $companyId);
            if ((int) $parent['project_id'] !== $projectId) {
                throw new \RuntimeException('parent_task_project_mismatch');
            }
        }
        $taskNo = trim((string) ($input['task_no'] ?? ''));
        if ($taskNo === '') {
            $taskNo = ProjectSupport::nextTaskNo($projectId);
        }
        $id = (new ProjectTask())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::intOrNull($input['branch_id'] ?? null) ?? ProjectSupport::branchId(),
            'project_id' => $projectId,
            'phase_id' => ProjectSupport::intOrNull($input['phase_id'] ?? null),
            'milestone_id' => ProjectSupport::intOrNull($input['milestone_id'] ?? null),
            'parent_task_id' => $parentId,
            'task_no' => $taskNo,
            'title' => substr($title, 0, 190),
            'description' => ProjectSupport::nullIfEmpty($input['description'] ?? null),
            'workflow_status' => ProjectWorkflowService::TASK_NEW,
            'priority' => in_array(($input['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                ? $input['priority'] : 'normal',
            'assignee_user_id' => ProjectSupport::intOrNull($input['assignee_user_id'] ?? null),
            'start_date' => ProjectSupport::nullIfEmpty($input['start_date'] ?? null),
            'due_date' => ProjectSupport::nullIfEmpty($input['due_date'] ?? null),
            'estimated_hours' => ProjectSupport::floatOrNull($input['estimated_hours'] ?? null),
            'actual_hours' => ProjectSupport::floatOrNull($input['actual_hours'] ?? null),
            'percent_complete' => 0,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'version' => 1,
            'status' => 'active',
            'notes' => ProjectSupport::nullIfEmpty($input['notes'] ?? null),
        ], ProjectSupport::actorFields(true)));

        (new ProjectTimelineService())->record(
            'task_created',
            'Task created: ' . $title,
            null,
            $projectId,
            (int) $id,
            'task',
            (int) $id,
            ['project_id' => $projectId, 'task_id' => (int) $id]
        );

        return ['id' => (int) $id, 'task_no' => $taskNo];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProjectSupport::requireCompanyId();
        $task = ProjectSupport::assertTask($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($task['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ProjectSupport::actorFields(false);
        foreach (['title', 'description', 'notes', 'priority', 'start_date', 'due_date'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProjectSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['phase_id', 'milestone_id', 'assignee_user_id', 'parent_task_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProjectSupport::intOrNull($input[$f]);
            }
        }
        foreach (['estimated_hours', 'actual_hours', 'percent_complete'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProjectSupport::floatOrNull($input[$f]);
            }
        }
        if (isset($patch['title']) && $patch['title'] === '') {
            throw new \InvalidArgumentException('title_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($task['version'] ?? 1) + 1;
        (new ProjectTask())->update($id, $patch);
        (new ProjectTimelineService())->record(
            'task_updated',
            'Task updated',
            null,
            (int) $task['project_id'],
            $id,
            'task',
            $id,
            ['project_id' => (int) $task['project_id'], 'task_id' => $id]
        );
    }

    public function softDelete(int $id): void
    {
        $companyId = ProjectSupport::requireCompanyId();
        $task = ProjectSupport::assertTask($id, $companyId);
        (new ProjectTask())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], ProjectSupport::actorFields(false)));
        (new ProjectTimelineService())->record(
            'task_deleted',
            'Task soft-deleted',
            null,
            (int) $task['project_id'],
            $id,
            'task',
            $id,
            ['project_id' => (int) $task['project_id'], 'task_id' => $id]
        );
    }

    /** @return array<string, list<array<string,mixed>>> */
    public function kanban(?int $projectId = null): array
    {
        $out = [];
        foreach (ProjectWorkflowService::taskStatuses() as $st) {
            $out[$st] = $this->list($projectId, 40, 0, $st, 0)['items'];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function ganttRows(?int $projectId = null): array
    {
        $items = $this->list($projectId, 200, 0, null, null)['items'];
        $rows = [];
        foreach ($items as $t) {
            $rows[] = [
                'id' => (int) $t['id'],
                'task_no' => $t['task_no'] ?? '',
                'title' => $t['title'] ?? '',
                'start_date' => $t['start_date'] ?? null,
                'due_date' => $t['due_date'] ?? null,
                'workflow_status' => $t['workflow_status'] ?? 'new',
                'percent_complete' => (float) ($t['percent_complete'] ?? 0),
                'parent_task_id' => $t['parent_task_id'] ?? null,
            ];
        }

        return $rows;
    }
}

final class ProjectPhaseService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $rows = (new ProjectPhase())->query(
            'SELECT * FROM rateb_project_phases
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC',
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
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = 'PH-' . substr((string) time(), -6);
        }
        $id = (new ProjectPhase())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => ProjectSupport::nullIfEmpty($input['name_ar'] ?? null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'start_date' => ProjectSupport::nullIfEmpty($input['start_date'] ?? null),
            'end_date' => ProjectSupport::nullIfEmpty($input['end_date'] ?? null),
            'status' => 'planned',
        ], ProjectSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ProjectMilestoneService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $rows = (new ProjectMilestone())->query(
            'SELECT * FROM rateb_project_milestones
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY sort_order ASC, due_date ASC, id ASC',
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
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $id = (new ProjectMilestone())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'phase_id' => ProjectSupport::intOrNull($input['phase_id'] ?? null),
            'name' => substr($name, 0, 190),
            'name_ar' => ProjectSupport::nullIfEmpty($input['name_ar'] ?? null),
            'due_date' => ProjectSupport::nullIfEmpty($input['due_date'] ?? null),
            'status' => 'pending',
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'notes' => ProjectSupport::nullIfEmpty($input['notes'] ?? null),
        ], ProjectSupport::actorFields(true)));

        (new ProjectTimelineService())->record(
            'milestone_created',
            'Milestone: ' . $name,
            null,
            $projectId,
            null,
            'milestone',
            (int) $id,
            ['project_id' => $projectId, 'milestone_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }
}

final class ProjectIssueService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $rows = (new ProjectIssue())->query(
            'SELECT * FROM rateb_project_issues
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'pid' => $projectId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, issue_no: string}
     */
    public function create(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $projectId = (int) ($input['project_id'] ?? 0);
        ProjectSupport::assertProject($projectId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $issueNo = ProjectSupport::nextChildNo('rateb_project_issues', 'ISS', $projectId);
        $id = (new ProjectIssue())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'task_id' => ProjectSupport::intOrNull($input['task_id'] ?? null),
            'issue_no' => $issueNo,
            'title' => substr($title, 0, 190),
            'description' => ProjectSupport::nullIfEmpty($input['description'] ?? null),
            'severity' => in_array(($input['severity'] ?? 'medium'), ['low', 'medium', 'high', 'critical'], true)
                ? $input['severity'] : 'medium',
            'status' => 'open',
            'assignee_user_id' => ProjectSupport::intOrNull($input['assignee_user_id'] ?? null),
            'due_date' => ProjectSupport::nullIfEmpty($input['due_date'] ?? null),
        ], ProjectSupport::actorFields(true)));

        return ['id' => (int) $id, 'issue_no' => $issueNo];
    }
}

final class ProjectRiskService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $rows = (new ProjectRisk())->query(
            'SELECT * FROM rateb_project_risks
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'pid' => $projectId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, risk_no: string}
     */
    public function create(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $projectId = (int) ($input['project_id'] ?? 0);
        ProjectSupport::assertProject($projectId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $riskNo = ProjectSupport::nextChildNo('rateb_project_risks', 'RSK', $projectId);
        $id = (new ProjectRisk())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'risk_no' => $riskNo,
            'title' => substr($title, 0, 190),
            'description' => ProjectSupport::nullIfEmpty($input['description'] ?? null),
            'probability' => in_array(($input['probability'] ?? 'medium'), ['low', 'medium', 'high'], true)
                ? $input['probability'] : 'medium',
            'impact' => in_array(($input['impact'] ?? 'medium'), ['low', 'medium', 'high'], true)
                ? $input['impact'] : 'medium',
            'status' => 'identified',
            'owner_user_id' => ProjectSupport::intOrNull($input['owner_user_id'] ?? null),
            'mitigation_plan' => ProjectSupport::nullIfEmpty($input['mitigation_plan'] ?? null),
        ], ProjectSupport::actorFields(true)));

        return ['id' => (int) $id, 'risk_no' => $riskNo];
    }
}

final class ProjectBudgetService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $rows = (new ProjectBudget())->query(
            'SELECT * FROM rateb_project_budgets
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY id DESC',
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
        $id = (new ProjectBudget())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'category' => substr(trim((string) ($input['category'] ?? 'general')), 0, 80) ?: 'general',
            'planned_amount' => (float) ($input['planned_amount'] ?? 0),
            'currency_code' => ProjectSupport::nullIfEmpty($input['currency_code'] ?? null),
            'notes' => ProjectSupport::nullIfEmpty($input['notes'] ?? null),
            'status' => 'draft',
        ], ProjectSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function recordCost(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $projectId = (int) ($input['project_id'] ?? 0);
        ProjectSupport::assertProject($projectId, $companyId);
        $costDate = trim((string) ($input['cost_date'] ?? ''));
        if ($costDate === '') {
            $costDate = date('Y-m-d');
        }
        $id = (new ProjectCost())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'budget_id' => ProjectSupport::intOrNull($input['budget_id'] ?? null),
            'cost_date' => $costDate,
            'amount' => (float) ($input['amount'] ?? 0),
            'currency_code' => ProjectSupport::nullIfEmpty($input['currency_code'] ?? null),
            'category' => ProjectSupport::nullIfEmpty($input['category'] ?? null),
            'description' => ProjectSupport::nullIfEmpty($input['description'] ?? null),
            'status' => 'recorded',
        ], ProjectSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @return list<array<string,mixed>> */
    public function listCosts(int $projectId): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $rows = (new ProjectCost())->query(
            'SELECT * FROM rateb_project_costs
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY cost_date DESC, id DESC',
            ['cid' => $companyId, 'pid' => $projectId]
        );

        return is_array($rows) ? $rows : [];
    }
}

final class ProjectResourceService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $rows = (new ProjectResource())->query(
            'SELECT * FROM rateb_project_resources
             WHERE company_id = :cid AND project_id = :pid AND deleted_at IS NULL
             ORDER BY id DESC',
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
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $id = (new ProjectResource())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'resource_type' => in_array(($input['resource_type'] ?? 'user'), ['user', 'equipment', 'material', 'other'], true)
                ? $input['resource_type'] : 'user',
            'name' => substr($name, 0, 190),
            'user_id' => ProjectSupport::intOrNull($input['user_id'] ?? null),
            'allocation_percent' => ProjectSupport::floatOrNull($input['allocation_percent'] ?? null),
            'start_date' => ProjectSupport::nullIfEmpty($input['start_date'] ?? null),
            'end_date' => ProjectSupport::nullIfEmpty($input['end_date'] ?? null),
            'cost_rate' => ProjectSupport::floatOrNull($input['cost_rate'] ?? null),
            'currency_code' => ProjectSupport::nullIfEmpty($input['currency_code'] ?? null),
            'status' => 'planned',
            'notes' => ProjectSupport::nullIfEmpty($input['notes'] ?? null),
        ], ProjectSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ProjectCommentService
{
    /** @return list<array<string,mixed>> */
    public function listForProject(int $projectId, ?int $taskId = null): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        ProjectSupport::assertProject($projectId, $companyId);
        $params = ['cid' => $companyId, 'pid' => $projectId];
        $where = 'company_id = :cid AND project_id = :pid AND deleted_at IS NULL';
        if ($taskId !== null) {
            $where .= ' AND task_id = :tid';
            $params['tid'] = $taskId;
        }
        $rows = (new ProjectComment())->query(
            'SELECT * FROM rateb_project_comments WHERE ' . $where . ' ORDER BY id DESC LIMIT 100',
            $params
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
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('body_required');
        }
        $id = (new ProjectComment())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'project_id' => $projectId,
            'task_id' => ProjectSupport::intOrNull($input['task_id'] ?? null),
            'body' => $body,
        ], ProjectSupport::actorFields(true)));

        (new ProjectTimelineService())->record(
            'comment',
            'Comment added',
            substr($body, 0, 200),
            $projectId,
            ProjectSupport::intOrNull($input['task_id'] ?? null),
            'comment',
            (int) $id,
            ['project_id' => $projectId]
        );

        return ['id' => (int) $id];
    }
}

final class ProjectAssignmentService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function assign(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $relatedType = trim((string) ($input['related_type'] ?? 'project'));
        $relatedId = (int) ($input['related_id'] ?? 0);
        $assignee = (int) ($input['assignee_user_id'] ?? 0);
        if ($relatedId < 1 || $assignee < 1) {
            throw new \InvalidArgumentException('assignment_required');
        }
        if ($relatedType === 'project') {
            ProjectSupport::assertProject($relatedId, $companyId);
        } elseif ($relatedType === 'task') {
            ProjectSupport::assertTask($relatedId, $companyId);
        }
        $id = (new ProjectAssignment())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'related_type' => substr($relatedType, 0, 40),
            'related_id' => $relatedId,
            'assignee_user_id' => $assignee,
            'role_label' => ProjectSupport::nullIfEmpty($input['role_label'] ?? null),
            'status' => 'active',
        ], ProjectSupport::actorFields(true)));

        if ($relatedType === 'project') {
            (new ProjectMember())->create(array_merge([
                'public_uuid' => ProjectSupport::uuidV4(),
                'company_id' => $companyId,
                'branch_id' => ProjectSupport::branchId(),
                'project_id' => $relatedId,
                'user_id' => $assignee,
                'role_id' => ProjectSupport::intOrNull($input['role_id'] ?? null),
                'role_label' => ProjectSupport::nullIfEmpty($input['role_label'] ?? null),
                'status' => 'active',
            ], ProjectSupport::actorFields(true)));
            (new Project())->update($relatedId, array_merge([
                'owner_user_id' => $assignee,
            ], ProjectSupport::actorFields(false)));
        } elseif ($relatedType === 'task') {
            (new ProjectTask())->update($relatedId, array_merge([
                'assignee_user_id' => $assignee,
            ], ProjectSupport::actorFields(false)));
        }

        return ['id' => (int) $id];
    }
}

final class ProjectTagService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProjectSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]+/', '', $name) ?: 'TAG', 0, 20));
        }
        $id = (new ProjectTag())->create(array_merge([
            'public_uuid' => ProjectSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProjectSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 120),
            'name_ar' => ProjectSupport::nullIfEmpty($input['name_ar'] ?? null),
            'color' => ProjectSupport::nullIfEmpty($input['color'] ?? null),
            'status' => 'active',
        ], ProjectSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
