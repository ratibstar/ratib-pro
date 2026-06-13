<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;
    private static string $resolvedDbName = '';

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

        $candidates = function_exists('rateb_erp_database_candidates')
            ? rateb_erp_database_candidates()
            : [defined('RATEB_DB_NAME') ? (string) RATEB_DB_NAME : 'outratib_rateb-erp'];

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
                if (!$isAccessDenied) {
                    throw $e;
                }
            }
        }

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

        if ($last instanceof PDOException) {
            $hint = 'Tried: ' . implode(', ', $tried) . '. Grant ' . RATEB_DB_USER
                . ' ALL PRIVILEGES on outratib_rateb-erp in cPanel → MySQL® Databases.';
            error_log('RATEB ERP DB connection failed: ' . $last->getMessage() . ' — ' . $hint);
            throw new PDOException($last->getMessage() . "\n\n" . $hint, (int) $last->getCode(), $last);
        }

        throw new PDOException('RATEB ERP database connection failed.');
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
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            RATEB_DB_HOST,
            RATEB_DB_PORT,
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
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return new PDO($dsn, RATEB_DB_USER, RATEB_DB_PASS, $options);
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$resolvedDbName = '';
    }
}
