<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AssignmentService;
use Rateb\App\Services\CandidateService;
use Rateb\App\Services\InterviewService;
use Rateb\App\Services\MedicalService;
use Rateb\App\Services\PassportService;
use Rateb\App\Services\RecruitmentAgencyService;
use Rateb\App\Services\RecruitmentContractService;
use Rateb\App\Services\RecruitmentDocumentMetaService;
use Rateb\App\Services\RecruitmentTimelineService;
use Rateb\App\Services\RecruitmentWorkflowService;
use Rateb\App\Services\VisaService;

/**
 * Phase 15A — Recruitment ONLINE controllers.
 * All mutations go through domain services (offline-replay ready).
 */
final class RecruitmentDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $list = (new CandidateService())->list(5, 0, '');
        $this->view('company/recruitment/dashboard', [
            'title' => __('recruitment'),
            'recent' => $list['items'],
            'total' => $list['total'],
            'statuses' => RecruitmentWorkflowService::statuses(),
        ], 'main');
    }
}

final class RecruitmentCandidatesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new CandidateService())->list($limit, ($page - 1) * $limit, $search);
        $this->view('company/recruitment/candidates/index', [
            'title' => __('recruitment_candidates'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'canCreate' => rateb_can('recruitment.create') || rateb_can('recruitment.manage'),
            'canManage' => rateb_can('recruitment.manage') || rateb_can('recruitment.update'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/recruitment/candidates/form', [
            'title' => __('recruitment_candidate_create'),
            'item' => null,
            'agencies' => (new RecruitmentAgencyService())->listActive(),
            'action' => rateb_url(rateb_app_route('recruitment/candidates')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        try {
            $created = (new CandidateService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new CandidateService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $timeline = (new RecruitmentTimelineService())->listForCandidate($id);
        $docs = (new RecruitmentDocumentMetaService())->listFor(
            RecruitmentDocumentMetaService::ENTITY_CANDIDATE,
            $id
        );
        $this->view('company/recruitment/candidates/show', [
            'title' => $item['full_name'] ?? __('recruitment_candidates'),
            'item' => $item,
            'timeline' => $timeline,
            'documents' => $docs,
            'statuses' => RecruitmentWorkflowService::statuses(),
            'transitions' => RecruitmentWorkflowService::allowedTransitions()[$item['workflow_status'] ?? 'draft'] ?? [],
            'canWorkflow' => rateb_can('recruitment.manage') || rateb_can('recruitment.admin') || rateb_can('recruitment.update'),
            'canUpload' => rateb_can('recruitment.manage') || rateb_can('recruitment.update'),
        ], 'main');
    }

    public function edit(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new CandidateService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $this->view('company/recruitment/candidates/form', [
            'title' => __('edit'),
            'item' => $item,
            'agencies' => (new RecruitmentAgencyService())->listActive(),
            'action' => rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new CandidateService())->update($id, $_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/edit'));
        }
    }

    public function destroy(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new CandidateService())->softDelete($id);
            SessionManager::flash('success', __('deleted'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new RecruitmentWorkflowService())->transition(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id));
    }

    public function storeDocument(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $file = $_FILES['document'] ?? $_FILES['file'] ?? [];
            (new RecruitmentDocumentMetaService())->storeUpload(
                RecruitmentDocumentMetaService::ENTITY_CANDIDATE,
                $id,
                is_array($file) ? $file : [],
                isset($_POST['title']) ? (string) $_POST['title'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id));
    }
}

final class RecruitmentAgenciesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $items = (new RecruitmentAgencyService())->listActive();
        $this->view('company/recruitment/agencies/index', [
            'title' => __('recruitment_agencies'),
            'items' => $items,
            'canCreate' => rateb_can('recruitment.manage') || rateb_can('recruitment.create'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/recruitment/agencies/form', [
            'title' => __('recruitment_agency_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('recruitment/agencies')),
        ], 'main');
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/agencies')));
        }
        try {
            (new RecruitmentAgencyService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('recruitment/agencies')));
    }
}

final class RecruitmentInterviewsController extends Controller
{
    public function store(array $params): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $candidateId = (int) ($params['id'] ?? $_POST['candidate_id'] ?? 0);
        try {
            (new InterviewService())->create($candidateId, $_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $candidateId));
    }
}

final class RecruitmentChildRecordsController extends Controller
{
    public function storeVisa(array $params): void
    {
        $this->storeChild($params, static function (int $cid, array $data): void {
            (new VisaService())->create($cid, $data);
        });
    }

    public function storeMedical(array $params): void
    {
        $this->storeChild($params, static function (int $cid, array $data): void {
            (new MedicalService())->create($cid, $data);
        });
    }

    public function storeContract(array $params): void
    {
        $this->storeChild($params, static function (int $cid, array $data): void {
            (new RecruitmentContractService())->create($cid, $data);
        });
    }

    public function storePassport(array $params): void
    {
        $this->storeChild($params, static function (int $cid, array $data): void {
            (new PassportService())->create($cid, $data);
        });
    }

    public function storeAssignment(array $params): void
    {
        $this->storeChild($params, static function (int $cid, array $data): void {
            (new AssignmentService())->assign($cid, $data);
        });
    }

    /** @param callable(int, array<string, mixed>): void $fn */
    private function storeChild(array $params, callable $fn): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('recruitment/candidates')));
        }
        $candidateId = (int) ($params['id'] ?? 0);
        try {
            $fn($candidateId, $_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $candidateId));
    }
}
