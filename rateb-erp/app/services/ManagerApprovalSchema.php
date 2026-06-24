<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use PDOException;

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
        self::$columnCache[$key] = self::probeColumn($table, $column);
        return self::$columnCache[$key];
    }

    public static function executePendingApproval(
        string $table,
        int $id,
        string $state,
        int $uid,
        int $companyId
    ): void {
        $includeBy = self::hasColumn($table, 'approved_by');
        $includeAt = self::hasColumn($table, 'approved_at');

        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                $built = self::pendingApprovalUpdate($table, $id, $state, $uid, $companyId, $includeBy, $includeAt);
                $db = Database::connection();
                $stmt = $db->prepare($built['sql']);
                $stmt->execute($built['params']);
                if ($stmt->rowCount() < 1) {
                    throw new \RuntimeException(__('manager_approval_already_processed'));
                }
                return;
            } catch (PDOException $e) {
                $missing = self::missingColumnFromError($e);
                if ($missing === 'approved_by' && $includeBy) {
                    $includeBy = false;
                    unset(self::$columnCache[$table . '.approved_by']);
                    continue;
                }
                if ($missing === 'approved_at' && $includeAt) {
                    $includeAt = false;
                    unset(self::$columnCache[$table . '.approved_at']);
                    continue;
                }
                throw DatabaseErrorService::toRuntimeException($e);
            }
        }

        throw new \RuntimeException(__('db_operation_failed'));
    }

    public static function executeResetApproval(string $table, int $id, int $companyId): void
    {
        $includeBy = self::hasColumn($table, 'approved_by');
        $includeAt = self::hasColumn($table, 'approved_at');

        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                $built = self::resetApprovalUpdate($table, $id, $companyId, $includeBy, $includeAt);
                $db = Database::connection();
                $stmt = $db->prepare($built['sql']);
                $stmt->execute($built['params']);
                if ($stmt->rowCount() < 1) {
                    throw new \RuntimeException(__('manager_approval_already_processed'));
                }
                return;
            } catch (PDOException $e) {
                $missing = self::missingColumnFromError($e);
                if ($missing === 'approved_by' && $includeBy) {
                    $includeBy = false;
                    unset(self::$columnCache[$table . '.approved_by']);
                    continue;
                }
                if ($missing === 'approved_at' && $includeAt) {
                    $includeAt = false;
                    unset(self::$columnCache[$table . '.approved_at']);
                    continue;
                }
                throw DatabaseErrorService::toRuntimeException($e);
            }
        }

        throw new \RuntimeException(__('db_operation_failed'));
    }

    /** @return array{sql: string, params: array<string, int|string|null>} */
    public static function pendingApprovalUpdate(
        string $table,
        int $id,
        string $state,
        int $uid,
        int $companyId,
        ?bool $includeApprovedBy = null,
        ?bool $includeApprovedAt = null
    ): array {
        $table = preg_replace('/[^a-z0-9_]/', '', $table) ?? '';
        if ($table === '' || $id < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $includeBy = $includeApprovedBy ?? self::hasColumn($table, 'approved_by');
        $includeAt = $includeApprovedAt ?? self::hasColumn($table, 'approved_at');

        $sets = ['manager_approval = :st'];
        $params = ['st' => $state, 'id' => $id, 'pending' => 'pending'];
        if ($includeBy) {
            $sets[] = 'approved_by = :uid';
            $params['uid'] = $uid > 0 ? $uid : null;
        }
        if ($includeAt) {
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
    public static function resetApprovalUpdate(
        string $table,
        int $id,
        int $companyId,
        ?bool $includeApprovedBy = null,
        ?bool $includeApprovedAt = null
    ): array {
        $table = preg_replace('/[^a-z0-9_]/', '', $table) ?? '';
        if ($table === '' || $id < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $includeBy = $includeApprovedBy ?? self::hasColumn($table, 'approved_by');
        $includeAt = $includeApprovedAt ?? self::hasColumn($table, 'approved_at');

        $sets = ['manager_approval = :st'];
        $params = ['st' => 'pending', 'id' => $id];
        if ($includeBy) {
            $sets[] = 'approved_by = NULL';
        }
        if ($includeAt) {
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

    private static function probeColumn(string $table, string $column): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->query(
                'SHOW COLUMNS FROM `' . $table . '` LIKE ' . $db->quote($column)
            );
            if ($stmt === false) {
                return self::fallbackAssumeColumn($column);
            }
            $exists = $stmt->fetch() !== false;
            $stmt->closeCursor();
            return $exists;
        } catch (\Throwable $e) {
            return self::fallbackAssumeColumn($column);
        }
    }

    private static function fallbackAssumeColumn(string $column): bool
    {
        return in_array($column, ['manager_approval', 'company_id'], true);
    }

    private static function missingColumnFromError(PDOException $e): string
    {
        $raw = $e->getMessage();
        if (preg_match("/Unknown column '([^']+)'/i", $raw, $m)) {
            return (string) $m[1];
        }
        return '';
    }
}
