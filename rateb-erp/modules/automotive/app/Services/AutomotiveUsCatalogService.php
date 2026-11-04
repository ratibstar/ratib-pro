<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/** Read-only US NHTSA and EPA catalog. Does not query VehiclesDB and does not decode VINs. */
final class AutomotiveUsCatalogService
{
    public const PAGE_SIZE = 25;

    public static function economyUnit(string $fuel): string
    {
        return preg_match('/electric/i', $fuel) === 1 ? 'MPGe' : 'MPG';
    }

    public static function licenseText(string $stored): string
    {
        if (preg_match('/17 USC|public domain/i', $stored) === 1) {
            return 'FuelEconomy.gov vehicles.csv. Commercial redistribution rights have not been independently established. https://fueleconomy.gov/feg/download.shtml';
        }
        return $stored;
    }

    public function __construct(private ?PDO $pdo = null, private ?AutomotiveGate $gate = null)
    {
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int} */
    public function epa(int $year, string $make, string $model, string $fuel, string $class, int $page): array
    {
        $this->gate()->assert('automotive.view');
        $releaseId = $this->releaseId('epa', 'vehicles');
        $where = 'WHERE release_id = :release';
        $params = ['release' => $releaseId];
        if ($year > 0) {
            $where .= ' AND model_year = :year';
            $params['year'] = $year;
        }
        if ($this->token($make) !== '') {
            $where .= ' AND make_name = :make';
            $params['make'] = trim($make);
        }
        $model = $this->like($model);
        if ($model !== '') {
            $where .= ' AND model_name LIKE :model';
            $params['model'] = $model;
        }
        if ($this->token($fuel) !== '') {
            $where .= ' AND fuel_type1 = :fuel';
            $params['fuel'] = trim($fuel);
        }
        if ($this->token($class) !== '') {
            $where .= ' AND vclass = :class';
            $params['class'] = trim($class);
        }
        $count = $this->db()->prepare("SELECT COUNT(*) FROM rateb_auto_ref_us_epa_vehicles {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        [$page, $limit, $offset] = $this->window($page, $total);
        $stmt = $this->db()->prepare(
            "SELECT id, epa_id, model_year, make_name, model_name, vclass, fuel_type1, city_mpg, highway_mpg, comb_mpg
             FROM rateb_auto_ref_us_epa_vehicles {$where}
             ORDER BY model_year DESC, make_name, model_name, id LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total, 'page' => $page];
    }

    /** @return array<string, mixed> */
    public function epaDetail(int $id): array
    {
        $this->gate()->assert('automotive.view');
        $stmt = $this->db()->prepare(
            'SELECT v.*, r.provider_code, r.version_label, r.retrieved_on, r.attribution, r.license_evidence, r.market_scope
             FROM rateb_auto_ref_us_epa_vehicles v
             INNER JOIN rateb_auto_ref_us_releases r ON r.id = v.release_id
             WHERE v.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('us_epa_missing');
        }
        return $row;
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int} */
    public function nhtsaModels(string $make, string $model, int $page): array
    {
        $this->gate()->assert('automotive.view');
        $releaseId = $this->releaseId('nhtsa', 'models');
        $where = 'WHERE release_id = :release';
        $params = ['release' => $releaseId];
        if ($this->token($make) !== '') {
            $where .= ' AND make_name = :make';
            $params['make'] = trim($make);
        }
        $model = $this->like($model);
        if ($model !== '') {
            $where .= ' AND model_name LIKE :model';
            $params['model'] = $model;
        }
        $count = $this->db()->prepare("SELECT COUNT(*) FROM rateb_auto_ref_us_nhtsa_models {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        [$page, $limit, $offset] = $this->window($page, $total);
        $stmt = $this->db()->prepare(
            "SELECT id, make_id, make_name, model_id, model_name FROM rateb_auto_ref_us_nhtsa_models {$where}
             ORDER BY make_name, model_name, id LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total, 'page' => $page];
    }

    private function releaseId(string $provider, string $dataset): int
    {
        $stmt = $this->db()->prepare(
            'SELECT id FROM rateb_auto_ref_us_releases WHERE provider_code = :provider AND dataset_code = :dataset AND market_scope = :scope ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['provider' => $provider, 'dataset' => $dataset, 'scope' => 'US']);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('us_release_missing');
        }
        return (int) $id;
    }

    /** @return array{0:int,1:int,2:int} */
    private function window(int $page, int $total): array
    {
        $page = max(1, $page);
        $pages = max(1, (int) ceil(max(0, $total) / self::PAGE_SIZE));
        if ($page > $pages) {
            $page = $pages;
        }
        return [$page, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE];
    }

    private function token(string $value): string
    {
        return trim($value);
    }

    private function like(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[%_\\\\]+/', '', $value) ?? '';
        return $value === '' ? '' : '%' . $value . '%';
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
