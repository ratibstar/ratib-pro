<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;

/** Platform super-admin branch CRUD on rateb.sa (same DB as ERP companies list). */
final class PlatformCompanyBranchService
{
    public static function assertEnabled(): void
    {
        if (!function_exists('rateb_platform_branch_manage_enabled') || !rateb_platform_branch_manage_enabled()) {
            throw new \RuntimeException(__('invalid_request'));
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function listCompanies(): array
    {
        self::bootstrapSuperAdmin();
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT c.id, c.name, c.slug, c.status, c.branch_limit, c.plan_id,
                    COUNT(b.id) AS branch_count
             FROM rateb_companies c
             LEFT JOIN rateb_branches b ON b.company_id = c.id
             GROUP BY c.id, c.name, c.slug, c.status, c.branch_limit, c.plan_id
             ORDER BY c.id DESC'
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $svc = new BranchService();
        foreach ($rows as &$row) {
            $cid = (int) ($row['id'] ?? 0);
            $stats = $svc->stats($cid);
            $row['branch_count'] = (int) ($stats['count'] ?? $row['branch_count'] ?? 0);
            $row['branch_limit_effective'] = (int) ($stats['limit'] ?? 0);
            $row['can_add_branch'] = $svc->canAddBranch($cid);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function companyRow(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        foreach (self::listCompanies() as $row) {
            if ((int) ($row['id'] ?? 0) === $companyId) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function companyBranches(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        self::bootstrapSuperAdmin();
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT b.id, b.name, b.code, b.status, b.is_main, b.address, b.phone, b.email, b.company_id,
                    c.name AS company_name, c.slug AS company_slug
             FROM rateb_branches b
             INNER JOIN rateb_companies c ON c.id = b.company_id
             WHERE b.company_id = :cid
             ORDER BY b.is_main DESC, b.name ASC'
        );
        $stmt->execute(['cid' => $companyId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public static function setBranchLimit(int $companyId, int $limit): bool
    {
        if ($companyId < 1) {
            return false;
        }
        self::bootstrapSuperAdmin();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE rateb_companies SET branch_limit = :lim WHERE id = :id');

        return $stmt->execute(['lim' => max(0, $limit), 'id' => $companyId]);
    }

    /** @return array{ok:bool, branch?:array<string,mixed>, portal_url?:string, error?:string} */
    public static function createBranch(int $companyId, array $data): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'invalid_company'];
        }
        self::bootstrapSuperAdmin();
        TenantContext::setCompanyId($companyId);
        $svc = new BranchService();
        $svc->ensureMainBranch($companyId);
        if (!$svc->canAddBranch($companyId)) {
            return ['ok' => false, 'error' => 'branch_limit_reached'];
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'branch_name_required'];
        }
        $resolved = $svc->resolveBranchCodeForCreate($companyId, (string) ($data['code'] ?? ''));
        if (empty($resolved['ok'])) {
            return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'branch_code_duplicate')];
        }
        $code = (string) ($resolved['code'] ?? '');
        try {
            $id = (new Branch())->create([
                'name' => $name,
                'code' => $code,
                'address' => trim((string) ($data['address'] ?? '')),
                'phone' => trim((string) ($data['phone'] ?? '')),
                'email' => trim((string) ($data['email'] ?? '')),
                'map_url' => trim((string) ($data['map_url'] ?? '')),
                'status' => 'active',
                'is_main' => 0,
            ]);
            $row = (new Branch())->queryOne(
                'SELECT b.*, c.name AS company_name, c.slug AS company_slug
                 FROM rateb_branches b
                 INNER JOIN rateb_companies c ON c.id = b.company_id
                 WHERE b.id = :id LIMIT 1',
                ['id' => $id]
            );
            if (!$row) {
                return ['ok' => false, 'error' => 'create_failed'];
            }

            return [
                'ok' => true,
                'branch' => $row,
                'portal_url' => function_exists('rateb_branch_portal_url')
                    ? rateb_branch_portal_url($id, $row)
                    : '',
            ];
        } catch (\Throwable $e) {
            error_log('PlatformCompanyBranchService::createBranch: ' . $e->getMessage());
            $raw = $e->getMessage();
            if ($e->getPrevious() instanceof \Throwable) {
                $raw .= ' ' . $e->getPrevious()->getMessage();
            }
            if (strpos($raw, '1062') !== false || stripos($raw, 'Duplicate entry') !== false) {
                return ['ok' => false, 'error' => 'branch_code_duplicate'];
            }

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function setBranchStatus(int $branchId, string $status): bool
    {
        if ($branchId < 1 || !in_array($status, ['active', 'inactive'], true)) {
            return false;
        }
        self::bootstrapSuperAdmin();

        return (new Branch())->update($branchId, ['status' => $status]);
    }

    private static function bootstrapSuperAdmin(): void
    {
        TenantContext::setSuperAdmin(true);
    }
}
