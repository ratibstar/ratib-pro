<?php
declare(strict_types=1);

use Rateb\App\Core\BranchContext;
use Rateb\App\Core\Database;
use Rateb\App\Services\ApiBranchGuardService;
use Rateb\App\Services\AuthorizationService;
use Rateb\App\Services\BranchAccessService;
use Rateb\App\Services\BranchFinancialReportingService;
use Rateb\App\Services\BranchService;
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
            'p7_branch_assignment' => $this->suiteP7BranchAssignment(),
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

    /** P7-1 branch assignment certification (WP7). */
    /** @return array<string,mixed> */
    private function suiteP7BranchAssignment(): array
    {
        $tests = [];
        $tests[] = $this->test(
            'P7 strict flag helper exists',
            function_exists('rateb_branch_strict_assignment')
        );
        $tests[] = $this->test(
            'P7 strict flag defaults false',
            function_exists('rateb_branch_strict_assignment') && rateb_branch_strict_assignment() === false
        );
        $tests[] = $this->test(
            'P7 backfill CLI script exists',
            is_file(RATEB_ROOT . '/bin/backfill-user-branch-assignments.php')
        );
        $adminSrc = is_file(RATEB_ROOT . '/app/controllers/Admin/AdminControllers.php')
            ? (string) file_get_contents(RATEB_ROOT . '/app/controllers/Admin/AdminControllers.php') : '';
        $tests[] = $this->test(
            'P7 UsersController branch assignment gate present',
            $adminSrc !== '' && str_contains($adminSrc, 'assertBranchAssignmentForRoles')
        );
        $accessSvc = new BranchAccessService();
        $tests[] = $this->test(
            'P7 branch_manager requires branch assignment (role policy)',
            $accessSvc->slugRequiresBranchAssignment('branch_manager')
                && $accessSvc->slugRequiresBranchAssignment('branch_user')
                && !$accessSvc->slugRequiresBranchAssignment('hq_admin')
        );

        if (!$this->dbReady()) {
            $tests[] = $this->test('P7 behavioral tests', false, 'database unavailable');
            return $this->suiteResult($tests);
        }

        $companyId = $this->firstCompanyId();
        $branchId = $this->firstBranchIdForCompany($companyId);
        if ($companyId < 1 || $branchId < 1) {
            $tests[] = $this->test('P7 fixture company and branch', false, 'no company/branch');
            return $this->suiteResult($tests);
        }

        $this->p7CleanupFixtureUsers();
        $bmEmptyId = $this->p7CreateFixtureUser($companyId, 'bm-empty');
        $bmAssignedId = $this->p7CreateFixtureUser($companyId, 'bm-assigned');
        $hqId = $this->p7CreateFixtureUser($companyId, 'hq');
        $cfaId = $this->p7CreateFixtureUser($companyId, 'cfa');
        $acctId = $this->p7CreateFixtureUser($companyId, 'acct');

        try {
            $bmRoleId = $this->roleIdBySlug('branch_manager');
            $hqRoleId = $this->roleIdBySlug('hq_admin');
            $cfaRoleId = $this->roleIdBySlug('company-full-access');
            $acctRoleId = $this->roleIdBySlug('accountant');
            if ($bmRoleId < 1 || $hqRoleId < 1 || $cfaRoleId < 1) {
                $tests[] = $this->test('P7 system roles present', false, 'missing role slugs');
                return $this->suiteResult($tests);
            }

            $this->p7SyncRole($bmEmptyId, $bmRoleId);
            $this->p7SyncRole($bmAssignedId, $bmRoleId);
            $this->p7SyncRole($hqId, $hqRoleId);
            $this->p7SyncRole($cfaId, $cfaRoleId);
            if ($acctRoleId > 0) {
                $this->p7SyncRole($acctId, $acctRoleId);
            }

            $this->p7ClearUserBranches($bmEmptyId);
            $this->p7ClearUserBranches($bmAssignedId);
            $this->p7ClearUserBranches($hqId);
            $this->p7ClearUserBranches($cfaId);
            $this->p7ClearUserBranches($acctId);
            $this->p7AssignBranch($bmAssignedId, $branchId);

            $companyBranchIds = $this->companyBranchIds($companyId);

            $legacy = $this->withStrictFlag('0', fn (): array => $this->invokeResolveBranchAccess(
                $companyId,
                $bmEmptyId,
                [],
                $companyBranchIds
            ));
            $tests[] = $this->test(
                'P7 branch_manager empty junction flag OFF legacy accessAll',
                ($legacy['accessAll'] ?? false) === true && ($legacy['allowedIds'] ?? []) !== []
            );

            $strict = $this->withStrictFlag('1', fn (): array => $this->invokeResolveBranchAccess(
                $companyId,
                $bmEmptyId,
                [],
                $companyBranchIds
            ));
            $tests[] = $this->test(
                'P7 branch_manager empty junction flag ON deny-all',
                ($strict['accessAll'] ?? true) === false && ($strict['allowedIds'] ?? [-1]) === []
            );

            $assigned = $this->invokeResolveBranchAccess(
                $companyId,
                $bmAssignedId,
                [$branchId],
                $companyBranchIds
            );
            $tests[] = $this->test(
                'P7 branch_manager single assignment scoped',
                ($assigned['accessAll'] ?? true) === false
                    && in_array($branchId, $assigned['allowedIds'] ?? [], true)
                    && count($assigned['allowedIds'] ?? []) === 1
            );

            $cfaResolved = $this->invokeResolveBranchAccess(
                $companyId,
                $cfaId,
                [],
                $companyBranchIds
            );
            $tests[] = $this->test(
                'P7 company-full-access unrestricted',
                ($cfaResolved['accessAll'] ?? false) === true
            );

            $hqResolved = $this->invokeResolveBranchAccess(
                $companyId,
                $hqId,
                [],
                $companyBranchIds
            );
            $tests[] = $this->test(
                'P7 HQ role unrestricted',
                ($hqResolved['accessAll'] ?? false) === true
            );

            $authz = new AuthorizationService();
            $tests[] = $this->test(
                'P7 branches.access_all permission unrestricted',
                $authz->userHasPermission($hqId, 'branches.access_all')
            );

            $apiLegacy = $this->withStrictFlag('0', function () use ($companyId, $bmEmptyId): array {
                return $this->bootstrapApiContext($companyId, $bmEmptyId);
            });
            $tests[] = $this->test(
                'P7 API bootstrap flag OFF matches legacy',
                ($apiLegacy['accessAll'] ?? false) === true
            );

            $apiStrict = $this->withStrictFlag('1', function () use ($companyId, $bmEmptyId): array {
                return $this->bootstrapApiContext($companyId, $bmEmptyId);
            });
            $tests[] = $this->test(
                'P7 API bootstrap flag ON deny-all',
                ($apiStrict['accessAll'] ?? true) === false && ($apiStrict['allowedIds'] ?? [-1]) === []
            );

            $branchSvc = new BranchService();
            $portalStrict = $this->withStrictFlag('1', fn (): bool => $branchSvc->userMayUsePortalBranch(
                $bmEmptyId,
                $branchId,
                $companyId
            ));
            $portalAssigned = $branchSvc->userMayUsePortalBranch($bmAssignedId, $branchId, $companyId);
            $tests[] = $this->test(
                'P7 portal validation strict deny / assigned allow',
                $portalStrict === false && $portalAssigned === true
            );

            $tests[] = $this->test(
                'P7 reject branch_manager save without branches (policy)',
                $accessSvc->roleIdsRequireBranchAssignment([$bmRoleId])
            );
            $tests[] = $this->test(
                'P7 accept HQ role without branches (policy)',
                !$accessSvc->roleIdsRequireBranchAssignment([$hqRoleId])
            );

            BranchContext::reset();
            BranchContext::setBootstrapped($companyId, false, []);
            $filterSql = function_exists('rateb_branch_filter_sql') ? rateb_branch_filter_sql('t', 'branch_id') : ['', []];
            BranchContext::reset();
            $tests[] = $this->test(
                'P7 branch filter deny state AND 1=0',
                is_array($filterSql)
                    && ($filterSql[0] ?? '') !== ''
                    && str_contains((string) ($filterSql[0] ?? ''), '1=0')
            );

            $cli = RATEB_ROOT . '/bin/backfill-user-branch-assignments.php';
            $dryOut = [];
            $dryCode = 1;
            exec(PHP_BINARY . ' ' . escapeshellarg($cli) . ' --dry-run 2>&1', $dryOut, $dryCode);
            $dryText = implode("\n", $dryOut);
            $tests[] = $this->test(
                'P7 backfill CLI dry-run',
                $dryCode === 0 && str_contains($dryText, 'dry-run')
            );

            $this->p7ClearUserBranches($bmEmptyId);
            exec(PHP_BINARY . ' ' . escapeshellarg($cli) . ' 2>&1', $runOut1, $runCode1);
            exec(PHP_BINARY . ' ' . escapeshellarg($cli) . ' 2>&1', $runOut2, $runCode2);
            $runText2 = implode("\n", $runOut2);
            $tests[] = $this->test(
                'P7 backfill CLI execute + idempotent',
                $runCode1 === 0
                    && $runCode2 === 0
                    && str_contains($runText2, 'Users scanned:')
                    && (preg_match('/Users scanned:\s+0/', $runText2) === 1
                        || preg_match('/Users updated:\s+0/', $runText2) === 1)
            );

            $this->p7AssignBranch($hqId, $branchId);
            $hqNotifyIds = $this->invokeHqManagerUserIds($companyId);
            $tests[] = $this->test(
                'P7 HQ notifications include HQ user after branch backfill',
                in_array($hqId, $hqNotifyIds, true)
            );
            $tests[] = $this->test(
                'P7 HQ notifications exclude branch-only manager',
                !in_array($bmAssignedId, $hqNotifyIds, true)
            );

            if ($acctRoleId > 0) {
                $acctLegacy = $this->withStrictFlag('0', fn (): array => $this->invokeResolveBranchAccess(
                    $companyId,
                    $acctId,
                    [],
                    $companyBranchIds
                ));
                $tests[] = $this->test(
                    'P7 regression non-restricted empty junction flag OFF legacy',
                    ($acctLegacy['accessAll'] ?? false) === true
                );
            }
        } catch (\Throwable $e) {
            $tests[] = $this->test('P7 behavioral tests', false, $e->getMessage());
        } finally {
            $this->p7CleanupFixtureUsers();
            BranchContext::reset();
            $this->withStrictFlag(null, static fn (): bool => true);
        }

        return $this->suiteResult($tests);
    }

    /** @return array{accessAll: bool, allowedIds: array<int, int>} */
    private function invokeResolveBranchAccess(
        int $companyId,
        int $userId,
        array $assigned,
        array $companyBranchIds
    ): array {
        $svc = new BranchAccessService();
        $ref = new \ReflectionMethod(BranchAccessService::class, 'resolveBranchAccess');
        $ref->setAccessible(true);
        /** @var array{accessAll: bool, allowedIds: array<int, int>} $result */
        $result = $ref->invoke(
            $svc,
            $companyId,
            $userId,
            $assigned,
            $companyBranchIds,
            false,
            (new AuthorizationService())->userHasPermission($userId, 'branches.access_all'),
            $this->userHasHeadOfficeRole($userId, $companyId)
        );
        return $result;
    }

    /** @return array{accessAll: bool, allowedIds: array<int, int>} */
    private function bootstrapApiContext(int $companyId, int $userId): array
    {
        BranchContext::reset();
        (new BranchAccessService())->bootstrapForApi($companyId, $userId, null);
        $ctx = [
            'accessAll' => BranchContext::accessAll(),
            'allowedIds' => BranchContext::allowedIds(),
        ];
        BranchContext::reset();
        return $ctx;
    }

    /** @param callable(): mixed $fn */
    private function withStrictFlag(?string $value, callable $fn): mixed
    {
        $key = 'RATEB_BRANCH_STRICT_ASSIGNMENT';
        $prev = getenv($key);
        if ($value === null) {
            putenv($key);
        } else {
            putenv($key . '=' . $value);
        }
        try {
            return $fn();
        } finally {
            if ($prev === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $prev);
            }
        }
    }

    /** @return array<int, int> */
    private function invokeHqManagerUserIds(int $companyId): array
    {
        $ref = new \ReflectionMethod(InterBranchTransferService::class, 'hqManagerUserIds');
        $ref->setAccessible(true);
        /** @var array<int, int> $ids */
        $ids = $ref->invoke(new InterBranchTransferService(), $companyId);
        return $ids;
    }

    private function userHasHeadOfficeRole(int $userId, int $companyId): bool
    {
        if (!$this->dbReady()) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT r.slug FROM rateb_user_roles ur
             INNER JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid
               AND (r.company_id IS NULL OR r.company_id = 0 OR r.company_id = :cid)
               AND r.slug IN (\'hq_admin\', \'hq_manager\', \'company-full-access\')
             LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'cid' => $companyId]);
        return $stmt->fetch() !== false;
    }

    private function roleIdBySlug(string $slug): int
    {
        if (!$this->dbReady()) {
            return 0;
        }
        $stmt = $this->db->prepare('SELECT id FROM rateb_roles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function firstBranchIdForCompany(int $companyId): int
    {
        if (!$this->dbReady() || $companyId < 1) {
            return 0;
        }
        $stmt = $this->db->prepare(
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'st' => 'active']);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array<int, int> */
    private function companyBranchIds(int $companyId): array
    {
        if (!$this->dbReady() || $companyId < 1) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY id ASC'
        );
        $stmt->execute(['cid' => $companyId, 'st' => 'active']);
        return array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'id'));
    }

    private function p7CreateFixtureUser(int $companyId, string $suffix): int
    {
        $email = 'p7-cert-' . $suffix . '@rateb.local';
        $hash = password_hash('p7-cert-test', PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
             VALUES (:cid, :name, :email, :pass, 0, :st, :loc)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'name' => 'P7 Cert ' . $suffix,
            'email' => $email,
            'pass' => $hash,
            'st' => 'active',
            'loc' => 'en',
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function p7SyncRole(int $userId, int $roleId): void
    {
        $this->db->prepare('DELETE FROM rateb_user_roles WHERE user_id = :uid')->execute(['uid' => $userId]);
        $this->db->prepare('INSERT INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)')
            ->execute(['uid' => $userId, 'rid' => $roleId]);
    }

    private function p7ClearUserBranches(int $userId): void
    {
        if (!$this->tableExists('rateb_user_branches')) {
            return;
        }
        $this->db->prepare('DELETE FROM rateb_user_branches WHERE user_id = :uid')->execute(['uid' => $userId]);
    }

    private function p7AssignBranch(int $userId, int $branchId): void
    {
        if (!$this->tableExists('rateb_user_branches') || $userId < 1 || $branchId < 1) {
            return;
        }
        $this->db->prepare('INSERT IGNORE INTO rateb_user_branches (user_id, branch_id) VALUES (:uid, :bid)')
            ->execute(['uid' => $userId, 'bid' => $branchId]);
    }

    private function p7CleanupFixtureUsers(): void
    {
        if (!$this->dbReady()) {
            return;
        }
        $rows = $this->db->query(
            "SELECT id FROM rateb_users WHERE email LIKE 'p7-cert-%@rateb.local'"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $uid = (int) ($row['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $this->p7ClearUserBranches($uid);
            $this->db->prepare('DELETE FROM rateb_user_roles WHERE user_id = :uid')->execute(['uid' => $uid]);
            $this->db->prepare('DELETE FROM rateb_users WHERE id = :uid')->execute(['uid' => $uid]);
        }
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
                    abs($assetAdj - $liabAdj) < 0.01 || ($assetAdj === 0.0 && $liabAdj === 0.0)
                );
            } catch (\Throwable $e) {
                $tests[] = $this->test('Elimination adjustments run', false, $e->getMessage());
            }
            try {
                $svc = new BranchFinancialReportingService();
                if (method_exists($svc, 'consolidatedTrialBalance')) {
                    $tb = $svc->consolidatedTrialBalance($companyId);
                    $tests[] = $this->test('Trial balance returns array', is_array($tb));
                } elseif (method_exists($svc, 'trialBalance')) {
                    $tb = $svc->trialBalance($companyId, null, null, null);
                    $tests[] = $this->test('Trial balance returns array', is_array($tb));
                } else {
                    $tests[] = $this->test('Trial balance method exists', false, 'consolidatedTrialBalance missing');
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
        $healthFile = RATEB_ROOT . '/public/erp-health.php';
        $healthSrc = is_file($healthFile) ? (string) file_get_contents($healthFile) : '';
        $tests[] = $this->test(
            'Health endpoint has no session impersonation',
            $healthSrc !== '' && strpos($healthSrc, "\$_SESSION['rateb_is_super_admin']") === false
        );
        $barcodeFile = RATEB_ROOT . '/app/services/DocumentBarcodeService.php';
        $barcodeSrc = is_file($barcodeFile) ? (string) file_get_contents($barcodeFile) : '';
        $tests[] = $this->test(
            'Document barcode tenant gate present',
            $barcodeSrc !== '' && strpos($barcodeSrc, 'canViewBarcodeRecord') !== false
        );
        $tests[] = $this->test('SecurityHeaders helper exists', is_file(RATEB_ROOT . '/app/Core/SecurityHeaders.php'));
        $tests[] = $this->test('ApiRateLimiter helper exists', is_file(RATEB_ROOT . '/app/Core/ApiRateLimiter.php'));
        $tests[] = $this->test(
            'Production reset script exists',
            is_file(RATEB_ROOT . '/bin/reset-production.php')
                && is_file(RATEB_ROOT . '/bin/ProductionResetRunner.php')
        );
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
            "SELECT COUNT(*) FROM (
                SELECT company_id FROM rateb_chart_of_accounts
                WHERE code IN ('1350','2150')
                GROUP BY company_id
                HAVING COUNT(DISTINCT code) >= 2
            ) AS seeded"
        )->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['COUNT(*)'] ?? 0) >= 1;
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
