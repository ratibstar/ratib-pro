<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;
use Rateb\App\Models\Employee;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;

/** Branch KPIs and comparison metrics for Head Office dashboards. */
final class BranchReportingService
{
    private BranchIsolationService $isolation;

    public function __construct(?BranchIsolationService $isolation = null)
    {
        $this->isolation = $isolation ?? new BranchIsolationService();
    }

    /** @return array<int, array<string, mixed>> */
    public function branchesOverview(int $companyId): array
    {
        TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }

        $branches = (new Branch())->query(
            'SELECT id, name, code, is_main, status FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY is_main DESC, name ASC',
            ['cid' => $companyId, 'st' => 'active']
        );

        $allowed = $this->isolation->effectiveBranchIds();
        $out = [];
        foreach ($branches as $branch) {
            $branchId = (int) ($branch['id'] ?? 0);
            if ($allowed !== [] && !in_array($branchId, $allowed, true)) {
                continue;
            }
            $metrics = $this->branchMetrics($companyId, $branchId);
            $out[] = array_merge($branch, $metrics);
        }
        return $out;
    }

    /** @return array<string, mixed> */
    public function branchMetrics(int $companyId, int $branchId): array
    {
        TenantContext::setCompanyId($companyId);

        $sales = (new PurchaseOrder())->queryOne(
            "SELECT COALESCE(SUM(total_amount), 0) AS total FROM rateb_purchase_orders
             WHERE company_id = :cid AND branch_id = :bid AND status IN ('approved','completed','received')",
            ['cid' => $companyId, 'bid' => $branchId]
        );
        $purchases = (new PurchaseOrder())->queryOne(
            "SELECT COALESCE(SUM(total_amount), 0) AS total FROM rateb_purchase_orders
             WHERE company_id = :cid AND branch_id = :bid",
            ['cid' => $companyId, 'bid' => $branchId]
        );
        $expenses = (new JournalEntry())->queryOne(
            "SELECT COALESCE(SUM(jl.debit), 0) AS total
             FROM rateb_journal_entries je
             INNER JOIN rateb_journal_lines jl ON jl.journal_entry_id = je.id
             WHERE je.company_id = :cid AND je.branch_id = :bid AND je.status = 'posted'",
            ['cid' => $companyId, 'bid' => $branchId]
        );
        $employees = (new Employee())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_employees WHERE company_id = :cid AND branch_id = :bid AND status = :st',
            ['cid' => $companyId, 'bid' => $branchId, 'st' => 'active']
        );
        $inventoryValue = (new Inventory())->queryOne(
            "SELECT COALESCE(SUM(quantity * unit_cost), 0) AS total FROM rateb_inventory
             WHERE company_id = :cid AND branch_id = :bid",
            ['cid' => $companyId, 'bid' => $branchId]
        );
        $purchaseRequests = (new PurchaseRequest())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_purchase_requests WHERE company_id = :cid AND branch_id = :bid',
            ['cid' => $companyId, 'bid' => $branchId]
        );

        $salesTotal = (float) ($sales['total'] ?? 0);
        $expenseTotal = (float) ($expenses['total'] ?? 0);

        return [
            'sales_total' => $salesTotal,
            'purchases_total' => (float) ($purchases['total'] ?? 0),
            'expenses_total' => $expenseTotal,
            'profit_total' => $salesTotal - $expenseTotal,
            'employees_count' => (int) ($employees['c'] ?? 0),
            'inventory_value' => (float) ($inventoryValue['total'] ?? 0),
            'purchase_requests_count' => (int) ($purchaseRequests['c'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function compareBranches(int $companyId, int $branchA, int $branchB): array
    {
        $this->isolation->assertCanAccess($branchA);
        $this->isolation->assertCanAccess($branchB);

        return [
            'branch_a' => array_merge(
                $this->branchRow($branchA),
                $this->branchMetrics($companyId, $branchA)
            ),
            'branch_b' => array_merge(
                $this->branchRow($branchB),
                $this->branchMetrics($companyId, $branchB)
            ),
        ];
    }

    /** @return array<string, string> */
    private function branchRow(int $branchId): array
    {
        $row = (new Branch())->find($branchId) ?? [];
        return [
            'id' => (string) $branchId,
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
        ];
    }

    /** Named reports for reporting layer. */
    /** @return array<int, array<string, mixed>> */
    public function reportSalesByBranch(int $companyId): array
    {
        return $this->branchesOverview($companyId);
    }

    /** @return array<int, array<string, mixed>> */
    public function reportProfitByBranch(int $companyId): array
    {
        $rows = $this->branchesOverview($companyId);
        foreach ($rows as &$row) {
            $row['metric'] = $row['profit_total'] ?? 0;
        }
        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function reportExpensesByBranch(int $companyId): array
    {
        $rows = $this->branchesOverview($companyId);
        foreach ($rows as &$row) {
            $row['metric'] = $row['expenses_total'] ?? 0;
        }
        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function reportInventoryByBranch(int $companyId): array
    {
        $rows = $this->branchesOverview($companyId);
        foreach ($rows as &$row) {
            $row['metric'] = $row['inventory_value'] ?? 0;
        }
        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function reportEmployeesByBranch(int $companyId): array
    {
        $rows = $this->branchesOverview($companyId);
        foreach ($rows as &$row) {
            $row['metric'] = $row['employees_count'] ?? 0;
        }
        return $rows;
    }
}
