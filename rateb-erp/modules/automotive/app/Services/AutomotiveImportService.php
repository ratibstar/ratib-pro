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
        if (str_starts_with($csv, 'provider,')) {
            return $this->previewLicensed($sourceId, $csv);
        }
        if ($csv === '' || strlen($csv) > 200000 || !str_starts_with($csv, 'name_en,')) {
            throw new \InvalidArgumentException('Import file must be a CSV preview with a name_en header.');
        }
        $hash = hash('sha256', $csv);
        $lines = preg_split('/\r\n|\n|\r/', $csv) ?: [];
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
            $fetched = $lines->fetchAll(PDO::FETCH_ASSOC);
            if ($this->isLicensedBatch($fetched)) {
                $this->applyLicensed($db, $batchId, (int) $row['source_id'], $fetched);
                $db->commit();
                return;
            }
            $applied = 0;
            foreach ($fetched as $line) {
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

    /** @return array{batch_id:int,rows:int,conflicts:int} */
    private function previewLicensed(int $sourceId, string $csv): array
    {
        $db = $this->db();
        $source = $db->prepare('SELECT license_ref FROM rateb_auto_ref_sources WHERE id = :id');
        $source->execute(['id' => $sourceId]);
        $license = trim((string) $source->fetchColumn());
        if ($license === '') {
            throw new \InvalidArgumentException('unlicensed_source');
        }
        $parsed = AutomotiveLicensedProviders::parse($csv);
        $hash = hash('sha256', $csv);
        $db->beginTransaction();
        try {
            $batch = $db->prepare(
                "INSERT INTO rateb_auto_ref_import_batches (source_id, content_sha256, status, row_count)
                 VALUES (:source, :hash, 'preview', :rows)"
            );
            $batch->execute(['source' => $sourceId, 'hash' => $hash, 'rows' => count($parsed['rows'])]);
            $batchId = (int) $db->lastInsertId();
            $conflicts = 0;
            $rowNo = 0;
            $insert = $db->prepare(
                'INSERT INTO rateb_auto_ref_import_rows (batch_id, row_no, raw_text, normalized_text, conflict_note)
                 VALUES (:batch, :no, :raw, :norm, :note)'
            );
            foreach ($parsed['rows'] as $record) {
                $rowNo++;
                $note = $this->licensedConflict($db, $record);
                if ($note !== null) {
                    $conflicts++;
                }
                $insert->execute([
                    'batch' => $batchId,
                    'no' => $rowNo,
                    'raw' => substr($record['raw'], 0, 500),
                    'norm' => substr($parsed['provider'] . '|' . $record['record_type'] . '|' . $record['code'], 0, 500),
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

    /** @param array<string, string> $record */
    private function licensedConflict(PDO $db, array $record): ?string
    {
        if ($record['record_type'] === 'part') {
            $stmt = $db->prepare('SELECT id FROM rateb_auto_ref_parts WHERE normalized_name = :name LIMIT 1');
            $stmt->execute(['name' => strtoupper($record['name_en'])]);
            return $stmt->fetch() ? 'duplicate_name' : null;
        }
        if ($record['record_type'] === 'engine') {
            $stmt = $db->prepare('SELECT id FROM rateb_auto_ref_engines WHERE code = :code LIMIT 1');
            $stmt->execute(['code' => $record['code']]);
            return $stmt->fetch() ? 'duplicate_code' : null;
        }
        if ($record['record_type'] === 'transmission') {
            $stmt = $db->prepare('SELECT id FROM rateb_auto_ref_transmissions WHERE code = :code LIMIT 1');
            $stmt->execute(['code' => $record['code']]);
            return $stmt->fetch() ? 'duplicate_code' : null;
        }
        return null;
    }

    /** @param list<array<string, mixed>> $rows */
    private function isLicensedBatch(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }
        $cols = str_getcsv((string) $rows[0]['raw_text'], ',', '"', '\\');
        return count($cols) === 9 && in_array(trim($cols[0]), AutomotiveLicensedProviders::RECORD_TYPES, true);
    }

    /** @param list<array<string, mixed>> $rows */
    private function applyLicensed(PDO $db, int $batchId, int $sourceId, array $rows): void
    {
        $applied = 0;
        foreach ($rows as $line) {
            if (($line['conflict_note'] ?? null) !== null) {
                continue;
            }
            $cols = str_getcsv((string) $line['raw_text'], ',', '"', '\\');
            if (count($cols) !== 9) {
                throw new \InvalidArgumentException('licensed_row_rejected');
            }
            $record = [
                'record_type' => trim($cols[0]),
                'market_code' => strtoupper(trim($cols[1])),
                'name_en' => trim($cols[2]),
                'name_ar' => trim($cols[3]),
                'parent_code' => strtoupper(trim($cols[4])),
                'year_from' => (int) trim($cols[5]),
                'year_to' => (int) trim($cols[6]),
                'code' => strtoupper(trim($cols[7])),
                'extra' => strtoupper(trim($cols[8])),
            ];
            $this->applyLicensedRecord($db, $sourceId, $record);
            $applied++;
        }
        $done = $db->prepare(
            "UPDATE rateb_auto_ref_import_batches SET status = 'applied', row_count = :rows WHERE id = :id AND status = 'preview'"
        );
        $done->execute(['rows' => $applied, 'id' => $batchId]);
        if ($done->rowCount() < 1) {
            throw new \InvalidArgumentException('Import batch cannot be approved.');
        }
        $audit = $db->prepare(
            'INSERT INTO rateb_auto_ref_audit (actor_user_id, action, entity_type, entity_id, batch_id)
             VALUES (:actor, :action, :type, :entity, :batch)'
        );
        $audit->execute([
            'actor' => (int) ($_SESSION['rateb_user_id'] ?? 0),
            'action' => 'import',
            'type' => 'licensed_batch',
            'entity' => $batchId,
            'batch' => $batchId,
        ]);
    }

    /** @param array<string, mixed> $record */
    private function applyLicensedRecord(PDO $db, int $sourceId, array $record): void
    {
        $type = (string) $record['record_type'];
        if ($type === 'engine' || $type === 'transmission') {
            if ($record['code'] === '' || $record['name_en'] === '' || $record['name_ar'] === '') {
                throw new \InvalidArgumentException('licensed_row_rejected');
            }
            $table = $type === 'engine' ? 'rateb_auto_ref_engines' : 'rateb_auto_ref_transmissions';
            $stmt = $db->prepare("INSERT INTO {$table} (code, name_en, name_ar) VALUES (:code, :en, :ar)");
            $stmt->execute(['code' => $record['code'], 'en' => $record['name_en'], 'ar' => $record['name_ar']]);
            return;
        }
        if ($type === 'model') {
            $brand = $db->prepare('SELECT id FROM rateb_auto_ref_vehicle_brands WHERE normalized_name = :name LIMIT 1');
            $brand->execute(['name' => $record['parent_code']]);
            $brandId = (int) $brand->fetchColumn();
            if ($brandId < 1 || $record['name_en'] === '' || $record['name_ar'] === '') {
                throw new \InvalidArgumentException('licensed_parent_missing');
            }
            $stmt = $db->prepare(
                'INSERT INTO rateb_auto_ref_models (vehicle_brand_id, name_en, name_ar, normalized_name)
                 VALUES (:brand, :en, :ar, :norm)'
            );
            $stmt->execute([
                'brand' => $brandId,
                'en' => $record['name_en'],
                'ar' => $record['name_ar'],
                'norm' => strtoupper($record['name_en']),
            ]);
            return;
        }
        if ($type === 'generation') {
            $modelId = $this->modelId($db, $record['parent_code'], (string) $record['extra']);
            $marketId = $this->marketId($db, (string) $record['market_code']);
            if ($modelId < 1 || $marketId < 1 || $record['year_to'] < $record['year_from']) {
                throw new \InvalidArgumentException('licensed_parent_missing');
            }
            $stmt = $db->prepare(
                'INSERT INTO rateb_auto_ref_generations (model_id, market_id, name_en, name_ar, year_from, year_to)
                 VALUES (:model, :market, :en, :ar, :from, :to)'
            );
            $stmt->execute([
                'model' => $modelId,
                'market' => $marketId,
                'en' => $record['name_en'],
                'ar' => $record['name_ar'],
                'from' => $record['year_from'],
                'to' => $record['year_to'],
            ]);
            return;
        }
        if ($type === 'vehicle') {
            $vehicleId = $this->ensureVehicle($db, $record);
            if ($vehicleId < 1) {
                throw new \InvalidArgumentException('licensed_parent_missing');
            }
            return;
        }
        if ($type === 'part') {
            if ($record['name_en'] === '' || $record['name_ar'] === '') {
                throw new \InvalidArgumentException('licensed_row_rejected');
            }
            $stmt = $db->prepare(
                'INSERT INTO rateb_auto_ref_parts (source_id, name_en, name_ar, normalized_name)
                 VALUES (:source, :en, :ar, :norm)'
            );
            $stmt->execute([
                'source' => $sourceId,
                'en' => $record['name_en'],
                'ar' => $record['name_ar'],
                'norm' => strtoupper((string) $record['name_en']),
            ]);
            return;
        }
        if ($type === 'fitment') {
            $part = $db->prepare('SELECT id FROM rateb_auto_ref_parts WHERE normalized_name = :name LIMIT 1');
            $part->execute(['name' => strtoupper((string) $record['name_en'])]);
            $partId = (int) $part->fetchColumn();
            $vehicleId = $this->ensureVehicle($db, $record);
            if ($partId < 1 || $vehicleId < 1) {
                throw new \InvalidArgumentException('licensed_parent_missing');
            }
            $stmt = $db->prepare(
                "INSERT INTO rateb_auto_ref_fitment (part_id, vehicle_id, fitment_status, source_id)
                 VALUES (:part, :vehicle, 'confirmed', :source)"
            );
            $stmt->execute(['part' => $partId, 'vehicle' => $vehicleId, 'source' => $sourceId]);
            return;
        }
        if ($type === 'diagram') {
            if ($record['code'] === '' || $record['name_en'] === '' || $record['name_ar'] === '') {
                throw new \InvalidArgumentException('licensed_row_rejected');
            }
            $system = $db->prepare(
                'INSERT INTO rateb_auto_ref_systems (code, name_en, name_ar) VALUES (:code, :en, :ar)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $system->execute(['code' => $record['code'], 'en' => $record['name_en'], 'ar' => $record['name_ar']]);
            $systemId = (int) $db->lastInsertId();
            $assembly = $db->prepare(
                'INSERT INTO rateb_auto_ref_assemblies (system_id, code, name_en, name_ar)
                 VALUES (:system, :code, :en, :ar)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $assembly->execute([
                'system' => $systemId,
                'code' => $record['parent_code'] !== '' ? $record['parent_code'] : $record['code'],
                'en' => $record['name_en'],
                'ar' => $record['name_ar'],
            ]);
            $diagram = $db->prepare(
                "INSERT INTO rateb_auto_ref_diagrams (assembly_id, version_no, status) VALUES (:assembly, 1, 'draft')"
            );
            $diagram->execute(['assembly' => (int) $db->lastInsertId()]);
            return;
        }
        throw new \InvalidArgumentException('unknown_record_type');
    }

    /** @param array<string, mixed> $record */
    private function ensureVehicle(PDO $db, array $record): int
    {
        [$engineCode, $transmissionCode] = array_pad(explode('|', (string) $record['code'], 2), 2, '');
        $modelId = $this->modelId($db, (string) $record['parent_code'], (string) $record['extra']);
        $marketId = $this->marketId($db, (string) $record['market_code']);
        $generation = $db->prepare(
            'SELECT id FROM rateb_auto_ref_generations
             WHERE model_id = :model AND market_id = :market AND year_from = :from AND year_to = :to LIMIT 1'
        );
        $generation->execute([
            'model' => $modelId,
            'market' => $marketId,
            'from' => $record['year_from'],
            'to' => $record['year_to'],
        ]);
        $generationId = (int) $generation->fetchColumn();
        $engine = $db->prepare('SELECT id FROM rateb_auto_ref_engines WHERE code = :code LIMIT 1');
        $engine->execute(['code' => $engineCode]);
        $engineId = (int) $engine->fetchColumn();
        $transmission = $db->prepare('SELECT id FROM rateb_auto_ref_transmissions WHERE code = :code LIMIT 1');
        $transmission->execute(['code' => $transmissionCode]);
        $transmissionId = (int) $transmission->fetchColumn();
        if ($generationId < 1 || $engineId < 1 || $transmissionId < 1 || $marketId < 1) {
            return 0;
        }
        $existing = $db->prepare(
            'SELECT id FROM rateb_auto_ref_vehicles
             WHERE generation_id = :generation AND engine_id = :engine AND transmission_id = :transmission AND market_id = :market'
        );
        $existing->execute([
            'generation' => $generationId,
            'engine' => $engineId,
            'transmission' => $transmissionId,
            'market' => $marketId,
        ]);
        $found = (int) $existing->fetchColumn();
        if ($found > 0) {
            return $found;
        }
        $insert = $db->prepare(
            'INSERT INTO rateb_auto_ref_vehicles (generation_id, engine_id, transmission_id, market_id)
             VALUES (:generation, :engine, :transmission, :market)'
        );
        $insert->execute([
            'generation' => $generationId,
            'engine' => $engineId,
            'transmission' => $transmissionId,
            'market' => $marketId,
        ]);
        return (int) $db->lastInsertId();
    }

    private function modelId(PDO $db, string $modelName, string $brandName): int
    {
        $stmt = $db->prepare(
            'SELECT mo.id FROM rateb_auto_ref_models mo
             JOIN rateb_auto_ref_vehicle_brands b ON b.id = mo.vehicle_brand_id
             WHERE mo.normalized_name = :model AND b.normalized_name = :brand LIMIT 1'
        );
        $stmt->execute(['model' => $modelName, 'brand' => $brandName]);
        return (int) $stmt->fetchColumn();
    }

    private function marketId(PDO $db, string $code): int
    {
        $stmt = $db->prepare('SELECT id FROM rateb_auto_ref_markets WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (int) $stmt->fetchColumn();
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
