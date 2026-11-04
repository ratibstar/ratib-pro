<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/** Coverage is calculated from stored rows. An empty result means no verified coverage. */
final class AutomotiveCoverageService
{
    public function __construct(private ?PDO $pdo = null, private ?AutomotiveGate $gate = null)
    {
    }

    /** @return list<array<string, mixed>> */
    public function summary(): array
    {
        $this->gate()->assert('automotive.view');
        $stmt = $this->db()->query(
            'SELECT m.code AS market_code, b.normalized_name AS brand,
                    COUNT(DISTINCT mo.id) AS models,
                    MIN(g.year_from) AS year_from, MAX(g.year_to) AS year_to,
                    COUNT(DISTINCT f.part_id) AS parts, COUNT(DISTINCT f.id) AS fitment
             FROM rateb_auto_ref_generations g
             JOIN rateb_auto_ref_markets m ON m.id = g.market_id
             JOIN rateb_auto_ref_models mo ON mo.id = g.model_id
             JOIN rateb_auto_ref_vehicle_brands b ON b.id = mo.vehicle_brand_id
             LEFT JOIN rateb_auto_ref_vehicles v ON v.generation_id = g.id
             LEFT JOIN rateb_auto_ref_fitment f ON f.vehicle_id = v.id
             GROUP BY m.code, b.normalized_name
             ORDER BY m.code, b.normalized_name'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
