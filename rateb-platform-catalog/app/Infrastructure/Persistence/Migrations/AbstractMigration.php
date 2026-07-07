<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

use PDO;
use PDOException;

abstract class AbstractMigration implements MigrationInterface
{
    public function __construct(
        protected readonly PDO $pdo
    ) {
    }

    protected function exec(string $sql): void
    {
        $statements = preg_split('/;\s*\n/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                $code = (int) $e->getCode();
                $msg = $e->getMessage();
                $benign = in_array($code, [1050, 1060, 1061, 1062, 1091], true)
                    || str_contains($msg, 'Duplicate')
                    || str_contains($msg, 'already exists');
                if (!$benign) {
                    throw $e;
                }
            }
        }
    }

    public static function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $sql = preg_replace('/^USE\s+`[^`]+`\s*;\s*/mi', '', $sql) ?? $sql;

        return $sql;
    }
}
