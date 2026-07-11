<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\EnterpriseContractService;
use Rateb\App\Services\EnterpriseTenderService;
use Rateb\App\Services\ProcurementCalendarService;
use Rateb\App\Services\ProcurementCommentService;
use Rateb\App\Services\ProcurementEnterpriseService;
use Rateb\App\Services\ProcurementTimelineService;
use Rateb\App\Services\ProcurementWorkflowService;
use Rateb\App\Services\RfqTemplateService;
use Rateb\App\Services\SpendAnalysisService;
use Rateb\App\Services\SupplierCategoryService;
use Rateb\App\Services\SupplierPortalService;
use Rateb\App\Services\SupplierProfileService;
use Rateb\App\Services\SupplierQualificationService;
use Rateb\App\Services\SupplierScorecardService;
use Rateb\App\Services\TenderBidService;
use Rateb\App\Services\VendorCollaborationService;

/**
 * Phase 21A — Enterprise Procurement ONLINE controllers (thin).
 * Additive EPROC under /eproc/* — does not replace legacy ProcurementService.
 */
final class EprocDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $svc = new ProcurementEnterpriseService();
        $this->view('company/eproc/dashboard', [
            'title' => __('procurement_platform'),
            'board' => $svc->boardCounts(),
            'spend' => $svc->spendSummary(),
            'timeline' => (new ProcurementTimelineService())->listRecent(10),
            'canManage' => rateb_can('procurement.manage') || rateb_can('procurement.admin'),
        ], 'main');
    }
}

final class EprocSuppliersController extends Controller
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
        $result = (new SupplierProfileService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/eproc/suppliers/index', [
            'title' => __('eproc_suppliers'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ProcurementWorkflowService::statuses(ProcurementWorkflowService::ENTITY_SUPPLIER_PROFILE),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/suppliers/form', [
            'title' => __('eproc_supplier_create'),
            'item' => null,
            'categories' => (new SupplierCategoryService())->listAll(),
            'action' => rateb_url(rateb_app_route('eproc/suppliers')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/suppliers')));
        }
        try {
            $created = (new SupplierProfileService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eproc/suppliers') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eproc/suppliers/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new SupplierProfileService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eproc/suppliers')));
        }
        $this->view('company/eproc/suppliers/show', [
            'title' => $item['name'] ?? __('eproc_suppliers'),
            'item' => $item,
            'comments' => (new ProcurementCommentService())->listFor('supplier_profile', $id),
            'timeline' => (new ProcurementTimelineService())->listForEntity('supplier_profile', $id),
            'transitions' => ProcurementWorkflowService::allowedTransitions(
                ProcurementWorkflowService::ENTITY_SUPPLIER_PROFILE
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('procurement.update') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/suppliers')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProcurementWorkflowService())->transition(
                ProcurementWorkflowService::ENTITY_SUPPLIER_PROFILE,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/suppliers') . '/' . $id));
    }
}

final class EprocCategoriesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/categories/index', [
            'title' => __('eproc_categories'),
            'items' => (new SupplierCategoryService())->listAll(),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/categories')));
        }
        try {
            (new SupplierCategoryService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/categories')));
    }
}

final class EprocScorecardsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $profileId = isset($_GET['profile_id']) ? (int) $_GET['profile_id'] : null;
        $result = (new SupplierScorecardService())->list($limit, ($page - 1) * $limit, $profileId > 0 ? $profileId : null);
        $this->view('company/eproc/scorecards/index', [
            'title' => __('eproc_scorecards'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/scorecards')));
        }
        try {
            (new SupplierScorecardService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/scorecards')));
    }
}

final class EprocTendersController extends Controller
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
        $result = (new EnterpriseTenderService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/eproc/tenders/index', [
            'title' => __('eproc_tenders'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ProcurementWorkflowService::statuses(ProcurementWorkflowService::ENTITY_TENDER),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/tenders/form', [
            'title' => __('eproc_tender_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('eproc/tenders')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/tenders')));
        }
        try {
            $created = (new EnterpriseTenderService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eproc/tenders') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eproc/tenders/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new EnterpriseTenderService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eproc/tenders')));
        }
        $this->view('company/eproc/tenders/show', [
            'title' => $item['title'] ?? __('eproc_tenders'),
            'item' => $item,
            'bids' => (new TenderBidService())->listForTender($id),
            'timeline' => (new ProcurementTimelineService())->listForEntity('tender', $id),
            'transitions' => ProcurementWorkflowService::allowedTransitions(
                ProcurementWorkflowService::ENTITY_TENDER
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('procurement.update') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/tenders')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProcurementWorkflowService())->transition(
                ProcurementWorkflowService::ENTITY_TENDER,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/tenders') . '/' . $id));
    }
}

final class EprocContractsController extends Controller
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
        $result = (new EnterpriseContractService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/eproc/contracts/index', [
            'title' => __('eproc_contracts'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ProcurementWorkflowService::statuses(ProcurementWorkflowService::ENTITY_CONTRACT),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/contracts/form', [
            'title' => __('eproc_contract_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('eproc/contracts')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/contracts')));
        }
        try {
            $created = (new EnterpriseContractService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eproc/contracts') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eproc/contracts/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new EnterpriseContractService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eproc/contracts')));
        }
        $this->view('company/eproc/contracts/show', [
            'title' => $item['title'] ?? __('eproc_contracts'),
            'item' => $item,
            'timeline' => (new ProcurementTimelineService())->listForEntity('contract', $id),
            'transitions' => ProcurementWorkflowService::allowedTransitions(
                ProcurementWorkflowService::ENTITY_CONTRACT
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('procurement.update') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/contracts')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProcurementWorkflowService())->transition(
                ProcurementWorkflowService::ENTITY_CONTRACT,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/contracts') . '/' . $id));
    }
}

final class EprocCalendarController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/calendar/index', [
            'title' => __('eproc_calendar'),
            'items' => (new ProcurementCalendarService())->list(100),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/calendar')));
        }
        try {
            (new ProcurementCalendarService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/calendar')));
    }
}

final class EprocSpendController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $period = trim((string) ($_GET['period'] ?? ''));
        $result = (new SpendAnalysisService())->list($limit, ($page - 1) * $limit, $period !== '' ? $period : null);
        $this->view('company/eproc/spend/index', [
            'title' => __('eproc_spend'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'period' => $period,
            'summary' => (new ProcurementEnterpriseService())->spendSummary(),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/spend')));
        }
        try {
            (new SpendAnalysisService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/spend')));
    }
}

final class EprocPortalController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/portal/index', [
            'title' => __('eproc_portal'),
            'items' => (new SupplierPortalService())->listInvites(100),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage') || rateb_can('procurement.portal'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/portal')));
        }
        try {
            (new SupplierPortalService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/portal')));
    }
}

final class EprocCollaborationController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new VendorCollaborationService())->list($limit, ($page - 1) * $limit, $status !== '' ? $status : null);
        $this->view('company/eproc/collaboration/index', [
            'title' => __('eproc_collaboration'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
            'statuses' => ProcurementWorkflowService::statuses(ProcurementWorkflowService::ENTITY_COLLABORATION),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/collaboration')));
        }
        try {
            $created = (new VendorCollaborationService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eproc/collaboration') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eproc/collaboration')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new VendorCollaborationService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eproc/collaboration')));
        }
        $this->view('company/eproc/collaboration/show', [
            'title' => $item['subject'] ?? __('eproc_collaboration'),
            'item' => $item,
            'transitions' => ProcurementWorkflowService::allowedTransitions(
                ProcurementWorkflowService::ENTITY_COLLABORATION
            )[$item['workflow_status'] ?? 'open'] ?? [],
            'canUpdate' => rateb_can('procurement.update') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/collaboration')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProcurementWorkflowService())->transition(
                ProcurementWorkflowService::ENTITY_COLLABORATION,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/collaboration') . '/' . $id));
    }
}

final class EprocRfqTemplatesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/rfq-templates/index', [
            'title' => __('eproc_rfq_templates'),
            'items' => (new RfqTemplateService())->listAll(),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/rfq-templates')));
        }
        try {
            (new RfqTemplateService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/rfq-templates')));
    }
}

final class EprocReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eproc/reports/index', [
            'title' => __('eproc_reports'),
            'board' => (new ProcurementEnterpriseService())->boardCounts(),
            'spend' => (new ProcurementEnterpriseService())->spendSummary(),
            'canView' => rateb_can('procurement.view') || rateb_can('procurement.manage'),
        ], 'main');
    }
}

final class EprocQualificationController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new SupplierQualificationService())->list($limit, ($page - 1) * $limit, $status !== '' ? $status : null);
        $this->view('company/eproc/qualification/index', [
            'title' => __('eproc_qualification'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
            'statuses' => ProcurementWorkflowService::statuses(ProcurementWorkflowService::ENTITY_QUALIFICATION),
            'canCreate' => rateb_can('procurement.create') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/qualification')));
        }
        try {
            $created = (new SupplierQualificationService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eproc/qualification') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eproc/qualification')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new SupplierQualificationService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eproc/qualification')));
        }
        $this->view('company/eproc/qualification/show', [
            'title' => $item['title'] ?? __('eproc_qualification'),
            'item' => $item,
            'transitions' => ProcurementWorkflowService::allowedTransitions(
                ProcurementWorkflowService::ENTITY_QUALIFICATION
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('procurement.update') || rateb_can('procurement.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eproc/qualification')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ProcurementWorkflowService())->transition(
                ProcurementWorkflowService::ENTITY_QUALIFICATION,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eproc/qualification') . '/' . $id));
    }
}
