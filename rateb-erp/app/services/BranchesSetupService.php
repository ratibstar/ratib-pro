<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;

final class BranchesSetupService
{
    private const MIGRATION_FILE = '119_branches.sql';
    private const MIGRATION_PHASE2 = '120_branches_phase2_catchup.sql';

    /** @return array<string, mixed> */
    public function report(?int $companyId = null): array
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = $companyId ?? (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }

        $db = Database::connection();
        $tableOk = $this->tableExists($db);
        $branchSvc = new BranchService();
        $stats = $branchSvc->stats($companyId);
        $branchCount = (int) ($stats['count'] ?? 0);
        $branches = [];
        $hasMain = false;
        if ($tableOk && $companyId > 0) {
            $branches = (new Branch())->all(20, 0, ['company_id' => $companyId]);
            $main = (new Branch())->queryOne(
                'SELECT id FROM rateb_branches WHERE company_id = :cid AND is_main = 1 LIMIT 1',
                ['cid' => $companyId]
            );
            $hasMain = $main !== null;
        }

        $perms = $this->permissionStatus($db);
        $hasLimitCol = $this->columnExists($db, 'rateb_plans', 'max_branches');
        $hasJeBranch = $this->columnExists($db, 'rateb_journal_entries', 'branch_id');
        $hasWhBranch = $this->columnExists($db, 'rateb_warehouses', 'branch_id');
        $hasCvBranch = $this->columnExists($db, 'rateb_cash_vouchers', 'branch_id');
        $hasUserBranches = $this->tableExistsNamed($db, 'rateb_user_branches');
        $hasEmpBranch = $this->columnExists($db, 'rateb_employees', 'branch_id');
        $usersLinked = false;
        $employeesLinked = false;
        if ($companyId > 0) {
            if ($hasUserBranches) {
                try {
                    $stmt = $db->prepare(
                        'SELECT COUNT(*) FROM rateb_user_branches ub
                         INNER JOIN rateb_users u ON u.id = ub.user_id
                         WHERE u.company_id = :cid'
                    );
                    $stmt->execute(['cid' => $companyId]);
                    $usersLinked = (int) $stmt->fetchColumn() > 0;
                } catch (\Throwable $e) {
                }
            }
            if ($hasEmpBranch) {
                try {
                    $stmt = $db->prepare(
                        'SELECT COUNT(*) FROM rateb_employees WHERE company_id = :cid AND branch_id IS NOT NULL'
                    );
                    $stmt->execute(['cid' => $companyId]);
                    $employeesLinked = (int) $stmt->fetchColumn() > 0;
                } catch (\Throwable $e) {
                }
            }
        }
        $cpLinked = function_exists('control_rateb_erp_nav_links')
            && isset(control_rateb_erp_nav_links()['branches']);

        $checks = [
            ['id' => 'table', 'label' => 'branch_check_table', 'done' => $tableOk, 'hint' => 'rateb_branches'],
            ['id' => 'migration', 'label' => 'branch_check_migration', 'done' => $this->migrationApplied($db, self::MIGRATION_FILE) || $tableOk, 'hint' => self::MIGRATION_FILE],
            ['id' => 'permissions', 'label' => 'branch_check_permissions', 'done' => $perms['view'] && $perms['manage'], 'hint' => 'branches.view / branches.manage'],
            ['id' => 'nav', 'label' => 'branch_check_nav', 'done' => function_exists('rateb_nav_can') && rateb_nav_can('branches.view'), 'hint' => __('branches')],
            ['id' => 'crud', 'label' => 'branch_check_crud', 'done' => $tableOk && class_exists(\Rateb\App\Controllers\Company\BranchesController::class), 'hint' => rateb_app_route('branches')],
            ['id' => 'map_url', 'label' => 'branch_check_map_url', 'done' => function_exists('rateb_external_url') && rateb_external_url('ratib.sa') === 'https://ratib.sa', 'hint' => 'rateb_external_url()'],
            ['id' => 'limit', 'label' => 'branch_check_limit', 'done' => $hasLimitCol, 'hint' => 'max_branches / branch_limit'],
            ['id' => 'export', 'label' => 'branch_check_export', 'done' => method_exists(\Rateb\App\Controllers\Company\BranchesController::class, 'export'), 'hint' => rateb_app_route('branches/export')],
            ['id' => 'main', 'label' => 'branch_check_main_branch', 'done' => $hasMain, 'hint' => BranchService::MAIN_CODE],
            ['id' => 'cp', 'label' => 'branch_check_control_panel', 'done' => $cpLinked, 'hint' => 'control_rateb_erp_nav_links'],
            ['id' => 'accounting', 'label' => 'branch_check_accounting_link', 'done' => $hasJeBranch && $hasCvBranch, 'hint' => 'branch_id'],
            ['id' => 'warehouse', 'label' => 'branch_check_warehouse_link', 'done' => $hasWhBranch, 'hint' => 'rateb_warehouses.branch_id'],
            ['id' => 'users', 'label' => 'branch_check_users_link', 'done' => $hasUserBranches && ($usersLinked || $companyId < 1), 'hint' => 'rateb_user_branches'],
            ['id' => 'employees', 'label' => 'branch_check_employees_link', 'done' => $hasEmpBranch && ($employeesLinked || $companyId < 1), 'hint' => 'rateb_employees.branch_id'],
            ['id' => 'data', 'label' => 'branch_check_has_branches', 'done' => $branchCount > 0, 'hint' => (string) $branchCount],
        ];

        $phase2Done = $hasUserBranches && $hasEmpBranch && ($companyId < 1 || ($usersLinked && $employeesLinked));
        $pending = $phase2Done ? [] : [
            ['label' => 'branch_todo_users', 'phase' => 2, 'done' => $phase2Done],
        ];

        $doneCount = count(array_filter($checks, static fn (array $c): bool => !empty($c['done'])));

        return [
            'company_id' => $companyId,
            'branch_count' => $branchCount,
            'branch_limit' => (int) ($stats['limit'] ?? 0),
            'branches' => $branches,
            'checks' => $checks,
            'pending' => array_values(array_filter($pending, static fn (array $p): bool => empty($p['done']))),
            'done_count' => $doneCount,
            'total_checks' => count($checks),
            'permissions' => $perms,
            'links' => [
                'list' => rateb_url(rateb_app_route('branches')),
                'create' => rateb_url(rateb_app_route('branches/create')),
                'admin' => rateb_url('admin'),
            ],
        ];
    }

    private function tableExists(\PDO $db): bool
    {
        try {
            $db->query('SELECT id FROM rateb_branches LIMIT 0');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists(\PDO $db, string $table, string $column): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $stmt->execute(['t' => $table, 'c' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableExistsNamed(\PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
            );
            $stmt->execute(['t' => $table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function migrationApplied(\PDO $db, string $file): bool
    {
        try {
            $stmt = $db->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
            $stmt->execute(['f' => $file]);
            return $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array{view:bool,manage:bool} */
    private function permissionStatus(\PDO $db): array
    {
        $out = ['view' => false, 'manage' => false];
        try {
            $stmt = $db->query(
                "SELECT slug FROM rateb_permissions WHERE slug IN ('branches.view','branches.manage')"
            );
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $slug = (string) ($row['slug'] ?? '');
                if ($slug === 'branches.view') {
                    $out['view'] = true;
                }
                if ($slug === 'branches.manage') {
                    $out['manage'] = true;
                }
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }
}
