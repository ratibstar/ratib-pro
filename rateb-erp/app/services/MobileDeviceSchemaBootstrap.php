<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Ensures rateb_mobile_devices (+ push columns) exist when migrations lag deploy.
 * Idempotent — safe on every device register/heartbeat.
 */
final class MobileDeviceSchemaBootstrap
{
    private static bool $ensured = false;

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }

        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_mobile_devices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                client_app VARCHAR(32) NOT NULL,
                platform VARCHAR(16) NOT NULL DEFAULT \'other\',
                device_id VARCHAR(64) NOT NULL,
                push_token VARCHAR(512) NULL,
                push_provider VARCHAR(16) NOT NULL DEFAULT \'none\',
                locale VARCHAR(16) NULL,
                app_version VARCHAR(64) NULL,
                last_seen_at DATETIME NULL,
                status ENUM(\'active\', \'inactive\', \'revoked\') NOT NULL DEFAULT \'active\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_mobile_device_identity (company_id, client_app, device_id),
                KEY idx_mobile_device_user (company_id, user_id, status),
                KEY idx_mobile_device_seen (company_id, last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureColumn($pdo, 'push_provider', "VARCHAR(16) NOT NULL DEFAULT 'none' AFTER push_token");
        self::ensureColumn($pdo, 'locale', 'VARCHAR(16) NULL AFTER push_provider');

        self::$ensured = true;
    }

    private static function ensureColumn(PDO $pdo, string $column, string $definition): void
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM rateb_mobile_devices LIKE " . $pdo->quote($column));
        if ($stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }
        $pdo->exec('ALTER TABLE rateb_mobile_devices ADD COLUMN ' . $column . ' ' . $definition);
    }

    /** @internal tests */
    public static function resetForTests(): void
    {
        self::$ensured = false;
    }
}
