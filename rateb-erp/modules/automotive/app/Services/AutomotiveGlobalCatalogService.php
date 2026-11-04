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
