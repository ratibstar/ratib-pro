<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDOException;

/** Manager-approval UPDATE helpers (oversight-safe). */
final class ManagerApprovalSchema
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function clearCache(): void
    {
        self::$columnCache = [];
    }

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
        self::runCorePendingUpdate($table, $id, $state, $companyId);
        self::applyAuditColumns($table, $id, $uid);
    }

    public static function executeResetApproval(string $table, int $id, int $companyId): void
    {
        self::ensureApprovalColumns($table);
        self::runCoreResetUpdate($table, $id, $companyId);
        self::clearAuditColumns($table, $id);
    }

    public static function ensureApprovalColumns(string $table): void
    {
        $table = self::sanitizeTable($table);
        if ($table === '') {
            return;
        }
        foreach (['manager_approval', 'approved_by', 'approved_at'] as $column) {
            if (!self::hasColumn($table, $column)) {
                self::tryAddColumn($table, $column);
            }
        }
    }

    public static function ensureContractApprovalStatus(): void
    {
        if (!self::hasColumn('rateb_contracts', 'approval_status')) {
            self::tryAddColumn('rateb_contracts', 'approval_status');
        }
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
        return self::corePendingUpdate($table, $id, $state, $companyId);
    }

    /** @return array{sql: string, params: array<string, int|string|null>} */
    public static function resetApprovalUpdate(
        string $table,
        int $id,
        int $companyId,
        ?bool $includeApprovedBy = null,
        ?bool $includeApprovedAt = null
    ): array {
        return self::coreResetUpdate($table, $id, $companyId);
    }

    private static function runCorePendingUpdate(string $table, int $id, string $state, int $companyId): void
    {
        self::normalizeManagerApprovalEnum($table);
        $built = self::corePendingUpdate($table, $id, $state, $companyId);
        try {
            $stmt = Database::connection()->prepare($built['sql']);
            $stmt->execute($built['params']);
        } catch (PDOException $e) {
            throw DatabaseErrorService::toRuntimeException($e);
        }
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('manager_approval_already_processed'));
        }
    }

    private static function runCoreResetUpdate(string $table, int $id, int $companyId): void
    {
        $built = self::coreResetUpdate($table, $id, $companyId);
        try {
            $stmt = Database::connection()->prepare($built['sql']);
            $stmt->execute($built['params']);
        } catch (PDOException $e) {
            throw DatabaseErrorService::toRuntimeException($e);
        }
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('manager_approval_already_processed'));
        }
    }

    /** @return array{sql: string, params: array<string, int|string|null>} */
    private static function corePendingUpdate(string $table, int $id, string $state, int $companyId): array
    {
        $table = self::sanitizeTable($table);
        if ($table === '' || $id < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if (!self::hasColumn($table, 'manager_approval')) {
            throw new \RuntimeException(__('db_schema_outdated') . ' [manager_approval]');
        }
        $sql = sprintf(
            'UPDATE `%s` SET manager_approval = :st WHERE id = :id AND manager_approval = :pending',
            $table
        );
        $params = ['st' => $state, 'id' => $id, 'pending' => 'pending'];
        if ($companyId > 0 && !self::isOversightSuperAdmin() && self::hasColumn($table, 'company_id')) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        return ['sql' => $sql, 'params' => $params];
    }

    /** @return array{sql: string, params: array<string, int|string|null>} */
    private static function coreResetUpdate(string $table, int $id, int $companyId): array
    {
        $table = self::sanitizeTable($table);
        if ($table === '' || $id < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if (!self::hasColumn($table, 'manager_approval')) {
            throw new \RuntimeException(__('db_schema_outdated') . ' [manager_approval]');
        }
        $sql = sprintf(
            'UPDATE `%s` SET manager_approval = :st WHERE id = :id AND manager_approval IN (\'approved\', \'rejected\')',
            $table
        );
        $params = ['st' => 'pending', 'id' => $id];
        if ($companyId > 0 && !self::isOversightSuperAdmin() && self::hasColumn($table, 'company_id')) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        return ['sql' => $sql, 'params' => $params];
    }

    private static function applyAuditColumns(string $table, int $id, int $uid): void
    {
        $table = self::sanitizeTable($table);
        if ($table === '' || $id < 1) {
            return;
        }
        try {
            $db = Database::connection();
            if (self::hasColumn($table, 'approved_by')) {
                $db->prepare('UPDATE `' . $table . '` SET approved_by = :uid WHERE id = :id')
                    ->execute(['uid' => $uid > 0 ? $uid : null, 'id' => $id]);
            }
            if (self::hasColumn($table, 'approved_at')) {
                $db->prepare('UPDATE `' . $table . '` SET approved_at = NOW() WHERE id = :id')
                    ->execute(['id' => $id]);
            }
        } catch (\Throwable $e) {
            // Audit fields are optional; core approval already saved.
        }
    }

    private static function clearAuditColumns(string $table, int $id): void
    {
        $table = self::sanitizeTable($table);
        if ($table === '' || $id < 1) {
            return;
        }
        try {
            $db = Database::connection();
            if (self::hasColumn($table, 'approved_by')) {
                $db->prepare('UPDATE `' . $table . '` SET approved_by = NULL WHERE id = :id')->execute(['id' => $id]);
            }
            if (self::hasColumn($table, 'approved_at')) {
                $db->prepare('UPDATE `' . $table . '` SET approved_at = NULL WHERE id = :id')->execute(['id' => $id]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private static function normalizeManagerApprovalEnum(string $table): void
    {
        $table = self::sanitizeTable($table);
        if ($table === '' || !self::hasColumn($table, 'manager_approval')) {
            return;
        }
        try {
            Database::connection()->exec(
                'ALTER TABLE `' . $table . '` MODIFY COLUMN manager_approval '
                . "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'"
            );
            self::$columnCache[$table . '.manager_approval'] = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public static function normalizeContractApprovalStatus(): void
    {
        if (!self::hasColumn('rateb_contracts', 'approval_status')) {
            return;
        }
        try {
            Database::connection()->exec(
                'ALTER TABLE rateb_contracts MODIFY COLUMN approval_status '
                . "ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft'"
            );
            self::$columnCache['rateb_contracts.approval_status'] = true;
        } catch (\Throwable $e) {
            // ignore
        }
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
}
