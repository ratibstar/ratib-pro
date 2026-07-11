<?php

declare(strict_types=1);

/**
 * Phase 18A — Enterprise Projects Platform (ONLINE) gate tests.
 *
 * Run: php tests/projects/run-projects-phase18a-tests.php
 */

final class ProjectsPhase18ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineProjects();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testNoServiceNameCollisions();
        $this->testWorkflowMaps();
        $this->testUuidHelper();
        $this->testControllersAndViews();
        $this->testRoutesRegistered();
        $this->testRbacConfig();
        $this->testSidebarAndLang();
        $this->testDistinctFromCrmTasks();
        $this->testArchitectureDoc();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testBaselineUntouched(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Enterprise Baseline / Offline Foundation markers intact', $ok);
    }

    private function testNoOfflineProjects(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/ProjectDomainServices.php');
        $flags = (string) file_get_contents(RATEB_ROOT . '/offline/config/feature-flags.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($domain, 'offline.projects')
            && !str_contains($flags, 'offline.projects');
        $this->record('No Offline Projects in 18A (online foundation only)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/184_projects_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_projects')
            && str_contains($sql, 'rateb_project_members')
            && str_contains($sql, 'rateb_project_roles')
            && str_contains($sql, 'rateb_project_phases')
            && str_contains($sql, 'rateb_project_milestones')
            && str_contains($sql, 'rateb_project_tasks')
            && str_contains($sql, 'parent_task_id')
            && str_contains($sql, 'rateb_project_activities')
            && str_contains($sql, 'rateb_project_timeline')
            && str_contains($sql, 'rateb_project_issues')
            && str_contains($sql, 'rateb_project_risks')
            && str_contains($sql, 'rateb_project_timesheets')
            && str_contains($sql, 'rateb_project_resources')
            && str_contains($sql, 'rateb_project_costs')
            && str_contains($sql, 'rateb_project_budgets')
            && str_contains($sql, 'rateb_project_comments')
            && str_contains($sql, 'rateb_project_assignments')
            && str_contains($sql, 'rateb_project_tags')
            && str_contains($sql, 'rateb_project_status_history')
            && str_contains($sql, 'projects.view')
            && str_contains($sql, 'projects.tasks')
            && str_contains($sql, 'projects.timesheets')
            && str_contains($sql, 'projects.budget')
            && str_contains($sql, 'projects.admin')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version');
        $this->record('migration 184 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\ProjectSupport::class)
            && class_exists(\Rateb\App\Services\ProjectService::class)
            && class_exists(\Rateb\App\Services\ProjectTaskService::class)
            && class_exists(\Rateb\App\Services\ProjectMilestoneService::class)
            && class_exists(\Rateb\App\Services\ProjectPhaseService::class)
            && class_exists(\Rateb\App\Services\ProjectTimelineService::class)
            && class_exists(\Rateb\App\Services\ProjectTimesheetService::class)
            && class_exists(\Rateb\App\Services\ProjectIssueService::class)
            && class_exists(\Rateb\App\Services\ProjectRiskService::class)
            && class_exists(\Rateb\App\Services\ProjectBudgetService::class)
            && class_exists(\Rateb\App\Services\ProjectResourceService::class)
            && class_exists(\Rateb\App\Services\ProjectCommentService::class)
            && class_exists(\Rateb\App\Services\ProjectAssignmentService::class)
            && class_exists(\Rateb\App\Services\ProjectWorkflowService::class)
            && class_exists(\Rateb\App\Services\ProjectActivityService::class);
        $this->record('domain services present', $ok);
    }

    private function testNoServiceNameCollisions(): void
    {
        $ok = class_exists(\Rateb\App\Services\TaskService::class)
            && class_exists(\Rateb\App\Services\ProjectTaskService::class)
            && class_exists(\Rateb\App\Services\AssignmentService::class)
            && class_exists(\Rateb\App\Services\ProjectAssignmentService::class)
            && class_exists(\Rateb\App\Services\ActivityService::class)
            && class_exists(\Rateb\App\Services\ProjectActivityService::class);
        $this->record('Project* names avoid CRM/Recruitment collisions', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $p = \Rateb\App\Services\ProjectWorkflowService::projectStatuses();
        $t = \Rateb\App\Services\ProjectWorkflowService::taskStatuses();
        $pm = \Rateb\App\Services\ProjectWorkflowService::allowedProjectTransitions();
        $tm = \Rateb\App\Services\ProjectWorkflowService::allowedTaskTransitions();
        $ok = $p === ['draft', 'planned', 'active', 'on_hold', 'completed', 'cancelled', 'archived']
            && $t === ['new', 'assigned', 'in_progress', 'review', 'done', 'cancelled']
            && in_array('planned', $pm['draft'], true)
            && in_array('active', $pm['planned'], true)
            && $pm['archived'] === []
            && in_array('assigned', $tm['new'], true)
            && in_array('done', $tm['review'], true)
            && $tm['done'] === [];
        $this->record('project + task workflow maps', $ok, implode(',', $p) . ' | ' . implode(',', $t));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\ProjectSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\ProjectsDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\ProjectsController::class)
            && class_exists(\Rateb\App\Controllers\Company\ProjectTasksController::class)
            && is_file(RATEB_ROOT . '/views/company/projects/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/projects/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/show.php')
            && is_file(RATEB_ROOT . '/views/company/projects/tasks/kanban.php')
            && is_file(RATEB_ROOT . '/views/company/projects/tasks/gantt.php')
            && is_file(RATEB_ROOT . '/views/company/projects/tasks/calendar.php')
            && is_file(RATEB_ROOT . '/views/company/projects/milestones/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/issues/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/risks/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/timesheets/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/resources/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/budget/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/timeline/index.php')
            && is_file(RATEB_ROOT . '/views/company/projects/reports/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "projects/list")
            && str_contains($routes, "projects/tasks/kanban")
            && str_contains($routes, "projects/tasks/gantt")
            && str_contains($routes, "projects/milestones")
            && str_contains($routes, "projects/timesheets")
            && str_contains($routes, "projects/budget")
            && str_contains($routes, 'ProjectsController')
            && str_contains($routes, 'Phase 18A');
        $this->record('routes registered', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $modules = $perms['company_modules'] ?? [];
        $implies = $perms['permission_implies'] ?? [];
        $ok = in_array('projects', $modules, true)
            && isset($implies['projects.manage'], $implies['projects.admin'])
            && isset($entities['projects'])
            && isset($labels['projects.view'], $labels['projects.tasks'], $labels['projects.timesheets'], $labels['projects.budget']);
        $this->record('RBAC module + implies + labels', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = (string) file_get_contents(RATEB_ROOT . '/config/lang/en.php');
        $ar = (string) file_get_contents(RATEB_ROOT . '/config/lang/ar.php');
        $ok = str_contains($nav, "'projects'")
            && str_contains($nav, 'projects/tasks')
            && str_contains($en, "'projects' =>")
            && str_contains($ar, "'projects' =>")
            && str_contains($en, "'project_kanban' =>")
            && str_contains($ar, "'project_kanban' =>");
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testDistinctFromCrmTasks(): void
    {
        $sql = (string) file_get_contents(RATEB_ROOT . '/migrations/184_projects_platform_enterprise.sql');
        $ok = str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_project_tasks')
            && !str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_tasks')
            && class_exists(\Rateb\App\Models\ProjectTask::class)
            && class_exists(\Rateb\App\Models\CrmTask::class);
        $this->record('project tasks distinct from CRM tasks', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $ok = is_file(RATEB_ROOT . '/docs/PHASE_18A_PROJECTS_ONLINE.md');
        $this->record('architecture doc present', $ok);
    }
}
