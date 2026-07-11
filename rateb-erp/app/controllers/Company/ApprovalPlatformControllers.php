<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\ApprovalAuditService;
use Rateb\App\Services\ApprovalChainService;
use Rateb\App\Services\ApprovalCommentService;
use Rateb\App\Services\ApprovalDelegationService;
use Rateb\App\Services\ApprovalRequestService;
use Rateb\App\Services\ApprovalRuleService;
use Rateb\App\Services\ApprovalService;
use Rateb\App\Services\ApprovalStageService;
use Rateb\App\Services\ApprovalTemplateService;
use Rateb\App\Services\ApprovalTimelineService;
use Rateb\App\Services\ApprovalWorkflowService;

/**
 * Phase 20A — Enterprise Approval Platform ONLINE controllers (thin).
 * Additive EAP under /approvals/* — does not replace legacy WorkflowService oversight.
 */
final class ApprovalDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $svc = new ApprovalService();
        $this->view('company/approvals/dashboard', [
            'title' => __('approval_platform'),
            'board' => $svc->boardCounts(),
            'pending' => $svc->listPending(10),
            'timeline' => (new ApprovalTimelineService())->listRecent(10),
            'statuses' => ApprovalWorkflowService::statuses(),
        ], 'main');
    }
}

final class ApprovalRequestsController extends Controller
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
        $result = (new ApprovalRequestService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/approvals/requests/index', [
            'title' => __('approval_requests'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ApprovalWorkflowService::statuses(),
            'canCreate' => rateb_can('approval.create') || rateb_can('approval.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/approvals/requests/form', [
            'title' => __('approval_request_create'),
            'item' => null,
            'templates' => (new ApprovalTemplateService())->listAll(),
            'action' => rateb_url(rateb_app_route('approvals/requests')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/requests')));
        }
        try {
            $created = (new ApprovalRequestService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('approvals/requests') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('approvals/requests/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new ApprovalRequestService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('approvals/requests')));
        }
        $this->view('company/approvals/requests/show', [
            'title' => $item['title'] ?? __('approval_requests'),
            'item' => $item,
            'comments' => (new ApprovalCommentService())->listForRequest($id),
            'timeline' => (new ApprovalTimelineService())->listForRequest($id),
            'transitions' => ApprovalWorkflowService::allowedTransitions()[$item['workflow_status'] ?? 'draft'] ?? [],
            'canSubmit' => rateb_can('approval.submit') || rateb_can('approval.manage'),
            'canApprove' => rateb_can('approval.approve') || rateb_can('approval.manage') || rateb_can('approval.admin'),
            'canReject' => rateb_can('approval.reject') || rateb_can('approval.manage') || rateb_can('approval.admin'),
            'canDelegate' => rateb_can('approval.delegate') || rateb_can('approval.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/requests')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ApprovalWorkflowService())->transition(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('approvals/requests') . '/' . $id));
    }

    public function storeComment(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/requests')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ApprovalCommentService())->create(array_merge($_POST, ['request_id' => $id]));
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('approvals/requests') . '/' . $id));
    }

    public function storeDelegation(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/requests')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ApprovalDelegationService())->create(array_merge($_POST, [
                'request_id' => $id,
                'from_user_id' => $_POST['from_user_id'] ?? (\Rateb\App\Services\ApprovalSupport::userId() ?? 0),
            ]));
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('approvals/requests') . '/' . $id));
    }
}

final class ApprovalPendingController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/approvals/pending', [
            'title' => __('approval_pending'),
            'items' => (new ApprovalService())->listPending(100),
            'canApprove' => rateb_can('approval.approve') || rateb_can('approval.manage'),
        ], 'main');
    }
}

final class ApprovalTemplatesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/approvals/templates', [
            'title' => __('approval_templates'),
            'items' => (new ApprovalTemplateService())->listAll(),
            'stages' => (new ApprovalStageService())->listForTemplate(null),
            'canCreate' => rateb_can('approval.create') || rateb_can('approval.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/templates')));
        }
        try {
            (new ApprovalTemplateService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('approvals/templates')));
    }

    public function storeStage(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/templates')));
        }
        try {
            (new ApprovalStageService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('approvals/templates')));
    }
}

final class ApprovalChainsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/approvals/chains', [
            'title' => __('approval_chains'),
            'items' => (new ApprovalChainService())->listAll(),
            'templates' => (new ApprovalTemplateService())->listAll(),
            'canCreate' => rateb_can('approval.create') || rateb_can('approval.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/chains')));
        }
        try {
            (new ApprovalChainService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('approvals/chains')));
    }
}

final class ApprovalRulesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/approvals/rules', [
            'title' => __('approval_rules'),
            'items' => (new ApprovalRuleService())->listAll(),
            'canCreate' => rateb_can('approval.create') || rateb_can('approval.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('approvals/rules')));
        }
        try {
            (new ApprovalRuleService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('approvals/rules')));
    }
}

final class ApprovalHistoryController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/approvals/history', [
            'title' => __('approval_history'),
            'timeline' => (new ApprovalTimelineService())->listRecent(50),
            'audit' => (new ApprovalAuditService())->listRecent(50),
        ], 'main');
    }
}

final class ApprovalReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $svc = new ApprovalService();
        $this->view('company/approvals/reports', [
            'title' => __('approval_reports'),
            'board' => $svc->boardCounts(),
            'total' => $svc->list(1, 0)['total'],
        ], 'main');
    }
}
