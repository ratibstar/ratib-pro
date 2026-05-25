<?php
/**
 * Mobile API tenant isolation — derive scope from JWT claims (never from query/body).
 */
declare(strict_types=1);

/**
 * Resolve sending-country tenant id for staff JWTs.
 * Priority: token claim country_id → users.country_id for sub.
 */
function rateb_mobile_resolve_tenant_country_id(PDO $pdo, array $claims): ?int
{
    $fromToken = (int) ($claims['country_id'] ?? 0);
    if ($fromToken > 0) {
        return $fromToken;
    }

    if (($claims['typ'] ?? '') !== 'staff') {
        return null;
    }

    $userId = (int) ($claims['sub'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT country_id FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $countryId = (int) ($row['country_id'] ?? 0);

    return $countryId > 0 ? $countryId : null;
}

/**
 * Resolve partner agency id from JWT (sub is canonical for partner accounts).
 */
function rateb_mobile_resolve_agency_id(array $claims): ?int
{
    if (($claims['typ'] ?? '') !== 'partner') {
        return null;
    }

    $agencyId = (int) ($claims['sub'] ?? 0);
    if ($agencyId <= 0) {
        $agencyId = (int) ($claims['agency_id'] ?? 0);
    }

    return $agencyId > 0 ? $agencyId : null;
}

function rateb_mobile_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

/**
 * Append worker-row tenant filter for company/staff reads.
 * Fail-closed when tenant is known but no scoping column exists.
 *
 * @param list<string> $where
 * @param list<mixed> $params
 */
function rateb_mobile_apply_worker_tenant_scope(
    PDO $pdo,
    array $claims,
    string $workerAlias,
    array &$where,
    array &$params
): void {
    if (($claims['typ'] ?? '') !== 'staff') {
        return;
    }

    $countryId = rateb_mobile_resolve_tenant_country_id($pdo, $claims);
    if ($countryId === null || $countryId <= 0) {
        error_log('rateb_mobile tenant: staff JWT missing country_id — empty worker scope');
        $where[] = '1=0';

        return;
    }

    // Direct country_id on workers (preferred when present).
    if (rateb_mobile_table_has_column($pdo, 'workers', 'country_id')) {
        $where[] = "{$workerAlias}.country_id = ?";
        $params[] = $countryId;

        return;
    }

    // Agents.tenant_id mirrors sending-country tenant in RATEB deployments.
    if (rateb_mobile_table_has_column($pdo, 'agents', 'tenant_id')) {
        $where[] = "EXISTS (
            SELECT 1 FROM agents ag_tenant
            WHERE ag_tenant.id = {$workerAlias}.agent_id
              AND ag_tenant.tenant_id = ?
        )";
        $params[] = $countryId;

        return;
    }

    if (rateb_mobile_table_has_column($pdo, 'agents', 'country_id')) {
        $where[] = "EXISTS (
            SELECT 1 FROM agents ag_tenant
            WHERE ag_tenant.id = {$workerAlias}.agent_id
              AND ag_tenant.country_id = ?
        )";
        $params[] = $countryId;

        return;
    }

    error_log('rateb_mobile tenant: no workers/agents country column — blocking worker read');
    rateb_mobile_json([
        'success' => false,
        'message' => 'Tenant isolation unavailable',
        'code' => 'config_error',
    ], 503);
}

/**
 * Scope cases to tenant via linked worker or case agent.
 *
 * @param list<string> $where
 * @param list<mixed> $params
 */
function rateb_mobile_apply_cases_tenant_scope(
    PDO $pdo,
    array $claims,
    string $caseAlias,
    array &$where,
    array &$params
): void {
    if (($claims['typ'] ?? '') !== 'staff') {
        return;
    }

    $countryId = rateb_mobile_resolve_tenant_country_id($pdo, $claims);
    if ($countryId === null || $countryId <= 0) {
        error_log('rateb_mobile tenant: staff JWT missing country_id — empty cases scope');
        $where[] = '1=0';

        return;
    }

    if (rateb_mobile_table_has_column($pdo, 'cases', 'country_id')) {
        $where[] = "{$caseAlias}.country_id = ?";
        $params[] = $countryId;

        return;
    }

    if (rateb_mobile_table_has_column($pdo, 'cases', 'tenant_id')) {
        $where[] = "{$caseAlias}.tenant_id = ?";
        $params[] = $countryId;

        return;
    }

    // Fall back: case belongs to tenant if its worker or agent is in tenant.
    $workerParts = [];
    $workerParams = [];
    $workerWhere = ['1=1'];
    rateb_mobile_apply_worker_tenant_scope($pdo, $claims, 'w_tenant', $workerWhere, $workerParams);
    $workerSql = implode(' AND ', $workerWhere);

    if (rateb_mobile_table_has_column($pdo, 'agents', 'tenant_id')) {
        $where[] = "(
            EXISTS (
                SELECT 1 FROM workers w_tenant
                WHERE w_tenant.id = {$caseAlias}.worker_id
                  AND {$workerSql}
            )
            OR EXISTS (
                SELECT 1 FROM agents ag_case
                WHERE ag_case.id = {$caseAlias}.agent_id
                  AND ag_case.tenant_id = ?
            )
        )";
        array_push($params, ...$workerParams);
        $params[] = $countryId;

        return;
    }

    $where[] = "EXISTS (
        SELECT 1 FROM workers w_tenant
        WHERE w_tenant.id = {$caseAlias}.worker_id
          AND {$workerSql}
    )";
    array_push($params, ...$workerParams);
}
