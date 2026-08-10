<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Services\AccountingDashboardService;
use Rateb\App\Services\AccountingService;
use Rateb\App\Services\AuditService;

final class AccessControlController extends Controller
{
    public function index(): void
    {
        $userModel = new \Rateb\App\Models\User();
        $rbac = $this->rbacScope();
        $companyId = (int) $rbac['company_id'];
        $authz = new \Rateb\App\Services\AuthorizationService();
        if ($companyId > 0) {
            $users = (int) ($userModel->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_users WHERE COALESCE(is_super_admin, 0) = 0 AND company_id = :cid',
                ['cid' => $companyId]
            )['c'] ?? 0);
            $roles = count($authz->allRoles($companyId));
        } else {
            $users = (int) ($userModel->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_users
                 WHERE COALESCE(is_super_admin, 0) = 1
                    OR (COALESCE(is_super_admin, 0) = 0 AND (company_id IS NULL OR company_id = 0))'
            )['c'] ?? 0);
            $authz->ensureSuggestedRoles();
            $roles = count($authz->allRoles(0));
        }
        $permissions = (new \Rateb\App\Models\Permission())->count();

        $this->view('admin/access-control/index', [
            'title' => __('access_control'),
            'stats' => ['users' => $users, 'roles' => $roles, 'permissions' => $permissions],
            'csrf' => Csrf::token(),
            'scopedCompanyId' => $companyId,
            'rbacScope' => $rbac['scope'],
            'rbacOpsCompanyId' => $this->opsCompanyId(),
        ], 'main');
    }

    public function matrix(): void
    {
        $authz = new \Rateb\App\Services\AuthorizationService();
        $rbac = $this->rbacScope();
        $companyId = (int) $rbac['company_id'];
        if ($companyId > 0 && !$authz->companyHasTenantRoleBootstrap($companyId)) {
            $authz->ensureCompanyRoles($companyId);
        }
        if ($companyId < 1) {
            $authz->ensureSuggestedRoles();
        }
        $roles = $authz->allRoles($companyId > 0 ? $companyId : 0);
        $matrixUrl = rateb_app_url('access-control/matrix');
        $this->view('admin/access-control/matrix', [
            'title' => __('permission_matrix'),
            'roles' => $roles,
            'permissionGroups' => $authz->allPermissionsGrouped(),
            'matrix' => $authz->rolePermissionMatrixForRoles($roles),
            'csrf' => Csrf::token(),
            'scopedCompanyId' => $companyId,
            'rbacScope' => $rbac['scope'],
            'rbacOpsCompanyId' => $this->opsCompanyId(),
            'rbacBaseUrl' => $matrixUrl,
            'matrixFormAction' => function_exists('rateb_url_query')
                ? rateb_url_query($matrixUrl, ['scope' => $rbac['scope']])
                : ($matrixUrl . '?scope=' . rawurlencode($rbac['scope'])),
        ], 'main');
    }

    public function saveMatrix(): void
    {
        $rbac = $this->rbacScope();
        $redirect = function_exists('rateb_url_query')
            ? rateb_url_query(rateb_app_url('access-control/matrix'), ['scope' => $rbac['scope']])
            : (rateb_app_url('access-control/matrix') . '?scope=' . rawurlencode($rbac['scope']));
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect($redirect);
        }
        $companyId = (int) $rbac['company_id'];
        $matrix = (array) $this->input('matrix', []);
        (new \Rateb\App\Services\AuthorizationService())->syncMatrixFromPost($matrix, $companyId > 0 ? $companyId : 0);
        (new AuditService())->log('update', 'role_permissions_matrix', null, [
            'roles' => count($matrix),
            'company_id' => $companyId,
            'scope' => $rbac['scope'],
        ]);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect($redirect);
    }

    /** @return array{scope:string,company_id:int} */
    private function rbacScope(): array
    {
        return \Rateb\App\Services\AuthorizationService::resolveRbacUiScope(
            (string) $this->input('scope', '')
        );
    }

    private function opsCompanyId(): int
    {
        if (function_exists('rateb_resolve_ops_company_id')) {
            $id = (int) rateb_resolve_ops_company_id();
            if ($id > 0) {
                return $id;
            }
        }

        return (int) ($_SESSION['rateb_company_id'] ?? 0);
    }
}

final class AccountingDashboardController extends Controller
{
    public function index(): void
    {
        (new \Rateb\App\Services\BillingService())->ensureBillingReady();
        $service = new AccountingService();
        $service->ensureDefaultAccounts(null);
        $dashSvc = new AccountingDashboardService($service);

        $this->view('admin/accounting/dashboard', [
            'title' => __('accounting_dashboard'),
            'dash' => $dashSvc->build(null),
            'trial' => $service->trialBalance(null),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function sync(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/accounting'));
        }
        $count = (new AccountingService())->syncFromSources(null);
        (new AuditService())->log('accounting_sync', 'journal', null, ['count' => $count]);
        SessionManager::flash('success', __('accounting_sync_done') . ' (' . $count . ')');
        Response::redirect(rateb_url('admin/accounting'));
    }
}

final class ChartOfAccountsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new ChartOfAccount();
        $this->viewPrefix = 'admin/chart-of-accounts';
        $this->routePrefix = 'admin/chart-of-accounts';
        $this->entityName = 'chart_of_accounts';
        $this->fields = [
            ['name' => 'code', 'label' => 'code', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text'],
            ['name' => 'account_type', 'label' => 'account_type', 'type' => 'select', 'options' => ['asset', 'liability', 'equity', 'revenue', 'expense']],
            ['name' => 'parent_id', 'label' => 'parent_account', 'type' => 'parent_select'],
            ['name' => 'is_active', 'label' => 'active', 'type' => 'select', 'options' => ['1', '0']],
        ];
    }

    public function index(): void
    {
        $canManage = rateb_can('accounting.manage');
        $service = new AccountingService();
        $service->ensureDefaultAccounts(null);
        $balanceMap = [];
        foreach ($service->trialBalance(null) as $row) {
            $balanceMap[(int) $row['id']] = (float) $row['total_debit'] - (float) $row['total_credit'];
        }
        $items = $this->model->query(
            'SELECT a.*, p.code AS parent_code, p.name AS parent_name, p.name_ar AS parent_name_ar
             FROM rateb_chart_of_accounts a
             LEFT JOIN rateb_chart_of_accounts p ON p.id = a.parent_id
             WHERE a.company_id IS NULL AND a.is_active = 1
             ORDER BY a.code'
        );
        foreach ($items as &$item) {
            $item['balance'] = $balanceMap[(int) $item['id']] ?? 0.0;
        }
        unset($item);
        $this->view($this->viewPrefix . '/index', [
            'title' => __('chart_of_accounts'),
            'items' => $items,
            'createEnabled' => $canManage,
            'actionsEnabled' => $canManage,
        ], $this->layout());
    }

    public function coaTree(): void
    {
        $canManage = rateb_can('accounting.manage');
        $service = new AccountingService();
        $tree = $service->coaTreeWithBalances(null);
        $typeTotals = [];
        foreach ($tree as $root) {
            $type = (string) ($root['account_type'] ?? '');
            if ($type !== '') {
                $typeTotals[$type] = (float) ($root['balance'] ?? 0);
            }
        }
        $this->view('admin/coa-tree/index', [
            'title' => __('coa_full_tree'),
            'tree' => $tree,
            'typeTotals' => $typeTotals,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'createEnabled' => $canManage,
            'actionsEnabled' => $canManage,
        ], $this->layout());
    }

    protected function guardManage(): void
    {
        if (!rateb_can('accounting.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
    }

    public function create(): void
    {
        $this->guardManage();
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __('chart_of_accounts'),
            'item' => null,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'parentOptions' => $this->parentOptions(),
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->queryOne(
            'SELECT * FROM rateb_chart_of_accounts WHERE id = :id AND company_id IS NULL',
            ['id' => $id]
        );
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __('chart_of_accounts'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'parentOptions' => $this->parentOptions($id),
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    /** @return array<int|string, string> */
    private function parentOptions(int $excludeId = 0): array
    {
        $tree = (new AccountingService())->coaTreeWithBalances(null);
        $options = ['' => '—'];
        $walk = static function (array $nodes, int $depth) use (&$walk, &$options, $excludeId): void {
            foreach ($nodes as $node) {
                $id = (int) ($node['id'] ?? 0);
                if ($id < 1 || $id === $excludeId) {
                    continue;
                }
                $name = rateb_locale() === 'ar' && !empty($node['name_ar']) ? $node['name_ar'] : ($node['name'] ?? '');
                $prefix = $depth > 0 ? str_repeat('　', $depth) . '└ ' : '';
                $options[$id] = $prefix . ($node['code'] ?? '') . ' — ' . $name;
                if (!empty($node['children'])) {
                    $walk($node['children'], $depth + 1);
                }
            }
        };
        $walk($tree, 0);
        return $options;
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $data['company_id'] = null;
        $data['is_active'] = (int) ($data['is_active'] ?? 1);
        $parentId = (int) ($data['parent_id'] ?? 0);
        $data['parent_id'] = $parentId > 0 ? $parentId : null;
        return $data;
    }

    public function destroy(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $service = new AccountingService();
        if ($service->destroyChartAccount($id, null)) {
            (new AuditService())->log('delete', $this->entityName, $id);
            SessionManager::flash('success', __('delete') . ' OK');
        } elseif ($service->deactivateChartAccount($id, null)) {
            (new AuditService())->log('deactivate', $this->entityName, $id);
            SessionManager::flash('success', __('account_deactivated'));
        } else {
            SessionManager::flash('error', __('account_delete_denied'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function bulkDestroy(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $deactivated = (new AccountingService())->bulkDeactivateChartAccounts($ids, null);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_deactivate', $this->entityName, $id);
        }
        SessionManager::flash('success', __('bulk_deactivated', ['count' => $deactivated]));
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class JournalEntriesController extends Controller
{
    public function index(): void
    {
        $items = (new JournalEntry())->query(
            'SELECT e.*, c.name AS company_name FROM rateb_journal_entries e
             LEFT JOIN rateb_companies c ON c.id = e.company_id
             ORDER BY e.id DESC LIMIT 100'
        );

        $this->view('admin/journal-entries/index', [
            'title' => __('journal_entries'),
            'items' => $items,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $entry = (new JournalEntry())->find($id);
        if (!$entry) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }

        $lines = (new JournalEntry())->query(
            'SELECT l.*, a.code, a.name, a.name_ar FROM rateb_journal_lines l
             JOIN rateb_chart_of_accounts a ON a.id = l.account_id
             WHERE l.journal_entry_id = :id ORDER BY l.id',
            ['id' => $id]
        );

        $this->view('admin/journal-entries/show', [
            'title' => __('journal_entries'),
            'entry' => $entry,
            'lines' => $lines,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}
