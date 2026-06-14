<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Services\AccountingService;
use Rateb\App\Services\AuditService;

final class AccessControlController extends Controller
{
    public function index(): void
    {
        $users = (new \Rateb\App\Models\User())->count();
        $roles = (new \Rateb\App\Models\Role())->count();
        $permissions = (new \Rateb\App\Models\Permission())->count();

        $this->view('admin/access-control/index', [
            'title' => __('access_control'),
            'stats' => ['users' => $users, 'roles' => $roles, 'permissions' => $permissions],
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function matrix(): void
    {
        $authz = new \Rateb\App\Services\AuthorizationService();
        $authz->dedupeDuplicateRoles();
        $this->view('admin/access-control/matrix', [
            'title' => __('permission_matrix'),
            'roles' => $authz->allRoles(),
            'permissionGroups' => $authz->allPermissionsGrouped(),
            'matrix' => $authz->rolePermissionMatrix(),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function saveMatrix(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/access-control/matrix'));
        }
        $matrix = (array) $this->input('matrix', []);
        (new \Rateb\App\Services\AuthorizationService())->syncMatrixFromPost($matrix);
        (new AuditService())->log('update', 'role_permissions_matrix', null, ['roles' => count($matrix)]);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_url('admin/access-control/matrix'));
    }
}

final class AccountingDashboardController extends Controller
{
    public function index(): void
    {
        (new \Rateb\App\Services\BillingService())->ensureBillingReady();
        $service = new AccountingService();
        $service->ensureDefaultAccounts(null);

        $this->view('admin/accounting/dashboard', [
            'title' => __('accounting_module'),
            'trial' => $service->trialBalance(null),
            'summary' => $service->financialSummary(null),
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
        $tree = (new AccountingService())->coaTreeWithBalances(null);
        $this->view($this->viewPrefix . '/index', [
            'title' => __('chart_of_accounts'),
            'tree' => $tree,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'createEnabled' => rateb_can('accounting.manage'),
            'actionsEnabled' => rateb_can('accounting.manage'),
        ], $this->layout());
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
        $rows = $this->model->query(
            'SELECT id, code, name, name_ar FROM rateb_chart_of_accounts
             WHERE company_id IS NULL AND is_active = 1 AND id != :ex ORDER BY code',
            ['ex' => $excludeId]
        );
        $options = ['' => '—'];
        foreach ($rows as $row) {
            $label = $row['code'] . ' — ' . (rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name']);
            $options[(int) $row['id']] = $label;
        }
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
