<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Infrastructure;

final class DatabaseConnectionFactory
{
    public static function createPdo(): \PDO
    {
        $dsn = getenv('RATEB_INFRA_DB_DSN');
        $user = getenv('RATEB_INFRA_DB_USER');
        $pass = getenv('RATEB_INFRA_DB_PASS');

        if (is_string($dsn) && trim($dsn) !== '') {
            return self::connect($dsn, (string) $user, (string) $pass);
        }

        self::ensureLegacyDbConstantsLoaded();

        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS')) {
            $host = (string) DB_HOST;
            $db = self::legacyInfraDatabaseName();
            $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
            [$user, $pass] = self::legacyInfraCredentials();
            $legacyDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
            return self::connect($legacyDsn, $user, $pass);
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
     * Legacy PDO dbname: rateb_infra_* are stored on the control panel database (CONTROL_PANEL_DB_NAME,
     * default admin_control_panel_db) — same as other control-scoped data. Override with RATEB_INFRA_DB_NAME
     * or RATEB_INFRA_DB_DSN when you need a different schema (e.g. workers on a dedicated host).
     */
    private static function legacyInfraDatabaseName(): string
    {
        $fromEnv = getenv('RATEB_INFRA_DB_NAME');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }
        if (defined('CONTROL_PANEL_DB_NAME') && (string) CONTROL_PANEL_DB_NAME !== '') {
            return (string) CONTROL_PANEL_DB_NAME;
        }
        $cpEnv = getenv('CONTROL_PANEL_DB_NAME');
        if (is_string($cpEnv) && trim($cpEnv) !== '') {
            return trim($cpEnv);
        }
        if (defined('DB_NAME') && (string) DB_NAME !== '') {
            return (string) DB_NAME;
        }

        throw new \RuntimeException('Infrastructure DB name is not configured (CONTROL_PANEL_DB_NAME / RATEB_INFRA_DB_NAME / DB_NAME).');
    }

    /**
     * Optional app-DB credentials when control-panel DB_USER cannot access RATEB_PRO_DB_NAME.
     *
     * @return array{0:string,1:string}
     */
    private static function legacyInfraCredentials(): array
    {
        $user = (string) DB_USER;
        $pass = (string) DB_PASS;
        $iu = getenv('RATEB_INFRA_DB_USER');
        if (is_string($iu) && trim($iu) !== '') {
            $user = trim($iu);
        }
        $ip = getenv('RATEB_INFRA_DB_PASS');
        if ($ip !== false && $ip !== '') {
            $pass = (string) $ip;
        }

        return [$user, $pass];
    }
}

