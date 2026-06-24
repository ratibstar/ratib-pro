<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

/** Detect manager-approval audit columns and build safe UPDATE statements. */
final class ManagerApprovalSchema
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function hasColumn(string $table, string $column): bool
    {
        $table = preg_replace('/[^a-z0-9_]/', '', $table) ?? '';
        $column = preg_replace('/[^a-z0-9_]/', '', $column) ?? '';
        if ($table === '' || $column === '') {
            return false;
        }
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
            );
            $stmt->execute(['tbl' => $table, 'col' => $column]);
            self::$columnCache[$key] = (int) ($stmt->fetchColumn() ?: 0) > 0;
        } catch (\Throwable $e) {
            self::$columnCache[$key] = false;
        }
        return self::$columnCache[$key];
    }

  /** @return array{sql: string, params: array<string, int|string|null>} */
    public static function pendingApprovalUpdate(
        string $table,
        int $id,
        string $state,
        int $uid,
        int $companyId
    ): array {
        $table = preg_replace('/[^a-z0-9_]/', '', $table) ?? '';
        if ($table === '' || $id < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if (!self::hasColumn($table, 'manager_approval')) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }

        $sets = ['manager_approval = :st'];
        $params = ['st' => $state, 'id' => $id, 'pending' => 'pending'];
        if (self::hasColumn($table, 'approved_by')) {
            $sets[] = 'approved_by = :uid';
            $params['uid'] = $uid > 0 ? $uid : null;
        }
        if (self::hasColumn($table, 'approved_at')) {
            $sets[] = 'approved_at = NOW()';
        }

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id AND manager_approval = :pending', $table, implode(', ', $sets));
        if ($companyId > 0 && !TenantContext::isSuperAdmin() && self::hasColumn($table, 'company_id')) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }

        return ['sql' => $sql, 'params' => $params];
    }

  /** @return array{sql: string, params: array<string, int|string|null>} */
    public static function resetApprovalUpdate(string $table, int $id, int $companyId): array
    {
        $table = preg_replace('/[^a-z0-9_]/', '', $table) ?? '';
        if ($table === '' || $id < 1 || !self::hasColumn($table, 'manager_approval')) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $sets = ['manager_approval = :st'];
        $params = ['st' => 'pending', 'id' => $id];
        if (self::hasColumn($table, 'approved_by')) {
            $sets[] = 'approved_by = NULL';
        }
        if (self::hasColumn($table, 'approved_at')) {
            $sets[] = 'approved_at = NULL';
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = :id AND manager_approval IN (\'approved\', \'rejected\')',
            $table,
            implode(', ', $sets)
        );
        if ($companyId > 0 && !TenantContext::isSuperAdmin() && self::hasColumn($table, 'company_id')) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }

        return ['sql' => $sql, 'params' => $params];
    }
}
