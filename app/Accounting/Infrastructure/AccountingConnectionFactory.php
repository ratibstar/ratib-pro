<?php
declare(strict_types=1);

namespace App\Accounting\Infrastructure;

/**
 * Resolves a shared PDO connection for event store, audit, and read-only reporting.
 */
final class AccountingConnectionFactory
{
    private static ?\PDO $pdo = null;

    public static function pdo(): ?\PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        if (class_exists(\Illuminate\Support\Facades\DB::class)) {
            try {
                /** @var \PDO $pdo */
                $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
                self::$pdo = $pdo;

                return self::$pdo;
            } catch (\Throwable) {
                // fall through
            }
        }

        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? (string) DB_HOST : 'localhost');
        $name = getenv('DB_NAME') ?: (defined('DB_NAME') ? (string) DB_NAME : '');
        $user = getenv('DB_USER') ?: (defined('DB_USER') ? (string) DB_USER : '');
        $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? (string) DB_PASS : '');

        if ($name === '' || $user === '') {
            return null;
        }

        try {
            $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            return self::$pdo;
        } catch (\Throwable $e) {
            error_log('AccountingConnectionFactory: ' . $e->getMessage());

            return null;
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
