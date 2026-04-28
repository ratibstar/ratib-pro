<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/helpers/CountryFilter.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/helpers/CountryFilter.php`.
 */
/**
 * CountryFilter - Enforce country_id in all tenant-scoped queries
 *
 * Usage:
 *   $sql = CountryFilter::where('users', 'user_id = ?');
 *   // Returns: SELECT * FROM users WHERE country_id = ? AND user_id = ?
 *
 *   $sql = CountryFilter::where('users', 'status = ?', ['active']);
 *   // Returns: SELECT * FROM users WHERE country_id = ? AND status = ?
 *
 * For INSERT: CountryFilter::insertParams() adds country_id to bind params.
 */
class CountryFilter
{
    /**
     * Get current country ID. Returns 0 if multi-tenant disabled.
     * Uses: TENANT_ID (subdomain) > COUNTRY_ID > $_SESSION['country_id'] (control_countries from login)
     */
    public static function getId(): int
    {
        if (defined('TENANT_ID') && TENANT_ID > 0) {
            return (int) TENANT_ID;
        }
        if (defined('COUNTRY_ID') && COUNTRY_ID > 0) {
            return (int) COUNTRY_ID;
        }
        $sid = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['country_id'])) ? $_SESSION['country_id'] : null;
        return ($sid !== null && $sid > 0) ? (int) $sid : 0;
    }

    /**
     * Check if multi-tenant mode is active.
     */
    public static function isActive(): bool
    {
        return self::getId() > 0;
    }

    /**
     * Add country_id clause to WHERE. Use for SELECT/UPDATE/DELETE.
     * Embeds country_id as literal (int, safe) - no extra bind param needed.
     *
     * @param string $table Table name (for JOINs, use alias e.g. 'u' for users u)
     * @param string $where Existing WHERE clause (without WHERE keyword)
     * @param string $alias Optional table alias if different from table
     * @return string Full WHERE clause including country_id
     */
    public static function where(string $table, string $where = '1=1', string $alias = ''): string
    {
        if (!self::isActive()) {
            return $where ? "($where)" : '1=1';
        }
        $col = $alias ? "{$alias}.country_id" : "{$table}.country_id";
        $cid = (int) self::getId();
        $clause = "{$col} = {$cid}";
        return $where ? "({$clause} AND ({$where}))" : "({$clause})";
    }

    /**
     * Get country_id for bind_param. Use when building prepared statements.
     *
     * @return array [country_id_value, type_string] e.g. [1, 'i']
     */
    public static function bindParam(): array
    {
        return [self::getId(), 'i'];
    }

    /**
     * Add country_id to INSERT params. Use for INSERT statements.
     *
     * @param array $columns Column names
     * @param array $values Values (will have country_id prepended)
     * @param array $types Types for bind_param: 's','i','d' (will have 'i' prepended)
     * @return array ['columns' => [...], 'placeholders' => '?,?,?', 'values' => [...], 'types' => 'iss']
     */
    public static function insertParams(array $columns, array $values, array $types = []): array
    {
        if (!self::isActive()) {
            $ph = implode(',', array_fill(0, count($columns), '?'));
            return [
                'columns'   => $columns,
                'placeholders' => $ph,
                'values'    => $values,
                'types'     => $types ? implode('', $types) : str_repeat('s', count($columns)),
            ];
        }
        array_unshift($columns, 'country_id');
        array_unshift($values, self::getId());
        if ($types) {
            array_unshift($types, 'i');
        } else {
            $types = array_merge(['i'], array_fill(0, count($values) - 1, 's'));
        }
        $ph = implode(',', array_fill(0, count($columns), '?'));
        return [
            'columns'      => $columns,
            'placeholders' => $ph,
            'values'       => $values,
            'types'        => implode('', $types),
        ];
    }

    /**
     * Build WHERE clause for raw SQL (when not using prepared - use sparingly).
     * Prefer where() with prepared statements.
     */
    public static function whereRaw(): string
    {
        if (!self::isActive()) {
            return '1=1';
        }
        return 'country_id = ' . (int) self::getId();
    }
}
