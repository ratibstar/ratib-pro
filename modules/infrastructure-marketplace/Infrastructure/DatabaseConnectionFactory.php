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

        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS')) {
            $host = (string) DB_HOST;
            $db = self::legacyInfraDatabaseName();
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
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS')) {
            return;
        }
        $config = dirname(__DIR__, 3) . '/includes/config.php';
        if (is_file($config)) {
            require_once $config;
        }
    }

    /**
     * Legacy PDO dbname: infra tables usually live on the main Ratib Pro DB, not the control-panel DB.
     * Override with RATIB_INFRA_DB_NAME or RATIB_INFRA_DB_DSN when needed.
     */
    private static function legacyInfraDatabaseName(): string
    {
        $fromEnv = getenv('RATIB_INFRA_DB_NAME');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }
        if (defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL
            && defined('RATIB_PRO_DB_NAME') && (string) RATIB_PRO_DB_NAME !== '') {
            return (string) RATIB_PRO_DB_NAME;
        }
        if (defined('DB_NAME') && (string) DB_NAME !== '') {
            return (string) DB_NAME;
        }

        throw new \RuntimeException('Infrastructure DB name is not configured (DB_NAME / RATIB_PRO_DB_NAME / RATIB_INFRA_DB_NAME).');
    }
}

