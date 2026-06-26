<?php
declare(strict_types=1);

use Rateb\App\Core\Database;
use Rateb\App\Services\ApiBranchGuardService;
use Rateb\App\Services\BranchAccessService;
use Rateb\App\Services\BranchFinancialReportingService;
use Rateb\App\Services\ConsolidationEliminationService;
use Rateb\App\Services\InterBranchTransferService;

final class EnterpriseTestRunner
{
    private ?\PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (\Throwable $e) {
            $this->db = null;
        }
    }

    private function dbReady(): bool
    {
        return $this->db instanceof \PDO;
    }

    /** @return array<string,mixed> */
    public function runAll(): array
    {
        $suites = [
            'branch_isolation' => $this->suiteBranchIsolation(),
            'financial' => $this->suiteFinancial(),
            'transfers' => $this->suiteTransfers(),
            'api_security' => $this->suiteApiSecurity(),
            'infrastructure' => $this->suiteInfrastructure(),
        ];
        $passed = 0;
        $total = 0;
        $failed = 0;
        foreach ($suites as $data) {
            foreach ($data['tests'] as $t) {
                $total++;
                if ($t['passed'] ?? false) {
                    $passed++;
                } else {
                    $failed++;
                }
            }
        }
        return [
            'passed' => $passed,
            'failed' => $failed,
            'total' => $total,
            'suites' => $suites,
            'generated_at' => date('c'),
        ];
    }

    /** @return array<string,mixed> */
    private function suiteBranchIsolation(): array
    {
        $tests = [];
        $tests[] = $this->test('BranchAccessService class loads', class_exists(BranchAccessService::class));
        if (!$this->dbReady()) {
            $tests[] = $this->test('rateb_user_branches table exists', false, 'database unavailable');
            $tests[] = $this->test('rateb_branches table exists', false, 'database unavailable');
            $tests[] = $this->test('Branch-scoped tables have branch_id', false, 'database unavailable');
            $tests[] = $this->test('HQ roles defined', false, 'database unavailable');
            $tests[] = $this->test('Branch manager role defined', false, 'database unavailable');
            return $this->suiteResult($tests);
        }
        $tests[] = $this->test('rateb_user_branches table exists', $this->tableExists('rateb_user_branches'));
        $tests[] = $this->test('rateb_branches table exists', $this->tableExists('rateb_branches'));
        $tests[] = $this->test(
            'Branch-scoped tables have branch_id',
            $this->columnExists('rateb_employees', 'branch_id')
                && $this->columnExists('rateb_journal_entries', 'branch_id')
        );
        $hqRole = $this->db->query(
            "SELECT COUNT(*) FROM rateb_roles WHERE slug IN ('hq_admin','hq_manager')"
        )->fetchColumn();
        $tests[] = $this->test('HQ roles defined', (int) $hqRole >= 1);
        $bmRole = $this->db->query(
            "SELECT COUNT(*) FROM rateb_roles WHERE slug = 'branch_manager'"
        )->fetchColumn();
        $tests[] = $this->test('Branch manager role defined', (int) $bmRole >= 1);
        return $this->suiteResult($tests);
    }

    /** @return array<string,mixed> */
    private function suiteFinancial(): array
    {
        $tests = [];
        $tests[] = $this->test(
            'BranchFinancialReportingService available',
            class_exists(BranchFinancialReportingService::class)
        );
        $tests[] = $this->test(
            'ConsolidationEliminationService available',
            class_exists(ConsolidationEliminationService::class)
        );
        if (!$this->dbReady()) {
            $tests[] = $this->test('Inter-branch GL accounts 1350/2150 seeded', false, 'database unavailable');
            $tests[] = $this->test('Company data for financial tests', false, 'database unavailable');
            return $this->suiteResult($tests);
        }
        $tests[] = $this->test(
            'Inter-branch GL accounts 1350/2150 seeded',
            $this->interBranchAccountsExist()
        );
        $companyId = $this->firstCompanyId();
        if ($companyId > 0) {
            try {
                $elim = (new ConsolidationEliminationService())->eliminationAdjustments($companyId);
                $assetAdj = (float) ($elim['asset_adjustment'] ?? 0);
                $liabAdj = (float) ($elim['liability_adjustment'] ?? 0);
                $tests[] = $this->test(
                    'Elimination asset/liability symmetric',
                    abs($assetAdj + $liabAdj) < 0.01 || ($assetAdj === 0.0 && $liabAdj === 0.0)
                );
            } catch (\Throwable $e) {
                $tests[] = $this->test('Elimination adjustments run', false, $e->getMessage());
            }
            try {
                $svc = new BranchFinancialReportingService();
                if (method_exists($svc, 'trialBalance')) {
                    $tb = $svc->trialBalance($companyId, null, null, null);
                    $tests[] = $this->test('Trial balance returns array', is_array($tb));
                } else {
                    $tests[] = $this->test('Trial balance method exists', false, 'trialBalance missing');
                }
            } catch (\Throwable $e) {
                $tests[] = $this->test('Trial balance executes', false, $e->getMessage());
            }
        } else {
            $tests[] = $this->test('Company data for financial tests', false, 'no company');
        }
        return $this->suiteResult($tests);
    }

    /** @return array<string,mixed> */
    private function suiteTransfers(): array
    {
        $tests = [];
        $tests[] = $this->test(
            'InterBranchTransferService class exists',
            class_exists(InterBranchTransferService::class)
        );
        if (!$this->dbReady()) {
            $tests[] = $this->test('rateb_branch_transfers table exists', false, 'database unavailable');
            $tests[] = $this->test('Transfer status supports failed', false, 'database unavailable');
            $tests[] = $this->test('Journal source_type supports branch_transfer', false, 'database unavailable');
            $tests[] = $this->test('Audit log table ready', false, 'database unavailable');
            $tests[] = $this->test('Notifications table ready', false, 'database unavailable');
            return $this->suiteResult($tests);
        }
        $tests[] = $this->test(
            'rateb_branch_transfers table exists',
            $this->tableExists('rateb_branch_transfers')
        );
        $tests[] = $this->test(
            'Transfer status supports failed',
            $this->transferStatusIncludes('failed')
        );
        $tests[] = $this->test(
            'Journal source_type supports branch_transfer',
            $this->journalSourceIncludes('branch_transfer')
        );
        $tests[] = $this->test(
            'Audit log table ready',
            $this->tableExists('rateb_audit_logs')
        );
        $tests[] = $this->test(
            'Notifications table ready',
            $this->tableExists('rateb_notifications')
        );
        return $this->suiteResult($tests);
    }

    /** @return array<string,mixed> */
    private function suiteApiSecurity(): array
    {
        $tests = [];
        $tests[] = $this->test(
            'ApiBranchGuardService available',
            class_exists(ApiBranchGuardService::class)
        );
        if (!$this->dbReady()) {
            $tests[] = $this->test('API tokens table exists', false, 'database unavailable');
            $tests[] = $this->test('erp-security-cert probe file exists', is_file(RATEB_ROOT . '/public/erp-security-cert.php'));
            return $this->suiteResult($tests);
        }
        $tests[] = $this->test(
            'API tokens table exists',
            $this->tableExists('rateb_api_tokens')
        );
        if ($this->tableExists('rateb_api_tokens')) {
            $tests[] = $this->test(
                'API tokens have branch_id column',
                $this->columnExists('rateb_api_tokens', 'branch_id')
            );
        }
        $tests[] = $this->test(
            'erp-security-cert probe file exists',
            is_file(RATEB_ROOT . '/public/erp-security-cert.php')
        );
        return $this->suiteResult($tests);
    }

    /** @return array<string,mixed> */
    private function suiteInfrastructure(): array
    {
        $tests = [];
        $tests[] = $this->test('erp-health probe exists', is_file(RATEB_ROOT . '/public/erp-health.php'));
        $tests[] = $this->test('erp-backup script exists', is_file(RATEB_ROOT . '/bin/erp-backup.php'));
        $tests[] = $this->test('erp-restore script exists', is_file(RATEB_ROOT . '/bin/erp-restore.php'));
        $tests[] = $this->test('Migration 135 file exists', is_file(RATEB_ROOT . '/migrations/135_phase6_interbranch_execution.sql'));
        $tests[] = $this->test('Enterprise seed guard exists', is_file(RATEB_ROOT . '/bin/enterprise-seed/guard.php'));
        return $this->suiteResult($tests);
    }

    /** @param array<int,array<string,mixed>> $tests @return array<string,mixed> */
    private function suiteResult(array $tests): array
    {
        $passed = 0;
        foreach ($tests as $t) {
            if ($t['passed'] ?? false) {
                $passed++;
            }
        }
        return ['passed' => $passed, 'total' => count($tests), 'tests' => $tests];
    }

    /** @return array<string,mixed> */
    private function test(string $name, bool $passed, ?string $reason = null): array
    {
        return ['name' => $name, 'passed' => $passed, 'reason' => $reason];
    }

    private function tableExists(string $table): bool
    {
        if (!$this->dbReady()) {
            return false;
        }
        $stmt = $this->db->query("SHOW TABLES LIKE " . $this->db->quote($table));
        return $stmt !== false && $stmt->fetch() !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->dbReady()) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => $table, 'c' => $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function firstCompanyId(): int
    {
        if (!$this->dbReady()) {
            return 0;
        }
        $id = $this->db->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
        return (int) ($id ?: 0);
    }

    private function interBranchAccountsExist(): bool
    {
        if (!$this->dbReady()) {
            return false;
        }
        $row = $this->db->query(
            "SELECT COUNT(DISTINCT code) AS c FROM rateb_chart_of_accounts WHERE code IN ('1350','2150')"
        )->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['c'] ?? 0) >= 2;
    }

    private function transferStatusIncludes(string $value): bool
    {
        if (!$this->dbReady()) {
            return false;
        }
        $row = $this->db->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_branch_transfers' AND COLUMN_NAME = 'status'"
        )->fetch(\PDO::FETCH_ASSOC);
        return $row && str_contains((string) ($row['COLUMN_TYPE'] ?? ''), "'" . $value . "'");
    }

    private function journalSourceIncludes(string $value): bool
    {
        if (!$this->dbReady()) {
            return false;
        }
        $row = $this->db->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'source_type'"
        )->fetch(\PDO::FETCH_ASSOC);
        return $row && str_contains((string) ($row['COLUMN_TYPE'] ?? ''), "'" . $value . "'");
    }
}
