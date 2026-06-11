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
        foreach ($candidates as $dbName) {
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

        if ($last instanceof PDOException) {
            error_log('RATEB ERP DB connection failed: ' . $last->getMessage());
            throw $last;
        }

        throw new PDOException('RATEB ERP database connection failed.');
    }

    private static function open(string $dbName): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            RATEB_DB_HOST,
            RATEB_DB_PORT,
            $dbName
        );

        return new PDO($dsn, RATEB_DB_USER, RATEB_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
