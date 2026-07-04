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

    /** @deprecated Use listBranches() — returns items only for legacy callers. */
    public static function companyBranches(int $companyId): array
    {
        $result = self::listBranches($companyId, ['per_page' => 500, 'page' => 1, 'archive' => 'all']);

        return $result['items'];
    }

    /**
     * Single production branch listing (search, filters, sort, pagination).
     *
     * @param array<string, mixed> $opts q, status, branch_type, archive, sort, dir, page, per_page
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public static function listBranches(int $companyId, array $opts = []): array
    {
        if ($companyId < 1) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 1];
        }
        self::bootstrapSuperAdmin();
        $normalized = self::normalizeListOptions($opts);
        [$where, $params] = self::buildListWhere($companyId, $normalized);
        $orderSql = self::buildListOrderSql($normalized);
        $pdo = Database::connection();
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM rateb_branches b WHERE ' . $where
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);
        $perPage = (int) $normalized['per_page'];
        $page = (int) $normalized['page'];
        $pages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;
        $archiveCols = BranchService::branchesHaveArchiveColumns()
            ? 'b.is_archived, b.archived_at,'
            : '0 AS is_archived, NULL AS archived_at,';
        $sql = 'SELECT b.id, b.name, b.code, b.status, b.is_main, ' . $archiveCols . '
                b.address, b.phone, b.email, b.map_url, b.company_id, b.created_at,
                c.name AS company_name, c.slug AS company_slug
                FROM rateb_branches b
                INNER JOIN rateb_companies c ON c.id = b.company_id
                WHERE ' . $where . ' ORDER BY ' . $orderSql
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @param array<string, mixed> $input */
    public static function listOptionsFromRequest(array $input): array
    {
        return [
            'q' => trim((string) ($input['q'] ?? '')),
            'status' => trim((string) ($input['status'] ?? '')),
            'branch_type' => trim((string) ($input['branch_type'] ?? '')),
            'archive' => trim((string) ($input['archive'] ?? '')),
            'sort' => trim((string) ($input['sort'] ?? '')),
            'dir' => trim((string) ($input['dir'] ?? '')),
            'page' => (int) ($input['page'] ?? 1),
            'per_page' => (int) ($input['per_page'] ?? 0),
        ];
    }

    /** @return array{ok:bool, error?:string, noop?:bool} */
    public static function archiveBranch(int $companyId, int $branchId): array
    {
        if ($companyId < 1 || $branchId < 1) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        if (!BranchService::branchesHaveArchiveColumns()) {
            return ['ok' => false, 'error' => 'db_schema_outdated'];
        }
        self::bootstrapSuperAdmin();
        TenantContext::setCompanyId($companyId);
        $row = self::loadBranchForCompany($companyId, $branchId);
        if (!$row) {
            return ['ok' => false, 'error' => 'record_not_found'];
        }
        if ((int) ($row['is_archived'] ?? 0) === 1) {
            return ['ok' => true, 'noop' => true];
        }
        $err = (new BranchService())->validateBranchArchiveForSave($companyId, $row);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $now = date('Y-m-d H:i:s');
        (new Branch())->update($branchId, ['is_archived' => 1, 'archived_at' => $now]);
        (new AuditService())->log('archive', 'branches', $branchId, [
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'previous_status' => (string) ($row['status'] ?? ''),
            'is_archived' => 1,
            'archived_at' => $now,
            'actor_user_id' => $_SESSION['rateb_user_id'] ?? ($_SESSION['control_user_id'] ?? null),
            'timestamp' => date('c'),
        ]);

        return ['ok' => true];
    }

    /** @return array{ok:bool, error?:string, noop?:bool} */
    public static function restoreBranch(int $companyId, int $branchId): array
    {
        if ($companyId < 1 || $branchId < 1) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        if (!BranchService::branchesHaveArchiveColumns()) {
            return ['ok' => false, 'error' => 'db_schema_outdated'];
        }
        self::bootstrapSuperAdmin();
        TenantContext::setCompanyId($companyId);
        $row = self::loadBranchForCompany($companyId, $branchId);
        if (!$row) {
            return ['ok' => false, 'error' => 'record_not_found'];
        }
        if ((int) ($row['is_archived'] ?? 0) !== 1) {
            return ['ok' => true, 'noop' => true];
        }
        (new Branch())->update($branchId, ['is_archived' => 0, 'archived_at' => null]);
        (new AuditService())->log('restore', 'branches', $branchId, [
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'previous_status' => (string) ($row['status'] ?? ''),
            'is_archived' => 0,
            'actor_user_id' => $_SESSION['rateb_user_id'] ?? ($_SESSION['control_user_id'] ?? null),
            'timestamp' => date('c'),
        ]);

        return ['ok' => true];
    }

    /**
     * @param array<int, int|string> $branchIds
     * @return array{ok:bool, success:int, failed:int, errors:array<int,string>}
     */
    public static function bulkBranchAction(int $companyId, array $branchIds, string $action): array
    {
        $action = strtolower(trim($action));
        $allowed = ['archive', 'restore', 'enable', 'disable'];
        if ($companyId < 1 || !in_array($action, $allowed, true)) {
            return ['ok' => false, 'success' => 0, 'failed' => 0, 'errors' => ['invalid_request']];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $branchIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return ['ok' => false, 'success' => 0, 'failed' => 0, 'errors' => ['bulk_none_selected']];
        }
        $success = 0;
        $failed = 0;
        $errors = [];
        foreach ($ids as $branchId) {
            $result = match ($action) {
                'archive' => self::archiveBranch($companyId, $branchId),
                'restore' => self::restoreBranch($companyId, $branchId),
                'enable' => self::setBranchStatus($companyId, $branchId, 'active'),
                'disable' => self::setBranchStatus($companyId, $branchId, 'inactive'),
                default => ['ok' => false, 'error' => 'invalid_request'],
            };
            if (!empty($result['ok'])) {
                $success++;
            } else {
                $failed++;
                $err = (string) ($result['error'] ?? 'invalid_request');
                $errors[$branchId] = $err;
            }
        }

        if ($success > 0) {
            (new AuditService())->log('bulk_' . $action, 'branches', $companyId, [
                'company_id' => $companyId,
                'action' => $action,
                'branch_ids' => $ids,
                'success' => $success,
                'failed' => $failed,
                'actor_user_id' => $_SESSION['rateb_user_id'] ?? ($_SESSION['control_user_id'] ?? null),
                'timestamp' => date('c'),
            ]);
        }

        return ['ok' => $failed === 0, 'success' => $success, 'failed' => $failed, 'errors' => $errors];
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
            (new AuditService())->log('create', 'branches', $id, [
                'branch_id' => $id,
                'company_id' => $companyId,
                'branch_code' => $code,
                'branch_name' => $name,
                'actor_user_id' => $_SESSION['rateb_user_id'] ?? ($_SESSION['control_user_id'] ?? null),
                'timestamp' => date('c'),
            ]);

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
        $row = self::loadBranchForCompany($companyId, $branchId);
        if (!$row) {
            return ['ok' => false, 'error' => 'record_not_found'];
        }
        if (BranchService::branchesHaveArchiveColumns() && (int) ($row['is_archived'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'branch_archived'];
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
        if (BranchService::branchesHaveArchiveColumns() && (int) ($row['is_archived'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'branch_archived'];
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

    /** @return array<string, mixed>|null */
    private static function loadBranchForCompany(int $companyId, int $branchId): ?array
    {
        return (new Branch())->queryOne(
            'SELECT * FROM rateb_branches WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $branchId, 'cid' => $companyId]
        ) ?: null;
    }

    /** @param array<string, mixed> $opts @return array<string, mixed> */
    private static function normalizeListOptions(array $opts): array
    {
        $defaultPerPage = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $perPage = (int) ($opts['per_page'] ?? 0);
        if ($perPage < 1) {
            $perPage = $defaultPerPage;
        }
        $perPage = max(5, min(100, $perPage));
        $page = max(1, (int) ($opts['page'] ?? 1));
        $sort = (string) ($opts['sort'] ?? 'name');
        if (!in_array($sort, ['name', 'code', 'status', 'created_at'], true)) {
            $sort = 'name';
        }
        $dir = strtolower((string) ($opts['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        return [
            'q' => trim((string) ($opts['q'] ?? '')),
            'status' => trim((string) ($opts['status'] ?? '')),
            'branch_type' => trim((string) ($opts['branch_type'] ?? '')),
            'archive' => trim((string) ($opts['archive'] ?? '')),
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @param array<string, mixed> $opts @return array{0:string,1:array<string,mixed>} */
    private static function buildListWhere(int $companyId, array $opts): array
    {
        $where = 'b.company_id = :cid';
        $params = ['cid' => $companyId];
        $archive = (string) ($opts['archive'] ?? '');
        if (BranchService::branchesHaveArchiveColumns()) {
            if ($archive === 'archived') {
                $where .= ' AND b.is_archived = 1';
            } elseif ($archive !== 'all') {
                $where .= ' AND b.is_archived = 0';
            }
        }
        $status = (string) ($opts['status'] ?? '');
        if (in_array($status, ['active', 'inactive'], true)) {
            $where .= ' AND b.status = :st';
            $params['st'] = $status;
        }
        $branchType = (string) ($opts['branch_type'] ?? '');
        if ($branchType === 'main') {
            $where .= ' AND b.is_main = 1';
        } elseif ($branchType === 'child') {
            $where .= ' AND b.is_main = 0';
        }
        $q = (string) ($opts['q'] ?? '');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= ' AND (b.name LIKE :q OR b.code LIKE :q OR b.phone LIKE :q OR b.email LIKE :q';
            $params['q'] = $like;
            if (in_array($q, ['active', 'inactive'], true)) {
                $where .= ' OR b.status = :qst';
                $params['qst'] = $q;
            }
            $where .= ')';
        }

        return [$where, $params];
    }

    /** @param array<string, mixed> $opts */
    private static function buildListOrderSql(array $opts): string
    {
        $sort = (string) ($opts['sort'] ?? 'name');
        $dir = (string) ($opts['dir'] ?? 'ASC');
        $col = match ($sort) {
            'code' => 'b.code',
            'status' => 'b.status',
            'created_at' => 'b.created_at',
            default => 'b.name',
        };

        return 'b.is_main DESC, ' . $col . ' ' . $dir . ', b.id ASC';
    }
}
