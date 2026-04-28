<?php
/**
 * EN: Handles core framework/runtime behavior in `core/BaseModel.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/BaseModel.php`.
 */
/**
 * BaseModel - Data isolation layer
 *
 * All queries MUST enforce: WHERE country_id = TENANT_ID
 * super_admin: optional tenant override (allow_cross_tenant)
 * Never accept country_id from POST. Never trust frontend.
 */
if (defined('BASE_MODEL_LOADED')) {
    return;
}

require_once __DIR__ . '/Database.php';

class BaseModel
{
    /**
     * Secure query with tenant isolation.
     *
     * @param string $sql SQL with :tenant_id placeholder, e.g. "SELECT * FROM users WHERE country_id = :tenant_id"
     * @param array $params Params (never include country_id from user input)
     * @param array $options ['allow_cross_tenant' => true] for super_admin only
     */
    public static function secureQuery(string $sql, array $params = [], array $options = []): PDOStatement
    {
        $allowCross = $options['allow_cross_tenant'] ?? false;
        $tenantId = defined('TENANT_ID') ? TENANT_ID : null;

        // super_admin can override tenant
        if ($allowCross && !empty($_SESSION['tenant_role']) && $_SESSION['tenant_role'] === 'super_admin') {
            $override = $_SESSION['tenant_override_id'] ?? null;
            if ($override !== null && $override > 0) {
                $tenantId = (int) $override;
            }
        }

        if ($tenantId === null && strpos($sql, ':tenant_id') !== false) {
            throw new RuntimeException('BaseModel: TENANT_ID not defined. Load TenantResolver first.');
        }

        $params['tenant_id'] = $tenantId;
        return Database::getInstance()->execute($sql, $params);
    }

    /**
     * Example: Secure SELECT
     */
    public static function secureSelect(string $table, array $columns = ['*'], array $where = [], array $options = []): array
    {
        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$table} WHERE country_id = :tenant_id";
        $params = [':tenant_id' => defined('TENANT_ID') ? TENANT_ID : 0];

        foreach ($where as $k => $v) {
            $sql .= " AND {$k} = :{$k}";
            $params[":{$k}"] = $v;
        }

        return Database::getInstance()->query($sql, $params);
    }

    /**
     * Example: Secure INSERT (country_id from TENANT_ID only)
     */
    public static function secureInsert(string $table, array $data): int
    {
        if (isset($data['country_id'])) {
            throw new InvalidArgumentException('BaseModel: Never pass country_id from input.');
        }
        $data['country_id'] = defined('TENANT_ID') ? TENANT_ID : 0;

        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})";

        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $v;
        }

        Database::getInstance()->execute($sql, $params);
        return (int) Database::getInstance()->lastInsertId();
    }
}

define('BASE_MODEL_LOADED', true);
