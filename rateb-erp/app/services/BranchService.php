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
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY is_main DESC, id ASC LIMIT 1',
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
            'SELECT id FROM rateb_branches WHERE company_id = :cid ORDER BY is_main DESC, id ASC LIMIT 1',
            ['cid' => $companyId]
        );
        if ($existing) {
            return (int) $existing['id'];
        }
        $prev = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            $id = (new Branch())->create([
                'company_id' => $companyId,
                'name' => __('main_branch'),
                'code' => self::MAIN_CODE,
                'status' => 'active',
                'is_main' => 1,
            ]);
            return $id;
        } finally {
            TenantContext::setCompanyId($prev);
        }
    }

    /** @return array<int, array{value:int,label:string}> */
    public function activeOptions(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = (new Branch())->query(
            'SELECT id, name, code FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY is_main DESC, name ASC',
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
            'SELECT id, company_id, name, code FROM rateb_branches WHERE status = :st ORDER BY company_id ASC, is_main DESC, name ASC',
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
        if ($userId < 1) {
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
        if ($userId < 1) {
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
