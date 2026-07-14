<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AccountingWorkflowService;
use Rateb\App\Services\CurrencyService;
use Rateb\App\Services\ExchangeRateService;
use Rateb\App\Services\OpeningBalanceService;
use Rateb\App\Services\ProfitCenterService;
use Rateb\App\Services\RecurringJournalService;
use Rateb\App\Services\TaxService;

/**
 * Phase 16A — Enterprise accounting platform UI (gap features).
 * Existing CoA / journals / fiscal controllers remain authoritative for core CRUD.
 */
final class AccountingPlatformHubController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $this->view('company/accounting/platform-hub', [
            'title' => __('accounting_platform'),
            'csrf' => Csrf::token(),
            'canCreate' => rateb_can('accounting.create') || rateb_can('accounting.manage'),
            'canUpdate' => rateb_can('accounting.update') || rateb_can('accounting.manage'),
            'canPost' => rateb_can('accounting.post'),
            'canReverse' => rateb_can('accounting.reverse') || rateb_can('accounting.post'),
            'canClose' => rateb_can('accounting.close_period') || rateb_can('accounting.post'),
            'canAdmin' => rateb_can('accounting.admin') || rateb_can('accounting.manage'),
        ], 'main');
    }
}

final class AccountingCurrenciesController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        $rows = [];
        try {
            $rows = (new CurrencyService())->list();
        } catch (\Throwable $e) {
            $rows = [];
        }
        $this->view('company/accounting/currencies/index', [
            'title' => __('accounting_currencies'),
            'rows' => $rows,
            'csrf' => Csrf::token(),
            'canCreate' => rateb_can('accounting.create') || rateb_can('accounting.manage'),
        ], 'main');
    }

    public function create(): void
    {
        rateb_require_ops_company();
        $this->view('company/accounting/currencies/form', [
            'title' => __('accounting_currency_create'),
            'row' => null,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/currencies'));
        }
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        try {
            (new CurrencyService())->create($_POST);
            SessionManager::flash('success', __('saved_successfully'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_app_url('accounting/currencies'));
    }
}

final class AccountingTaxCodesController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        $rows = [];
        try {
            $rows = (new TaxService())->list();
        } catch (\Throwable $e) {
            $rows = [];
        }
        $this->view('company/accounting/tax-codes/index', [
            'title' => __('accounting_tax_codes'),
            'rows' => $rows,
            'csrf' => Csrf::token(),
            'canCreate' => rateb_can('accounting.create') || rateb_can('accounting.manage'),
        ], 'main');
    }

    public function create(): void
    {
        rateb_require_ops_company();
        $this->view('company/accounting/tax-codes/form', [
            'title' => __('accounting_tax_code_create'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/tax-codes'));
        }
        TenantContext::setCompanyId(rateb_require_ops_company());
        try {
            (new TaxService())->create($_POST);
            SessionManager::flash('success', __('saved_successfully'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_app_url('accounting/tax-codes'));
    }
}

final class AccountingProfitCentersController extends Controller
{
    public function index(): void
    {
        TenantContext::setCompanyId(rateb_require_ops_company());
        $rows = [];
        try {
            $rows = (new ProfitCenterService())->list();
        } catch (\Throwable $e) {
            $rows = [];
        }
        $this->view('company/accounting/profit-centers/index', [
            'title' => __('accounting_profit_centers'),
            'rows' => $rows,
            'csrf' => Csrf::token(),
            'canCreate' => rateb_can('accounting.create') || rateb_can('accounting.manage'),
        ], 'main');
    }

    public function create(): void
    {
        rateb_require_ops_company();
        $this->view('company/accounting/profit-centers/form', [
            'title' => __('accounting_profit_center_create'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/profit-centers'));
        }
        TenantContext::setCompanyId(rateb_require_ops_company());
        try {
            (new ProfitCenterService())->create($_POST);
            SessionManager::flash('success', __('saved_successfully'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_app_url('accounting/profit-centers'));
    }
}

final class AccountingExchangeRatesController extends Controller
{
    public function store(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/currencies'));
        }
        TenantContext::setCompanyId(rateb_require_ops_company());
        try {
            (new ExchangeRateService())->create($_POST);
            SessionManager::flash('success', __('saved_successfully'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_app_url('accounting/currencies'));
    }
}

final class AccountingRecurringController extends Controller
{
    public function index(): void
    {
        try {
            TenantContext::setCompanyId(rateb_require_ops_company());
            $this->view('company/accounting/recurring/index', [
                'title' => __('accounting_recurring'),
                'csrf' => Csrf::token(),
                'canCreate' => rateb_can('accounting.create') || rateb_can('accounting.manage'),
            ], 'main');
        } catch (\Throwable $e) {
            // Soft-fail: missing migration/schema must not 500 warm probes / console noise.
            SessionManager::flash('error', $e->getMessage());
            Response::redirect(rateb_app_url('accounting/platform'));
        }
    }

    public function create(): void
    {
        try {
            rateb_require_ops_company();
            $this->view('company/accounting/recurring/form', [
                'title' => __('accounting_recurring_create'),
                'csrf' => Csrf::token(),
            ], 'main');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect(rateb_app_url('accounting/platform'));
        }
    }

    public function store(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/recurring'));
        }
        TenantContext::setCompanyId(rateb_require_ops_company());
        try {
            $lines = $this->parseLines($_POST);
            (new RecurringJournalService())->create(array_merge($_POST, ['lines' => $lines]));
            SessionManager::flash('success', __('saved_successfully'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_app_url('accounting/recurring'));
    }

    public function generate(array $params): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/recurring'));
        }
        TenantContext::setCompanyId(rateb_require_ops_company());
        $id = (int) ($params['id'] ?? 0);
        try {
            $created = (new RecurringJournalService())->generateDraft($id);
            SessionManager::flash('success', __('saved_successfully') . ' #' . $created['id']);
            Response::redirect(rateb_app_url('journal-entries/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect(rateb_app_url('accounting/recurring'));
        }
    }

    /** @param array<string, mixed> $post @return list<array<string, mixed>> */
    private function parseLines(array $post): array
    {
        $accounts = $post['line_account_id'] ?? [];
        $debits = $post['line_debit'] ?? [];
        $credits = $post['line_credit'] ?? [];
        if (!is_array($accounts)) {
            $accounts = [$accounts];
            $debits = [$debits];
            $credits = [$credits];
        }
        $out = [];
        foreach ($accounts as $i => $aid) {
            $out[] = [
                'account_id' => (int) $aid,
                'debit' => (float) ($debits[$i] ?? 0),
                'credit' => (float) ($credits[$i] ?? 0),
            ];
        }

        return $out;
    }
}

final class AccountingOpeningBalancesController extends Controller
{
    public function create(): void
    {
        rateb_require_ops_company();
        $this->view('company/accounting/opening-balances/form', [
            'title' => __('accounting_opening_balances'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/opening-balances/create'));
        }
        TenantContext::setCompanyId(rateb_require_ops_company());
        try {
            $accounts = $_POST['line_account_id'] ?? [];
            $debits = $_POST['line_debit'] ?? [];
            $credits = $_POST['line_credit'] ?? [];
            if (!is_array($accounts)) {
                $accounts = [$accounts];
                $debits = [$debits];
                $credits = [$credits];
            }
            $lines = [];
            foreach ($accounts as $i => $aid) {
                $lines[] = [
                    'account_id' => (int) $aid,
                    'debit' => (float) ($debits[$i] ?? 0),
                    'credit' => (float) ($credits[$i] ?? 0),
                ];
            }
            $created = (new OpeningBalanceService())->create([
                'entry_date' => $_POST['entry_date'] ?? date('Y-m-d'),
                'description' => $_POST['description'] ?? 'Opening balances',
                'lines' => $lines,
            ]);
            SessionManager::flash('success', __('saved_successfully'));
            Response::redirect(rateb_app_url('journal-entries/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect(rateb_app_url('accounting/opening-balances/create'));
        }
    }
}

final class AccountingWorkflowController extends Controller
{
    public function transition(array $params): void
    {
        rateb_require_post('accounting');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('journal-entries'));
        }
        TenantContext::setCompanyId(rateb_require_ops_company());
        $id = (int) ($params['id'] ?? 0);
        $to = trim((string) ($_POST['to_status'] ?? ''));
        try {
            if ($to === AccountingWorkflowService::STATUS_POSTED && !(rateb_can('accounting.post') || rateb_can('accounting.admin'))) {
                throw new \RuntimeException('forbidden');
            }
            if ($to === AccountingWorkflowService::STATUS_REVERSED && !(rateb_can('accounting.reverse') || rateb_can('accounting.post') || rateb_can('accounting.admin'))) {
                throw new \RuntimeException('forbidden');
            }
            (new AccountingWorkflowService())->transition($id, $to, trim((string) ($_POST['reason'] ?? '')) ?: null);
            SessionManager::flash('success', __('saved_successfully'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_app_url('journal-entries/' . $id));
    }
}
