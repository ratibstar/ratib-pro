<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\DocumentManagementEnterpriseService;
use Rateb\App\Services\DocumentManagementSupport;
use Rateb\App\Services\DocumentTimelineService;
use Rateb\App\Services\DocumentWorkflowService;
use Rateb\App\Services\DmsDocumentService;
use Rateb\App\Services\DmsFavoriteService;
use Rateb\App\Services\DmsFolderService;
use Rateb\App\Services\DmsLegalHoldService;
use Rateb\App\Services\DmsPermissionService;
use Rateb\App\Services\DmsRepositoryService;
use Rateb\App\Services\DmsRetentionService;
use Rateb\App\Services\DmsSearchService;
use Rateb\App\Services\DmsShareService;
use Rateb\App\Services\DmsVersionService;

/**
 * Phase 26A — Enterprise Document Management (DMS) Platform ONLINE controllers (thin).
 * Additive under /dms/* — legacy documents / DocumentService untouched; Offline deferred to 26B.
 */
final class DocumentManagementPlatformController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->redirect(rateb_url(rateb_app_route('dms/dashboard')));
    }
}

final class DmsDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/dms/dashboard', [
            'title' => __('dms_platform'),
            'board' => (new DocumentManagementEnterpriseService())->boardCounts(),
            'timeline' => (new DocumentTimelineService())->listRecent(10),
            'canManage' => rateb_can('documents.manage') || rateb_can('documents.admin'),
        ], 'main');
    }
}

final class DmsRepositoriesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new DmsRepositoryService())->list($limit, ($page - 1) * $limit, $search);
        $this->view('company/dms/repositories/index', [
            'title' => __('dms_repositories'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('documents.create') || rateb_can('documents.manage') || rateb_can('documents.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/repositories')));
        }
        try {
            (new DmsRepositoryService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/repositories')));
    }
}

final class DmsFoldersController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $repoId = DocumentManagementSupport::intOrNull($_GET['repository_id'] ?? null);
        $result = (new DmsFolderService())->list($limit, ($page - 1) * $limit, $repoId);
        $this->view('company/dms/folders/index', [
            'title' => __('dms_folders'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('documents.create') || rateb_can('documents.manage') || rateb_can('documents.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/folders')));
        }
        try {
            (new DmsFolderService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/folders')));
    }
}

final class DmsDocumentsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $wf = trim((string) ($_GET['workflow_status'] ?? ''));
        $result = (new DmsDocumentService())->list($limit, ($page - 1) * $limit, $search, $wf !== '' ? $wf : null);
        $this->view('company/dms/documents/index', [
            'title' => __('dms_documents'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('documents.create') || rateb_can('documents.manage') || rateb_can('documents.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/documents')));
        }
        try {
            (new DmsDocumentService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/documents')));
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new DmsDocumentService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('dms/documents')));
        }
        $this->view('company/dms/documents/show', [
            'title' => (string) ($item['title'] ?? __('dms_documents')),
            'item' => $item,
            'versions' => (new DmsVersionService())->listForDocument($id)['items'],
            'timeline' => (new DocumentTimelineService())->listForEntity('document', $id),
            'transitions' => DocumentWorkflowService::allowedTransitions(DocumentWorkflowService::ENTITY_DOCUMENT)
                [$item['workflow_status'] ?? 'draft'] ?? [],
            'canTransition' => rateb_can('documents.update') || rateb_can('documents.manage') || rateb_can('documents.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/documents')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new DocumentWorkflowService())->transition(
                DocumentWorkflowService::ENTITY_DOCUMENT,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null,
                DocumentManagementSupport::intOrNull($_POST['expected_version'] ?? null)
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/documents') . '/' . $id));
    }
}

final class DmsVersionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $documentId = DocumentManagementSupport::intOrNull($_GET['document_id'] ?? null);
        $items = [];
        $total = 0;
        if ($documentId !== null) {
            $result = (new DmsVersionService())->listForDocument($documentId);
            $items = $result['items'];
            $total = $result['total'];
        }
        $this->view('company/dms/versions/index', [
            'title' => __('dms_versions'),
            'items' => $items,
            'total' => $total,
            'documentId' => $documentId,
        ], 'main');
    }
}

final class DmsSearchController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $q = trim((string) ($_GET['q'] ?? ''));
        $result = (new DmsSearchService())->search($q, $limit, ($page - 1) * $limit);
        $this->view('company/dms/search/index', [
            'title' => __('dms_search'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'query' => $q,
        ], 'main');
    }
}

final class DmsFavoritesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new DmsFavoriteService())->listForUser(null, $limit, ($page - 1) * $limit);
        $this->view('company/dms/favorites/index', [
            'title' => __('dms_favorites'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ], 'main');
    }
}

final class DmsSharesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new DmsShareService())->list($limit, ($page - 1) * $limit);
        $this->view('company/dms/shares/index', [
            'title' => __('dms_shares'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canShare' => rateb_can('documents.share') || rateb_can('documents.manage') || rateb_can('documents.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/shares')));
        }
        try {
            (new DmsShareService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/shares')));
    }
}

final class DmsRetentionController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new DmsRetentionService())->listPolicies($limit, ($page - 1) * $limit);
        $this->view('company/dms/retention/index', [
            'title' => __('dms_retention'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canManage' => rateb_can('documents.retention') || rateb_can('documents.admin') || rateb_can('documents.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/retention')));
        }
        try {
            (new DmsRetentionService())->createPolicy($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/retention')));
    }
}

final class DmsLegalHoldsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new DmsLegalHoldService())->list($limit, ($page - 1) * $limit);
        $this->view('company/dms/legal-holds/index', [
            'title' => __('dms_legal_holds'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('documents.retention') || rateb_can('documents.admin') || rateb_can('documents.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/legal-holds')));
        }
        try {
            (new DmsLegalHoldService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/legal-holds')));
    }
}

final class DmsPermissionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new DmsPermissionService())->list($limit, ($page - 1) * $limit);
        $this->view('company/dms/permissions/index', [
            'title' => __('dms_permissions'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canManage' => rateb_can('documents.admin') || rateb_can('documents.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('dms/permissions')));
        }
        try {
            (new DmsPermissionService())->grant($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('dms/permissions')));
    }
}

final class DmsTimelinePageController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new DocumentTimelineService())->listPaged($limit, ($page - 1) * $limit);
        $this->view('company/dms/timeline/index', [
            'title' => __('dms_timeline'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ], 'main');
    }
}

final class DmsReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/dms/reports/index', [
            'title' => __('dms_reports'),
            'board' => (new DocumentManagementEnterpriseService())->boardCounts(),
        ], 'main');
    }
}
