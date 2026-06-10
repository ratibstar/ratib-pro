<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            RATEB_DB_HOST,
            RATEB_DB_PORT,
            RATEB_DB_NAME
        );

        try {
            self::$pdo = new PDO($dsn, RATEB_DB_USER, RATEB_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('RATEB ERP DB connection failed: ' . $e->getMessage());
            throw $e;
        }

        return self::$pdo;
    }
}
