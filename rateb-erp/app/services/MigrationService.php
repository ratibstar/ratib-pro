<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDO;

final class MigrationService
{
    /** @return array<int, string> */
    public function runAll(): array
    {
        $log = [];
        $pdo = Database::connection();
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $files = glob($root . '/migrations/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (!is_file($file) || substr(basename($file), -4) !== '.sql') {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            $name = basename($file);
            $log[] = 'Running ' . $name . '…';
            $pdo->exec($sql);
            $log[] = 'Done: ' . $name;
        }

        if (empty($files)) {
            $log[] = 'No migration SQL files found.';
        } else {
            $log[] = 'All migrations completed.';
        }

        return $log;
    }

    public function isSchemaReady(): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
            return $stmt !== false && $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
