<?php
declare(strict_types=1);

namespace App\Accounting\Admin\Support;

use App\Accounting\Infrastructure\AccountingConnectionFactory;

/**
 * Shared PDO access for Phase 6 read-only admin queries.
 */
trait AccountingControlDbTrait
{
    protected function controlPdo(): ?\PDO
    {
        return AccountingConnectionFactory::pdo();
    }

    protected function tableExists(string $table): bool
    {
        $pdo = $this->controlPdo();
        if ($pdo === null) {
            return false;
        }
        try {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        $pdo = $this->controlPdo();
        if ($pdo === null || !$this->tableExists($table)) {
            return false;
        }
        try {
            $safeTable = str_replace('`', '', $table);
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $safeTable . '` LIKE :col');
            $stmt->execute(['col' => $column]);

            return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array{rows:list<array<string,mixed>>, total:int}
     */
    protected function paginate(string $sql, string $countSql, array $params, int $page, int $perPage): array
    {
        $pdo = $this->controlPdo();
        if ($pdo === null) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare($sql . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return ['rows' => $rows, 'total' => $total];
    }
}
