<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/** Compare live ERP schema against columns required by inventory save flows. */
final class SchemaDiagnosticService
{
    /** @var array<string, list<string>> */
    private const REQUIRED_COLUMNS = [
        'rateb_inventory' => [
            'item_code',
            'category_id',
            'barcode',
            'qr_code',
            'min_stock',
            'max_stock',
            'production_date',
            'document_path',
            'notes',
        ],
        'rateb_stock_movements' => [
            'movement_no',
        ],
    ];

    /** @return list<array{table: string, column: string}> */
    public function missingInventoryColumns(?PDO $pdo = null): array
    {
        try {
            $pdo = $pdo ?? Database::connection();
        } catch (\Throwable $e) {
            return [];
        }

        $dbName = $this->currentDatabase($pdo);
        if ($dbName === '') {
            return [];
        }

        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (!$this->tableExists($pdo, $dbName, $table)) {
                $missing[] = ['table' => $table, 'column' => '(table missing)'];
                continue;
            }
            foreach ($columns as $column) {
                if (!$this->columnExists($pdo, $dbName, $table, $column)) {
                    $missing[] = ['table' => $table, 'column' => $column];
                }
            }
        }

        return $missing;
    }

    /** @param list<array{table: string, column: string}> $missing */
    public function formatMissingInventorySummary(array $missing): string
    {
        if ($missing === []) {
            return '';
        }
        $parts = [];
        foreach ($missing as $row) {
            $parts[] = ($row['table'] ?? '?') . '.' . ($row['column'] ?? '?');
        }
        return implode(', ', $parts);
    }

    private function currentDatabase(PDO $pdo): string
    {
        try {
            $row = $pdo->query('SELECT DATABASE()')->fetch(PDO::FETCH_NUM);
            return is_array($row) ? (string) ($row[0] ?? '') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function tableExists(PDO $pdo, string $dbName, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables
             WHERE table_schema = :db AND table_name = :tbl LIMIT 1'
        );
        $stmt->execute(['db' => $dbName, 'tbl' => $table]);
        $exists = (bool) $stmt->fetchColumn();
        $stmt->closeCursor();
        return $exists;
    }

    private function columnExists(PDO $pdo, string $dbName, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = :db AND table_name = :tbl AND column_name = :col LIMIT 1'
        );
        $stmt->execute(['db' => $dbName, 'tbl' => $table, 'col' => $column]);
        $exists = (bool) $stmt->fetchColumn();
        $stmt->closeCursor();
        return $exists;
    }
}
