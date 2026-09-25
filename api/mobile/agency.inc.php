<?php
/**
 * Mobile API agency database context — control_agencies id → that agency's database,
 * using the same lookup and connection helper as includes/config.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/control_lookup_conn.php';
require_once __DIR__ . '/../../control-panel/api/control/agency-db-helper.php';

/**
 * Active, non-suspended control_agencies row (with DB credentials) or null.
 *
 * @return array<string, mixed>|null
 */
function rateb_mobile_load_agency_row(int $agencyId): ?array
{
    if ($agencyId <= 0) {
        return null;
    }

    $lookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
    if (!$lookup instanceof mysqli) {
        $lookup = ($GLOBALS['conn'] ?? null) instanceof mysqli ? $GLOBALS['conn'] : null;
    }
    if (!$lookup instanceof mysqli) {
        return null;
    }

    try {
        $active = function_exists('rateb_control_agency_active_fragment')
            ? rateb_control_agency_active_fragment($lookup, 'a')
            : '1=1';
        $stmt = $lookup->prepare(
            "SELECT a.id AS agency_row_id, a.name AS agency_name, a.country_id, c.slug AS country_slug,
                    a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name
             FROM control_agencies a
             LEFT JOIN control_countries c ON c.id = a.country_id
             WHERE a.id = ? AND a.is_active = 1 AND {$active}
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $agencyId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        error_log('rateb_mobile agency: lookup failed for agency ' . $agencyId . ': ' . $e->getMessage());

        return null;
    }
}

/**
 * Point Database::getInstance() and $GLOBALS['conn'] at the agency database.
 *
 * @return array{agency_id: int, country_id: int, use_country_filter: bool}|null
 */
function rateb_mobile_connect_agency(int $agencyId): ?array
{
    $row = rateb_mobile_load_agency_row($agencyId);
    if ($row === null) {
        return null;
    }

    $countryId = (int) ($row['country_id'] ?? 0);
    try {
        $acct = getAgencyDbConnection($row, $countryId);
    } catch (Throwable $e) {
        $acct = null;
    }
    if (!is_array($acct) || !(($acct['conn'] ?? null) instanceof mysqli)) {
        error_log('rateb_mobile agency: DB connect failed for agency ' . $agencyId . ': ' . getAgencyDbConnectionLastError());

        return null;
    }

    $GLOBALS['conn'] = $acct['conn'];
    $GLOBALS['agency_db'] = [
        'host' => $acct['connect_host'] ?? $row['db_host'],
        'port' => (int) ($acct['connect_port'] ?? ($row['db_port'] ?? 3306)),
        'db' => $acct['db_name'] ?? $row['db_name'],
        'user' => $acct['connect_user'] ?? $row['db_user'],
        'pass' => $acct['connect_pass'] ?? $row['db_pass'],
    ];
    $useCountryFilter = !empty($acct['use_country_filter']);
    if ($useCountryFilter) {
        $GLOBALS['agency_db']['use_country_filter'] = true;
    }

    $context = [
        'agency_id' => $agencyId,
        'country_id' => $countryId,
        'use_country_filter' => $useCountryFilter,
    ];
    $GLOBALS['rateb_mobile_agency_context'] = $context;

    return $context;
}

/**
 * Staff tokens carry the control_agencies id: switch to that agency database or fail closed.
 * Staff tokens without agency_id stay on the main database, where tenant scope returns nothing.
 *
 * @param array<string, mixed> $claims
 */
function rateb_mobile_apply_agency_context(array $claims): void
{
    if (($claims['typ'] ?? '') !== 'staff') {
        return;
    }

    $agencyId = (int) ($claims['agency_id'] ?? 0);
    if ($agencyId <= 0) {
        return;
    }

    $context = rateb_mobile_connect_agency($agencyId);
    if ($context === null) {
        rateb_mobile_json([
            'success' => false,
            'message' => 'Server configuration error',
            'code' => 'config_error',
        ], 503);
    }

    $tokenCountryId = (int) ($claims['country_id'] ?? 0);
    if ($tokenCountryId > 0 && $context['country_id'] > 0 && $tokenCountryId !== $context['country_id']) {
        rateb_mobile_json(['success' => false, 'message' => 'Unauthorized', 'code' => 'unauthorized'], 401);
    }
}
