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
use Rateb\App\Models\Supplier;
use Rateb\App\Services\AccountingService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DocumentService;
use Rateb\App\Controllers\Shared\ExportController;

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
            'canPay' => rateb_can_post_entity('accounting'),
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

    public function costOfSales(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $report = (new AccountingService())->costOfSalesReport(
            $companyId,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null
        );
        $this->view('company/accounting/cost-of-sales', [
            'title' => __('cost_of_sales'),
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function reportsHub(): void
    {
        $svc = new \Rateb\App\Services\AccountingReportsService();
        $this->view('company/accounting/reports-hub', [
            'title' => __('accounting_reports'),
            'catalog' => $svc->catalogForUser(),
            'reportCount' => $svc->reportCountForUser(),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function trialBalanceReport(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $this->view('company/accounting/trial-balance', [
            'title' => __('trial_balance'),
            'trial' => $companyId > 0 ? (new AccountingService())->trialBalance($companyId) : [],
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function journalRegister(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $rows = $companyId > 0
            ? (new AccountingService())->exportJournalEntries(
                $companyId,
                $from !== '' ? $from : null,
                $to !== '' ? $to : null
            )
            : [];
        $this->view('company/accounting/journal-register', [
            'title' => __('journal_register'),
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function accountStatement(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $accountId = (int) ($_GET['account_id'] ?? 0);
        $service = new AccountingService();
        $report = $accountId > 0 && $companyId > 0
            ? $service->accountStatement($companyId, $accountId, $from !== '' ? $from : null, $to !== '' ? $to : null)
            : ['account' => null, 'lines' => [], 'opening' => 0.0, 'closing' => 0.0, 'total_debit' => 0.0, 'total_credit' => 0.0];
        $accounts = $companyId > 0
            ? (new ChartOfAccount())->query(
                'SELECT id, code, name, name_ar FROM rateb_chart_of_accounts WHERE company_id <=> :cid AND is_active = 1 ORDER BY code',
                ['cid' => $companyId]
            )
            : [];
        $this->view('company/accounting/account-statement', [
            'title' => __('general_account_statement'),
            'report' => $report,
            'accounts' => $accounts,
            'accountId' => $accountId,
            'from' => $from,
            'to' => $to,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function partnersSubsidiaryLedger(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $report = $companyId > 0
            ? (new AccountingService())->partnersSubsidiaryLedger(
                $companyId,
                $from !== '' ? $from : null,
                $to !== '' ? $to : null
            )
            : ['accounts' => []];
        $this->view('company/accounting/partners-subsidiary-ledger', [
            'title' => __('partners_subsidiary_ledger'),
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

    public function supplierPaymentForm(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            Response::redirect(rateb_app_url('accounting/supplier-payments'));
        }
        rateb_bootstrap_ops_tenant();
        TenantContext::setCompanyId($companyId);
        $service = new AccountingService();
        $poId = (int) ($_GET['purchase_order_id'] ?? 0);
        $invoiceId = (int) ($_GET['invoice_id'] ?? 0);
        $supplierId = (int) ($_GET['supplier_id'] ?? 0);
        $payable = null;
        $po = null;
        if ($poId > 0) {
            $payable = $service->purchaseOrderPayable($companyId, $poId);
            $po = $payable['po'] ?? null;
            if ($supplierId < 1 && $payable) {
                $supplierId = (int) ($payable['supplier_id'] ?? 0);
            }
        }
        $payableOrders = $service->listPayablePurchaseOrders($companyId, $supplierId > 0 ? $supplierId : null);
        $payableInvoices = $service->listPayableSupplierInvoices($companyId, $supplierId > 0 ? $supplierId : null);
        $suppliers = (new Supplier())->all(500, 0, ['company_id' => $companyId]);
        $supplierBalances = [];
        foreach ($suppliers as $sup) {
            $sid = (int) ($sup['id'] ?? 0);
            if ($sid > 0) {
                $supplierBalances[(string) $sid] = $service->supplierOutstandingBalance($companyId, $sid);
            }
        }
        $supplierBalance = $supplierId > 0 ? $service->supplierOutstandingBalance($companyId, $supplierId) : 0.0;
        $this->view('company/accounting/supplier-payment-form', [
            'title' => __('create_supplier_payment'),
            'po' => $po,
            'payable' => $payable,
            'paidAmount' => (float) ($payable['paid'] ?? 0),
            'payableOrders' => $payableOrders,
            'payableInvoices' => $payableInvoices,
            'suppliers' => $suppliers,
            'supplierBalances' => $supplierBalances,
            'supplierBalance' => $supplierBalance,
            'selectedSupplierId' => $supplierId,
            'selectedPoId' => $poId,
            'selectedInvoiceId' => $invoiceId,
            'linkType' => $invoiceId > 0 ? 'invoice' : 'po',
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('accounting'),
        ], 'main');
    }

    public function storeSupplierPayment(): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/supplier-payments'));
        }
        $companyId = rateb_require_ops_company();
        $service = new AccountingService();
        $id = $service->postSupplierPayment($companyId, [
            'purchase_order_id' => (int) ($_POST['purchase_order_id'] ?? 0),
            'invoice_id' => (int) ($_POST['invoice_id'] ?? 0),
            'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
            'amount' => (float) ($_POST['amount'] ?? 0),
            'payment_date' => trim((string) ($_POST['payment_date'] ?? date('Y-m-d'))),
            'due_date' => trim((string) ($_POST['due_date'] ?? '')),
            'payment_method' => trim((string) ($_POST['payment_method'] ?? 'bank')),
            'bank_account_id' => (int) ($_POST['bank_account_id'] ?? 0),
            'reference_no' => trim((string) ($_POST['reference_no'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ], (int) SessionManager::get('rateb_user_id', 0) ?: null);
        if ($id) {
            if (!empty($_FILES['entity_attachment']) && is_array($_FILES['entity_attachment'])) {
                $upload = (new DocumentService())->storeUpload(
                    $_FILES['entity_attachment'],
                    'supplier_payment',
                    $id,
                    __('attach_transfer_voucher')
                );
                if (!($upload['success'] ?? false) && !empty($upload['error'])) {
                    SessionManager::flash('warning', (string) $upload['error']);
                }
            }
            (new AuditService())->log('create', 'supplier_payment', $id, []);
            SessionManager::flash('success', __('supplier_payment_posted'));
            Response::redirect(rateb_app_url('accounting/supplier-payments'));
        }
        SessionManager::flash('error', __('supplier_payment_failed'));
        $redirect = rateb_app_url('accounting/supplier-payments/create');
        $poId = (int) ($_POST['purchase_order_id'] ?? 0);
        if ($poId > 0) {
            $redirect = rateb_url_query($redirect, ['purchase_order_id' => $poId]);
        }
        Response::redirect($redirect);
    }

    public function bankReconciliationDetail(array $params): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $bankId = (int) ($params['id'] ?? 0);
        $data = $companyId > 0 ? (new AccountingService())->bankAccountReconciliation($companyId, $bankId) : null;
        if (!$data) {
            SessionManager::flash('error', __('no_records'));
            Response::redirect(rateb_app_url('accounting/bank-reconciliation'));
        }
        $this->view('company/accounting/bank-reconciliation-detail', [
            'title' => __('bank_reconciliation'),
            'data' => $data,
            'bankId' => $bankId,
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('bank-reconciliation'),
            'bulkEnabled' => rateb_can_manage_entity('bank-reconciliation'),
        ], 'main');
    }

    public function importBankStatement(array $params): void
    {
        rateb_require_post('bank-reconciliation');
        if (!rateb_can_manage_entity('bank-reconciliation') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/bank-reconciliation'));
        }
        $companyId = rateb_require_ops_company();
        $bankId = (int) ($params['id'] ?? 0);
        $csv = (string) ($_POST['statement_csv'] ?? '');
        $result = (new AccountingService())->importBankStatementCsv($companyId, $bankId, $csv);
        SessionManager::flash('success', __('bank_statement_imported') . ' (' . (int) $result['imported'] . ')');
        Response::redirect(rateb_app_url('accounting/bank-reconciliation/' . $bankId));
    }

    public function reconcileStatementLine(array $params): void
    {
        rateb_require_post('bank-reconciliation');
        if (!rateb_can_manage_entity('bank-reconciliation') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/bank-reconciliation'));
        }
        $companyId = rateb_require_ops_company();
        $lineId = (int) ($params['line_id'] ?? 0);
        $bankId = (int) ($_POST['bank_account_id'] ?? 0);
        $journalId = (int) ($_POST['journal_entry_id'] ?? 0) ?: null;
        if ((new AccountingService())->markStatementLineReconciled($lineId, $companyId, $journalId)) {
            SessionManager::flash('success', __('bank_line_reconciled'));
        }
        Response::redirect(rateb_app_url('accounting/bank-reconciliation/' . $bankId));
    }

    public function supplierPayments(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $this->view('company/accounting/supplier-payments', [
            'title' => __('supplier_payments'),
            'items' => $companyId > 0 ? (new AccountingService())->listSupplierPayments($companyId) : [],
            'csrf' => Csrf::token(),
            'canPost' => rateb_can_post_entity('accounting'),
            'bulkEnabled' => rateb_can_post_entity('accounting'),
            'exportRoute' => rateb_app_url('accounting/supplier-payments/export'),
            'exportEnabled' => rateb_can_export_entity('supplier-payments'),
        ], 'main');
    }

    public function exportSupplierPayments(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            Response::redirect(rateb_app_url('accounting/supplier-payments'));
        }
        $rows = (new AccountingService())->listSupplierPayments($companyId);
        foreach ($rows as &$row) {
            $method = (string) ($row['payment_method'] ?? '');
            $row['payment_method'] = match ($method) {
                'bank', 'bank_transfer' => __('payment_method_bank'),
                'cheque' => __('payment_method_cheque'),
                'cash' => __('payment_method_cash'),
                default => $method,
            };
            $row['status'] = __((string) ($row['status'] ?? ''));
        }
        unset($row);
        ExportController::send('supplier_payments', [
            ['name' => 'payment_no', 'label' => __('payment_no')],
            ['name' => 'payment_date', 'label' => __('actual_payment_date')],
            ['name' => 'due_date', 'label' => __('due_date')],
            ['name' => 'supplier_name', 'label' => __('supplier')],
            ['name' => 'order_no', 'label' => __('purchase_order')],
            ['name' => 'invoice_no', 'label' => __('supplier_invoice')],
            ['name' => 'payment_method', 'label' => __('payment_method')],
            ['name' => 'reference_no', 'label' => __('reference_bank_or_check')],
            ['name' => 'amount', 'label' => __('amount'), 'type' => 'money'],
            ['name' => 'status', 'label' => __('status')],
            ['name' => 'notes', 'label' => __('notes')],
        ], $rows, __('supplier_payments'), 'supplier-payments');
    }

    public function voidSupplierPayment(array $params): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/supplier-payments'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->voidSupplierPayment($id, $companyId)) {
            (new AuditService())->log('void', 'supplier_payment', $id, []);
            SessionManager::flash('success', __('supplier_payment_voided'));
        } else {
            SessionManager::flash('error', __('supplier_payment_void_failed'));
        }
        Response::redirect(rateb_app_url('accounting/supplier-payments'));
    }

    public function bulkVoidSupplierPayments(): void
    {
        rateb_require_post('accounting');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/supplier-payments'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/supplier-payments'));
        }
        $companyId = rateb_require_ops_company();
        $count = (new AccountingService())->bulkVoidSupplierPayments($ids, $companyId);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_void', 'supplier_payment', $id);
        }
        SessionManager::flash('success', __('bulk_voided', ['count' => $count]));
        Response::redirect(rateb_app_url('accounting/supplier-payments'));
    }

    public function destroyBankStatementLine(array $params): void
    {
        rateb_require_post('bank-reconciliation');
        if (!rateb_can_manage_entity('bank-reconciliation') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/bank-reconciliation'));
        }
        $companyId = rateb_require_ops_company();
        $lineId = (int) ($params['line_id'] ?? 0);
        $bankId = (int) ($_POST['bank_account_id'] ?? 0);
        if ((new AccountingService())->deleteUnreconciledBankLine($lineId, $companyId)) {
            (new AuditService())->log('delete', 'bank_statement_line', $lineId);
            SessionManager::flash('success', __('bank_line_deleted'));
        } else {
            SessionManager::flash('error', __('bank_line_delete_denied'));
        }
        Response::redirect(rateb_app_url('accounting/bank-reconciliation/' . $bankId));
    }

    public function bulkDestroyBankStatementLines(): void
    {
        rateb_require_post('bank-reconciliation');
        if (!rateb_can_manage_entity('bank-reconciliation') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/bank-reconciliation'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/bank-reconciliation'));
        }
        $companyId = rateb_require_ops_company();
        $bankId = (int) ($_POST['bank_account_id'] ?? 0);
        $count = (new AccountingService())->bulkDeleteUnreconciledBankLines($ids, $companyId);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', 'bank_statement_line', $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $count]));
        Response::redirect(rateb_app_url('accounting/bank-reconciliation/' . $bankId));
    }

    /** @return array<int, int> */
    private function parseBulkIds(): array
    {
        $raw = $this->input('ids', []);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
    }

    public function exportProfitLoss(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            Response::redirect(rateb_app_url('accounting/profit-loss'));
        }
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $report = (new AccountingService())->profitAndLoss($companyId, $from !== '' ? $from : null, $to !== '' ? $to : null);
        $rows = [];
        foreach ($report['lines'] as $line) {
            $rows[] = [
                'code' => $line['code'],
                'name' => $line['name'],
                'account_type' => $line['account_type'],
                'debit' => $line['total_debit'],
                'credit' => $line['total_credit'],
            ];
        }
        \Rateb\App\Controllers\Shared\ExportController::send('profit_loss', [
            ['name' => 'code', 'label' => __('code')],
            ['name' => 'name', 'label' => __('name')],
            ['name' => 'account_type', 'label' => __('account_type')],
            ['name' => 'debit', 'label' => __('debit')],
            ['name' => 'credit', 'label' => __('credit')],
        ], $rows, __('profit_loss'), 'accounting');
    }

    public function exportBalanceSheet(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            Response::redirect(rateb_app_url('accounting/balance-sheet'));
        }
        $asOf = trim((string) ($_GET['as_of'] ?? ''));
        $report = (new AccountingService())->balanceSheet($companyId, $asOf !== '' ? $asOf : null);
        $rows = [];
        foreach ($report['lines'] as $line) {
            $rows[] = [
                'code' => $line['code'],
                'name' => $line['name'],
                'account_type' => $line['account_type'],
                'balance' => (float) ($line['total_debit'] ?? 0) - (float) ($line['total_credit'] ?? 0),
            ];
        }
        \Rateb\App\Controllers\Shared\ExportController::send('balance_sheet', [
            ['name' => 'code', 'label' => __('code')],
            ['name' => 'name', 'label' => __('name')],
            ['name' => 'account_type', 'label' => __('account_type')],
            ['name' => 'balance', 'label' => __('balance')],
        ], $rows, __('balance_sheet'), 'accounting');
    }

    public function exportVatReport(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            Response::redirect(rateb_app_url('accounting/vat-report'));
        }
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $report = (new AccountingService())->vatReport($companyId, $from !== '' ? $from : null, $to !== '' ? $to : null);
        $rows = [[
            'output_vat' => $report['output_vat'],
            'input_vat' => $report['input_vat'],
            'net_vat' => $report['net_vat'],
            'invoice_tax' => $report['invoice_tax'],
            'po_tax' => $report['po_tax'],
        ]];
        \Rateb\App\Controllers\Shared\ExportController::send('vat_report', [
            ['name' => 'output_vat', 'label' => __('output_vat')],
            ['name' => 'input_vat', 'label' => __('input_vat')],
            ['name' => 'net_vat', 'label' => __('net_vat')],
            ['name' => 'invoice_tax', 'label' => __('invoice_tax_total')],
            ['name' => 'po_tax', 'label' => __('po_tax_total')],
        ], $rows, __('vat_report'), 'accounting');
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
        $this->permissionResource = 'chart-of-accounts';
        $this->fields = [
            ['name' => 'code', 'label' => 'code', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text'],
            ['name' => 'account_type', 'label' => 'account_type', 'type' => 'select', 'lookup' => 'account_types'],
            ['name' => 'parent_id', 'label' => 'parent_account', 'type' => 'fk', 'lookup' => 'coa_parents'],
        ];
    }

    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        TenantContext::setCompanyId($companyId);
        $service = new AccountingService();
        $service->ensureDefaultAccounts($companyId);
        $balanceMap = [];
        foreach ($service->trialBalance($companyId) as $row) {
            $balanceMap[(int) $row['id']] = (float) $row['total_debit'] - (float) $row['total_credit'];
        }
        $items = $companyId > 0 ? $this->model->query(
            'SELECT a.*, p.code AS parent_code, p.name AS parent_name, p.name_ar AS parent_name_ar
             FROM rateb_chart_of_accounts a
             LEFT JOIN rateb_chart_of_accounts p ON p.id = a.parent_id
             WHERE a.company_id = :cid AND a.is_active = 1
             ORDER BY a.code',
            ['cid' => $companyId]
        ) : [];
        foreach ($items as &$item) {
            $item['balance'] = $balanceMap[(int) $item['id']] ?? 0.0;
        }
        unset($item);
        $canManage = rateb_can_manage_entity('chart-of-accounts');
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __('chart_of_accounts'),
            'items' => $items,
            'canManage' => $canManage,
            'routePrefix' => $this->routePrefix,
        ]), $this->layout());
    }

    public function coaTree(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        TenantContext::setCompanyId($companyId);
        $service = new AccountingService();
        $tree = $service->coaTreeWithBalances($companyId);
        $typeTotals = [];
        foreach ($tree as $root) {
            $type = (string) ($root['account_type'] ?? '');
            if ($type !== '') {
                $typeTotals[$type] = (float) ($root['balance'] ?? 0);
            }
        }
        $canManage = rateb_can_manage_entity('chart-of-accounts');
        $this->view('company/accounting/coa-tree', [
            'title' => __('coa_full_tree'),
            'tree' => $tree,
            'typeTotals' => $typeTotals,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'createEnabled' => $canManage,
            'actionsEnabled' => $canManage,
        ], $this->layout());
    }

    public function create(): void
    {
        $this->guardManage();
        rateb_resolve_ops_company_id();
        (new AccountingService())->ensureDefaultAccounts((int) TenantContext::companyId());
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __('chart_of_accounts'),
            'item' => null,
        ]), $this->layout());
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->queryOne(
            'SELECT * FROM rateb_chart_of_accounts WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __('chart_of_accounts'),
            'item' => $item,
        ]), $this->layout());
    }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        return parent::formViewData($extra);
    }

    /** @return array<int|string, string> */
    private function parentOptionsLegacy(int $companyId, int $excludeId = 0): array
    {
        $tree = (new AccountingService())->coaTreeWithBalances($companyId);
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
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            $data['company_id'] = $companyId;
        }
        $data['is_active'] = 1;
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
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $service = new AccountingService();
        if ($service->destroyChartAccount($id, $companyId)) {
            (new AuditService())->log('delete', $this->entityName, $id);
            SessionManager::flash('success', __('delete') . ' OK');
        } elseif ($service->deactivateChartAccount($id, $companyId)) {
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
        $companyId = rateb_resolve_ops_company_id();
        $deactivated = (new AccountingService())->bulkDeactivateChartAccounts($ids, $companyId);
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
        $companyId = rateb_resolve_ops_company_id();
        $model = new JournalEntry();
        $items = $companyId > 0 ? $model->query(
            'SELECT * FROM rateb_journal_entries WHERE company_id = :cid ORDER BY id DESC LIMIT 100',
            ['cid' => $companyId]
        ) : [];

        $this->view('company/journal-entries/index', [
            'title' => __('journal_entries'),
            'items' => $items,
            'canManage' => rateb_can_manage_entity('journal-entries'),
        ], 'main');
    }

    public function entryApproval(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $statusFilter = trim((string) ($_GET['status'] ?? 'all'));
        $dateFrom = trim((string) ($_GET['from'] ?? ''));
        $dateTo = trim((string) ($_GET['to'] ?? ''));
        $perPage = max(10, min(100, (int) ($_GET['show'] ?? 10)));

        $sql = 'SELECT * FROM rateb_journal_entries WHERE company_id = :cid AND source_type = :manual';
        $params = ['cid' => $companyId, 'manual' => 'manual'];
        if ($statusFilter === 'pending') {
            $sql .= ' AND status = :st';
            $params['st'] = 'draft';
        } elseif ($statusFilter === 'approved') {
            $sql .= ' AND status = :st';
            $params['st'] = 'posted';
        } elseif ($statusFilter === 'rejected') {
            $sql .= ' AND status = :st';
            $params['st'] = 'rejected';
        } elseif ($statusFilter === 'void') {
            $sql .= ' AND status = :st';
            $params['st'] = 'void';
        }
        if ($dateFrom !== '') {
            $sql .= ' AND entry_date >= :from';
            $params['from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND entry_date <= :to';
            $params['to'] = $dateTo;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $perPage;

        $model = new JournalEntry();
        $items = $companyId > 0 ? $model->query($sql, $params) : [];
        $statsRow = $companyId > 0 ? $model->queryOne(
            'SELECT COUNT(*) AS total,
                    SUM(status = :draft) AS pending,
                    SUM(status = :posted) AS approved
             FROM rateb_journal_entries
             WHERE company_id = :cid AND source_type = :manual',
            ['cid' => $companyId, 'manual' => 'manual', 'draft' => 'draft', 'posted' => 'posted']
        ) : null;

        $this->view('company/accounting/entry-approval', [
            'title' => __('entry_approval'),
            'items' => $items,
            'stats' => [
                'total' => (int) ($statsRow['total'] ?? 0),
                'pending' => (int) ($statsRow['pending'] ?? 0),
                'approved' => (int) ($statsRow['approved'] ?? 0),
            ],
            'statusFilter' => $statusFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'perPage' => $perPage,
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
            Response::redirect(rateb_app_url('accounting/entry-approval'));
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
        if ($id < 1) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $entry = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$entry || ($entry['source_type'] ?? '') !== 'manual' || ($entry['status'] ?? '') !== 'draft') {
            SessionManager::flash('error', __('journal_edit_denied'));
            Response::redirect(rateb_app_url('journal-entries'));
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
        if ($id < 1) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
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
            Response::redirect(rateb_app_url('accounting/entry-approval'));
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
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        $entry = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        $service = new AccountingService();
        $reason = $entry ? $service->postDraftEntryReason($id, $companyId) : 'journal_post_failed';
        if ($reason === null) {
            (new AuditService())->log('post', 'journal_entry', $id, []);
            SessionManager::flash('success', __('journal_approved'));
        } else {
            SessionManager::flash('error', __($reason));
        }
        Response::redirect(rateb_app_url('accounting/entry-approval'));
    }

    public function voidEntry(array $params): void
    {
        rateb_require_approve('journal-entries');
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->voidPostedEntry($id, $companyId)) {
            (new AuditService())->log('void', 'journal_entry', $id, []);
            SessionManager::flash('success', __('journal_voided'));
        } else {
            SessionManager::flash('error', __('journal_void_failed'));
        }
        Response::redirect(rateb_app_url('accounting/entry-approval'));
    }

    public function rejectEntry(array $params): void
    {
        rateb_require_approve('journal-entries');
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        $reason = trim((string) ($_POST['reject_reason'] ?? ''));
        $userId = (int) SessionManager::get('rateb_user_id', 0) ?: null;
        if ((new AccountingService())->rejectManualDraft($id, $companyId, $reason, $userId)) {
            (new AuditService())->log('reject', 'journal_entry', $id, ['reason' => $reason]);
            SessionManager::flash('success', __('journal_rejected'));
        } else {
            SessionManager::flash('error', __('journal_reject_failed'));
        }
        Response::redirect(rateb_app_url('accounting/entry-approval'));
    }

    public function bulkReject(): void
    {
        rateb_require_approve('journal-entries');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $companyId = rateb_require_ops_company();
        $reason = trim((string) ($_POST['reject_reason'] ?? ''));
        $userId = (int) SessionManager::get('rateb_user_id', 0) ?: null;
        $rejected = (new AccountingService())->bulkRejectManualDrafts($ids, $companyId, $reason, $userId);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_reject', 'journal_entry', $id, ['reason' => $reason]);
        }
        SessionManager::flash('success', __('bulk_rejected', ['count' => $rejected]));
        Response::redirect(rateb_app_url('accounting/entry-approval'));
    }

    public function destroy(array $params): void
    {
        rateb_require_post('journal-entries');
        if (!rateb_can_manage_entity('journal-entries') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->deleteManualDraft($id, $companyId)) {
            (new AuditService())->log('delete', 'journal_entry', $id);
            SessionManager::flash('success', __('journal_draft_deleted'));
        } else {
            SessionManager::flash('error', __('journal_delete_denied'));
        }
        Response::redirect(rateb_app_url('journal-entries'));
    }

    public function bulkDestroy(): void
    {
        rateb_require_post('journal-entries');
        if (!rateb_can_manage_entity('journal-entries') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('journal-entries'));
        }
        $companyId = rateb_require_ops_company();
        $deleted = (new AccountingService())->bulkDeleteManualDrafts($ids, $companyId);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', 'journal_entry', $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        Response::redirect(rateb_app_url('journal-entries'));
    }

    public function bulkApprove(): void
    {
        rateb_require_approve('journal-entries');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $companyId = rateb_require_ops_company();
        $service = new AccountingService();
        $approved = 0;
        $lastReason = null;
        foreach ($ids as $id) {
            $reason = $service->postDraftEntryReason((int) $id, $companyId);
            if ($reason === null) {
                $approved++;
                (new AuditService())->log('post', 'journal_entry', (int) $id, []);
            } else {
                $lastReason = $reason;
            }
        }
        if ($approved > 0) {
            SessionManager::flash('success', __('bulk_approved', ['count' => $approved]));
        } elseif ($lastReason !== null) {
            SessionManager::flash('error', __($lastReason));
        } else {
            SessionManager::flash('error', __('journal_post_failed'));
        }
        Response::redirect(rateb_app_url('accounting/entry-approval'));
    }

    public function bulkVoid(): void
    {
        rateb_require_approve('journal-entries');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/entry-approval'));
        }
        $companyId = rateb_require_ops_company();
        $voided = (new AccountingService())->bulkVoidPostedManual($ids, $companyId);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_void', 'journal_entry', $id);
        }
        SessionManager::flash('success', __('bulk_voided', ['count' => $voided]));
        Response::redirect(rateb_app_url('accounting/entry-approval'));
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

    /** @return array<int, int> */
    private function parseBulkIds(): array
    {
        $raw = $this->input('ids', []);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
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
            'canManage' => rateb_can_manage_entity('cash-vouchers'),
        ], 'main');
    }

    public function voucherApproval(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $statusFilter = trim((string) ($_GET['status'] ?? 'all'));
        $dateFrom = trim((string) ($_GET['from'] ?? ''));
        $dateTo = trim((string) ($_GET['to'] ?? ''));
        $perPage = max(10, min(100, (int) ($_GET['show'] ?? 10)));

        $sql = 'SELECT v.*, a.code AS counter_code, a.name AS counter_name, a.name_ar AS counter_name_ar
                FROM rateb_cash_vouchers v
                JOIN rateb_chart_of_accounts a ON a.id = v.counter_account_id
                WHERE v.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($statusFilter === 'pending') {
            $sql .= ' AND v.status = :st';
            $params['st'] = 'draft';
        } elseif ($statusFilter === 'approved') {
            $sql .= ' AND v.status = :st';
            $params['st'] = 'posted';
        } elseif ($statusFilter === 'rejected') {
            $sql .= ' AND v.status = :st';
            $params['st'] = 'rejected';
        } elseif ($statusFilter === 'void') {
            $sql .= ' AND v.status = :st';
            $params['st'] = 'void';
        }
        if ($dateFrom !== '') {
            $sql .= ' AND v.voucher_date >= :from';
            $params['from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND v.voucher_date <= :to';
            $params['to'] = $dateTo;
        }
        $sql .= ' ORDER BY v.id DESC LIMIT ' . $perPage;

        $model = new JournalEntry();
        $items = $companyId > 0 ? $model->query($sql, $params) : [];
        $statsRow = $companyId > 0 ? $model->queryOne(
            'SELECT COUNT(*) AS total,
                    SUM(status = :draft) AS pending,
                    SUM(status = :posted) AS approved
             FROM rateb_cash_vouchers WHERE company_id = :cid',
            ['cid' => $companyId, 'draft' => 'draft', 'posted' => 'posted']
        ) : null;

        $this->view('company/accounting/voucher-approval', [
            'title' => __('voucher_approval'),
            'items' => $items,
            'stats' => [
                'total' => (int) ($statsRow['total'] ?? 0),
                'pending' => (int) ($statsRow['pending'] ?? 0),
                'approved' => (int) ($statsRow['approved'] ?? 0),
            ],
            'statusFilter' => $statusFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'perPage' => $perPage,
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
            'customer_id' => (int) ($_POST['customer_id'] ?? 0) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: ($type === 'receipt' ? 'Cash receipt' : 'Cash payment'),
            'description_ar' => trim((string) ($_POST['description_ar'] ?? '')),
            'counter_account_id' => $counter,
            'bank_account_id' => (int) ($_POST['bank_account_id'] ?? 0) ?: null,
        ], (int) SessionManager::get('rateb_user_id', 0) ?: null);
        (new AuditService())->log('create', 'cash_voucher', $id, ['status' => 'draft']);
        SessionManager::flash('success', __('voucher_saved'));
        Response::redirect(rateb_app_url('accounting/voucher-approval'));
    }

    public function edit(array $params): void
    {
        if (!rateb_can_manage_entity('cash-vouchers')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $voucher = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$voucher || ($voucher['status'] ?? '') !== 'draft') {
            SessionManager::flash('error', __('voucher_edit_denied'));
            Response::redirect(rateb_app_url('cash-vouchers/' . $id));
        }
        (new AccountingService())->ensureDefaultAccounts($companyId);
        $accounts = (new ChartOfAccount())->query(
            'SELECT id, code, name, name_ar FROM rateb_chart_of_accounts WHERE company_id = :cid AND is_active = 1 ORDER BY code',
            ['cid' => $companyId]
        );
        $this->view('company/cash-vouchers/form', [
            'title' => __('edit_cash_voucher'),
            'voucher' => $voucher,
            'accounts' => $accounts,
            'bankAccounts' => (new AccountingService())->listBankAccounts($companyId),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        rateb_require_post('cash-vouchers');
        if (!rateb_can_manage_entity('cash-vouchers') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        $type = (string) ($_POST['voucher_type'] ?? 'receipt');
        if (!in_array($type, ['receipt', 'payment'], true)) {
            $type = 'receipt';
        }
        $service = new AccountingService();
        if ($service->updateCashVoucherDraft($id, $companyId, [
            'voucher_type' => $type,
            'voucher_date' => trim((string) ($_POST['voucher_date'] ?? '')) ?: date('Y-m-d'),
            'amount' => (float) ($_POST['amount'] ?? 0),
            'party_name' => trim((string) ($_POST['party_name'] ?? '')),
            'customer_id' => (int) ($_POST['customer_id'] ?? 0) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'description_ar' => trim((string) ($_POST['description_ar'] ?? '')),
            'counter_account_id' => (int) ($_POST['counter_account_id'] ?? 0),
            'bank_account_id' => (int) ($_POST['bank_account_id'] ?? 0) ?: null,
        ])) {
            (new AuditService())->log('update', 'cash_voucher', $id, ['status' => 'draft']);
            SessionManager::flash('success', __('voucher_saved'));
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        SessionManager::flash('error', __('voucher_edit_denied'));
        Response::redirect(rateb_app_url('cash-vouchers/' . $id . '/edit'));
    }

    public function destroy(array $params): void
    {
        rateb_require_post('cash-vouchers');
        if (!rateb_can_manage_entity('cash-vouchers') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->deleteCashVoucherDraft($id, $companyId)) {
            (new AuditService())->log('delete', 'cash_voucher', $id);
            SessionManager::flash('success', __('voucher_deleted'));
        } else {
            SessionManager::flash('error', __('voucher_delete_denied'));
        }
        Response::redirect(rateb_app_url('cash-vouchers'));
    }

    public function bulkDestroy(): void
    {
        rateb_require_post('cash-vouchers');
        if (!rateb_can_manage_entity('cash-vouchers') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('cash-vouchers'));
        }
        $companyId = rateb_require_ops_company();
        $deleted = (new AccountingService())->bulkDeleteCashVoucherDrafts($ids, $companyId);
        foreach ($ids as $bid) {
            (new AuditService())->log('bulk_delete', 'cash_voucher', $bid);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        Response::redirect(rateb_app_url('cash-vouchers'));
    }

    public function bulkApprove(): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $companyId = rateb_require_ops_company();
        $service = new AccountingService();
        $approved = 0;
        foreach ($ids as $id) {
            $voucher = (new JournalEntry())->queryOne(
                'SELECT voucher_date FROM rateb_cash_vouchers WHERE id = :id AND company_id = :cid',
                ['id' => $id, 'cid' => $companyId]
            );
            if ($voucher && !$service->periodBlocksPosting($companyId, (string) ($voucher['voucher_date'] ?? ''))
                && $service->postCashVoucher((int) $id, $companyId)) {
                $approved++;
                (new AuditService())->log('post', 'cash_voucher', (int) $id, []);
            }
        }
        SessionManager::flash('success', __('bulk_approved', ['count' => $approved]));
        Response::redirect(rateb_app_url('accounting/voucher-approval'));
    }

    public function bulkReject(): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $companyId = rateb_require_ops_company();
        $reason = trim((string) ($_POST['reject_reason'] ?? ''));
        $userId = (int) SessionManager::get('rateb_user_id', 0) ?: null;
        $rejected = (new AccountingService())->bulkRejectCashVoucherDrafts($ids, $companyId, $reason, $userId);
        foreach ($ids as $bid) {
            (new AuditService())->log('bulk_reject', 'cash_voucher', $bid, ['reason' => $reason]);
        }
        SessionManager::flash('success', __('bulk_rejected', ['count' => $rejected]));
        Response::redirect(rateb_app_url('accounting/voucher-approval'));
    }

    public function bulkVoid(): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $companyId = rateb_require_ops_company();
        $voided = (new AccountingService())->bulkVoidCashVouchers($ids, $companyId);
        foreach ($ids as $bid) {
            (new AuditService())->log('bulk_void', 'cash_voucher', $bid);
        }
        SessionManager::flash('success', __('bulk_voided', ['count' => $voided]));
        Response::redirect(rateb_app_url('accounting/voucher-approval'));
    }

    public function show(array $params): void
    {
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $voucher = (new JournalEntry())->queryOne(
            'SELECT v.*, a.code AS counter_code, a.name AS counter_name, a.name_ar AS counter_name_ar,
                    c.code AS customer_code, c.name AS customer_name, c.name_ar AS customer_name_ar
             FROM rateb_cash_vouchers v
             JOIN rateb_chart_of_accounts a ON a.id = v.counter_account_id
             LEFT JOIN rateb_customers c ON c.id = v.customer_id
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
            'canManage' => rateb_can_manage_entity('cash-vouchers'),
            'canApprove' => rateb_can_approve_entity('cash-vouchers'),
        ], 'main');
    }

    public function postVoucher(array $params): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
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
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        if ($voucher && $service->postCashVoucher($id, $companyId)) {
            (new AuditService())->log('post', 'cash_voucher', $id, []);
            SessionManager::flash('success', __('voucher_approved'));
        } else {
            SessionManager::flash('error', __('voucher_post_failed'));
        }
        Response::redirect(rateb_app_url('accounting/voucher-approval'));
    }

    public function rejectVoucher(array $params): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        $reason = trim((string) ($_POST['reject_reason'] ?? ''));
        $userId = (int) SessionManager::get('rateb_user_id', 0) ?: null;
        if ((new AccountingService())->rejectCashVoucherDraft($id, $companyId, $reason, $userId)) {
            (new AuditService())->log('reject', 'cash_voucher', $id, ['reason' => $reason]);
            SessionManager::flash('success', __('voucher_rejected'));
        } else {
            SessionManager::flash('error', __('voucher_reject_failed'));
        }
        Response::redirect(rateb_app_url('accounting/voucher-approval'));
    }

    public function voidVoucher(array $params): void
    {
        rateb_require_approve('cash-vouchers');
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('accounting/voucher-approval'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->voidCashVoucher($id, $companyId)) {
            (new AuditService())->log('void', 'cash_voucher', $id, []);
            SessionManager::flash('success', __('voucher_voided'));
        } else {
            SessionManager::flash('error', __('voucher_void_failed'));
        }
        Response::redirect(rateb_app_url('accounting/voucher-approval'));
    }

    /** @return array<int, int> */
    private function parseBulkIds(): array
    {
        $raw = $this->input('ids', []);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
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
            'canManage' => rateb_can_manage_entity('fiscal-periods'),
            'canPost' => rateb_can_post_entity('fiscal-periods'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can_manage_entity('fiscal-periods')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('fiscal-periods'));
        }
        $this->view('company/fiscal-periods/form', [
            'title' => __('new_fiscal_period'),
            'item' => null,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        rateb_require_post('fiscal-periods');
        if (!rateb_can_manage_entity('fiscal-periods') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('fiscal-periods'));
        }
        $companyId = rateb_require_ops_company();
        $id = (new AccountingService())->createFiscalPeriod(
            $companyId,
            trim((string) ($_POST['name'] ?? '')),
            trim((string) ($_POST['start_date'] ?? '')),
            trim((string) ($_POST['end_date'] ?? ''))
        );
        if ($id) {
            (new AuditService())->log('create', 'fiscal_period', $id, []);
            SessionManager::flash('success', __('fiscal_period_created'));
        } else {
            SessionManager::flash('error', __('fiscal_period_create_failed'));
        }
        Response::redirect(rateb_app_url('fiscal-periods'));
    }

    public function destroy(array $params): void
    {
        rateb_require_post('fiscal-periods');
        if (!rateb_can_manage_entity('fiscal-periods') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('fiscal-periods'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->deleteOpenFiscalPeriod($id, $companyId)) {
            (new AuditService())->log('delete', 'fiscal_period', $id);
            SessionManager::flash('success', __('fiscal_period_deleted'));
        } else {
            SessionManager::flash('error', __('fiscal_period_delete_denied'));
        }
        Response::redirect(rateb_app_url('fiscal-periods'));
    }

    public function close(array $params): void
    {
        rateb_require_post('fiscal-periods');
        if (!rateb_can_post_entity('accounting') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('fiscal-periods'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->closeFiscalPeriod(
            $id,
            $companyId,
            (int) SessionManager::get('rateb_user_id', 0) ?: null,
            !empty($_POST['with_closing_entry'])
        )) {
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
            'canManage' => rateb_can_manage_entity('bank-accounts'),
            'createEnabled' => $companyId > 0 && rateb_can_manage_entity('bank-accounts'),
            'bulkEnabled' => $companyId > 0 && rateb_can_manage_entity('bank-accounts'),
            'actionsEnabled' => rateb_can_manage_entity('bank-accounts'),
        ], 'main');
    }

    public function create(): void
    {
        if (!rateb_can_manage_entity('bank-accounts')) {
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
        rateb_require_post('bank-accounts');
        if (!rateb_can_manage_entity('bank-accounts') || !$this->validateCsrf()) {
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

    public function edit(array $params): void
    {
        if (!rateb_can_manage_entity('bank-accounts')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(rateb_app_url('bank-accounts'));
        }
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $item = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_bank_accounts WHERE id = :id AND company_id = :cid AND is_active = 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view('company/bank-accounts/form', [
            'title' => __('edit_bank_account'),
            'item' => $item,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        rateb_require_post('bank-accounts');
        if (!rateb_can_manage_entity('bank-accounts') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('bank-accounts'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->updateBankAccount($id, $companyId, [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'bank_name' => trim((string) ($_POST['bank_name'] ?? '')),
            'account_number' => trim((string) ($_POST['account_number'] ?? '')),
            'is_default' => !empty($_POST['is_default']),
        ])) {
            (new AuditService())->log('update', 'bank_account', $id, []);
            SessionManager::flash('success', __('bank_account_saved'));
        } else {
            SessionManager::flash('error', __('invalid_request'));
        }
        Response::redirect(rateb_app_url('bank-accounts'));
    }

    public function destroy(array $params): void
    {
        rateb_require_post('bank-accounts');
        if (!rateb_can_manage_entity('bank-accounts') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('bank-accounts'));
        }
        $companyId = rateb_require_ops_company();
        $id = (int) ($params['id'] ?? 0);
        if ((new AccountingService())->deactivateBankAccount($id, $companyId)) {
            (new AuditService())->log('deactivate', 'bank_account', $id);
            SessionManager::flash('success', __('bank_account_deactivated'));
        } else {
            SessionManager::flash('error', __('bank_account_delete_denied'));
        }
        Response::redirect(rateb_app_url('bank-accounts'));
    }

    public function bulkDestroy(): void
    {
        rateb_require_post('bank-accounts');
        if (!rateb_can_manage_entity('bank-accounts') || !$this->validateCsrf()) {
            Response::redirect(rateb_app_url('bank-accounts'));
        }
        $ids = $this->parseBulkIds();
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            Response::redirect(rateb_app_url('bank-accounts'));
        }
        $companyId = rateb_require_ops_company();
        $count = (new AccountingService())->bulkDeactivateBankAccounts($ids, $companyId);
        foreach ($ids as $bid) {
            (new AuditService())->log('bulk_deactivate', 'bank_account', $bid);
        }
        SessionManager::flash('success', __('bulk_deactivated', ['count' => $count]));
        Response::redirect(rateb_app_url('bank-accounts'));
    }

    /** @return array<int, int> */
    private function parseBulkIds(): array
    {
        $raw = $this->input('ids', []);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
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
            ['name' => 'parent_id', 'label' => 'parent', 'type' => 'fk', 'lookup' => 'cost_centers'],
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

final class CustomersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Customer();
        $this->viewPrefix = 'company/customers';
        $this->routePrefix = rateb_app_route('customers');
        $this->entityName = 'customers';
        $this->indexFields = [
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'phone', 'label' => 'phone'],
            ['name' => 'email', 'label' => 'email'],
            ['name' => 'cost_center_id', 'label' => 'cost_centers', 'type' => 'fk', 'lookup' => 'cost_centers'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text'],
            ['name' => 'phone', 'label' => 'phone', 'type' => 'text'],
            ['name' => 'email', 'label' => 'email', 'type' => 'email'],
            ['name' => 'tax_id', 'label' => 'vat_number', 'type' => 'text'],
            ['name' => 'cost_center_id', 'label' => 'cost_centers', 'type' => 'fk', 'lookup' => 'cost_centers'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'rows' => 2],
        ];
    }

    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $items = $companyId > 0
            ? $this->model->query(
                'SELECT c.*, cc.code AS cost_center_code
                 FROM rateb_customers c
                 LEFT JOIN rateb_cost_centers cc ON cc.id = c.cost_center_id
                 WHERE c.company_id = :cid AND c.is_active = 1
                 ORDER BY c.name',
                ['cid' => $companyId]
            )
            : [];
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $items,
            'total' => count($items),
            'page' => 1,
            'limit' => 100,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->indexFields,
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
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_CUSTOMER, 'code');
        $ccId = (int) ($data['cost_center_id'] ?? 0);
        $data['cost_center_id'] = $ccId > 0 ? $ccId : null;
        return $data;
    }

    protected function layout(): string
    {
        return 'main';
    }
}
