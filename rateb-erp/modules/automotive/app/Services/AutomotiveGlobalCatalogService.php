<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/** Global catalog reads and writes. Company users cannot mutate these tables. */
final class AutomotiveGlobalCatalogService
{
    public function __construct(private ?PDO $pdo = null, private ?AutomotiveGate $gate = null)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $term, string $marketCode = ''): array
    {
        $this->gate()->assert('automotive.view');
        $like = '%' . strtoupper(trim($term)) . '%';
        $sql = 'SELECT p.id, p.name_en, p.name_ar, p.status, n.normalized_number, n.number_type,
                       f.fitment_status, m.code AS market_code
                FROM rateb_auto_ref_parts p
                LEFT JOIN rateb_auto_ref_part_numbers n ON n.part_id = p.id
                LEFT JOIN rateb_auto_ref_fitment f ON f.part_id = p.id
                LEFT JOIN rateb_auto_ref_markets m ON m.id = n.market_id
                WHERE (p.normalized_name LIKE :q OR n.normalized_number LIKE :q)';
        $params = ['q' => $like];
        if ($marketCode !== '') {
            $sql .= ' AND m.code = :market';
            $params['market'] = strtoupper($marketCode);
        }
        $sql .= ' ORDER BY p.id LIMIT 50';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{parts:int,vehicles:int,fitment:int} */
    public function counts(): array
    {
        $this->gate()->assert('automotive.reference.view');
        $db = $this->db();
        return [
            'parts' => (int) $db->query('SELECT COUNT(*) FROM rateb_auto_ref_parts')->fetchColumn(),
            'vehicles' => (int) $db->query('SELECT COUNT(*) FROM rateb_auto_ref_vehicles')->fetchColumn(),
            'fitment' => (int) $db->query('SELECT COUNT(*) FROM rateb_auto_ref_fitment')->fetchColumn(),
        ];
    }

    public function createPart(string $nameEn, string $nameAr): int
    {
        $this->gate()->assert('automotive.reference.manage');
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);
        if ($nameEn === '' || $nameAr === '') {
            throw new \InvalidArgumentException('Part names are required.');
        }
        $db = $this->db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO rateb_auto_ref_parts (name_en, name_ar, normalized_name) VALUES (:en, :ar, :norm)'
            );
            $stmt->execute(['en' => $nameEn, 'ar' => $nameAr, 'norm' => strtoupper($nameEn)]);
            $id = (int) $db->lastInsertId();
            $this->audit($db, 'create', 'part', $id);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function retirePart(int $partId): void
    {
        $this->gate()->assert('automotive.reference.retire');
        if ($partId < 1) {
            throw new \InvalidArgumentException('Part is required.');
        }
        $db = $this->db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE rateb_auto_ref_parts SET status = 'retired' WHERE id = :id");
            $stmt->execute(['id' => $partId]);
            if ($stmt->rowCount() < 1) {
                throw new \InvalidArgumentException('Part was not found.');
            }
            $this->audit($db, 'retire', 'part', $partId);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function models(): array
    {
        $this->gate()->assert('automotive.reference.view');
        return $this->db()->query(
            'SELECT m.id, m.name_en, m.name_ar, b.name_en AS brand_en
             FROM rateb_auto_ref_models m
             INNER JOIN rateb_auto_ref_vehicle_brands b ON b.id = m.vehicle_brand_id
             ORDER BY m.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createModel(int $brandId, string $nameEn, string $nameAr): int
    {
        $this->gate()->assert('automotive.reference.manage');
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);
        if ($brandId < 1 || $nameEn === '' || $nameAr === '') {
            throw new \InvalidArgumentException('Model brand and both names are required.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_ref_models (vehicle_brand_id, name_en, name_ar, normalized_name)
             VALUES (:brand, :en, :ar, :norm)'
        );
        $stmt->execute(['brand' => $brandId, 'en' => $nameEn, 'ar' => $nameAr, 'norm' => strtoupper($nameEn)]);
        return (int) $this->db()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function generations(): array
    {
        $this->gate()->assert('automotive.reference.view');
        return $this->db()->query(
            'SELECT g.id, g.name_en, g.name_ar, g.year_from, g.year_to FROM rateb_auto_ref_generations g ORDER BY g.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createGeneration(int $modelId, int $marketId, string $nameEn, string $nameAr, int $yearFrom, int $yearTo): int
    {
        $this->gate()->assert('automotive.reference.manage');
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);
        if ($modelId < 1 || $marketId < 1 || $nameEn === '' || $nameAr === '' || $yearTo < $yearFrom) {
            throw new \InvalidArgumentException('Generation years and both names are required.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_ref_generations (model_id, market_id, name_en, name_ar, year_from, year_to)
             VALUES (:model, :market, :en, :ar, :from_year, :to_year)'
        );
        $stmt->execute([
            'model' => $modelId,
            'market' => $marketId,
            'en' => $nameEn,
            'ar' => $nameAr,
            'from_year' => $yearFrom,
            'to_year' => $yearTo,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function engines(): array
    {
        $this->gate()->assert('automotive.reference.view');
        return $this->db()->query('SELECT id, code, name_en, name_ar FROM rateb_auto_ref_engines ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createEngine(string $code, string $nameEn, string $nameAr): int
    {
        return $this->createCoded('rateb_auto_ref_engines', $code, $nameEn, $nameAr);
    }

    /** @return list<array<string, mixed>> */
    public function transmissions(): array
    {
        $this->gate()->assert('automotive.reference.view');
        return $this->db()->query('SELECT id, code, name_en, name_ar FROM rateb_auto_ref_transmissions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createTransmission(string $code, string $nameEn, string $nameAr): int
    {
        return $this->createCoded('rateb_auto_ref_transmissions', $code, $nameEn, $nameAr);
    }

    /** @return list<array<string, mixed>> */
    public function diagrams(): array
    {
        $this->gate()->assert('automotive.reference.view');
        return $this->db()->query(
            'SELECT d.id, d.version_no, d.status, a.code AS assembly_code
             FROM rateb_auto_ref_diagrams d
             INNER JOIN rateb_auto_ref_assemblies a ON a.id = d.assembly_id
             ORDER BY d.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createDiagram(string $systemCode, string $assemblyCode, int $versionNo, ?string $svg): int
    {
        $this->gate()->assert('automotive.reference.manage');
        $svg = AutomotiveSvg::sanitize($svg);
        $systemCode = strtoupper(trim($systemCode));
        $assemblyCode = strtoupper(trim($assemblyCode));
        if ($systemCode === '' || $assemblyCode === '' || $versionNo < 1) {
            throw new \InvalidArgumentException('Diagram system, assembly, and version are required.');
        }
        $db = $this->db();
        $db->beginTransaction();
        try {
            $system = $db->prepare(
                'INSERT INTO rateb_auto_ref_systems (code, name_en, name_ar) VALUES (:code, :en, :ar)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $system->execute(['code' => $systemCode, 'en' => $systemCode, 'ar' => $systemCode]);
            $systemId = (int) $db->lastInsertId();
            $assembly = $db->prepare(
                'INSERT INTO rateb_auto_ref_assemblies (system_id, code, name_en, name_ar) VALUES (:system, :code, :en, :ar)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $assembly->execute(['system' => $systemId, 'code' => $assemblyCode, 'en' => $assemblyCode, 'ar' => $assemblyCode]);
            $assemblyId = (int) $db->lastInsertId();
            $diagram = $db->prepare(
                'INSERT INTO rateb_auto_ref_diagrams (assembly_id, version_no, svg_text) VALUES (:assembly, :version, :svg)'
            );
            $diagram->execute(['assembly' => $assemblyId, 'version' => $versionNo, 'svg' => $svg]);
            $id = (int) $db->lastInsertId();
            $this->audit($db, 'create', 'diagram', $id);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function createCoded(string $table, string $code, string $nameEn, string $nameAr): int
    {
        $this->gate()->assert('automotive.reference.manage');
        if (!in_array($table, ['rateb_auto_ref_engines', 'rateb_auto_ref_transmissions'], true)) {
            throw new \InvalidArgumentException('Record type is not allowed.');
        }
        $code = strtoupper(trim($code));
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);
        if ($code === '' || $nameEn === '' || $nameAr === '') {
            throw new \InvalidArgumentException('Code and both names are required.');
        }
        $stmt = $this->db()->prepare("INSERT INTO {$table} (code, name_en, name_ar) VALUES (:code, :en, :ar)");
        $stmt->execute(['code' => $code, 'en' => $nameEn, 'ar' => $nameAr]);
        return (int) $this->db()->lastInsertId();
    }

    public function saveDiagram(int $assemblyId, int $versionNo, ?string $svg): int
    {
        $this->gate()->assert('automotive.reference.manage');
        $svg = AutomotiveSvg::sanitize($svg);
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_ref_diagrams (assembly_id, version_no, svg_text) VALUES (:assembly, :version, :svg)'
        );
        $stmt->execute(['assembly' => $assemblyId, 'version' => $versionNo, 'svg' => $svg]);
        return (int) $this->db()->lastInsertId();
    }

    private function audit(PDO $db, string $action, string $type, int $id): void
    {
        $actor = (int) ($_SESSION['rateb_user_id'] ?? 0);
        $stmt = $db->prepare(
            'INSERT INTO rateb_auto_ref_audit (actor_user_id, action, entity_type, entity_id) VALUES (:actor, :action, :type, :id)'
        );
        $stmt->execute(['actor' => $actor, 'action' => $action, 'type' => $type, 'id' => $id]);
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
