<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Services\AccountingService;
use Rateb\App\Services\AuditService;

final class AccountingDashboardController extends Controller
{
    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        TenantContext::setCompanyId($companyId);
        $service = new AccountingService();
        $service->ensureDefaultAccounts($companyId);

        $this->view('company/accounting/dashboard', [
            'title' => __('accounting_module'),
            'trial' => $service->trialBalance($companyId),
            'summary' => $service->financialSummary($companyId),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('accounting'),
        ], 'main');
    }

    public function sync(): void
    {
        rateb_require_manage('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $count = (new AccountingService())->syncFromSources($companyId);
        (new AuditService())->log('accounting_sync', 'journal', null, ['count' => $count, 'company_id' => $companyId]);
        SessionManager::flash('success', __('accounting_sync_done') . ' (' . $count . ')');
        Response::redirect(rateb_app_url('accounting'));
    }
}

final class ChartOfAccountsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new ChartOfAccount();
        $this->viewPrefix = 'company/chart-of-accounts';
        $this->routePrefix = rateb_app_route('chart-of-accounts');
        $this->entityName = 'chart_of_accounts';
        $this->fields = [
            ['name' => 'code', 'label' => 'code', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text'],
            ['name' => 'account_type', 'label' => 'account_type', 'type' => 'select', 'options' => ['asset', 'liability', 'equity', 'revenue', 'expense']],
        ];
    }

    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        TenantContext::setCompanyId($companyId);
        (new AccountingService())->ensureDefaultAccounts($companyId);
        $items = $this->model->query(
            'SELECT * FROM rateb_chart_of_accounts WHERE company_id = :cid ORDER BY code',
            ['cid' => $companyId]
        );
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $items,
            'total' => count($items),
            'page' => 1,
            'limit' => 100,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
        ]), $this->layout());
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $data['company_id'] = (int) SessionManager::get('rateb_company_id', 0);
        $data['is_active'] = 1;
        return $data;
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $items = (new JournalEntry())->query(
            'SELECT * FROM rateb_journal_entries WHERE company_id = :cid ORDER BY id DESC LIMIT 100',
            ['cid' => $companyId]
        );

        $this->view('company/journal-entries/index', [
            'title' => __('journal_entries'),
            'items' => $items,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function show(array $params): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $id = (int) ($params['id'] ?? 0);
        $entry = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
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

        $this->view('company/journal-entries/show', [
            'title' => __('journal_entries'),
            'entry' => $entry,
            'lines' => $lines,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}
