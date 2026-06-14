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
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $service = new AccountingService();
        $service->ensureDefaultAccounts($companyId > 0 ? $companyId : null);

        $this->view('company/accounting/dashboard', [
            'title' => __('accounting_module'),
            'trial' => $companyId > 0 ? $service->trialBalance($companyId) : [],
            'summary' => $companyId > 0 ? $service->financialSummary($companyId) : [],
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('accounting'),
            'canPost' => rateb_can_post_entity('accounting'),
            'companies' => rateb_is_super_admin() ? (new \Rateb\App\Models\Company())->all(200, 0) : [],
            'selectedCompanyId' => $companyId,
        ], 'main');
    }

    public function sync(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting'));
        }
        $companyId = rateb_require_ops_company();
        $count = (new AccountingService())->syncFromSources($companyId);
        (new AuditService())->log('accounting_sync', 'journal', null, ['count' => $count, 'company_id' => $companyId]);
        SessionManager::flash('success', __('accounting_sync_done') . ' (' . $count . ')');
        Response::redirect(rateb_app_url('accounting'));
    }

    public function accountsPayable(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $data = (new AccountingService())->accountsPayable($companyId);
        $this->view('company/accounting/accounts-payable', [
            'title' => __('accounts_payable'),
            'rows' => $data['rows'],
            'totalOpen' => $data['total_open'],
            'totalPosted' => $data['total_posted'],
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function accountsReceivable(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $data = (new AccountingService())->accountsReceivable($companyId);
        $this->view('company/accounting/accounts-receivable', [
            'title' => __('accounts_receivable'),
            'rows' => $data['rows'],
            'totalOpen' => $data['total_open'],
            'totalPaid' => $data['total_paid'],
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function profitLoss(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $report = (new AccountingService())->profitAndLoss(
            $companyId,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null
        );
        $this->view('company/accounting/profit-loss', [
            'title' => __('profit_loss'),
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function balanceSheet(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $asOf = trim((string) ($_GET['as_of'] ?? ''));
        $report = (new AccountingService())->balanceSheet(
            $companyId,
            $asOf !== '' ? $asOf : null
        );
        $this->view('company/accounting/balance-sheet', [
            'title' => __('balance_sheet'),
            'report' => $report,
            'asOf' => $asOf,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function vatReport(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $report = (new AccountingService())->vatReport(
            $companyId,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null
        );
        $this->view('company/accounting/vat-report', [
            'title' => __('vat_report'),
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function costCenterReport(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $report = (new AccountingService())->costCenterReport(
            $companyId > 0 ? $companyId : null,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null
        );
        $this->view('company/accounting/cost-center-report', [
            'title' => __('cost_center_report'),
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function zatcaSettings(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            Response::redirect(rateb_app_url('accounting'));
        }
        $zatca = new \Rateb\App\Services\ZatcaService();
        $this->view('company/accounting/zatca-settings', [
            'title' => __('zatca_settings'),
            'profile' => $zatca->getTaxProfile($companyId),
            'readiness' => $zatca->readinessStatus($companyId),
            'invoices' => $zatca->listInvoicesWithQr($companyId),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('accounting'),
        ], 'main');
    }

    public function saveZatcaSettings(): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_manage_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/zatca-settings'));
        }
        $companyId = rateb_require_ops_company();
        (new \Rateb\App\Services\ZatcaService())->saveTaxProfile($companyId, $_POST);
        (new AuditService())->log('update', 'zatca_profile', $companyId, []);
        SessionManager::flash('success', __('save') . ' OK');
        Response::redirect(rateb_app_url('accounting/zatca-settings'));
    }

    public function generateZatcaQr(array $params): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_manage_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/zatca-settings'));
        }
        $companyId = rateb_require_ops_company();
        $invoiceId = (int) ($params['id'] ?? 0);
        if ((new \Rateb\App\Services\ZatcaService())->generateInvoiceQr($companyId, $invoiceId)) {
            SessionManager::flash('success', __('zatca_qr_generated'));
        } else {
            SessionManager::flash('error', __('zatca_qr_failed'));
        }
        Response::redirect(rateb_app_url('accounting/zatca-settings'));
    }

    public function budgetReport(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $year = (int) ($_GET['year'] ?? date('Y'));
        if ($year < 2000) {
            $year = (int) date('Y');
        }
        $service = new AccountingService();
        $service->ensureDefaultAccounts($companyId > 0 ? $companyId : null);
        $accounts = $companyId > 0
            ? (new ChartOfAccount())->query(
                'SELECT id, code, name, name_ar, account_type FROM rateb_chart_of_accounts
                 WHERE company_id = :cid AND is_active = 1 AND account_type IN (\'revenue\',\'expense\')
                 ORDER BY code',
                ['cid' => $companyId]
            )
            : [];
        $this->view('company/accounting/budget-report', [
            'title' => __('budget_report'),
            'report' => $companyId > 0 ? $service->budgetVsActual($companyId, $year) : ['year' => $year, 'lines' => [], 'totals' => ['budget' => 0, 'actual' => 0, 'variance' => 0]],
            'accounts' => $accounts,
            'year' => $year,
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('accounting'),
        ], 'main');
    }

    public function saveBudget(): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_manage_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/budget-report'));
        }
        $companyId = rateb_require_ops_company();
        $year = (int) ($_POST['fiscal_year'] ?? date('Y'));
        $accountIds = $_POST['budget_account_id'] ?? [];
        $amounts = $_POST['budget_amount'] ?? [];
        $lines = [];
        foreach ($accountIds as $i => $aid) {
            $lines[] = ['account_id' => (int) $aid, 'amount' => (float) ($amounts[$i] ?? 0)];
        }
        (new AccountingService())->saveBudgetLines($companyId, $year, $lines);
        (new AuditService())->log('update', 'budget', $companyId, ['year' => $year]);
        SessionManager::flash('success', __('budget_saved'));
        Response::redirect(rateb_app_url('accounting/budget-report') . '?year=' . $year);
    }

    public function cfoDashboard(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $this->view('company/accounting/cfo-dashboard', [
            'title' => __('cfo_dashboard'),
            'metrics' => $companyId > 0 ? (new AccountingService())->cfoMetrics($companyId) : [],
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function bankReconciliation(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $this->view('company/accounting/bank-reconciliation', [
            'title' => __('bank_reconciliation'),
            'data' => $companyId > 0 ? (new AccountingService())->bankReconciliation($companyId) : ['accounts' => [], 'total_cash' => 0, 'petty_cash' => 0],
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function exportTrialBalance(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            Response::redirect(rateb_app_url('accounting'));
        }
        $rows = (new AccountingService())->trialBalance($companyId);
        $exportRows = [];
        foreach ($rows as $r) {
            $exportRows[] = [
                'code' => $r['code'],
                'name' => $r['name'],
                'account_type' => $r['account_type'],
                'debit' => $r['total_debit'],
                'credit' => $r['total_credit'],
            ];
        }
        \Rateb\App\Controllers\Shared\ExportController::send('trial_balance', [
            ['name' => 'code', 'label' => __('code')],
            ['name' => 'name', 'label' => __('name')],
            ['name' => 'account_type', 'label' => __('account_type')],
            ['name' => 'debit', 'label' => __('debit')],
            ['name' => 'credit', 'label' => __('credit')],
        ], $exportRows, __('trial_balance'), 'accounting');
    }

    public function exportJournals(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            Response::redirect(rateb_app_url('accounting'));
        }
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $rows = (new AccountingService())->exportJournalEntries(
            $companyId,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null
        );
        \Rateb\App\Controllers\Shared\ExportController::send('journal_entries', [
            ['name' => 'entry_no', 'label' => __('entry_no')],
            ['name' => 'entry_date', 'label' => __('evaluation_date')],
            ['name' => 'description', 'label' => __('description')],
            ['name' => 'status', 'label' => __('status')],
            ['name' => 'code', 'label' => __('code')],
            ['name' => 'name', 'label' => __('name')],
            ['name' => 'debit', 'label' => __('debit')],
            ['name' => 'credit', 'label' => __('credit')],
            ['name' => 'memo', 'label' => __('memo')],
        ], $rows, __('journal_entries'), 'accounting');
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
        $companyId = rateb_resolve_ops_company_id();
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
        $data['company_id'] = rateb_resolve_ops_company_id();
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
        $companyId = rateb_resolve_ops_company_id();
        $items = (new JournalEntry())->query(
            'SELECT * FROM rateb_journal_entries WHERE company_id = :cid ORDER BY id DESC LIMIT 100',
            ['cid' => $companyId]
        );

        $this->view('company/journal-entries/index', [
            'title' => __('journal_entries'),
            'items' => $items,
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('journal-entries'),
            'canApprove' => rateb_can_approve_entity('journal-entries'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can_manage_entity('journal-entries')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_resolve_ops_company_id();
        (new AccountingService())->ensureDefaultAccounts($companyId);
        $accounts = (new ChartOfAccount())->query(
            'SELECT id, code, name, name_ar FROM rateb_chart_of_accounts WHERE company_id = :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
        $this->view('company/journal-entries/form', [
            'title' => __('new_journal_entry'),
            'entry' => null,
            'lines' => [],
            'accounts' => $accounts,
            'costCenters' => $this->loadCostCenters($companyId),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('journal-entries');
        if (!rateb_can_manage_entity('journal-entries') || !$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_require_ops_company();
        $service = new AccountingService();
        try {
            $id = $service->createManualDraft(
                $companyId,
                $this->entryDate(),
                $this->description(),
                $this->descriptionAr(),
                $this->collectLines(),
                (int) SessionManager::get('rateb_user_id', 0) ?: null
            );
            (new AuditService())->log('create', 'journal_entry', $id, ['status' => 'draft']);
            SessionManager::flash('success', __('journal_draft_saved'));
            Response::redirect(rateb_app_url('journal-entries/' . $id));
        } catch (\InvalidArgumentException $e) {
            SessionManager::flash('error', __('journal_not_balanced'));
            Response::redirect(rateb_app_url('journal-entries/create'));
        }
    }

    public function edit(array $params): void
    {
        if (!rateb_can_manage_entity('journal-entries')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $entry = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$entry || ($entry['source_type'] ?? '') !== 'manual' || ($entry['status'] ?? '') !== 'draft') {
            SessionManager::flash('error', __('journal_edit_denied'));
            Response::redirect(rateb_app_url('journal-entries/' . $id));
        }
        $lines = (new JournalEntry())->query(
            'SELECT * FROM rateb_journal_lines WHERE journal_entry_id = :id ORDER BY id',
            ['id' => $id]
        );
        $accounts = (new ChartOfAccount())->query(
            'SELECT id, code, name, name_ar FROM rateb_chart_of_accounts WHERE company_id = :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
        $this->view('company/journal-entries/form', [
            'title' => __('edit_journal_entry'),
            'entry' => $entry,
            'lines' => $lines,
            'accounts' => $accounts,
            'costCenters' => $this->loadCostCenters($companyId),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        rateb_require_post('journal-entries');
        if (!rateb_can_manage_entity('journal-entries') || !$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        $service = new AccountingService();
        try {
            if (!$service->updateManualDraft(
                $id,
                $companyId,
                $this->entryDate(),
                $this->description(),
                $this->descriptionAr(),
                $this->collectLines()
            )) {
                SessionManager::flash('error', __('journal_edit_denied'));
                Response::redirect(rateb_app_url('journal-entries/' . $id));
            }
            (new AuditService())->log('update', 'journal_entry', $id, ['status' => 'draft']);
            SessionManager::flash('success', __('journal_draft_saved'));
            Response::redirect(rateb_app_url('journal-entries/' . $id));
        } catch (\InvalidArgumentException $e) {
            SessionManager::flash('error', __('journal_not_balanced'));
            Response::redirect(rateb_app_url('journal-entries/' . $id . '/edit'));
        }
    }

    public function postEntry(array $params): void
    {
        rateb_require_approve('journal-entries');
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        $entry = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        $service = new AccountingService();
        if ($entry && $service->periodBlocksPosting($companyId, (string) ($entry['entry_date'] ?? ''))) {
            SessionManager::flash('error', __('fiscal_period_closed_block'));
            Response::redirect(rateb_app_url('journal-entries/' . $id));
        }
        if ($entry && $service->postDraftEntry($id, $companyId)) {
            (new AuditService())->log('post', 'journal_entry', $id, []);
            SessionManager::flash('success', __('journal_approved'));
        } else {
            SessionManager::flash('error', __('journal_post_failed'));
        }
        Response::redirect(rateb_app_url('journal-entries/' . $id));
    }

    public function voidEntry(array $params): void
    {
        rateb_require_approve('journal-entries');
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->voidPostedEntry($id, $companyId)) {
            (new AuditService())->log('void', 'journal_entry', $id, []);
            SessionManager::flash('success', __('journal_voided'));
        } else {
            SessionManager::flash('error', __('journal_void_failed'));
        }
        Response::redirect(rateb_app_url('journal-entries/' . $id));
    }

    public function show(array $params): void
    {
        $companyId = rateb_resolve_ops_company_id();
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
            'SELECT l.*, a.code, a.name, a.name_ar, cc.code AS cc_code, cc.name AS cc_name, cc.name_ar AS cc_name_ar
             FROM rateb_journal_lines l
             JOIN rateb_chart_of_accounts a ON a.id = l.account_id
             LEFT JOIN rateb_cost_centers cc ON cc.id = l.cost_center_id
             WHERE l.journal_entry_id = :id ORDER BY l.id',
            ['id' => $id]
        );

        $this->view('company/journal-entries/show', [
            'title' => __('journal_entries'),
            'entry' => $entry,
            'lines' => $lines,
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('journal-entries'),
            'canApprove' => rateb_can_approve_entity('journal-entries'),
        ], 'main');
    }

    /** @return array<int, array{account_id:int,debit:float,credit:float,memo?:string}> */
    private function collectLines(): array
    {
        $accountIds = $_POST['line_account_id'] ?? [];
        $debits = $_POST['line_debit'] ?? [];
        $credits = $_POST['line_credit'] ?? [];
        $memos = $_POST['line_memo'] ?? [];
        $costCenters = $_POST['line_cost_center_id'] ?? [];
        $lines = [];
        $count = max(count($accountIds), count($debits), count($credits));
        for ($i = 0; $i < $count; $i++) {
            $accountId = (int) ($accountIds[$i] ?? 0);
            $debit = (float) ($debits[$i] ?? 0);
            $credit = (float) ($credits[$i] ?? 0);
            if ($accountId <= 0 || ($debit <= 0 && $credit <= 0)) {
                continue;
            }
            $ccId = (int) ($costCenters[$i] ?? 0);
            $lines[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => trim((string) ($memos[$i] ?? '')) ?: null,
                'cost_center_id' => $ccId > 0 ? $ccId : null,
            ];
        }
        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadCostCenters(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new \Rateb\App\Models\CostCenter())->query(
            'SELECT id, code, name, name_ar FROM rateb_cost_centers WHERE company_id = :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
    }

    private function entryDate(): string
    {
        $date = trim((string) ($_POST['entry_date'] ?? ''));
        return $date !== '' ? $date : date('Y-m-d');
    }

    private function description(): string
    {
        return trim((string) ($_POST['description'] ?? '')) ?: 'Manual entry';
    }

    private function descriptionAr(): string
    {
        return trim((string) ($_POST['description_ar'] ?? '')) ?: 'قيد يدوي';
    }
}

final class CashVouchersController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $this->view('company/cash-vouchers/index', [
            'title' => __('cash_vouchers'),
            'items' => (new AccountingService())->listCashVouchers($companyId),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('cash-vouchers'),
            'canApprove' => rateb_can_approve_entity('cash-vouchers'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can_manage_entity('cash-vouchers')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_resolve_ops_company_id();
        (new AccountingService())->ensureDefaultAccounts($companyId);
        $accounts = (new ChartOfAccount())->query(
            'SELECT id, code, name, name_ar FROM rateb_chart_of_accounts WHERE company_id = :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
        $this->view('company/cash-vouchers/form', [
            'title' => __('new_cash_voucher'),
            'voucher' => null,
            'accounts' => $accounts,
            'bankAccounts' => (new AccountingService())->listBankAccounts($companyId),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('cash-vouchers');
        if (!rateb_can_manage_entity('cash-vouchers') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_require_ops_company();
        $type = (string) ($_POST['voucher_type'] ?? 'receipt');
        if (!in_array($type, ['receipt', 'payment'], true)) {
            $type = 'receipt';
        }
        $amount = (float) ($_POST['amount'] ?? 0);
        $counter = (int) ($_POST['counter_account_id'] ?? 0);
        if ($amount <= 0 || $counter < 1) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_app_url('cash-vouchers/create'));
        }
        $id = (new AccountingService())->createCashVoucherDraft($companyId, [
            'voucher_type' => $type,
            'voucher_date' => trim((string) ($_POST['voucher_date'] ?? '')) ?: date('Y-m-d'),
            'amount' => $amount,
            'party_name' => trim((string) ($_POST['party_name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: ($type === 'receipt' ? 'Cash receipt' : 'Cash payment'),
            'description_ar' => trim((string) ($_POST['description_ar'] ?? '')),
            'counter_account_id' => $counter,
            'bank_account_id' => (int) ($_POST['bank_account_id'] ?? 0) ?: null,
        ], (int) SessionManager::get('rateb_user_id', 0) ?: null);
        (new AuditService())->log('create', 'cash_voucher', $id, ['status' => 'draft']);
        SessionManager::flash('success', __('voucher_saved'));
        Response::redirect(rateb_app_url('cash-vouchers/' . $id));
    }

    public function show(array $params): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $voucher = (new JournalEntry())->queryOne(
            'SELECT v.*, a.code AS counter_code, a.name AS counter_name, a.name_ar AS counter_name_ar
             FROM rateb_cash_vouchers v
             JOIN rateb_chart_of_accounts a ON a.id = v.counter_account_id
             WHERE v.id = :id AND v.company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$voucher) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view('company/cash-vouchers/show', [
            'title' => __('cash_vouchers'),
            'voucher' => $voucher,
            'csrf' => Csrf::token(),
            'canApprove' => rateb_can_approve_entity('cash-vouchers'),
        ], 'main');
    }

    public function postVoucher(array $params): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        $voucher = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        $service = new AccountingService();
        if ($voucher && $service->periodBlocksPosting($companyId, (string) ($voucher['voucher_date'] ?? ''))) {
            SessionManager::flash('error', __('fiscal_period_closed_block'));
            Response::redirect(rateb_app_url('cash-vouchers/' . $id));
        }
        if ($voucher && $service->postCashVoucher($id, $companyId)) {
            (new AuditService())->log('post', 'cash_voucher', $id, []);
            SessionManager::flash('success', __('voucher_approved'));
        } else {
            SessionManager::flash('error', __('voucher_post_failed'));
        }
        Response::redirect(rateb_app_url('cash-vouchers/' . $id));
    }

    public function voidVoucher(array $params): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->voidCashVoucher($id, $companyId)) {
            (new AuditService())->log('void', 'cash_voucher', $id, []);
            SessionManager::flash('success', __('voucher_voided'));
        } else {
            SessionManager::flash('error', __('voucher_void_failed'));
        }
        Response::redirect(rateb_app_url('cash-vouchers/' . $id));
    }
}

final class FiscalPeriodsController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $this->view('company/fiscal-periods/index', [
            'title' => __('fiscal_periods'),
            'items' => (new AccountingService())->listFiscalPeriods($companyId),
            'csrf' => Csrf::token(),
            'canPost' => rateb_can_post_entity('fiscal-periods'),
        ], 'main');
    }

    public function close(array $params): void
    {
        rateb_require_post('fiscal-periods');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('fiscal-periods'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->closeFiscalPeriod($id, $companyId, (int) SessionManager::get('rateb_user_id', 0) ?: null)) {
            (new AuditService())->log('close', 'fiscal_period', $id, []);
            SessionManager::flash('success', __('fiscal_period_closed'));
        } else {
            SessionManager::flash('error', __('fiscal_period_close_failed'));
        }
        Response::redirect(rateb_app_url('fiscal-periods'));
    }

    public function reopen(array $params): void
    {
        rateb_require_post('fiscal-periods');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('fiscal-periods'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->reopenFiscalPeriod($id, $companyId)) {
            (new AuditService())->log('reopen', 'fiscal_period', $id, []);
            SessionManager::flash('success', __('fiscal_period_reopened'));
        } else {
            SessionManager::flash('error', __('fiscal_period_reopen_failed'));
        }
        Response::redirect(rateb_app_url('fiscal-periods'));
    }
}

final class BankAccountsController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $this->view('company/bank-accounts/index', [
            'title' => __('bank_accounts'),
            'items' => (new AccountingService())->listBankAccounts($companyId > 0 ? $companyId : null),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('accounting'),
            'createEnabled' => $companyId > 0 && rateb_can_manage_entity('accounting'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can_manage_entity('accounting')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('bank-accounts'));
        }
        $this->view('company/bank-accounts/form', [
            'title' => __('new_bank_account'),
            'item' => null,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_manage_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('bank-accounts'));
        }
        $companyId = rateb_require_ops_company();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_app_url('bank-accounts/create'));
        }
        $id = (new AccountingService())->createBankAccount($companyId, [
            'name' => $name,
            'bank_name' => trim((string) ($_POST['bank_name'] ?? '')),
            'account_number' => trim((string) ($_POST['account_number'] ?? '')),
            'opening_balance' => (float) ($_POST['opening_balance'] ?? 0),
            'is_default' => !empty($_POST['is_default']),
        ]);
        (new AuditService())->log('create', 'bank_account', $id, []);
        SessionManager::flash('success', __('bank_account_saved'));
        Response::redirect(rateb_app_url('bank-accounts'));
    }
}

final class CostCentersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\CostCenter();
        $this->viewPrefix = 'company/cost-centers';
        $this->routePrefix = rateb_app_route('cost-centers');
        $this->entityName = 'cost_centers';
        $this->fields = [
            ['name' => 'code', 'label' => 'code', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text'],
        ];
    }

    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $items = $companyId > 0
            ? $this->model->query('SELECT * FROM rateb_cost_centers WHERE company_id = :cid ORDER BY code', ['cid' => $companyId])
            : [];
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
            'createEnabled' => $this->createEnabled && $companyId > 0,
            'actionsEnabled' => $this->actionsEnabled,
        ]), $this->layout());
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $data['company_id'] = rateb_require_ops_company();
        $data['is_active'] = 1;
        return $data;
    }

    protected function layout(): string
    {
        return 'main';
    }
}
