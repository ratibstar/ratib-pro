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

        $salesTotal = $this->branchMetricSum(
            'rateb_purchase_orders',
            "SELECT COALESCE(SUM(total_amount), 0) AS total FROM rateb_purchase_orders
             WHERE company_id = :cid AND branch_id = :bid AND status IN ('approved','completed','received')",
            ['cid' => $companyId, 'bid' => $branchId]
        );
        $purchasesTotal = $this->branchMetricSum(
            'rateb_purchase_orders',
            "SELECT COALESCE(SUM(total_amount), 0) AS total FROM rateb_purchase_orders
             WHERE company_id = :cid AND branch_id = :bid",
            ['cid' => $companyId, 'bid' => $branchId]
        );
        $expenseTotal = $this->branchMetricSum(
            'rateb_journal_entries',
            "SELECT COALESCE(SUM(jl.debit), 0) AS total
             FROM rateb_journal_entries je
             INNER JOIN rateb_journal_lines jl ON jl.journal_entry_id = je.id
             WHERE je.company_id = :cid AND je.branch_id = :bid AND je.status = 'posted'",
            ['cid' => $companyId, 'bid' => $branchId],
            'branch_id'
        );
        $employeesCount = (int) $this->branchMetricSum(
            'rateb_employees',
            'SELECT COUNT(*) AS total FROM rateb_employees WHERE company_id = :cid AND branch_id = :bid AND status = :st',
            ['cid' => $companyId, 'bid' => $branchId, 'st' => 'active']
        );
        $inventoryValue = $this->branchMetricSum(
            'rateb_inventory',
            "SELECT COALESCE(SUM(quantity * unit_cost), 0) AS total FROM rateb_inventory
             WHERE company_id = :cid AND branch_id = :bid",
            ['cid' => $companyId, 'bid' => $branchId]
        );
        $purchaseRequestsCount = (int) $this->branchMetricSum(
            'rateb_purchase_requests',
            'SELECT COUNT(*) AS total FROM rateb_purchase_requests WHERE company_id = :cid AND branch_id = :bid',
            ['cid' => $companyId, 'bid' => $branchId]
        );

        return [
            'sales_total' => $salesTotal,
            'purchases_total' => $purchasesTotal,
            'expenses_total' => $expenseTotal,
            'profit_total' => $salesTotal - $expenseTotal,
            'employees_count' => $employeesCount,
            'inventory_value' => $inventoryValue,
            'purchase_requests_count' => $purchaseRequestsCount,
        ];
    }

    /** @param array<string, mixed> $params */
    private function branchMetricSum(string $table, string $sql, array $params, string $branchColumn = 'branch_id'): float
    {
        if (!$this->tableHasColumn($table, $branchColumn)) {
            return 0.0;
        }
        try {
            $model = match ($table) {
                'rateb_purchase_orders' => new PurchaseOrder(),
                'rateb_journal_entries' => new JournalEntry(),
                'rateb_employees' => new Employee(),
                'rateb_inventory' => new Inventory(),
                'rateb_purchase_requests' => new PurchaseRequest(),
                default => null,
            };
            if ($model === null) {
                return 0.0;
            }
            $row = $model->queryOne($sql, $params);
            return (float) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            if (DatabaseErrorService::isSchemaIssue($e)) {
                return 0.0;
            }
            throw $e;
        }
    }

    /** @var array<string, bool> */
    private static array $columnCache = [];

    private function tableHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $pdo = \Rateb\App\Core\Database::connection();
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($column)
            );
            self::$columnCache[$key] = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            self::$columnCache[$key] = false;
        }
        return self::$columnCache[$key];
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
