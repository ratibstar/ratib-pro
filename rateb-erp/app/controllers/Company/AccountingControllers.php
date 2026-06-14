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
            'canPost' => rateb_can_post_entity('accounting'),
        ], 'main');
    }

    public function sync(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $count = (new AccountingService())->syncFromSources($companyId);
        (new AuditService())->log('accounting_sync', 'journal', null, ['count' => $count, 'company_id' => $companyId]);
        SessionManager::flash('success', __('accounting_sync_done') . ' (' . $count . ')');
        Response::redirect(rateb_app_url('accounting'));
    }

    public function accountsPayable(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
            'canManage' => rateb_can_manage_entity('journal-entries'),
            'canPost' => rateb_can_post_entity('accounting'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can_manage_entity('journal-entries')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->postDraftEntry($id, $companyId)) {
            (new AuditService())->log('post', 'journal_entry', $id, []);
            SessionManager::flash('success', __('journal_posted'));
        } else {
            SessionManager::flash('error', __('journal_post_failed'));
        }
        Response::redirect(rateb_app_url('journal-entries/' . $id));
    }

    public function voidEntry(array $params): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
            'canManage' => rateb_can_manage_entity('journal-entries'),
            'canPost' => rateb_can_post_entity('accounting'),
        ], 'main');
    }

    /** @return array<int, array{account_id:int,debit:float,credit:float,memo?:string}> */
    private function collectLines(): array
    {
        $accountIds = $_POST['line_account_id'] ?? [];
        $debits = $_POST['line_debit'] ?? [];
        $credits = $_POST['line_credit'] ?? [];
        $memos = $_POST['line_memo'] ?? [];
        $lines = [];
        $count = max(count($accountIds), count($debits), count($credits));
        for ($i = 0; $i < $count; $i++) {
            $accountId = (int) ($accountIds[$i] ?? 0);
            $debit = (float) ($debits[$i] ?? 0);
            $credit = (float) ($credits[$i] ?? 0);
            if ($accountId <= 0 || ($debit <= 0 && $credit <= 0)) {
                continue;
            }
            $lines[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => trim((string) ($memos[$i] ?? '')) ?: null,
            ];
        }
        return $lines;
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $this->view('company/cash-vouchers/index', [
            'title' => __('cash_vouchers'),
            'items' => (new AccountingService())->listCashVouchers($companyId),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('cash-vouchers'),
            'canPost' => rateb_can_post_entity('accounting'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can_manage_entity('cash-vouchers')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        (new AccountingService())->ensureDefaultAccounts($companyId);
        $accounts = (new ChartOfAccount())->query(
            'SELECT id, code, name, name_ar FROM rateb_chart_of_accounts WHERE company_id = :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
        $this->view('company/cash-vouchers/form', [
            'title' => __('new_cash_voucher'),
            'voucher' => null,
            'accounts' => $accounts,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('cash-vouchers');
        if (!rateb_can_manage_entity('cash-vouchers') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        ], (int) SessionManager::get('rateb_user_id', 0) ?: null);
        (new AuditService())->log('create', 'cash_voucher', $id, ['status' => 'draft']);
        SessionManager::flash('success', __('voucher_saved'));
        Response::redirect(rateb_app_url('cash-vouchers/' . $id));
    }

    public function show(array $params): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
            'canPost' => rateb_can_post_entity('accounting'),
        ], 'main');
    }

    public function postVoucher(array $params): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->postCashVoucher($id, $companyId)) {
            (new AuditService())->log('post', 'cash_voucher', $id, []);
            SessionManager::flash('success', __('voucher_posted'));
        } else {
            SessionManager::flash('error', __('voucher_post_failed'));
        }
        Response::redirect(rateb_app_url('cash-vouchers/' . $id));
    }

    public function voidVoucher(array $params): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
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
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $this->view('company/fiscal-periods/index', [
            'title' => __('fiscal_periods'),
            'items' => (new AccountingService())->listFiscalPeriods($companyId),
            'csrf' => Csrf::token(),
            'canPost' => rateb_can_post_entity('accounting'),
        ], 'main');
    }

    public function close(array $params): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('fiscal-periods'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->closeFiscalPeriod($id, $companyId, (int) SessionManager::get('rateb_user_id', 0) ?: null)) {
            (new AuditService())->log('close', 'fiscal_period', $id, []);
            SessionManager::flash('success', __('fiscal_period_closed'));
        } else {
            SessionManager::flash('error', __('fiscal_period_close_failed'));
        }
        Response::redirect(rateb_app_url('fiscal-periods'));
    }
}
