<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;

final class BranchService
{
    public const MAIN_CODE = 'MB001';

    private static ?bool $branchesTableExists = null;
    private static ?bool $userBranchesTableExists = null;
    private static ?bool $branchesHaveIsMain = null;

    public static function branchesTableExists(): bool
    {
        if (self::$branchesTableExists !== null) {
            return self::$branchesTableExists;
        }
        try {
            $pdo = \Rateb\App\Core\Database::connection();
            $stmt = $pdo->query('SHOW TABLES LIKE \'rateb_branches\'');
            self::$branchesTableExists = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            self::$branchesTableExists = false;
        }
        return self::$branchesTableExists;
    }

    public static function userBranchesTableExists(): bool
    {
        if (self::$userBranchesTableExists !== null) {
            return self::$userBranchesTableExists;
        }
        try {
            $pdo = \Rateb\App\Core\Database::connection();
            $stmt = $pdo->query('SHOW TABLES LIKE \'rateb_user_branches\'');
            self::$userBranchesTableExists = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            self::$userBranchesTableExists = false;
        }
        return self::$userBranchesTableExists;
    }

    public static function branchesHaveIsMainColumn(): bool
    {
        if (!self::branchesTableExists()) {
            return false;
        }
        if (self::$branchesHaveIsMain !== null) {
            return self::$branchesHaveIsMain;
        }
        try {
            $pdo = \Rateb\App\Core\Database::connection();
            $stmt = $pdo->query('SHOW COLUMNS FROM rateb_branches LIKE \'is_main\'');
            self::$branchesHaveIsMain = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            self::$branchesHaveIsMain = false;
        }
        return self::$branchesHaveIsMain;
    }

    public static function branchOrderSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '';
        if (self::branchesHaveIsMainColumn()) {
            return $prefix . 'is_main DESC, ' . $prefix . 'id ASC';
        }
        return $prefix . 'id ASC';
    }

    public function countForCompany(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        return (new Branch())->count(['company_id' => $companyId]);
    }

    public function branchLimit(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $company = (new Company())->find($companyId);
        if (!$company) {
            return 10;
        }
        $limit = (int) ($company['branch_limit'] ?? 0);
        if ($limit > 0) {
            return $limit;
        }
        $planId = (int) ($company['plan_id'] ?? 0);
        if ($planId > 0) {
            $plan = (new Plan())->find($planId);
            $planLimit = (int) ($plan['max_branches'] ?? 0);
            if ($planLimit > 0) {
                return $planLimit;
            }
        }
        return 10;
    }

    public function canAddBranch(int $companyId): bool
    {
        return $this->countForCompany($companyId) < $this->branchLimit($companyId);
    }

    public function normalizeBranchCode(string $code): string
    {
        return trim($code);
    }

    public function isBranchCodeTaken(int $companyId, string $code, int $excludeBranchId = 0): bool
    {
        $code = $this->normalizeBranchCode($code);
        if ($companyId < 1 || $code === '') {
            return false;
        }
        $sql = 'SELECT id FROM rateb_branches WHERE company_id = :cid AND code = :code';
        $params = ['cid' => $companyId, 'code' => $code];
        if ($excludeBranchId > 0) {
            $sql .= ' AND id != :exclude';
            $params['exclude'] = $excludeBranchId;
        }
        $sql .= ' LIMIT 1';

        return (new Branch())->queryOne($sql, $params) !== null;
    }

    /** @return null|string Error slug (branch_last_active) or null when allowed. */
    public function validateBranchStatusForSave(int $companyId, string $currentStatus, string $newStatus): ?string
    {
        if ($newStatus !== 'inactive' || $currentStatus !== 'active' || $companyId < 1) {
            return null;
        }
        $activeCount = (new Branch())->count(['company_id' => $companyId, 'status' => 'active']);
        if ($activeCount <= 1) {
            return 'branch_last_active';
        }

        return null;
    }

    /** @return null|string Error slug (e.g. branch_code_duplicate) or null when valid. */
    public function validateBranchCodeForSave(int $companyId, string $code, int $excludeBranchId = 0): ?string
    {
        $code = $this->normalizeBranchCode($code);
        if ($code === '') {
            return null;
        }
        if ($this->isBranchCodeTaken($companyId, $code, $excludeBranchId)) {
            return 'branch_code_duplicate';
        }

        return null;
    }

    /**
     * Resolve branch code for create: auto-generate when empty, validate manual codes.
     *
     * @return array{ok:bool, code?:string, error?:string}
     */
    public function resolveBranchCodeForCreate(int $companyId, string $rawCode): array
    {
        $code = $this->normalizeBranchCode($rawCode);
        if ($code === '') {
            $n = $this->countForCompany($companyId) + 1;
            $attempts = 0;
            do {
                $code = 'BR' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
                $n++;
                $attempts++;
            } while ($this->isBranchCodeTaken($companyId, $code) && $attempts < 1000);
            if ($this->isBranchCodeTaken($companyId, $code)) {
                return ['ok' => false, 'error' => 'branch_code_duplicate'];
            }

            return ['ok' => true, 'code' => $code];
        }
        $err = $this->validateBranchCodeForSave($companyId, $code, 0);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }

        return ['ok' => true, 'code' => $code];
    }

    /** @return list<array{company_id:int, code:string, branch_ids:array<int>}> */
    public function findDuplicateBranchCodes(): array
    {
        $rows = (new Branch())->query(
            'SELECT company_id, code, GROUP_CONCAT(id ORDER BY id) AS ids, COUNT(*) AS cnt
             FROM rateb_branches
             WHERE code IS NOT NULL AND TRIM(code) <> \'\'
             GROUP BY company_id, code
             HAVING cnt > 1'
        );
        $out = [];
        foreach ($rows as $row) {
            $ids = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) ($row['ids'] ?? ''))
            )));
            $out[] = [
                'company_id' => (int) ($row['company_id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'branch_ids' => $ids,
            ];
        }

        return $out;
    }

    /** @return array{count:int,limit:int} */
    public function stats(int $companyId): array
    {
        $limit = $this->branchLimit($companyId);
        $count = $this->countForCompany($companyId);
        return ['count' => $count, 'limit' => $limit];
    }

    public function defaultBranchId(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $row = (new Branch())->queryOne(
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY '
            . self::branchOrderSql() . ' LIMIT 1',
            ['cid' => $companyId, 'st' => 'active']
        );
        return (int) ($row['id'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function findActiveForPortalByCode(string $companySlug, string $branchCode): ?array
    {
        $companySlug = trim($companySlug);
        $branchCode = trim($branchCode);
        if ($companySlug === '' || $branchCode === '') {
            return null;
        }
        $row = (new Branch())->queryOne(
            'SELECT b.*, c.name AS company_name, c.slug AS company_slug
             FROM rateb_branches b
             INNER JOIN rateb_companies c ON c.id = b.company_id
             WHERE b.code = :code AND b.status = :st
               AND (c.slug = :slug OR CAST(c.id AS CHAR) = :slug)
             LIMIT 1',
            ['slug' => $companySlug, 'code' => $branchCode, 'st' => 'active']
        );
        return $row ?: null;
    }

    public function resolvePortalBranchIdFromRequest(): int
    {
        $branchId = (int) ($_GET['branch_id'] ?? $_POST['branch_id'] ?? 0);
        if ($branchId > 0) {
            return $branchId;
        }
        $companySlug = trim((string) ($_GET['company'] ?? $_POST['company'] ?? ''));
        $branchCode = trim((string) ($_GET['branch'] ?? $_POST['branch'] ?? ''));
        if ($companySlug === '' || $branchCode === '') {
            return 0;
        }
        $row = $this->findActiveForPortalByCode($companySlug, $branchCode);
        return $row ? (int) ($row['id'] ?? 0) : 0;
    }

    /** @return array<string, mixed>|null */
    public function findActiveForPortal(int $branchId): ?array
    {
        if ($branchId < 1) {
            return null;
        }
        $row = (new Branch())->queryOne(
            'SELECT b.*, c.name AS company_name, c.slug AS company_slug
             FROM rateb_branches b
             INNER JOIN rateb_companies c ON c.id = b.company_id
             WHERE b.id = :id AND b.status = :st LIMIT 1',
            ['id' => $branchId, 'st' => 'active']
        );
        return $row ?: null;
    }

    public function userMayUsePortalBranch(int $userId, int $branchId, int $companyId): bool
    {
        if ($userId < 1 || $branchId < 1) {
            return false;
        }
        $branch = $this->findActiveForPortal($branchId);
        if (!$branch) {
            return false;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return (int) ($branch['company_id'] ?? 0) > 0;
        }
        if ($companyId < 1 || (int) ($branch['company_id'] ?? 0) !== $companyId) {
            return false;
        }
        $assigned = $this->getUserBranchIds($userId);
        if ($assigned === []) {
            return true;
        }
        return in_array($branchId, $assigned, true);
    }

    public function ensureMainBranch(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $existing = (new Branch())->queryOne(
            'SELECT id FROM rateb_branches WHERE company_id = :cid ORDER BY ' . self::branchOrderSql() . ' LIMIT 1',
            ['cid' => $companyId]
        );
        if ($existing) {
            return (int) $existing['id'];
        }
        $prev = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            $data = [
                'company_id' => $companyId,
                'name' => __('main_branch'),
                'code' => self::MAIN_CODE,
                'status' => 'active',
            ];
            if (self::branchesHaveIsMainColumn()) {
                $data['is_main'] = 1;
            }
            $id = (new Branch())->create($data);
            return $id;
        } finally {
            TenantContext::setCompanyId($prev);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $isMainSelect = self::branchesHaveIsMainColumn() ? 'is_main' : '0 AS is_main';
        return (new Branch())->query(
            'SELECT id, name, code, ' . $isMainSelect . ' FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY '
            . self::branchOrderSql() . ', name ASC',
            ['cid' => $companyId, 'st' => 'active']
        );
    }

    /** @return array<int, array{value:int,label:string}> */
    public function activeOptions(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = (new Branch())->query(
            'SELECT id, name, code FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY '
            . self::branchOrderSql() . ', name ASC',
            ['cid' => $companyId, 'st' => 'active']
        );
        $out = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $label = $code . ' — ' . $label;
            }
            $out[] = ['value' => (int) $row['id'], 'label' => $label];
        }
        if (function_exists('rateb_branch_access_all') && !rateb_branch_access_all()) {
            $allowed = array_flip(rateb_allowed_branch_ids());
            $out = array_values(array_filter(
                $out,
                static fn (array $opt): bool => isset($allowed[(int) ($opt['value'] ?? 0)])
            ));
        }
        return $out;
    }

    /** @return array<int, array<int, array{value:int,label:string}>> */
    public function optionsByCompany(): array
    {
        $rows = (new Branch())->query(
            'SELECT id, company_id, name, code FROM rateb_branches WHERE status = :st ORDER BY company_id ASC, '
            . self::branchOrderSql() . ', name ASC',
            ['st' => 'active']
        );
        $out = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['company_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $label = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $label = $code . ' — ' . $label;
            }
            $out[$cid][] = ['value' => (int) $row['id'], 'label' => $label];
        }
        return $out;
    }

    /** @return array<int, int> */
    public function getUserBranchIds(int $userId): array
    {
        if ($userId < 1 || !self::userBranchesTableExists()) {
            return [];
        }
        $rows = (new Branch())->query(
            'SELECT branch_id FROM rateb_user_branches WHERE user_id = :uid ORDER BY branch_id ASC',
            ['uid' => $userId]
        );
        return array_map('intval', array_column($rows, 'branch_id'));
    }

    /** @param array<int, int|string> $branchIds */
    public function syncUserBranches(int $userId, int $companyId, array $branchIds): void
    {
        if ($userId < 1 || !self::userBranchesTableExists()) {
            return;
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_user_branches WHERE user_id = :uid')->execute(['uid' => $userId]);
        if ($companyId < 1 || $branchIds === []) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $branchIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $check = $db->prepare(
            'SELECT id FROM rateb_branches WHERE company_id = ? AND status = ? AND id IN (' . $placeholders . ')'
        );
        $check->execute(array_merge([$companyId, 'active'], $ids));
        $valid = array_map('intval', array_column($check->fetchAll(\PDO::FETCH_ASSOC), 'id'));
        $stmt = $db->prepare('INSERT INTO rateb_user_branches (user_id, branch_id) VALUES (:uid, :bid)');
        foreach ($valid as $bid) {
            $stmt->execute(['uid' => $userId, 'bid' => $bid]);
        }
    }
}
