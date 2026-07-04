<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;

/** Platform super-admin branch CRUD on rateb.sa (same DB as ERP companies list). */
final class PlatformCompanyBranchService
{
    private const BRANCH_EDITABLE_FIELDS = [
        'name',
        'code',
        'phone',
        'email',
        'address',
        'map_url',
        'status',
    ];
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
            'SELECT b.id, b.name, b.code, b.status, b.is_main, b.address, b.phone, b.email, b.map_url, b.company_id,
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

    /** @return array{ok:bool, error?:string, noop?:bool} */
    public static function setBranchStatus(int $companyId, int $branchId, string $status): array
    {
        if ($companyId < 1 || $branchId < 1 || !in_array($status, ['active', 'inactive'], true)) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        self::bootstrapSuperAdmin();
        TenantContext::setCompanyId($companyId);
        $row = (new Branch())->queryOne(
            'SELECT id, status FROM rateb_branches WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $branchId, 'cid' => $companyId]
        );
        if (!$row) {
            return ['ok' => false, 'error' => 'record_not_found'];
        }
        $current = (string) ($row['status'] ?? 'active');
        if ($current === $status) {
            return ['ok' => true, 'noop' => true];
        }
        $statusErr = (new BranchService())->validateBranchStatusForSave($companyId, $current, $status);
        if ($statusErr !== null) {
            return ['ok' => false, 'error' => $statusErr];
        }
        (new Branch())->update($branchId, ['status' => $status]);
        (new AuditService())->log('toggle_status', 'branches', $branchId, [
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'previous_status' => $current,
            'new_status' => $status,
            'actor_user_id' => $_SESSION['rateb_user_id'] ?? ($_SESSION['control_user_id'] ?? null),
            'timestamp' => date('c'),
        ]);

        return ['ok' => true];
    }

    /** @return array{ok:bool, branch?:array<string,mixed>, error?:string} */
    public static function updateBranch(int $companyId, int $branchId, array $data): array
    {
        if ($companyId < 1 || $branchId < 1) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        self::bootstrapSuperAdmin();
        TenantContext::setCompanyId($companyId);
        $row = (new Branch())->queryOne(
            'SELECT * FROM rateb_branches WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $branchId, 'cid' => $companyId]
        );
        if (!$row) {
            return ['ok' => false, 'error' => 'record_not_found'];
        }
        $input = self::whitelistBranchUpdateData($data);
        $svc = new BranchService();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'branch_name_required'];
        }
        $codeInput = array_key_exists('code', $input)
            ? $svc->normalizeBranchCode((string) $input['code'])
            : '';
        $code = $codeInput !== '' ? $codeInput : (string) ($row['code'] ?? '');
        $codeErr = $svc->validateBranchCodeForSave($companyId, $code, $branchId);
        if ($codeErr !== null) {
            return ['ok' => false, 'error' => $codeErr];
        }
        $currentStatus = (string) ($row['status'] ?? 'active');
        $status = array_key_exists('status', $input)
            ? (string) $input['status']
            : $currentStatus;
        if (!in_array($status, ['active', 'inactive'], true)) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        $statusErr = $svc->validateBranchStatusForSave($companyId, $currentStatus, $status);
        if ($statusErr !== null) {
            return ['ok' => false, 'error' => $statusErr];
        }
        $update = [
            'name' => $name,
            'code' => $code,
            'phone' => trim((string) ($input['phone'] ?? $row['phone'] ?? '')),
            'email' => trim((string) ($input['email'] ?? $row['email'] ?? '')),
            'address' => trim((string) ($input['address'] ?? $row['address'] ?? '')),
            'map_url' => trim((string) ($input['map_url'] ?? $row['map_url'] ?? '')),
            'status' => $status,
        ];
        if ($update['map_url'] !== '' && function_exists('rateb_external_url')) {
            $update['map_url'] = rateb_external_url($update['map_url']);
        }
        $changed = [];
        foreach ($update as $field => $value) {
            $old = (string) ($row[$field] ?? '');
            if ((string) $value !== $old) {
                $changed[$field] = ['from' => $old, 'to' => (string) $value];
            }
        }
        if ($changed === []) {
            return ['ok' => true, 'branch' => $row];
        }
        try {
            (new Branch())->update($branchId, $update);
            $fresh = (new Branch())->queryOne(
                'SELECT b.*, c.name AS company_name, c.slug AS company_slug
                 FROM rateb_branches b
                 INNER JOIN rateb_companies c ON c.id = b.company_id
                 WHERE b.id = :id AND b.company_id = :cid LIMIT 1',
                ['id' => $branchId, 'cid' => $companyId]
            );
            if (!$fresh) {
                return ['ok' => false, 'error' => 'update_failed'];
            }
            (new AuditService())->log('update', 'branches', $branchId, [
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'changed' => $changed,
                'actor_user_id' => $_SESSION['rateb_user_id'] ?? ($_SESSION['control_user_id'] ?? null),
                'timestamp' => date('c'),
            ]);

            return ['ok' => true, 'branch' => $fresh];
        } catch (\Throwable $e) {
            error_log('PlatformCompanyBranchService::updateBranch: ' . $e->getMessage());
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

    /** @return array<string, mixed> */
    private static function whitelistBranchUpdateData(array $data): array
    {
        $out = [];
        foreach (self::BRANCH_EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $out[$field] = $data[$field];
            }
        }

        return $out;
    }

    private static function bootstrapSuperAdmin(): void
    {
        TenantContext::setSuperAdmin(true);
    }
}
