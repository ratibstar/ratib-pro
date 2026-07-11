<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\ProjectActivityService;
use Rateb\App\Services\ProjectAssignmentService;
use Rateb\App\Services\ProjectBudgetService;
use Rateb\App\Services\ProjectCommentService;
use Rateb\App\Services\ProjectIssueService;
use Rateb\App\Services\ProjectMilestoneService;
use Rateb\App\Services\ProjectResourceService;
use Rateb\App\Services\ProjectRiskService;
use Rateb\App\Services\ProjectService;
use Rateb\App\Services\ProjectTaskService;
use Rateb\App\Services\ProjectTimelineService;
use Rateb\App\Services\ProjectTimesheetService;
use Rateb\App\Services\ProjectWorkflowService;

/**
 * Phase 18A — Projects ONLINE controllers (thin).
 * All mutations go through domain services.
 */
final class ProjectsDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projects = (new ProjectService())->list(8, 0, '');
        $this->view('company/projects/dashboard', [
            'title' => __('projects'),
            'recent' => $projects['items'],
            'total' => $projects['total'],
            'board' => (new ProjectService())->boardCounts(),
            'timeline' => (new ProjectTimelineService())->listRecent(10),
            'statuses' => ProjectWorkflowService::projectStatuses(),
        ], 'main');
    }
}

final class ProjectsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new ProjectService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/projects/index', [
            'title' => __('projects'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'board' => (new ProjectService())->boardCounts(),
            'statuses' => ProjectWorkflowService::projectStatuses(),
            'canCreate' => rateb_can('projects.create') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/projects/form', [
            'title' => __('project_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('projects')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        try {
            $created = (new ProjectService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('projects') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('projects/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new ProjectService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        $this->view('company/projects/show', [
            'title' => $item['name'] ?? __('projects'),
            'item' => $item,
            'tasks' => (new ProjectTaskService())->list($id, 30, 0)['items'],
            'milestones' => (new ProjectMilestoneService())->listForProject($id),
            'issues' => (new ProjectIssueService())->listForProject($id),
            'risks' => (new ProjectRiskService())->listForProject($id),
            'comments' => (new ProjectCommentService())->listForProject($id),
            'timeline' => (new ProjectTimelineService())->listForProject($id),
            'statuses' => ProjectWorkflowService::projectStatuses(),
            'transitions' => ProjectWorkflowService::allowedProjectTransitions()[$item['workflow_status'] ?? 'draft'] ?? [],
            'canWorkflow' => rateb_can('projects.update') || rateb_can('projects.manage') || rateb_can('projects.admin'),
            'canAssign' => rateb_can('projects.assign') || rateb_can('projects.manage'),
            'canTasks' => rateb_can('projects.tasks') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function edit(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new ProjectService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        $this->view('company/projects/form', [
            'title' => __('project_edit'),
            'item' => $item,
            'action' => rateb_url(rateb_app_route('projects') . '/' . $id),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProjectService())->update($id, $_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('projects') . '/' . $id));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('projects') . '/' . $id . '/edit'));
        }
    }

    public function destroy(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProjectService())->softDelete($id);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('projects')));
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProjectWorkflowService())->transitionProject(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('projects') . '/' . $id));
    }

    public function assign(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProjectAssignmentService())->assign([
                'related_type' => 'project',
                'related_id' => $id,
                'assignee_user_id' => (int) ($_POST['assignee_user_id'] ?? 0),
                'role_label' => $_POST['role_label'] ?? null,
            ]);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('projects') . '/' . $id));
    }

    public function storeComment(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProjectCommentService())->create(array_merge($_POST, ['project_id' => $id]));
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('projects') . '/' . $id));
    }
}

final class ProjectTasksController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        $result = (new ProjectTaskService())->list($projectId, 50, 0);
        $this->view('company/projects/tasks/index', [
            'title' => __('project_tasks'),
            'items' => $result['items'],
            'total' => $result['total'],
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'taskStatuses' => ProjectWorkflowService::taskStatuses(),
            'canCreate' => rateb_can('projects.tasks') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function kanban(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        $this->view('company/projects/tasks/kanban', [
            'title' => __('project_kanban'),
            'columns' => (new ProjectTaskService())->kanban($projectId),
            'statuses' => ProjectWorkflowService::taskStatuses(),
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
        ], 'main');
    }

    public function gantt(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        $this->view('company/projects/tasks/gantt', [
            'title' => __('project_gantt'),
            'rows' => (new ProjectTaskService())->ganttRows($projectId),
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
        ], 'main');
    }

    public function calendar(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        $this->view('company/projects/tasks/calendar', [
            'title' => __('project_calendar'),
            'items' => (new ProjectTaskService())->list($projectId, 200, 0)['items'],
            'milestones' => $projectId ? (new ProjectMilestoneService())->listForProject($projectId) : [],
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/tasks')));
        }
        try {
            (new ProjectTaskService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/tasks') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/tasks')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $result = (new ProjectWorkflowService())->transitionTask(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('projects') . '/' . $result['project_id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('projects/tasks')));
        }
    }
}

final class ProjectMilestonesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $items = $projectId > 0 ? (new ProjectMilestoneService())->listForProject($projectId) : [];
        $this->view('company/projects/milestones/index', [
            'title' => __('project_milestones'),
            'items' => $items,
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'canCreate' => rateb_can('projects.update') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/milestones')));
        }
        try {
            (new ProjectMilestoneService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/milestones') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }
}

final class ProjectIssuesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $this->view('company/projects/issues/index', [
            'title' => __('project_issues'),
            'items' => $projectId > 0 ? (new ProjectIssueService())->listForProject($projectId) : [],
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'canCreate' => rateb_can('projects.update') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/issues')));
        }
        try {
            (new ProjectIssueService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/issues') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }
}

final class ProjectRisksController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $this->view('company/projects/risks/index', [
            'title' => __('project_risks'),
            'items' => $projectId > 0 ? (new ProjectRiskService())->listForProject($projectId) : [],
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'canCreate' => rateb_can('projects.update') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/risks')));
        }
        try {
            (new ProjectRiskService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/risks') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }
}

final class ProjectTimesheetsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        $result = (new ProjectTimesheetService())->list($projectId, 50, 0);
        $this->view('company/projects/timesheets/index', [
            'title' => __('project_timesheets'),
            'items' => $result['items'],
            'total' => $result['total'],
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'canCreate' => rateb_can('projects.timesheets') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/timesheets')));
        }
        try {
            (new ProjectTimesheetService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/timesheets') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }
}

final class ProjectResourcesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $this->view('company/projects/resources/index', [
            'title' => __('project_resources'),
            'items' => $projectId > 0 ? (new ProjectResourceService())->listForProject($projectId) : [],
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'canCreate' => rateb_can('projects.update') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/resources')));
        }
        try {
            (new ProjectResourceService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/resources') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }
}

final class ProjectBudgetController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $this->view('company/projects/budget/index', [
            'title' => __('project_budget'),
            'budgets' => $projectId > 0 ? (new ProjectBudgetService())->listForProject($projectId) : [],
            'costs' => $projectId > 0 ? (new ProjectBudgetService())->listCosts($projectId) : [],
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'canCreate' => rateb_can('projects.budget') || rateb_can('projects.manage'),
        ], 'main');
    }

    public function storeBudget(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/budget')));
        }
        try {
            (new ProjectBudgetService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/budget') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }

    public function storeCost(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/budget')));
        }
        try {
            (new ProjectBudgetService())->recordCost($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/budget') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }
}

final class ProjectTimelineController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $this->view('company/projects/timeline/index', [
            'title' => __('project_timeline'),
            'items' => $projectId > 0
                ? (new ProjectTimelineService())->listForProject($projectId)
                : (new ProjectTimelineService())->listRecent(40),
            'project_id' => $projectId,
            'projects' => (new ProjectService())->list(100, 0)['items'],
            'activities' => $projectId > 0 ? (new ProjectActivityService())->listForProject($projectId) : [],
        ], 'main');
    }

    public function storeActivity(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('projects/timeline')));
        }
        try {
            (new ProjectActivityService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $pid = (int) ($_POST['project_id'] ?? 0);
        $this->redirect(rateb_url(rateb_app_route('projects/timeline') . ($pid > 0 ? '?project_id=' . $pid : '')));
    }
}

final class ProjectReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/projects/reports/index', [
            'title' => __('project_reports'),
            'board' => (new ProjectService())->boardCounts(),
            'taskBoard' => (new ProjectTaskService())->kanban(null),
            'statuses' => ProjectWorkflowService::projectStatuses(),
            'taskStatuses' => ProjectWorkflowService::taskStatuses(),
        ], 'main');
    }
}
