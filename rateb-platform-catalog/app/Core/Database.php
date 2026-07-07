<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $writePdo = null;

    private static ?PDO $readPdo = null;

    public static function writeConnection(): PDO
    {
        if (self::$writePdo instanceof PDO) {
            return self::$writePdo;
        }

        self::$writePdo = self::open(
            (string) (defined('RATEB_PLATFORM_CATALOG_DB_NAME') ? RATEB_PLATFORM_CATALOG_DB_NAME : 'admin_rateb_platform_catalog')
        );

        return self::$writePdo;
    }

    public static function readConnection(): PDO
    {
        if (self::$readPdo instanceof PDO) {
            return self::$readPdo;
        }

        $readHost = defined('RATEB_PLATFORM_CATALOG_DB_READ_HOST')
            ? (string) RATEB_PLATFORM_CATALOG_DB_READ_HOST
            : (defined('RATEB_PLATFORM_CATALOG_DB_HOST') ? (string) RATEB_PLATFORM_CATALOG_DB_HOST : '127.0.0.1');

        $writeHost = defined('RATEB_PLATFORM_CATALOG_DB_HOST')
            ? (string) RATEB_PLATFORM_CATALOG_DB_HOST
            : '127.0.0.1';

        if ($readHost === $writeHost) {
            self::$readPdo = self::writeConnection();

            return self::$readPdo;
        }

        self::$readPdo = self::open(
            (string) (defined('RATEB_PLATFORM_CATALOG_DB_NAME') ? RATEB_PLATFORM_CATALOG_DB_NAME : 'admin_rateb_platform_catalog'),
            $readHost
        );

        return self::$readPdo;
    }

    /** @deprecated Use writeConnection() or readConnection() */
    public static function connection(): PDO
    {
        return self::writeConnection();
    }

    public static function disconnect(): void
    {
        self::$writePdo = null;
        self::$readPdo = null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = [], bool $useRead = true): array
    {
        $pdo = $useRead ? self::readConnection() : self::writeConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = [], bool $useRead = true): ?array
    {
        $pdo = $useRead ? self::readConnection() : self::writeConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? $row : null;
    }

    public static function ping(bool $useRead = true): bool
    {
        try {
            $pdo = $useRead ? self::readConnection() : self::writeConnection();
            $stmt = $pdo->query('SELECT 1');
            if ($stmt instanceof \PDOStatement) {
                $stmt->fetch();
                $stmt->closeCursor();
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::writeConnection();
        if ($pdo->inTransaction()) {
            return $callback();
        }

        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function open(string $dbName, ?string $host = null): PDO
    {
        $host = $host ?? (defined('RATEB_PLATFORM_CATALOG_DB_HOST') ? (string) RATEB_PLATFORM_CATALOG_DB_HOST : '127.0.0.1');
        $port = defined('RATEB_PLATFORM_CATALOG_DB_PORT') ? (int) RATEB_PLATFORM_CATALOG_DB_PORT : 3306;
        $user = defined('RATEB_PLATFORM_CATALOG_DB_USER') ? (string) RATEB_PLATFORM_CATALOG_DB_USER : 'root';
        $pass = defined('RATEB_PLATFORM_CATALOG_DB_PASS') ? (string) RATEB_PLATFORM_CATALOG_DB_PASS : '';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new PDOException(
                'Platform Catalog DB connection failed for "' . $dbName . '": ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        $pdo->exec('SET NAMES utf8mb4');
        $pdo->exec('SET CHARACTER SET utf8mb4');

        return $pdo;
    }
}
