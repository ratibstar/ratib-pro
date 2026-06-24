<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDOException;

/** Detect manager-approval audit columns and build safe UPDATE statements. */
final class ManagerApprovalSchema
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function hasColumn(string $table, string $column): bool
    {
        $table = self::sanitizeTable($table);
        $column = self::sanitizeColumn($column);
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
        self::ensureApprovalColumns($table);

        $includeBy = self::hasColumn($table, 'approved_by');
        $includeAt = self::hasColumn($table, 'approved_at');
        $last = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            try {
                if (!self::hasColumn($table, 'manager_approval')) {
                    throw new \RuntimeException(__('db_schema_outdated') . ' [manager_approval]');
                }
                $built = self::pendingApprovalUpdate($table, $id, $state, $uid, $companyId, $includeBy, $includeAt);
                $db = Database::connection();
                $stmt = $db->prepare($built['sql']);
                $stmt->execute($built['params']);
                if ($stmt->rowCount() < 1) {
                    throw new \RuntimeException(__('manager_approval_already_processed'));
                }
                return;
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (PDOException $e) {
                $last = $e;
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
                if ($missing !== '') {
                    self::tryAddColumn($table, $missing);
                    unset(self::$columnCache[$table . '.' . $missing]);
                    continue;
                }
                throw DatabaseErrorService::toRuntimeException($e);
            }
        }

        if ($last instanceof PDOException) {
            throw DatabaseErrorService::toRuntimeException($last);
        }
        throw new \RuntimeException(__('db_operation_failed'));
    }

    public static function executeResetApproval(string $table, int $id, int $companyId): void
    {
        self::ensureApprovalColumns($table);

        $includeBy = self::hasColumn($table, 'approved_by');
        $includeAt = self::hasColumn($table, 'approved_at');
        $last = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            try {
                if (!self::hasColumn($table, 'manager_approval')) {
                    throw new \RuntimeException(__('db_schema_outdated') . ' [manager_approval]');
                }
                $built = self::resetApprovalUpdate($table, $id, $companyId, $includeBy, $includeAt);
                $db = Database::connection();
                $stmt = $db->prepare($built['sql']);
                $stmt->execute($built['params']);
                if ($stmt->rowCount() < 1) {
                    throw new \RuntimeException(__('manager_approval_already_processed'));
                }
                return;
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (PDOException $e) {
                $last = $e;
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
                if ($missing !== '') {
                    self::tryAddColumn($table, $missing);
                    unset(self::$columnCache[$table . '.' . $missing]);
                    continue;
                }
                throw DatabaseErrorService::toRuntimeException($e);
            }
        }

        if ($last instanceof PDOException) {
            throw DatabaseErrorService::toRuntimeException($last);
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
        $table = self::sanitizeTable($table);
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
        if ($companyId > 0 && !self::isOversightSuperAdmin() && self::hasColumn($table, 'company_id')) {
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
        $table = self::sanitizeTable($table);
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
        if ($companyId > 0 && !self::isOversightSuperAdmin() && self::hasColumn($table, 'company_id')) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    public static function ensureApprovalColumns(string $table): void
    {
        $table = self::sanitizeTable($table);
        if ($table === '') {
            return;
        }
        if (!self::hasColumn($table, 'manager_approval')) {
            self::tryAddColumn($table, 'manager_approval');
        }
        if (!self::hasColumn($table, 'approved_by')) {
            self::tryAddColumn($table, 'approved_by');
        }
        if (!self::hasColumn($table, 'approved_at')) {
            self::tryAddColumn($table, 'approved_at');
        }
    }

    public static function ensureContractApprovalStatus(): void
    {
        if (self::hasColumn('rateb_contracts', 'approval_status')) {
            return;
        }
        self::tryAddColumn('rateb_contracts', 'approval_status');
    }

    private static function tryAddColumn(string $table, string $column): void
    {
        $table = self::sanitizeTable($table);
        $column = self::sanitizeColumn($column);
        if ($table === '' || $column === '') {
            return;
        }
        $ddl = match ($column) {
            'manager_approval' => 'ADD COLUMN manager_approval ENUM(\'pending\',\'approved\',\'rejected\') NOT NULL DEFAULT \'pending\'',
            'approved_by' => 'ADD COLUMN approved_by INT UNSIGNED NULL',
            'approved_at' => 'ADD COLUMN approved_at DATETIME NULL',
            'approval_status' => 'ADD COLUMN approval_status ENUM(\'draft\',\'pending\',\'approved\',\'rejected\') NOT NULL DEFAULT \'draft\'',
            default => '',
        };
        if ($ddl === '') {
            return;
        }
        try {
            Database::connection()->exec('ALTER TABLE `' . $table . '` ' . $ddl);
            self::$columnCache[$table . '.' . $column] = true;
        } catch (\Throwable $e) {
            self::$columnCache[$table . '.' . $column] = false;
        }
    }

    private static function isOversightSuperAdmin(): bool
    {
        if (\Rateb\App\Core\TenantContext::isSuperAdmin()) {
            return true;
        }
        return function_exists('rateb_is_super_admin') && rateb_is_super_admin();
    }

    private static function sanitizeTable(string $table): string
    {
        return preg_replace('/[^a-z0-9_]/', '', $table) ?? '';
    }

    private static function sanitizeColumn(string $column): string
    {
        return preg_replace('/[^a-z0-9_]/', '', $column) ?? '';
    }

    private static function probeColumn(string $table, string $column): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->query(
                'SHOW COLUMNS FROM `' . $table . '` LIKE ' . $db->quote($column)
            );
            if ($stmt === false) {
                return false;
            }
            $exists = $stmt->fetch() !== false;
            $stmt->closeCursor();
            return $exists;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function missingColumnFromError(PDOException $e): string
    {
        $raw = $e->getMessage();
        if (preg_match("/Unknown column '([^']+)'/i", $raw, $m)) {
            return self::sanitizeColumn((string) $m[1]);
        }
        return '';
    }
}
