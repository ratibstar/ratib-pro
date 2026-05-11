<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Infrastructure;

final class DatabaseConnectionFactory
{
    public static function createPdo(): \PDO
    {
        $dsn = getenv('RATIB_INFRA_DB_DSN');
        $user = getenv('RATIB_INFRA_DB_USER');
        $pass = getenv('RATIB_INFRA_DB_PASS');

        if (is_string($dsn) && trim($dsn) !== '') {
            return self::connect($dsn, (string) $user, (string) $pass);
        }

        self::ensureLegacyDbConstantsLoaded();

        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
            $host = (string) DB_HOST;
            $db = (string) DB_NAME;
            $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
            $legacyDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
            return self::connect($legacyDsn, (string) DB_USER, (string) DB_PASS);
        }

        throw new \RuntimeException('Infrastructure DB connection is not configured.');
    }

    private static function connect(string $dsn, string $user, string $pass): \PDO
    {
        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private static function ensureLegacyDbConstantsLoaded(): void
    {
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
            return;
        }
        $config = dirname(__DIR__, 3) . '/includes/config.php';
        if (is_file($config)) {
            require_once $config;
        }
    }
}

