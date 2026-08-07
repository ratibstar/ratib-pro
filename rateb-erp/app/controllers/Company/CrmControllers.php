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
use Rateb\App\Services\CrmNoteService;
use Rateb\App\Services\CrmTimelineService;
use Rateb\App\Services\CrmWorkflowService;
use Rateb\App\Services\LeadService;
use Rateb\App\Services\MeetingService;
use Rateb\App\Services\CrmQuotationService;
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
            'timeline' => (new CrmTimelineService())->listRecent(10),
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
        ], 'main');
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
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $pipelineId = (int) ($_GET['pipeline_id'] ?? 0);
        $board = (new PipelineService())->board($pipelineId > 0 ? $pipelineId : null);
        $this->view('company/crm/pipeline/index', [
            'title' => __('crm_pipeline'),
            'pipelines' => (new PipelineService())->listPipelines(),
            'board' => $board,
            'canManage' => rateb_can('crm.pipeline') || rateb_can('crm.manage'),
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

    public function moveOpportunity(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/pipeline')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new OpportunityService())->moveStage($id, (int) ($_POST['stage_id'] ?? 0));
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
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities')));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('crm/opportunities/create')));
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
        $result = (new ContactService())->list(50, 0, trim((string) ($_GET['q'] ?? '')));
        $this->view('company/crm/contacts/index', [
            'title' => __('crm_contacts'),
            'items' => $result['items'],
            'total' => $result['total'],
            'canCreate' => rateb_can('crm.create') || rateb_can('crm.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/contacts')));
        }
        try {
            (new ContactService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/contacts')));
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

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('crm/companies')));
        }
        try {
            (new CrmCompanyService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/companies')));
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
        $this->view('company/crm/customer-profile', [
            'title' => __('crm_customer_profile'),
            'customer_id' => $customerId,
            'timeline' => (new CrmTimelineService())->listForCustomer($customerId, 50),
            'activities' => (new ActivityService())->list(20, 0),
        ], 'main');
    }
}

/** Phase 1 — Sales quotations (CRM extension; no workflow to invoice yet). */
final class CrmQuotationsController extends Controller
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
        $result = (new CrmQuotationService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/crm/quotations/index', [
            'title' => __('crm_quotations'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'canCreate' => rateb_can('crm.create') || rateb_can('crm.manage'),
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
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('crm/quotations')));
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
        $this->view('company/crm/quotations/show', [
            'title' => __('crm_quotation'),
            'item' => $item,
            'lines' => (new CrmQuotationService())->linesFor($id),
        ], 'main');
    }
}
