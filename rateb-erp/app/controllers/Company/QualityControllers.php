<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\QualityAuditService;
use Rateb\App\Services\QualityChecklistService;
use Rateb\App\Services\QualityComplaintService;
use Rateb\App\Services\QualityDefectService;
use Rateb\App\Services\QualityEnterpriseService;
use Rateb\App\Services\QualityInspectionService;
use Rateb\App\Services\QualityNonconformityService;
use Rateb\App\Services\QualityPlanService;
use Rateb\App\Services\QualityStandardService;
use Rateb\App\Services\QualitySupport;
use Rateb\App\Services\QualityTimelineService;
use Rateb\App\Services\QualityWorkflowService;
use Rateb\App\Services\QmsCorrectiveActionService;
use Rateb\App\Services\QmsPreventiveActionService;
use Rateb\App\Services\SupplierQualityService;

/**
 * Phase 25A — Enterprise Quality Management (QMS) Platform ONLINE controllers (thin).
 * Additive under /qms/* — soft-links MFG/EAM only; Offline deferred to 25B.
 */
final class QualityPlatformController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->redirect(rateb_url(rateb_app_route('qms/dashboard')));
    }
}

final class QualityDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/qms/dashboard', [
            'title' => __('quality_platform'),
            'board' => (new QualityEnterpriseService())->boardCounts(),
            'timeline' => (new QualityTimelineService())->listRecent(10),
            'canManage' => rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }
}

final class QualityPlansController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new QualityPlanService())->list($limit, ($page - 1) * $limit, $search);
        $this->view('company/qms/plans/index', [
            'title' => __('quality_plans'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/plans')));
        }
        try {
            (new QualityPlanService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/plans')));
    }
}

final class QualityStandardsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityStandardService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/standards/index', [
            'title' => __('quality_standards'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/standards')));
        }
        try {
            (new QualityStandardService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/standards')));
    }
}

final class QualityChecklistsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityChecklistService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/checklists/index', [
            'title' => __('quality_checklists'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/checklists')));
        }
        try {
            (new QualityChecklistService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/checklists')));
    }
}

final class QualityInspectionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new QualityInspectionService())->list($limit, ($page - 1) * $limit, $status !== '' ? $status : null);
        $this->view('company/qms/inspections/index', [
            'title' => __('quality_inspections'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
            'statuses' => QualityWorkflowService::statuses(QualityWorkflowService::ENTITY_INSPECTION),
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.inspect')
                || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/inspections')));
        }
        try {
            $created = (new QualityInspectionService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('qms/inspections') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('qms/inspections')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new QualityInspectionService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('qms/inspections')));
        }
        $this->view('company/qms/inspections/show', [
            'title' => (string) ($item['title'] ?? __('quality_inspections')),
            'item' => $item,
            'timeline' => (new QualityTimelineService())->listForEntity('inspection', $id),
            'transitions' => QualityWorkflowService::allowedTransitions(QualityWorkflowService::ENTITY_INSPECTION)
                [$item['workflow_status'] ?? 'planned'] ?? [],
            'canTransition' => rateb_can('quality.inspect') || rateb_can('quality.update')
                || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/inspections')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new QualityWorkflowService())->transition(
                QualityWorkflowService::ENTITY_INSPECTION,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null,
                QualitySupport::intOrNull($_POST['expected_version'] ?? null)
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/inspections') . '/' . $id));
    }
}

final class QualityDefectsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityDefectService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/defects/index', [
            'title' => __('quality_defects'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/defects')));
        }
        try {
            (new QualityDefectService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/defects')));
    }
}

final class QualityNonconformitiesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityNonconformityService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/nonconformities/index', [
            'title' => __('quality_nonconformities'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/nonconformities')));
        }
        try {
            (new QualityNonconformityService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/nonconformities')));
    }
}

final class QualityCorrectiveActionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new QmsCorrectiveActionService())->list($limit, ($page - 1) * $limit, $status !== '' ? $status : null);
        $this->view('company/qms/corrective-actions/index', [
            'title' => __('quality_corrective_actions'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
            'statuses' => QualityWorkflowService::statuses(QualityWorkflowService::ENTITY_CORRECTIVE),
            'canCreate' => rateb_can('quality.corrective') || rateb_can('quality.create')
                || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/corrective-actions')));
        }
        try {
            $created = (new QmsCorrectiveActionService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('qms/corrective-actions') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('qms/corrective-actions')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new QmsCorrectiveActionService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('qms/corrective-actions')));
        }
        $this->view('company/qms/corrective-actions/show', [
            'title' => (string) ($item['title'] ?? __('quality_corrective_actions')),
            'item' => $item,
            'timeline' => (new QualityTimelineService())->listForEntity('corrective_action', $id),
            'transitions' => QualityWorkflowService::allowedTransitions(QualityWorkflowService::ENTITY_CORRECTIVE)
                [$item['workflow_status'] ?? 'draft'] ?? [],
            'canTransition' => rateb_can('quality.corrective') || rateb_can('quality.update')
                || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/corrective-actions')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new QualityWorkflowService())->transition(
                QualityWorkflowService::ENTITY_CORRECTIVE,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null,
                QualitySupport::intOrNull($_POST['expected_version'] ?? null)
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/corrective-actions') . '/' . $id));
    }
}

final class QualityPreventiveActionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QmsPreventiveActionService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/preventive-actions/index', [
            'title' => __('quality_preventive_actions'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.preventive') || rateb_can('quality.create')
                || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/preventive-actions')));
        }
        try {
            (new QmsPreventiveActionService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/preventive-actions')));
    }
}

final class QualityAuditsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityAuditService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/audits/index', [
            'title' => __('quality_audits'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.audit') || rateb_can('quality.create')
                || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/audits')));
        }
        try {
            (new QualityAuditService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/audits')));
    }
}

final class QualityComplaintsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityComplaintService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/complaints/index', [
            'title' => __('quality_complaints'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/complaints')));
        }
        try {
            (new QualityComplaintService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/complaints')));
    }
}

final class QualitySupplierQualityController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new SupplierQualityService())->list($limit, ($page - 1) * $limit);
        $this->view('company/qms/supplier-quality/index', [
            'title' => __('quality_supplier_quality'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('quality.create') || rateb_can('quality.manage') || rateb_can('quality.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('qms/supplier-quality')));
        }
        try {
            (new SupplierQualityService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('qms/supplier-quality')));
    }
}

final class QualityReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/qms/reports/index', [
            'title' => __('quality_reports'),
            'board' => (new QualityEnterpriseService())->boardCounts(),
        ], 'main');
    }
}

final class QualityTimelinePageController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityTimelineService())->listPaged($limit, ($page - 1) * $limit);
        $this->view('company/qms/timeline/index', [
            'title' => __('quality_timeline'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ], 'main');
    }
}
