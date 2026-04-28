<?php
/**
 * EN: Handles core framework/runtime behavior in `core/query/QueryGateway.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/query/QueryGateway.php`.
 */

require_once __DIR__ . '/../../admin/core/EventBus.php';

/**
 * QueryGateway
 *
 * Single entry policy for query payloads:
 * - TenantQuery (preferred, tenant-safe contract)
 * - Raw SQL string (system context allowed; tenant context controlled by strict mode)
 *
 * StructuredQuery is internal-only and rejected as public API input.
 */
final class QueryGateway
{
    /** @var PDO|mysqli|null */
    private static $connection = null;

    /**
     * Optional connection injection for legacy callers that already own a DB handle.
     *
     * @param mixed $connection
     */
    public static function setConnection($connection): void
    {
        self::$connection = $connection;
    }

    /**
     * Execute query through a single policy + execution gate.
     *
     * @param mixed $query
     * @param mixed $params
     * @return PDOStatement|mysqli_stmt
     */
    public static function execute($query, $params = [])
    {
        self::requireTenantExecutionContext();
        $normalized = self::normalize($query, $params);
        $sql = $normalized['sql'];
        $boundParams = $normalized['params'];
        $startedAt = microtime(true);

        self::enforceQueryPolicy($sql, $boundParams, $normalized['kind']);

        $connection = self::resolveConnection();
        try {
            if ($connection instanceof mysqli) {
                $stmt = self::executeMysqli($connection, $sql, $boundParams);
                emitEvent('QUERY_EXECUTED', 'info', 'Query executed through gateway', [
                    'tenant_id' => self::resolveTenantContextId(),
                    'query' => self::compactSql($sql),
                    'mode' => self::isStrictModeEnabled() ? 'STRICT' : 'SAFE',
                    'source' => 'query_gateway',
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
                return $stmt;
            }
            if ($connection instanceof PDO) {
                $stmt = self::executePdo($connection, $sql, $boundParams);
                emitEvent('QUERY_EXECUTED', 'info', 'Query executed through gateway', [
                    'tenant_id' => self::resolveTenantContextId(),
                    'query' => self::compactSql($sql),
                    'mode' => self::isStrictModeEnabled() ? 'STRICT' : 'SAFE',
                    'source' => 'query_gateway',
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
                return $stmt;
            }
        } catch (Throwable $e) {
            emitEvent('QUERY_EXECUTION_FAILED', 'error', 'Query gateway execution failed', [
                'tenant_id' => self::resolveTenantContextId(),
                'query' => self::compactSql($sql),
                'mode' => self::isStrictModeEnabled() ? 'STRICT' : 'SAFE',
                'source' => 'query_gateway',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        throw new RuntimeException('QueryGateway: unsupported DB connection type.');
    }

    /**
     * Normalize query input into SQL + params.
     *
     * @param mixed $query
     * @param mixed $params
     * @return array{sql:string, params:array, kind:string}
     */
    public static function normalize($query, $params = []): array
    {
        self::requireTenantExecutionContext();

        if (self::isTenantQueryObject($query)) {
            /** @var TenantQuery $query */
            $compiled = $query->toSqlAndParams();
            return [
                'sql' => (string) ($compiled['sql'] ?? ''),
                'params' => is_array($compiled['params'] ?? null) ? $compiled['params'] : [],
                'kind' => 'tenant_query',
            ];
        }

        if (self::isStructuredQueryObject($query)) {
            throw new RuntimeException('QueryGateway: StructuredQuery is internal-only and cannot be executed directly.');
        }

        if (!is_string($query)) {
            throw new InvalidArgumentException('QueryGateway: query must be TenantQuery or raw SQL string.');
        }

        $sql = (string) $query;
        $boundParams = is_array($params) ? $params : [];

        // Tenant context + strict mode => raw SQL is blocked.
        if (self::isTenantContext() && self::isStrictModeEnabled()) {
            self::logPolicyWarning($sql, 'raw_sql_blocked_in_strict_tenant_context');
            throw new RuntimeException('QueryGateway: raw SQL is disallowed in strict tenant context. Use TenantQuery.');
        }

        // Non-strict tenant context => warn but allow for compatibility.
        if (self::isTenantContext() && !self::isStrictModeEnabled()) {
            self::logPolicyWarning($sql, 'raw_sql_allowed_with_warning_in_tenant_context');
        }

        return [
            'sql' => $sql,
            'params' => $boundParams,
            'kind' => 'raw_sql',
        ];
    }

    private static function requireTenantExecutionContext(): void
    {
        if (!class_exists('TenantExecutionContext', false)) {
            require_once __DIR__ . '/../TenantExecutionContext.php';
        }
    }

    private static function isTenantQueryObject($query): bool
    {
        return is_object($query) && get_class($query) === 'TenantQuery';
    }

    private static function isStructuredQueryObject($query): bool
    {
        return is_object($query) && get_class($query) === 'StructuredQuery';
    }

    private static function enforceQueryPolicy(string $sql, array $params, string $kind): void
    {
        if (TenantExecutionContext::isInitialized() && TenantExecutionContext::isSystemContext()) {
            return;
        }

        $tenantId = self::resolveTenantContextId();
        if ($tenantId === null) {
            return;
        }

        $allowReason = self::tenantAllowlistReason($sql);
        if ($allowReason !== null) {
            self::logAllowlistBypass($tenantId, $sql, $allowReason);
            return;
        }

        if ($kind === 'tenant_query') {
            // TenantQuery is the canonical safe contract and auto-injects tenant_id.
            return;
        }

        // Raw SQL path is legacy-compat only.
        $violations = self::validateRawSqlTenantBindings($sql, $params, $tenantId);
        if (!empty($violations)) {
            self::logPolicyViolation($tenantId, $sql, implode(' | ', $violations));
            if (self::isStrictModeEnabled()) {
                throw new RuntimeException('QueryGateway: tenant policy violation in strict mode.');
            }
        }
    }

    private static function resolveConnection()
    {
        if (self::$connection instanceof PDO || self::$connection instanceof mysqli) {
            return self::$connection;
        }

        if (function_exists('getTenantDB')) {
            $conn = getTenantDB();
            if ($conn instanceof PDO || $conn instanceof mysqli) {
                return $conn;
            }
        }

        if (function_exists('ratib_app_default_db_connection')) {
            $conn = ratib_app_default_db_connection();
            if ($conn instanceof PDO || $conn instanceof mysqli) {
                return $conn;
            }
        }

        throw new RuntimeException('QueryGateway: unable to resolve DB connection.');
    }

    private static function executePdo(PDO $connection, string $sql, array $params): PDOStatement
    {
        $stmt = $connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private static function executeMysqli(mysqli $connection, string $sql, array $params): mysqli_stmt
    {
        $stmt = $connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $connection->error);
        }

        if (!empty($params)) {
            $orderedParams = array_values($params);
            $types = self::mysqliBindTypes($orderedParams);
            $stmt->bind_param($types, ...$orderedParams);
        }

        $stmt->execute();
        return $stmt;
    }

    private static function mysqliBindTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    private static function resolveTenantContextId(): ?int
    {
        if (!TenantExecutionContext::isInitialized()) {
            return null;
        }
        if (TenantExecutionContext::isSystemContext()) {
            return null;
        }
        $tid = TenantExecutionContext::getTenantId();
        return ($tid !== null && (int) $tid > 0) ? (int) $tid : null;
    }

    private static function validateRawSqlTenantBindings(string $sql, array $params, int $tenantId): array
    {
        $violations = [];
        $normalizedSql = strtolower($sql);
        $statementType = strtolower((string) strtok(ltrim($normalizedSql), " \t\r\n"));

        if (strpos($normalizedSql, 'tenant_id') === false) {
            $violations[] = 'tenant raw SQL missing tenant_id constraint';
            return $violations;
        }

        if (($statementType === 'select' || $statementType === 'delete' || $statementType === 'update')
            && !preg_match('/\bwhere\b[\s\S]*\btenant_id\b/i', $sql)
        ) {
            $violations[] = strtoupper($statementType) . ' missing tenant_id in WHERE clause';
        }

        if ($statementType === 'insert' && !preg_match('/\binsert\s+into\s+[^\(]+\(([^)]*\btenant_id\b[^)]*)\)/i', $sql)) {
            $violations[] = 'INSERT missing tenant_id column';
        }

        if (preg_match_all('/\btenant_id\b\s*=\s*(\?|\:[a-zA-Z_][a-zA-Z0-9_]*|\d+)/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $entry) {
                $token = (string) $entry[0];
                $offset = (int) $entry[1];
                $boundValue = self::resolveTokenValue($token, $params, $sql, $offset);
                if ($boundValue === null) {
                    $violations[] = 'tenant_id placeholder has no bound parameter';
                    continue;
                }
                if ((int) $boundValue !== $tenantId) {
                    $violations[] = 'tenant_id parameter mismatch (expected ' . $tenantId . ', got ' . (string) $boundValue . ')';
                }
            }
        }

        return $violations;
    }

    private static function resolveTokenValue(string $token, array $params, string $sql, int $tokenOffset)
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $token)) {
            return (int) $token;
        }

        if ($token[0] === ':') {
            if (array_key_exists($token, $params)) {
                return $params[$token];
            }
            $withoutColon = substr($token, 1);
            if (array_key_exists($withoutColon, $params)) {
                return $params[$withoutColon];
            }
            return null;
        }

        if ($token === '?') {
            $ordered = array_values($params);
            $prefix = substr($sql, 0, $tokenOffset);
            $position = substr_count($prefix, '?');
            return array_key_exists($position, $ordered) ? $ordered[$position] : null;
        }

        return null;
    }

    private static function tenantAllowlistReason(string $sql): ?string
    {
        $normalizedSql = strtolower((string) preg_replace('/\s+/', ' ', trim($sql)));
        if ($normalizedSql === '') {
            return null;
        }

        $allowlist = self::getTenantQueryAllowlist();
        $tables = is_array($allowlist['tables'] ?? null) ? $allowlist['tables'] : [];
        $keywords = is_array($allowlist['keywords'] ?? null) ? $allowlist['keywords'] : [];

        foreach ($keywords as $kw) {
            $kw = strtolower(trim((string) $kw));
            if ($kw !== '' && strpos($normalizedSql, $kw) !== false) {
                return 'keyword:' . $kw;
            }
        }

        foreach ($tables as $table) {
            $table = strtolower(trim((string) $table));
            if ($table === '') {
                continue;
            }
            if (
                strpos($normalizedSql, ' from ' . $table) !== false ||
                strpos($normalizedSql, ' join ' . $table) !== false ||
                strpos($normalizedSql, ' into ' . $table) !== false ||
                strpos($normalizedSql, 'update ' . $table . ' ') !== false ||
                strpos($normalizedSql, 'delete from ' . $table) !== false
            ) {
                return 'table:' . $table;
            }
        }

        return null;
    }

    private static function getTenantQueryAllowlist(): array
    {
        if (defined('TENANT_QUERY_ALLOWLIST') && is_array(TENANT_QUERY_ALLOWLIST)) {
            return TENANT_QUERY_ALLOWLIST;
        }
        return [
            'tables' => ['tenants', 'migrations', 'schema_migrations', 'migration_history', 'system_events', 'logs'],
            'keywords' => ['information_schema', 'show tables', 'show columns', 'describe ', 'explain ', 'select 1', 'health_check', 'heartbeat'],
        ];
    }

    private static function isTenantContext(): bool
    {
        if (!TenantExecutionContext::isInitialized()) {
            return false;
        }
        if (TenantExecutionContext::isSystemContext()) {
            return false;
        }
        $tid = TenantExecutionContext::getTenantId();
        return $tid !== null && (int) $tid > 0;
    }

    private static function isStrictModeEnabled(): bool
    {
        if (defined('TENANT_STRICT_MODE')) {
            return (bool) TENANT_STRICT_MODE;
        }
        $env = getenv('TENANT_STRICT_MODE');
        if ($env === false) {
            return false;
        }
        $env = strtolower(trim((string) $env));
        return in_array($env, ['1', 'true', 'on', 'yes'], true);
    }

    private static function logPolicyWarning(string $sql, string $reason): void
    {
        $endpoint = (string) ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'cli'));
        $tenantId = TenantExecutionContext::getTenantId();
        $compactSql = self::compactSql($sql);
        error_log(
            'QUERY_GATEWAY_POLICY endpoint=' . $endpoint .
            ' tenant_id=' . (string) ($tenantId ?? 0) .
            ' reason="' . $reason . '"' .
            ' query="' . $compactSql . '"'
        );
        emitEvent('QUERY_GATEWAY_POLICY', 'warn', 'Query gateway policy warning', [
            'tenant_id' => ($tenantId !== null && (int) $tenantId > 0) ? (int) $tenantId : null,
            'query' => $compactSql,
            'endpoint' => $endpoint,
            'mode' => self::isStrictModeEnabled() ? 'STRICT' : 'SAFE',
            'source' => 'query_gateway',
            'policy_reason' => $reason,
        ]);
    }

    private static function logAllowlistBypass(int $tenantId, string $sql, string $reason): void
    {
        self::logPolicyWarning($sql, 'allowlist_bypass ' . $reason . ' tenant_id=' . $tenantId);
    }

    private static function logPolicyViolation(int $tenantId, string $sql, string $warning): void
    {
        self::logPolicyWarning($sql, 'violation ' . $warning . ' tenant_id=' . $tenantId);
    }

    private static function compactSql(string $sql): string
    {
        $compactSql = preg_replace('/\s+/', ' ', trim($sql));
        if ($compactSql === null) {
            $compactSql = trim($sql);
        }
        if (strlen($compactSql) > 800) {
            $compactSql = substr($compactSql, 0, 800) . '...';
        }
        return $compactSql;
    }
}

