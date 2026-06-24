<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\BranchTransfer;
use Rateb\App\Services\BranchAccessService;
use Rateb\App\Services\BranchFinancialReportingService;
use Rateb\App\Services\BranchIsolationService;
use Rateb\App\Services\BranchReportingService;
use Rateb\App\Services\BranchService;
use Rateb\App\Services\ConsolidationEliminationService;

final class BranchDashboardController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $rows = (new BranchReportingService())->branchesOverview($companyId);
        $branches = (new BranchAccessService())->allowedBranchIds($companyId);

        $this->view('company/branch-dashboard/index', [
            'title' => __('branch_dashboard'),
            'rows' => $rows,
            'branches' => $this->branchOptions($companyId, $branches),
            'activeFilter' => function_exists('rateb_active_branch_filter_id') ? rateb_active_branch_filter_id() : 0,
            'isHeadOffice' => (new BranchAccessService())->isHeadOfficeUser(),
            'csrf' => Csrf::token(),
        ]);
    }

    public function compare(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $branchA = (int) $this->input('branch_a', 0);
        $branchB = (int) $this->input('branch_b', 0);
        $data = null;
        if ($branchA > 0 && $branchB > 0 && $branchA !== $branchB) {
            try {
                $data = (new BranchReportingService())->compareBranches($companyId, $branchA, $branchB);
            } catch (\Throwable $e) {
                SessionManager::flash('error', $e->getMessage());
            }
        }

        $allowed = (new BranchAccessService())->allowedBranchIds($companyId);
        $this->view('company/branch-dashboard/compare', [
            'title' => __('branch_comparison'),
            'comparison' => $data,
            'branchA' => $branchA,
            'branchB' => $branchB,
            'branches' => $this->branchOptions($companyId, $allowed),
            'csrf' => Csrf::token(),
        ]);
    }

    public function reports(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $type = (string) $this->input('type', 'sales');
        $svc = new BranchReportingService();
        $rows = match ($type) {
            'profit' => $svc->reportProfitByBranch($companyId),
            'expenses' => $svc->reportExpensesByBranch($companyId),
            'inventory' => $svc->reportInventoryByBranch($companyId),
            'employees' => $svc->reportEmployeesByBranch($companyId),
            default => $svc->reportSalesByBranch($companyId),
        };

        $this->view('company/branch-dashboard/reports', [
            'title' => __('branch_reports'),
            'type' => $type,
            'rows' => $rows,
            'csrf' => Csrf::token(),
        ]);
    }

    /** @param array<int, int> $allowedIds */
    /** @return array<int, array<string, mixed>> */
    private function branchOptions(int $companyId, array $allowedIds): array
    {
        $all = (new BranchService())->listForCompany($companyId);
        if ($allowedIds === []) {
            return $all;
        }
        return array_values(array_filter($all, static fn (array $b): bool => in_array((int) ($b['id'] ?? 0), $allowedIds, true)));
    }
}

final class InterBranchTransfersController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
        [$filter, $params] = (new BranchIsolationService())->sqlFilter('t', 'source_branch_id');
        $rows = (new BranchTransfer())->query(
            "SELECT t.*, sb.name AS source_name, db.name AS dest_name
             FROM rateb_branch_transfers t
             LEFT JOIN rateb_branches sb ON sb.id = t.source_branch_id
             LEFT JOIN rateb_branches db ON db.id = t.dest_branch_id
             WHERE t.company_id = :cid{$filter}
             ORDER BY t.id DESC LIMIT 100",
            array_merge(['cid' => $companyId], $params)
        );

        $this->view('company/branch-transfers/index', [
            'title' => __('branch_transfers'),
            'items' => $rows,
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $this->view('company/branch-transfers/form', [
            'title' => __('create') . ' ' . __('branch_transfers'),
            'branches' => (new BranchService())->listForCompany($companyId),
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        rateb_bootstrap_ops_tenant();
        Csrf::verifyOrAbort();
        $companyId = (int) TenantContext::companyId();
        $source = (int) $this->input('source_branch_id', 0);
        $dest = (int) $this->input('dest_branch_id', 0);
        $type = (string) $this->input('transfer_type', 'inventory');

        if ($source < 1 || $dest < 1 || $source === $dest) {
            SessionManager::flash('error', __('branch_transfer_invalid'));
            $this->redirect(rateb_url(rateb_app_route('branch-transfers/create')));
            return;
        }

        $isolation = new BranchIsolationService();
        $isolation->assertCanAccess($source);
        $isolation->assertCanAccess($dest);

        $model = new BranchTransfer();
        $no = $model->generateTransferNo();
        $data = $isolation->stampCreate([
            'company_id' => $companyId,
            'transfer_no' => $no,
            'transfer_type' => $type,
            'source_branch_id' => $source,
            'dest_branch_id' => $dest,
            'source_entity_type' => trim((string) $this->input('source_entity_type', '')),
            'source_entity_id' => (int) $this->input('source_entity_id', 0) ?: null,
            'quantity' => (float) $this->input('quantity', 0) ?: null,
            'amount' => (float) $this->input('amount', 0) ?: null,
            'status' => 'pending',
            'notes' => trim((string) $this->input('notes', '')),
            'created_by' => (int) SessionManager::get('rateb_user_id', 0) ?: null,
        ]);
        $model->create($data);
        SessionManager::flash('success', __('saved'));
        $this->redirect(rateb_url(rateb_app_route('branch-transfers')));
    }

    public function approve(int $id): void
    {
        rateb_bootstrap_ops_tenant();
        Csrf::verifyOrAbort();
        $row = (new BranchTransfer())->find($id);
        if (!$row) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url(rateb_app_route('branch-transfers')));
            return;
        }
        (new BranchIsolationService())->assertCanAccess((int) $row['dest_branch_id']);
        (new BranchTransfer())->update($id, [
            'status' => 'approved',
            'approved_by' => (int) SessionManager::get('rateb_user_id', 0) ?: null,
        ]);
        SessionManager::flash('success', __('approved'));
        $this->redirect(rateb_url(rateb_app_route('branch-transfers')));
    }
}

final class BranchFinancialReportsController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $branches = (new BranchService())->listForCompany($companyId);
        $this->view('company/branch-financial/index', [
            'title' => __('branch_financial_reports'),
            'branches' => $branches,
            'canConsolidated' => function_exists('rateb_can') && rateb_can('branch.financial.consolidated'),
        ]);
    }

    public function profitLoss(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $branchId = (int) ($_GET['branch_id'] ?? 0);
        $from = trim((string) ($_GET['from'] ?? date('Y-01-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $report = $branchId > 0
            ? (new BranchFinancialReportingService())->profitAndLossByBranch($companyId, $branchId, $from, $to)
            : null;
        $this->renderFinancial('company/branch-financial/pl', __('branch_pl_report'), $report, $companyId, $branchId, $from, $to);
    }

    public function balanceSheet(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $branchId = (int) ($_GET['branch_id'] ?? 0);
        $asOf = trim((string) ($_GET['as_of'] ?? date('Y-m-d')));
        $report = $branchId > 0
            ? (new BranchFinancialReportingService())->balanceSheetByBranch($companyId, $branchId, $asOf)
            : null;
        $this->view('company/branch-financial/bs', [
            'title' => __('branch_bs_report'),
            'report' => $report,
            'branchId' => $branchId,
            'asOf' => $asOf,
            'branches' => (new BranchService())->listForCompany($companyId),
        ], $this->layout());
    }

    public function cashFlow(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $branchId = (int) ($_GET['branch_id'] ?? 0);
        $from = trim((string) ($_GET['from'] ?? date('Y-01-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $report = $branchId > 0
            ? (new BranchFinancialReportingService())->cashFlowByBranch($companyId, $branchId, $from, $to)
            : null;
        $this->renderFinancial('company/branch-financial/cf', __('branch_cf_report'), $report, $companyId, $branchId, $from, $to);
    }

    public function consolidated(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) TenantContext::companyId();
        $from = trim((string) ($_GET['from'] ?? date('Y-01-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $type = (string) ($_GET['type'] ?? 'pl');
        $svc = new BranchFinancialReportingService();
        $report = match ($type) {
            'bs' => $svc->consolidatedBalanceSheet($companyId, $to),
            'cf' => $svc->consolidatedCashFlow($companyId, $from, $to),
            default => $svc->consolidatedProfitAndLoss($companyId, $from, $to),
        };
        $interBranch = (new ConsolidationEliminationService())->interBranchBalances($companyId);
        $this->view('company/branch-financial/consolidated', [
            'title' => __('consolidated_financial_reports'),
            'report' => $report,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'interBranch' => $interBranch,
        ], $this->layout());
    }

    /** @param array<string, mixed>|null $report */
    private function renderFinancial(string $view, string $title, ?array $report, int $companyId, int $branchId, string $from, string $to): void
    {
        $this->view($view, [
            'title' => $title,
            'report' => $report,
            'branchId' => $branchId,
            'from' => $from,
            'to' => $to,
            'branches' => (new BranchService())->listForCompany($companyId),
        ], $this->layout());
    }

    private function layout(): string
    {
        return 'main';
    }
}
