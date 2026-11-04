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
            $message = $e->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'uq_auto_ref_import_hash')) {
                throw new \InvalidArgumentException('duplicate_import');
            }
            throw $e;
        }
    }

    public function approve(int $batchId): void
    {
        $this->gate()->assert('automotive.reference.approve');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $batch = $db->prepare(
                "SELECT id, source_id, status FROM rateb_auto_ref_import_batches WHERE id = :id FOR UPDATE"
            );
            $batch->execute(['id' => $batchId]);
            $row = $batch->fetch(PDO::FETCH_ASSOC);
            if (!$row || (string) $row['status'] !== 'preview') {
                throw new \InvalidArgumentException('Import batch cannot be approved.');
            }
            $lines = $db->prepare(
                'SELECT row_no, raw_text, conflict_note FROM rateb_auto_ref_import_rows WHERE batch_id = :id ORDER BY row_no'
            );
            $lines->execute(['id' => $batchId]);
            $applied = 0;
            foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
                if (($line['conflict_note'] ?? null) !== null) {
                    continue;
                }
                $cols = str_getcsv((string) $line['raw_text'], ',', '"', '\\');
                $nameEn = trim((string) ($cols[0] ?? ''));
                $nameAr = trim((string) ($cols[1] ?? ''));
                if ($nameEn === '' || $nameAr === '') {
                    throw new \InvalidArgumentException('Import row ' . (int) $line['row_no'] . ' is missing a name.');
                }
                $insert = $db->prepare(
                    'INSERT INTO rateb_auto_ref_parts (source_id, name_en, name_ar, normalized_name)
                     VALUES (:source, :en, :ar, :norm)'
                );
                $insert->execute([
                    'source' => (int) $row['source_id'],
                    'en' => $nameEn,
                    'ar' => $nameAr,
                    'norm' => strtoupper($nameEn),
                ]);
                $partId = (int) $db->lastInsertId();
                $audit = $db->prepare(
                    'INSERT INTO rateb_auto_ref_audit (actor_user_id, action, entity_type, entity_id, batch_id)
                     VALUES (:actor, :action, :type, :entity, :batch)'
                );
                $audit->execute([
                    'actor' => (int) ($_SESSION['rateb_user_id'] ?? 0),
                    'action' => 'import',
                    'type' => 'part',
                    'entity' => $partId,
                    'batch' => $batchId,
                ]);
                $applied++;
            }
            $done = $db->prepare(
                "UPDATE rateb_auto_ref_import_batches SET status = 'applied', row_count = :rows WHERE id = :id AND status = 'preview'"
            );
            $done->execute(['rows' => $applied, 'id' => $batchId]);
            if ($done->rowCount() < 1) {
                throw new \InvalidArgumentException('Import batch cannot be approved.');
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
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
