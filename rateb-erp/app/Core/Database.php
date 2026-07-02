<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;
    private static string $resolvedDbName = '';

    /** @var array{host:string,port:int,user:string,pass:string,db:string}|null */
    private static ?array $connectionOverride = null;

    /**
     * Force the next connection() to a specific database (provisioning / control-panel per-agency).
     *
     * @param array{host?:string,port?:int,user?:string,pass?:string,db:string} $config
     */
    public static function useConnectionOverride(array $config): void
    {
        self::disconnect();
        self::$connectionOverride = [
            'host' => (string) ($config['host'] ?? (defined('RATEB_DB_HOST') ? RATEB_DB_HOST : '127.0.0.1')),
            'port' => (int) ($config['port'] ?? (defined('RATEB_DB_PORT') ? RATEB_DB_PORT : 3306)),
            'user' => (string) ($config['user'] ?? (defined('RATEB_DB_USER') ? RATEB_DB_USER : 'root')),
            'pass' => (string) ($config['pass'] ?? (defined('RATEB_DB_PASS') ? RATEB_DB_PASS : '')),
            'db' => (string) $config['db'],
        ];
    }

    public static function clearConnectionOverride(): void
    {
        self::$connectionOverride = null;
        self::disconnect();
    }

    public static function hasConnectionOverride(): bool
    {
        return self::$connectionOverride !== null;
    }

    public static function resolvedDatabaseName(): string
    {
        if (self::$resolvedDbName !== '') {
            return self::$resolvedDbName;
        }
        return defined('RATEB_DB_NAME') ? (string) RATEB_DB_NAME : '';
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (self::$connectionOverride !== null) {
            $cfg = self::$connectionOverride;
            self::$pdo = self::openWith(
                (string) $cfg['db'],
                (string) $cfg['host'],
                (int) $cfg['port'],
                (string) $cfg['user'],
                (string) $cfg['pass']
            );
            self::$resolvedDbName = (string) $cfg['db'];

            return self::$pdo;
        }

        $agencyBinding = self::resolveAgencyBindingForRequest();
        if ($agencyBinding !== null) {
            self::$pdo = self::openWith(
                (string) $agencyBinding['db'],
                (string) $agencyBinding['host'],
                (int) $agencyBinding['port'],
                (string) $agencyBinding['user'],
                (string) $agencyBinding['pass']
            );
            self::$resolvedDbName = (string) $agencyBinding['db'];

            return self::$pdo;
        }

        $candidates = function_exists('rateb_erp_database_candidates')
            ? rateb_erp_database_candidates()
            : [defined('RATEB_DB_NAME') ? (string) RATEB_DB_NAME : 'admin_rateb-erp'];

        $dedicatedOnly = self::mustUseAgencyDatabaseOnly();
        $last = null;
        $tried = [];
        foreach ($candidates as $dbName) {
            $tried[] = $dbName;
            try {
                self::$pdo = self::open($dbName);
                self::$resolvedDbName = $dbName;
                return self::$pdo;
            } catch (PDOException $e) {
                $last = $e;
                $msg = $e->getMessage();
                $isAccessDenied = strpos($msg, '1044') !== false || strpos($msg, '1049') !== false;
                if (!$isAccessDenied || $dedicatedOnly) {
                    throw $e;
                }
            }
        }

        if (!$dedicatedOnly) {
            $probed = self::probeErpDatabase($candidates);
            if ($probed !== null && !in_array($probed, $tried, true)) {
                try {
                    self::$pdo = self::open($probed);
                    self::$resolvedDbName = $probed;
                    return self::$pdo;
                } catch (PDOException $e) {
                    $last = $e;
                    $tried[] = $probed;
                }
            }
        }

        if ($last instanceof PDOException) {
            $expectedDb = defined('RATEB_ERP_DB_NAME') ? (string) RATEB_ERP_DB_NAME : '';
            $hint = 'Tried: ' . implode(', ', $tried) . '.';
            if ($dedicatedOnly && $expectedDb !== '') {
                $hint .= ' This agency host must use ERP database "' . $expectedDb
                    . '". Grant MySQL ALL on that database in cPanel → MySQL® Databases.';
            } else {
                $hint .= ' Grant ' . RATEB_DB_USER
                    . ' ALL PRIVILEGES on admin_rateb-erp in cPanel → MySQL® Databases.';
            }
            error_log('RATEB ERP DB connection failed: ' . $last->getMessage() . ' — ' . $hint);
            throw new PDOException($last->getMessage() . "\n\n" . $hint, (int) $last->getCode(), $last);
        }

        throw new PDOException('RATEB ERP database connection failed.');
    }

    /** @return array{host:string,port:int,user:string,pass:string,db:string,agency_id?:int}|null */
    private static function resolveAgencyBindingForRequest(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        $lookupFile = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
        if (is_file($lookupFile)) {
            require_once $lookupFile;
            if (function_exists('rateb_agency_erp_binding_for_request_host')) {
                $binding = rateb_agency_erp_binding_for_request_host();
                if (is_array($binding) && trim((string) ($binding['db'] ?? '')) !== '') {
                    return $binding;
                }
            }
        }

        return self::agencyBindingFromConstants();
    }

    /** @return array{host:string,port:int,user:string,pass:string,db:string}|null */
    private static function agencyBindingFromConstants(): ?array
    {
        if (!(defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED)) {
            return null;
        }
        if (!defined('RATEB_ERP_DB_NAME') || trim((string) RATEB_ERP_DB_NAME) === '') {
            return null;
        }
        $host = defined('RATEB_ERP_DB_HOST') && (string) RATEB_ERP_DB_HOST !== ''
            ? (string) RATEB_ERP_DB_HOST
            : (defined('RATEB_DB_HOST') ? (string) RATEB_DB_HOST : '127.0.0.1');
        $port = defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306;
        $user = defined('RATEB_ERP_DB_USER') && (string) RATEB_ERP_DB_USER !== ''
            ? (string) RATEB_ERP_DB_USER
            : (defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : 'root');
        $pass = defined('RATEB_ERP_DB_PASS')
            ? (string) RATEB_ERP_DB_PASS
            : (defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : '');

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'db' => (string) RATEB_ERP_DB_NAME,
        ];
    }

    private static function mustUseAgencyDatabaseOnly(): bool
    {
        if (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED) {
            return true;
        }
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }
        if (PHP_SAPI !== 'cli' && function_exists('rateb_erp_is_main_platform_host')) {
            $host = function_exists('rateb_normalize_http_host')
                ? rateb_normalize_http_host((string) ($_SERVER['HTTP_HOST'] ?? ''))
                : strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
            if ($host !== '' && !rateb_erp_is_main_platform_host($host)) {
                return true;
            }
        }

        return false;
    }

    private static function probeErpDatabase(array $preferred): ?string
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', RATEB_DB_HOST, RATEB_DB_PORT);
            $pdo = new PDO($dsn, RATEB_DB_USER, RATEB_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->query("SHOW DATABASES LIKE '%rateb%erp%'");
            if ($stmt === false) {
                return null;
            }
            $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($preferred as $name) {
                if (in_array($name, $found, true)) {
                    return $name;
                }
            }
            return isset($found[0]) ? (string) $found[0] : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private static function open(string $dbName): PDO
    {
        return self::openWith(
            $dbName,
            RATEB_DB_HOST,
            RATEB_DB_PORT,
            RATEB_DB_USER,
            RATEB_DB_PASS
        );
    }

    private static function openWith(string $dbName, string $host, int $port, string $user, string $pass): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $dbName
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return new PDO($dsn, $user, $pass, $options);
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$resolvedDbName = '';
    }
}

