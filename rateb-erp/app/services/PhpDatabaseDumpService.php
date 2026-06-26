<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Pure-PHP MySQL dump (no exec/mysqldump) for shared hosting where shell is disabled.
 */
final class PhpDatabaseDumpService
{
    private const ROW_BATCH = 200;

    public function writeGzipFile(string $targetPath): void
    {
        $pdo = Database::connection();
        $dbName = Database::resolvedDatabaseName();

        $dir = dirname($targetPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Cannot create backup directory.');
        }

        @set_time_limit(0);

        $gz = gzopen($targetPath, 'wb9');
        if ($gz === false) {
            throw new \RuntimeException('Cannot create gzip backup file.');
        }

        try {
            $this->gzWriteLine($gz, '-- RATEB ERP PHP backup ' . date('c'));
            $this->gzWriteLine($gz, 'SET NAMES utf8mb4;');
            $this->gzWriteLine($gz, 'SET FOREIGN_KEY_CHECKS=0;');
            $this->gzWriteLine($gz, 'USE `' . str_replace('`', '``', $dbName) . '`;');
            $this->gzWriteLine($gz, '');

            foreach ($this->listTables($pdo) as $table) {
                $this->dumpTable($pdo, $gz, $table);
            }

            $this->gzWriteLine($gz, 'SET FOREIGN_KEY_CHECKS=1;');
        } finally {
            gzclose($gz);
        }

        if (!is_file($targetPath) || (filesize($targetPath) ?: 0) < 50) {
            @unlink($targetPath);
            throw new \RuntimeException('Backup file is empty.');
        }
    }

    /** @return array<int, string> */
    private function listTables(PDO $pdo): array
    {
        $tables = [];
        $stmt = $pdo->query('SHOW TABLES');
        if ($stmt === false) {
            return [];
        }
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            if (!empty($row[0])) {
                $tables[] = (string) $row[0];
            }
        }

        return $tables;
    }

    private function dumpTable(PDO $pdo, $gz, string $table): void
    {
        $safeTable = str_replace('`', '``', $table);
        $this->gzWriteLine($gz, '-- Table `' . $safeTable . '`');
        $this->gzWriteLine($gz, 'DROP TABLE IF EXISTS `' . $safeTable . '`;');

        $createStmt = $pdo->query('SHOW CREATE TABLE `' . $safeTable . '`');
        $createRow = $createStmt !== false ? $createStmt->fetch(PDO::FETCH_ASSOC) : false;
        $createSql = is_array($createRow) ? (string) ($createRow['Create Table'] ?? $createRow['Create View'] ?? '') : '';
        if ($createSql !== '') {
            $this->gzWriteLine($gz, $createSql . ';');
            $this->gzWriteLine($gz, '');
        }

        $countStmt = $pdo->query('SELECT COUNT(*) FROM `' . $safeTable . '`');
        $total = $countStmt !== false ? (int) $countStmt->fetchColumn() : 0;
        if ($total < 1) {
            return;
        }

        $offset = 0;
        while ($offset < $total) {
            $limit = self::ROW_BATCH;
            $dataStmt = $pdo->query(
                'SELECT * FROM `' . $safeTable . '` LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
            );
            if ($dataStmt === false) {
                break;
            }
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $this->gzWriteLine($gz, $this->buildInsert($safeTable, $row, $pdo));
            }

            $offset += count($rows);
            if (count($rows) < $limit) {
                break;
            }
        }

        $this->gzWriteLine($gz, '');
    }

    /** @param array<string, mixed> $row */
    private function buildInsert(string $table, array $row, PDO $pdo): string
    {
        $cols = [];
        $vals = [];
        foreach ($row as $col => $val) {
            $cols[] = '`' . str_replace('`', '``', (string) $col) . '`';
            $vals[] = $val === null ? 'NULL' : $pdo->quote((string) $val);
        }

        return 'INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ');';
    }

    /** @param resource $gz */
    private function gzWriteLine($gz, string $line): void
    {
        gzwrite($gz, $line . "\n");
    }
}
