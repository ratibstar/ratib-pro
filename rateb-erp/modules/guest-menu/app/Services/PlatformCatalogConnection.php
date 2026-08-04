<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use PDO;
use PDOException;

/** Shared UTF-8 platform-catalog PDO (SET NAMES required — charset in DSN alone is not enough on some hosts). */
final class PlatformCatalogConnection
{
    public static function connect(): ?PDO
    {
        $config = dirname(RATEB_ROOT) . '/rateb-platform-catalog/config/database.php';
        if (!is_file($config)) {
            return null;
        }
        require_once $config;
        if (!defined('RATEB_PLATFORM_CATALOG_DB_NAME')) {
            return null;
        }
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                RATEB_PLATFORM_CATALOG_DB_HOST,
                (int) RATEB_PLATFORM_CATALOG_DB_PORT,
                RATEB_PLATFORM_CATALOG_DB_NAME
            );
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
            $pdo = new PDO($dsn, RATEB_PLATFORM_CATALOG_DB_USER, RATEB_PLATFORM_CATALOG_DB_PASS, $options);
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

            return $pdo;
        } catch (PDOException) {
            return null;
        }
    }
}
