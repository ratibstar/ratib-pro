<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;

/**
 * Schema probe for offline services — works on older ERP cores that lack
 * Database::liveTableHasColumn() (staging v1.0.1) without modifying Core.
 */
final class OfflineSchema
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        if (method_exists(Database::class, 'liveTableHasColumn')) {
            return self::$cache[$key] = (bool) Database::liveTableHasColumn($table, $column);
        }

        try {
            $pdo = Database::connection();
            $safeTable = str_replace('`', '', $table);
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . $safeTable . '` LIKE ' . $pdo->quote($column)
            );
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }

            return self::$cache[$key] = $exists;
        } catch (\Throwable $e) {
            return self::$cache[$key] = false;
        }
    }
}
