<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\ActivityService;
use Rateb\App\Services\CallService;
use Rateb\App\Services\CampaignService;
use Rateb\App\Services\ContactService;
use Rateb\App\Services\CrmAssignmentService;
use Rateb\App\Services\CrmCompanyService;
use Rateb\App\Services\CrmActivityIntelligenceService;
use Rateb\App\Services\CrmAdminConfigService;
use Rateb\App\Services\CrmAdvancedDashboardService;
use Rateb\App\Services\CrmAnalyticsService;
use Rateb\App\Services\CrmAutomationRulesEngineService;
use Rateb\App\Services\CrmAutomationService;
use Rateb\App\Services\CrmConversionService;
use Rateb\App\Services\CrmCustomer360Service;
use Rateb\App\Services\CrmDashboardService;
use Rateb\App\Services\CrmEnterpriseForecastService;
use Rateb\App\Services\CrmForecastEngineService;
use Rateb\App\Services\CrmGovernanceService;
use Rateb\App\Services\CrmLifecycleService;
use Rateb\App\Services\CrmNoteService;
use Rateb\App\Services\CrmOpportunityIntelligenceService;
use Rateb\App\Services\CrmPipelineHealthService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmQuotationWorkflowService;
use Rateb\App\Services\CrmReportExportService;
use Rateb\App\Services\CrmReportService;
use Rateb\App\Services\CrmRetentionService;
use Rateb\App\Services\CrmRevenueIntelligenceService;
use Rateb\App\Services\CrmRevenueTrackingService;
use Rateb\App\Services\CrmSalesPerformanceService;
use Rateb\App\Services\CrmSalesTeamService;
use Rateb\App\Services\CrmSalesWorkspaceService;
use Rateb\App\Services\CrmTimelineService;
use Rateb\App\Services\CrmWorkflowService;
use Rateb\App\Services\LeadService;
use Rateb\App\Services\MeetingService;
use Rateb\App\Services\OpportunityService;
use Rateb\App\Services\PipelineService;
use Rateb\App\Services\TaskService;

/**
 * Phase 17A — CRM ONLINE controllers (thin).
 * All mutations go through domain services.
 */
final class CrmDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $leads = (new LeadService())->list(8, 0, '');
        $this->view('company/crm/dashboard', [
            'title' => __('crm'),
            'recent' => $leads['items'],
            'total' => $leads['total'],
            'board' => (new LeadService())->boardCounts(),
            'kpis' => (new CrmDashboardService())->kpis(),
            'timeline' => (new CrmTimelineService())->listExpanded(15),
            'statuses' => CrmWorkflowService::statuses(),
        ], 'main');
    }
}

final class CrmLeadsController extends Controller
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
        $result = (new LeadService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/crm/leads/index', [
            'title' => __('crm_leads'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'board' => (new LeadService())->boardCounts(),
            'statuses' => CrmWorkflowService::statuses(),
            'canCreate' => rateb_can('crm.create') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function board(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/crm/leads/board', [
            'title' => __('crm_lead_board'),
            'board' => (new LeadService())->boardCounts(),
            'statuses' => CrmWorkflowService::statuses(),
            'byStatus' => $this->leadsByStatus(),
        ], 'main');
    }

    /** @return array<string, list<array<string,mixed>>> */
    private function leadsByStatus(): array
    {
        $out = [];
        foreach (CrmWorkflowService::statuses() as $st) {
            $out[$st] = (new LeadService())->list(20, 0, '', $st)['items'];
        }

        return $out;
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/crm/leads/form', [
            'title' => __('crm_lead_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('crm/leads')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        try {
            $created = (new LeadService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/leads/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new LeadService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $this->view('company/crm/leads/show', [
            'title' => $item['title'] ?? __('crm_leads'),
            'item' => $item,
            'timeline' => (new CrmTimelineService())->listForLead($id),
            'statuses' => CrmWorkflowService::statuses(),
            'transitions' => CrmWorkflowService::allowedTransitions()[$item['workflow_status'] ?? 'new'] ?? [],
            'canWorkflow' => rateb_can('crm.update') || rateb_can('crm.manage') || rateb_can('crm.admin'),
            'canAssign' => rateb_can('crm.assign') || rateb_can('crm.manage'),
            'canActivities' => rateb_can('crm.activities') || rateb_can('crm.manage'),
            'canConvert' => rateb_can('crm.create') || rateb_can('crm.manage') || rateb_can('crm.admin'),
        ], 'main');
    }

    public function convertToOpportunity(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $created = (new CrmConversionService())->leadToOpportunity($id, $_POST);
            SessionManager::flash('success', __('saved_ok') . ' — ' . $created['opportunity_no']);
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities') . '/' . $created['opportunity_id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/leads') . '/' . $id));
        }
    }

    public function edit(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new LeadService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $this->view('company/crm/leads/form', [
            'title' => __('crm_lead_edit'),
            'item' => $item,
            'action' => rateb_url(rateb_app_route('crm/leads') . '/' . $id),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new LeadService())->update($id, $_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads') . '/' . $id));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/leads') . '/' . $id . '/edit'));
        }
    }

    public function destroy(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new LeadService())->softDelete($id);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/leads')));
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new CrmWorkflowService())->transition(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/leads') . '/' . $id));
    }

    public function assign(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new CrmAssignmentService())->assign([
                'related_type' => 'lead',
                'related_id' => $id,
                'assignee_user_id' => (int) ($_POST['assignee_user_id'] ?? 0),
                'role_label' => $_POST['role_label'] ?? 'owner',
            ]);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/leads') . '/' . $id));
    }

    public function storeNote(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/leads')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new CrmNoteService())->create([
                'related_type' => 'lead',
                'related_id' => $id,
                'lead_id' => $id,
                'body' => (string) ($_POST['body'] ?? ''),
            ]);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/leads') . '/' . $id));
    }
}

final class CrmPipelineController extends Controller
{
    private function canViewPipeline(): bool
    {
        return rateb_can('crm.pipeline.view') || rateb_can('crm.pipeline') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canManagePipeline(): bool
    {
        return rateb_can('crm.pipeline.manage') || rateb_can('crm.pipeline') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canForecast(): bool
    {
        return rateb_can('crm.pipeline.forecast') || rateb_can('crm.pipeline') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $pipelineId = (int) ($_GET['pipeline_id'] ?? 0);
        $svc = new PipelineService();
        $board = $svc->board($pipelineId > 0 ? $pipelineId : null);
        $pid = (int) (($board['pipeline']['id'] ?? 0));
        $healthSvc = new CrmPipelineHealthService();
        $this->view('company/crm/pipeline/index', [
            'title' => __('crm_pipeline'),
            'pipelines' => $svc->listPipelines(),
            'board' => $board,
            'lossReasons' => $svc->listLossReasons(),
            'forecast' => $this->canForecast() ? (new CrmReportService())->forecast($pid > 0 ? $pid : null) : null,
            'health' => $healthSvc->healthScore($pid > 0 ? $pid : null),
            'bottlenecks' => $healthSvc->bottleneckAnalysis($pid > 0 ? $pid : null),
            'stage_durations' => $healthSvc->stageDurationTracking($pid > 0 ? $pid : null),
            'canManage' => $this->canManagePipeline(),
            'canForecast' => $this->canForecast(),
        ], 'main');
    }

    public function storePipeline(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
        }
        try {
            (new PipelineService())->createPipeline($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
    }

    public function storeStage(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
        }
        try {
            $stageId = (int) ($_POST['stage_id'] ?? 0);
            (new PipelineService())->upsertStage($_POST, $stageId > 0 ? $stageId : null);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
    }

    public function storeLossReason(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
        }
        try {
            (new PipelineService())->createLossReason($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
    }

    public function moveOpportunity(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new OpportunityService())->moveStage($id, (int) ($_POST['stage_id'] ?? 0), $_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
    }
}

final class CrmOpportunitiesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new OpportunityService())->list($limit, ($page - 1) * $limit, trim((string) ($_GET['q'] ?? '')));
        $this->view('company/crm/opportunities/index', [
            'title' => __('crm_opportunities'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => trim((string) ($_GET['q'] ?? '')),
            'canCreate' => rateb_can('crm.create') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/crm/opportunities/form', [
            'title' => __('crm_opportunity_create'),
            'pipelines' => (new PipelineService())->listPipelines(),
            'action' => rateb_url(rateb_app_route('crm/opportunities')),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities')));
        }
        try {
            $created = (new OpportunityService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new OpportunityService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities')));
        }
        $this->view('company/crm/opportunities/show', [
            'title' => __('crm_opportunities'),
            'item' => $item,
            'timeline' => (new CrmTimelineService())->listForOpportunity($id, 40),
            'canConvert' => rateb_can('crm.quote.create') || rateb_can('crm.create') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function convertToQuotation(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $created = (new CrmConversionService())->opportunityToQuotation($id, $_POST);
            SessionManager::flash('success', __('saved_ok') . ' — ' . $created['quotation_no']);
            $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $created['quotation_id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities') . '/' . $id));
        }
    }
}

final class CrmMeetingsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new MeetingService())->list(50, 0);
        $this->view('company/crm/meetings/index', [
            'title' => __('crm_meetings'),
            'items' => $result['items'],
            'total' => $result['total'],
            'canCreate' => rateb_can('crm.activities') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/meetings')));
        }
        try {
            (new MeetingService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/meetings')));
    }
}

final class CrmTasksController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new TaskService())->list(50, 0, trim((string) ($_GET['status'] ?? '')) ?: null);
        $this->view('company/crm/tasks/index', [
            'title' => __('crm_tasks'),
            'items' => $result['items'],
            'total' => $result['total'],
            'canCreate' => rateb_can('crm.activities') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/tasks')));
        }
        try {
            (new TaskService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/tasks')));
    }

    public function complete(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/tasks')));
        }
        try {
            (new TaskService())->complete((int) ($params['id'] ?? 0));
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/tasks')));
    }
}

final class CrmCampaignsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new CampaignService())->list(50, 0);
        $this->view('company/crm/campaigns/index', [
            'title' => __('crm_campaigns'),
            'items' => $result['items'],
            'total' => $result['total'],
            'canCreate' => rateb_can('crm.campaign') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/campaigns')));
        }
        try {
            (new CampaignService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/campaigns')));
    }
}

final class CrmContactsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $crmCompanyId = (int) ($_GET['crm_company_id'] ?? 0);
        $result = (new ContactService())->list(
            50,
            0,
            trim((string) ($_GET['q'] ?? '')),
            $crmCompanyId > 0 ? $crmCompanyId : null
        );
        $this->view('company/crm/contacts/index', [
            'title' => __('crm_contacts'),
            'items' => $result['items'],
            'total' => $result['total'],
            'companies' => (new CrmCompanyService())->list(100, 0)['items'],
            'crm_company_id' => $crmCompanyId,
            'canCreate' => rateb_can('crm.create') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new ContactService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url(rateb_app_route('crm/contacts')));
        }
        $graph = (new ContactService())->relatedGraph($id);
        $this->view('company/crm/contacts/show', [
            'title' => __('crm_contacts'),
            'item' => $item,
            'leads' => $graph['leads'],
            'opportunities' => $graph['opportunities'],
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/contacts')));
        }
        try {
            $created = (new ContactService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('crm/contacts') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/contacts')));
        }
    }
}

final class CrmCompaniesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new CrmCompanyService())->list(50, 0, trim((string) ($_GET['q'] ?? '')));
        $this->view('company/crm/companies/index', [
            'title' => __('crm_companies'),
            'items' => $result['items'],
            'total' => $result['total'],
            'canCreate' => rateb_can('crm.create') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new CrmCompanyService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url(rateb_app_route('crm/companies')));
        }
        $graph = (new CrmCompanyService())->relatedGraph($id);
        $this->view('company/crm/companies/show', [
            'title' => __('crm_companies'),
            'item' => $item,
            'contacts' => $graph['contacts'],
            'leads' => $graph['leads'],
            'opportunities' => $graph['opportunities'],
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/companies')));
        }
        try {
            $created = (new CrmCompanyService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('crm/companies') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/companies')));
        }
    }
}

final class CrmCustomerProfileController extends Controller
{
    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $customerId = (int) ($params['id'] ?? 0);
        try {
            $data = (new CrmCustomer360Service())->assemble($customerId);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm')));

            return;
        }
        $teams = [];
        $territories = [];
        try {
            $teamSvc = new CrmSalesTeamService();
            $teams = $teamSvc->listTeams();
            $territories = $teamSvc->listTerritories();
        } catch (\Throwable $e) {
            $teams = [];
            $territories = [];
        }
        $this->view('company/crm/customer-profile', array_merge($data, [
            'title' => __('crm_customer_360'),
            'customer_id' => $customerId,
            'canLifecycle' => rateb_can('crm.lifecycle.manage') || rateb_can('crm.manage') || rateb_can('crm.admin'),
            'canRetention' => rateb_can('crm.retention.view') || rateb_can('crm.manage') || rateb_can('crm.admin'),
            'teams' => $teams,
            'territories' => $territories,
        ]), 'main');
    }

    public function transitionLifecycle(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm')));
        }
        $customerId = (int) ($params['id'] ?? 0);
        try {
            (new CrmLifecycleService())->transition(
                $customerId,
                (string) ($_POST['to_stage'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/customers') . '/' . $customerId));
    }

    public function assignOwnership(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm')));
        }
        $customerId = (int) ($params['id'] ?? 0);
        try {
            (new CrmLifecycleService())->assignOwnership($customerId, $_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/customers') . '/' . $customerId));
    }

    public function setRenewal(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm')));
        }
        $customerId = (int) ($params['id'] ?? 0);
        try {
            (new CrmRetentionService())->setRenewal($customerId, $_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/customers') . '/' . $customerId));
    }
}

final class CrmReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $reports = new CrmReportService();
        $engine = new CrmForecastEngineService();
        $analytics = new CrmAnalyticsService();
        $pipelineId = (int) ($_GET['pipeline_id'] ?? 0);
        $pid = $pipelineId > 0 ? $pipelineId : null;
        $analyticsDash = (rateb_can('crm.analytics.view') || rateb_can('crm.reports.view') || rateb_can('crm.manage'))
            ? $analytics->dashboard($pid)
            : null;
        $this->view('company/crm/reports/index', [
            'title' => __('crm_reports'),
            'funnel' => $reports->salesFunnel($pid),
            'conversions' => $reports->conversionRates(),
            'sources' => $reports->leadSources(),
            'performance' => $reports->salesPerformance(),
            'lost' => $reports->lostOpportunities(),
            'forecast' => $reports->forecast($pid),
            'engine' => $engine->compute($pid),
            'win_probability' => $engine->winProbabilityTracking(),
            'accuracy' => $engine->accuracyReport(),
            'quote_metrics' => (new CrmQuotationService())->performanceMetrics(),
            'revenue' => (new CrmRevenueTrackingService())->summary(),
            'analytics' => $analyticsDash,
            'activity_intel' => (new CrmActivityIntelligenceService())->analyze(
                (int) ($_GET['owner_user_id'] ?? 0) ?: null,
                trim((string) ($_GET['date_from'] ?? '')) ?: null,
                trim((string) ($_GET['date_to'] ?? '')) ?: null
            ),
            'saved_filters' => (new CrmReportExportService())->listSavedFilters(),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'pipelines' => (new PipelineService())->listPipelines(),
            'pipeline_id' => $pipelineId,
            'canExport' => rateb_can('crm.reports.export') || rateb_can('crm.export.manage') || rateb_can('crm.manage'),
            'canForecastManage' => rateb_can('crm.forecast.manage') || rateb_can('crm.manage'),
            'canAnalytics' => rateb_can('crm.analytics.view') || rateb_can('crm.reports.view') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function snapshot(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/reports')));
        }
        try {
            $created = (new CrmForecastEngineService())->snapshot(
                (int) ($_POST['pipeline_id'] ?? 0) ?: null,
                null,
                trim((string) ($_POST['period_key'] ?? '')) ?: null
            );
            SessionManager::flash('success', __('saved_ok') . ' — ' . $created['period_key']);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/reports')));
    }

    public function export(): void
    {
        if (!(rateb_can('crm.reports.export') || rateb_can('crm.export.manage') || rateb_can('crm.manage') || rateb_can('crm.admin'))) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url(rateb_app_route('crm/reports')));
        }
        (new CrmReportExportService())->streamCsv($_GET);
    }

    public function saveFilter(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/reports')));
        }
        try {
            (new CrmReportExportService())->saveFilter($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/reports')));
    }
}

final class CrmAutomationController extends Controller
{
    public function run(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm')));
        }
        try {
            $result = (new CrmAutomationService())->runAll();
            SessionManager::flash(
                'success',
                __('crm_automation_ran')
                . ' — reminders:' . $result['follow_up']['reminders']
                . ' overdue:' . $result['follow_up']['overdue']
                . ' quote_alerts:' . $result['quote_expiry']['alerts']
                . ' inactive:' . $result['inactivity']['alerts']
                . ' expired:' . $result['expired_quotes']
                . ' no_activity:' . ($result['no_activity']['alerts'] ?? 0)
                . ' renewal:' . ($result['renewal']['alerts'] ?? 0)
                . ' stale:' . ($result['stale']['alerts'] ?? 0)
                . ' followups:' . ($result['customer_follow_up']['alerts'] ?? 0)
            );
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm')));
    }
}

final class CrmAdminController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $cfg = (new CrmAdminConfigService())->overview();
        $engine = new CrmAutomationRulesEngineService();
        $this->view('company/crm/admin/index', [
            'title' => __('crm_admin_config'),
            'pipelines' => $cfg['pipelines'],
            'loss_reasons' => $cfg['loss_reasons'],
            'activity_types' => $cfg['activity_types'],
            'automation_rules' => $cfg['automation_rules'],
            'execution_history' => $engine->executionHistory(30),
            'canManage' => rateb_can('crm.config.manage') || rateb_can('crm.manage') || rateb_can('crm.admin'),
        ], 'main');
    }

    public function storeActivityType(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/admin')));
        }
        try {
            (new CrmAdminConfigService())->saveActivityType($_POST, (int) ($_POST['id'] ?? 0) ?: null);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/admin')));
    }

    public function updateAutomationRule(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/admin')));
        }
        try {
            $id = (int) ($params['id'] ?? 0);
            if (isset($_POST['condition_json']) || isset($_POST['action_json'])) {
                (new CrmAutomationRulesEngineService())->saveRule($id, $_POST);
            } else {
                (new CrmAdminConfigService())->updateAutomationRule($id, $_POST);
            }
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/admin')));
    }
}

/** Phase 6 — Sales execution workspace. */
final class CrmWorkspaceController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $filters = [
            'user_id' => (int) ($_GET['user_id'] ?? 0) ?: null,
            'team_id' => (int) ($_GET['team_id'] ?? 0) ?: null,
            'territory_id' => (int) ($_GET['territory_id'] ?? 0) ?: null,
        ];
        $data = (new CrmSalesWorkspaceService())->assemble($filters);
        $teams = [];
        $territories = [];
        try {
            $teamSvc = new CrmSalesTeamService();
            $teams = $teamSvc->listTeams();
            $territories = $teamSvc->listTerritories();
        } catch (\Throwable $e) {
            // ignore
        }
        $this->view('company/crm/workspace/index', array_merge($data, [
            'title' => __('crm_workspace'),
            'teams' => $teams,
            'territories' => $territories,
        ]), 'main');
    }
}

/** Phase 6 — Advanced role dashboards. */
final class CrmDashboardsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $role = trim((string) ($_GET['role'] ?? 'rep'));
        $dash = (new CrmAdvancedDashboardService())->forRole(
            $role,
            (int) ($_GET['user_id'] ?? 0) ?: null,
            (int) ($_GET['team_id'] ?? 0) ?: null,
            (int) ($_GET['pipeline_id'] ?? 0) ?: null
        );
        $this->view('company/crm/dashboards/index', [
            'title' => __('crm_advanced_dashboards'),
            'dash' => $dash,
            'role' => $dash['role'],
            'pipelines' => (new PipelineService())->listPipelines(),
            'pipeline_id' => (int) ($_GET['pipeline_id'] ?? 0),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'team_id' => (int) ($_GET['team_id'] ?? 0),
        ], 'main');
    }
}

/** Phase 6 — Opportunity intelligence actions. */
final class CrmIntelligenceController extends Controller
{
    public function refresh(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/workspace')));
        }
        try {
            $n = count((new CrmOpportunityIntelligenceService())->refreshOpen(50));
            SessionManager::flash('success', __('saved_ok') . ' — ' . $n);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/workspace')));
    }

    public function scoreOpportunity(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $score = (new CrmOpportunityIntelligenceService())->score($id, true);
            SessionManager::flash('success', __('crm_intelligence_score') . ': ' . $score['intelligence_score']);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/opportunities') . '/' . $id));
    }
}

/** Phase 7 — Revenue intelligence. */
final class CrmRevenueController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $pipelineId = (int) ($_GET['pipeline_id'] ?? 0) ?: null;
        $data = (new CrmRevenueIntelligenceService())->dashboard(
            $pipelineId,
            trim((string) ($_GET['date_from'] ?? '')) ?: null,
            trim((string) ($_GET['date_to'] ?? '')) ?: null
        );
        if (class_exists(\Rateb\App\Services\AuditService::class)) {
            (new \Rateb\App\Services\AuditService())->log('crm.report.access', 'crm_revenue_intel', null, [
                'pipeline_id' => $pipelineId,
            ]);
        }
        $this->view('company/crm/revenue/index', [
            'title' => __('crm_revenue_intelligence'),
            'data' => $data,
            'pipelines' => (new PipelineService())->listPipelines(),
            'pipeline_id' => (int) ($_GET['pipeline_id'] ?? 0),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ], 'main');
    }
}

/** Phase 7 — Enterprise forecasting. */
final class CrmForecastController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $svc = new CrmEnterpriseForecastService();
        $periodType = trim((string) ($_GET['period_type'] ?? 'month'));
        $forecast = $svc->compute(
            $periodType,
            (int) ($_GET['pipeline_id'] ?? 0) ?: null,
            (int) ($_GET['team_id'] ?? 0) ?: null,
            (int) ($_GET['user_id'] ?? 0) ?: null,
            trim((string) ($_GET['period_key'] ?? '')) ?: null
        );
        $this->view('company/crm/forecast/index', [
            'title' => __('crm_enterprise_forecast'),
            'forecast' => $forecast,
            'history' => $svc->changeHistory(30),
            'pipelines' => (new PipelineService())->listPipelines(),
            'period_type' => $periodType,
            'pipeline_id' => (int) ($_GET['pipeline_id'] ?? 0),
            'team_id' => (int) ($_GET['team_id'] ?? 0),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'canManage' => rateb_can('crm.forecast.enterprise') || rateb_can('crm.forecast.manage') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function snapshot(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/forecast')));
        }
        try {
            $created = (new CrmEnterpriseForecastService())->snapshot(
                trim((string) ($_POST['period_type'] ?? 'month')),
                (int) ($_POST['pipeline_id'] ?? 0) ?: null,
                (int) ($_POST['team_id'] ?? 0) ?: null,
                (int) ($_POST['user_id'] ?? 0) ?: null,
                trim((string) ($_POST['period_key'] ?? '')) ?: null
            );
            SessionManager::flash('success', __('saved_ok') . ' — ' . $created['period_key'] . ' (' . $created['confidence_score'] . '%)');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/forecast')));
    }
}

/** Phase 7 — Governance + data quality. */
final class CrmGovernanceController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $svc = new CrmGovernanceService();
        $this->view('company/crm/governance/index', [
            'title' => __('crm_governance'),
            'health' => $svc->healthDashboard(),
            'issues' => $svc->listOpenIssues(50),
            'automation_gov' => $svc->automationGovernanceCheck(),
            'canManage' => rateb_can('crm.governance.manage') || rateb_can('crm.manage') || rateb_can('crm.admin'),
        ], 'main');
    }

    public function scan(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/governance')));
        }
        try {
            $r = (new CrmGovernanceService())->runDataQualityScan(true);
            SessionManager::flash('success', __('saved_ok') . ' — issues:' . $r['created']);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/governance')));
    }

    public function resolve(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/governance')));
        }
        try {
            (new CrmGovernanceService())->resolveIssue((int) ($params['id'] ?? 0), trim((string) ($_POST['note'] ?? '')) ?: null);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/governance')));
    }

    public function saveSetting(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/governance')));
        }
        try {
            $key = trim((string) ($_POST['setting_key'] ?? ''));
            $json = trim((string) ($_POST['setting_json'] ?? ''));
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('invalid_json');
            }
            (new CrmGovernanceService())->saveSetting($key, $decoded);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/governance')));
    }
}

/** Phase 7 — Sales performance management. */
final class CrmPerformanceController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $from = trim((string) ($_GET['date_from'] ?? ''));
        $to = trim((string) ($_GET['date_to'] ?? ''));
        $data = (new CrmSalesPerformanceService())->dashboard($from !== '' ? $from : null, $to !== '' ? $to : null);
        if (class_exists(\Rateb\App\Services\AuditService::class)) {
            (new \Rateb\App\Services\AuditService())->log('crm.report.access', 'crm_performance', null, [
                'date_from' => $from,
                'date_to' => $to,
            ]);
        }
        $this->view('company/crm/performance/index', [
            'title' => __('crm_sales_performance_mgmt'),
            'data' => $data,
            'date_from' => $from,
            'date_to' => $to,
        ], 'main');
    }
}

/** Phase 5 — Sales teams, territories, ownership rules. */
final class CrmTeamsController extends Controller
{
    private function canView(): bool
    {
        return rateb_can('crm.teams.view') || rateb_can('crm.teams.manage') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canManage(): bool
    {
        return rateb_can('crm.teams.manage') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->canView()) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url(rateb_app_route('crm')));
        }
        $svc = new CrmSalesTeamService();
        $teams = $svc->listTeams();
        $membersByTeam = [];
        foreach ($teams as $team) {
            $tid = (int) ($team['id'] ?? 0);
            $membersByTeam[$tid] = $svc->membersFor($tid);
        }
        $this->view('company/crm/teams/index', [
            'title' => __('crm_sales_teams'),
            'teams' => $teams,
            'members_by_team' => $membersByTeam,
            'territories' => $svc->listTerritories(),
            'ownership_rules' => $svc->listOwnershipRules(),
            'canManage' => $this->canManage(),
        ], 'main');
    }

    public function storeTeam(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/teams')));
        }
        try {
            (new CrmSalesTeamService())->createTeam($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/teams')));
    }

    public function storeMember(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/teams')));
        }
        try {
            (new CrmSalesTeamService())->addMember((int) ($params['id'] ?? 0), $_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/teams')));
    }

    public function storeTerritory(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/teams')));
        }
        try {
            (new CrmSalesTeamService())->createTerritory($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/teams')));
    }

    public function storeOwnershipRule(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/teams')));
        }
        try {
            (new CrmSalesTeamService())->saveOwnershipRule($_POST, (int) ($_POST['id'] ?? 0) ?: null);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/teams')));
    }
}

/** Phase 1+2 — Sales quotations lifecycle (no invoice conversion). */
final class CrmQuotationsController extends Controller
{
    private function canViewQuote(): bool
    {
        return rateb_can('crm.quote.view') || rateb_can('crm.view') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canCreateQuote(): bool
    {
        return rateb_can('crm.quote.create') || rateb_can('crm.create') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canUpdateQuote(): bool
    {
        return rateb_can('crm.quote.update') || rateb_can('crm.update') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canConvertQuote(): bool
    {
        return rateb_can('crm.quote.convert') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canApproveQuote(): bool
    {
        return rateb_can('crm.quote.approve') || rateb_can('crm.manage') || rateb_can('crm.admin');
    }

    private function canVersionQuote(): bool
    {
        return rateb_can('crm.quote.version') || rateb_can('crm.quote.create') || rateb_can('crm.create') || rateb_can('crm.manage');
    }

    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $svc = new CrmQuotationService();
        $result = $svc->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/crm/quotations/index', [
            'title' => __('crm_quotations'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => CrmQuotationWorkflowService::statuses(),
            'metrics' => $svc->performanceMetrics(),
            'canCreate' => $this->canCreateQuote(),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/crm/quotations/form', [
            'title' => __('crm_quotation_create'),
            'item' => null,
            'lead_id' => (int) ($_GET['lead_id'] ?? 0),
            'opportunity_id' => (int) ($_GET['opportunity_id'] ?? 0),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'action' => rateb_url(rateb_app_route('crm/quotations')),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate((string) ($_POST['_csrf'] ?? ''))) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        try {
            $created = (new CrmQuotationService())->create($_POST);
            SessionManager::flash('success', __('saved_ok') . ' — ' . $created['quotation_no']);
            $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/quotations/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new CrmQuotationService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        $status = (string) ($item['status'] ?? 'draft');
        $approval = (string) ($item['approval_status'] ?? 'none');
        $this->view('company/crm/quotations/show', [
            'title' => __('crm_quotation'),
            'item' => $item,
            'lines' => (new CrmQuotationService())->linesFor($id),
            'timeline' => (new CrmTimelineService())->listForQuotation($id, 40),
            'history' => (new CrmQuotationService())->statusHistory($id),
            'transitions' => CrmQuotationWorkflowService::allowedTransitions()[$status] ?? [],
            'canWorkflow' => $this->canUpdateQuote(),
            'canConvertCustomer' => $this->canConvertQuote() && $status === CrmQuotationWorkflowService::STATUS_ACCEPTED,
            'canVersion' => $this->canVersionQuote(),
            'canSubmitApproval' => $this->canUpdateQuote() && $status === 'draft' && $approval !== 'pending',
            'canDecideApproval' => $this->canApproveQuote() && $approval === 'pending',
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new CrmQuotationWorkflowService())->transition(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $id));
    }

    public function convertToCustomer(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $created = (new CrmConversionService())->quotationToCustomer($id, $_POST);
            SessionManager::flash('success', __('saved_ok') . ' — #' . $created['customer_id']);
            $this->redirect(rateb_url(rateb_app_route('crm/customers') . '/' . $created['customer_id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $id));
        }
    }

    public function duplicate(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $created = (new CrmQuotationService())->duplicate($id);
            SessionManager::flash('success', __('saved_ok') . ' — ' . $created['quotation_no']);
            $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $id));
        }
    }

    public function version(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $created = (new CrmQuotationService())->createVersion($id);
            SessionManager::flash('success', __('saved_ok') . ' — v' . $created['version_no']);
            $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $id));
        }
    }

    public function submitApproval(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new CrmQuotationService())->submitForApproval($id);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $id));
    }

    public function decideApproval(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $approve = (string) ($_POST['decision'] ?? '') === 'approve';
            (new CrmQuotationService())->decideApproval($id, $approve, isset($_POST['reason']) ? (string) $_POST['reason'] : null);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/quotations') . '/' . $id));
    }
}

final class CrmCallsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new CallService())->list(50, 0);
        $this->view('company/crm/calls/index', [
            'title' => __('crm_calls'),
            'items' => $result['items'],
            'total' => $result['total'],
            'canCreate' => rateb_can('crm.activities') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/calls')));
        }
        try {
            (new CallService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/calls')));
    }
}

final class CrmActivitiesController extends Controller
{
    private function canManageActivities(): bool
    {
        return rateb_can('crm.activities.manage') || rateb_can('crm.activities') || rateb_can('crm.manage');
    }

    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new ActivityService())->list(50, 0);
        $tasks = (new TaskService())->list(30, 0);
        $this->view('company/crm/activities/index', [
            'title' => __('crm_activities'),
            'items' => $result['items'],
            'total' => $result['total'],
            'tasks' => $tasks['items'],
            'history' => (new ActivityService())->history(40),
            'canCreate' => $this->canManageActivities(),
            'canAssign' => rateb_can('crm.activities.assign') || rateb_can('crm.assign') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/activities')));
        }
        try {
            (new ActivityService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/activities')));
    }
}
