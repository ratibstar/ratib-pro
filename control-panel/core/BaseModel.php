<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/core/BaseModel.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/core/BaseModel.php`.
 */
if (defined('BASE_MODEL_LOADED')) return;
require_once __DIR__ . '/Database.php';
class BaseModel {
    public static function secureQuery(string $sql, array $params = [], array $options = []): PDOStatement {
        $allowCross = $options['allow_cross_tenant'] ?? false;
        $tenantId = defined('TENANT_ID') ? TENANT_ID : null;
        if ($allowCross && !empty($_SESSION['tenant_role']) && $_SESSION['tenant_role'] === 'super_admin') {
            $override = $_SESSION['tenant_override_id'] ?? null;
            if ($override !== null && $override > 0) $tenantId = (int) $override;
        }
        if ($tenantId === null && strpos($sql, ':tenant_id') !== false) throw new RuntimeException('BaseModel: TENANT_ID not defined.');
        $params['tenant_id'] = $tenantId;
        return Database::execute($sql, $params);
    }
    public static function secureSelect(string $table, array $columns = ['*'], array $where = [], array $options = []): array {
        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$table} WHERE country_id = :tenant_id";
        $params = [':tenant_id' => defined('TENANT_ID') ? TENANT_ID : 0];
        foreach ($where as $k => $v) { $sql .= " AND {$k} = :{$k}"; $params[":{$k}"] = $v; }
        return Database::fetchAll($sql, $params);
    }
    public static function secureInsert(string $table, array $data): int {
        if (isset($data['country_id'])) throw new InvalidArgumentException('BaseModel: Never pass country_id from input.');
        $data['country_id'] = defined('TENANT_ID') ? TENANT_ID : 0;
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $params = [];
        foreach ($data as $k => $v) $params[':' . $k] = $v;
        Database::execute("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})", $params);
        return (int) Database::getConnection()->lastInsertId();
    }
}
define('BASE_MODEL_LOADED', true);
