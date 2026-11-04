<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/** Licensed dataset preview and approval. Does not import files by itself. */
final class AutomotiveImportService
{
    public function __construct(private ?PDO $pdo = null, private ?AutomotiveGate $gate = null)
    {
    }

    /** @return array{batch_id:int,rows:int,conflicts:int} */
    public function preview(int $sourceId, string $csv): array
    {
        $this->gate()->assert('automotive.reference.import');
        $csv = trim($csv);
        if ($csv === '' || strlen($csv) > 200000 || !str_starts_with($csv, 'name_en,')) {
            throw new \InvalidArgumentException('Import file must be a CSV preview with a name_en header.');
        }
        $hash = hash('sha256', $csv);
        $lines = preg_split('/\R/', $csv) ?: [];
        $db = $this->db();
        $db->beginTransaction();
        try {
            $batch = $db->prepare(
                "INSERT INTO rateb_auto_ref_import_batches (source_id, content_sha256, status, row_count)
                 VALUES (:source, :hash, 'preview', :rows)"
            );
            $batch->execute(['source' => $sourceId, 'hash' => $hash, 'rows' => max(0, count($lines) - 1)]);
            $batchId = (int) $db->lastInsertId();
            $conflicts = 0;
            $rowNo = 0;
            foreach (array_slice($lines, 1) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $rowNo++;
                $note = null;
                $normalized = strtoupper(trim($line));
                $existing = $db->prepare('SELECT id FROM rateb_auto_ref_parts WHERE normalized_name = :name LIMIT 1');
                $name = strtoupper(trim(explode(',', $line)[0] ?? ''));
                $existing->execute(['name' => $name]);
                if ($existing->fetch()) {
                    $note = 'duplicate_name';
                    $conflicts++;
                }
                $insert = $db->prepare(
                    'INSERT INTO rateb_auto_ref_import_rows (batch_id, row_no, raw_text, normalized_text, conflict_note)
                     VALUES (:batch, :no, :raw, :norm, :note)'
                );
                $insert->execute([
                    'batch' => $batchId,
                    'no' => $rowNo,
                    'raw' => substr($line, 0, 500),
                    'norm' => substr($normalized, 0, 500),
                    'note' => $note,
                ]);
            }
            $db->commit();
            return ['batch_id' => $batchId, 'rows' => $rowNo, 'conflicts' => $conflicts];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function approve(int $batchId): void
    {
        $this->gate()->assert('automotive.reference.approve');
        $stmt = $this->db()->prepare(
            "UPDATE rateb_auto_ref_import_batches SET status = 'approved' WHERE id = :id AND status = 'preview'"
        );
        $stmt->execute(['id' => $batchId]);
        if ($stmt->rowCount() < 1) {
            throw new \InvalidArgumentException('Import batch cannot be approved.');
        }
    }

    private function gate(): AutomotiveGate
    {
        return $this->gate ?? new AutomotiveGate();
    }

    private function db(): PDO
    {
        return $this->pdo ?? Database::connection();
    }
}
