<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/core/Database.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/core/Database.php`.
 */
if (defined('DB_CLASS_LOADED')) return;
if (class_exists('Database', false)) { define('DB_CLASS_LOADED', true); return; }
class Database {
    private static ?PDO $pdo = null;
    private static ?int $tenantId = null;
    public static function getConnection(?int $tenantId = null): PDO {
        if (self::$pdo === null) {
            if (!defined('DB_HOST') || !defined('DB_NAME')) throw new RuntimeException('Database: DB constants not defined.');
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306) . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        }
        if ($tenantId !== null && self::$tenantId !== $tenantId) self::$tenantId = $tenantId;
        return self::$pdo;
    }
    public static function execute(string $sql, array $params = []): PDOStatement {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    public static function fetchOne(string $sql, array $params = []): ?array {
        return self::execute($sql, $params)->fetch() ?: null;
    }
    public static function fetchAll(string $sql, array $params = []): array {
        return self::execute($sql, $params)->fetchAll();
    }
}
define('DB_CLASS_LOADED', true);
