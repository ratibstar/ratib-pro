<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/** Read-only catalog over source-native vehicle rows. Does not invent specifications. */
final class AutomotiveOpenCatalogService
{
    public const PAGE_SIZE = 25;

    public function __construct(private ?PDO $pdo = null, private ?AutomotiveGate $gate = null)
    {
    }

    /** @return array<string, mixed> */
    public function release(): array
    {
        $this->gate()->assert('automotive.view');
        $stmt = $this->db()->query(
            "SELECT id, provider_code, version_label, retrieved_on, attribution, file_sha256
             FROM rateb_auto_ref_open_releases
             WHERE provider_code = 'vehiclesdb' AND version_label = '2026.10.4' AND CHAR_LENGTH(file_sha256) = 64
             ORDER BY id DESC LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('open_release_missing');
        }
        return $row;
    }

    /** @return list<array{kind:string,models:int}> */
    public function kinds(): array
    {
        $releaseId = (int) $this->release()['id'];
        $stmt = $this->db()->prepare(
            'SELECT kind, COUNT(*) AS models FROM rateb_auto_ref_open_models WHERE release_id = :release GROUP BY kind ORDER BY kind'
        );
        $stmt->execute(['release' => $releaseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int} */
    public function makes(string $kind, string $term, int $page): array
    {
        $releaseId = (int) $this->release()['id'];
        [$kind, $term, $page, $limit, $offset] = $this->bounds($kind, $term, $page);
        $where = 'WHERE release_id = :release';
        $params = ['release' => $releaseId];
        if ($kind !== '') {
            $where .= ' AND kind = :kind';
            $params['kind'] = $kind;
        }
        if ($term !== '') {
            $where .= ' AND make_slug LIKE :term';
            $params['term'] = $term;
        }
        $count = $this->db()->prepare("SELECT COUNT(DISTINCT make_slug) FROM rateb_auto_ref_open_models {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        [$page, $limit, $offset] = $this->pageWindow($page, $total);
        $stmt = $this->db()->prepare(
            "SELECT make_slug, COUNT(*) AS models FROM rateb_auto_ref_open_models {$where}
             GROUP BY make_slug ORDER BY make_slug LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total, 'page' => $page];
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int} */
    public function models(string $kind, string $make, string $market, string $term, int $page): array
    {
        $releaseId = (int) $this->release()['id'];
        [$kind, $term, $page, $limit, $offset] = $this->bounds($kind, $term, $page);
        $make = $this->token($make);
        $market = $this->token($market);
        $where = 'WHERE m.release_id = :release';
        $params = ['release' => $releaseId];
        if ($kind !== '') {
            $where .= ' AND m.kind = :kind';
            $params['kind'] = $kind;
        }
        if ($make !== '') {
            $where .= ' AND m.make_slug = :make';
            $params['make'] = $make;
        }
        if ($market !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM rateb_auto_ref_open_model_markets k WHERE k.model_id = m.id AND k.market_code = :market)';
            $params['market'] = $market;
        }
        if ($term !== '') {
            $where .= ' AND (m.make_slug LIKE :term_make OR m.model_slug LIKE :term_model OR m.name_en LIKE :term_name)';
            $params['term_make'] = $term;
            $params['term_model'] = $term;
            $params['term_name'] = $term;
        }
        $count = $this->db()->prepare("SELECT COUNT(*) FROM rateb_auto_ref_open_models m {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        [$page, $limit, $offset] = $this->pageWindow($page, $total);
        $stmt = $this->db()->prepare(
            "SELECT m.id, m.kind, m.make_slug, m.model_slug, m.name_en, m.name_ar, m.source_record_id
             FROM rateb_auto_ref_open_models m {$where}
             ORDER BY m.make_slug, m.name_en, m.id LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total, 'page' => $page];
    }

    /** @return array<string, mixed> */
    public function detail(int $modelId): array
    {
        $release = $this->release();
        $stmt = $this->db()->prepare(
            'SELECT id, kind, make_slug, model_slug, name_en, name_ar, source_record_id, record_sha256
             FROM rateb_auto_ref_open_models WHERE id = :id AND release_id = :release LIMIT 1'
        );
        $stmt->execute(['id' => $modelId, 'release' => (int) $release['id']]);
        $model = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$model) {
            throw new \InvalidArgumentException('open_model_missing');
        }
        $sources = $this->db()->prepare(
            'SELECT origin_code, license_code FROM rateb_auto_ref_open_model_sources WHERE model_id = :id ORDER BY origin_code'
        );
        $sources->execute(['id' => $modelId]);
        $licenses = $this->db()->prepare(
            'SELECT license_code FROM rateb_auto_ref_open_model_licenses WHERE model_id = :id ORDER BY license_code'
        );
        $licenses->execute(['id' => $modelId]);
        $markets = $this->db()->prepare(
            'SELECT market_code, origin_code, evidence FROM rateb_auto_ref_open_model_markets WHERE model_id = :id ORDER BY market_code, origin_code'
        );
        $markets->execute(['id' => $modelId]);
        return [
            'release' => $release,
            'model' => $model,
            'sources' => $sources->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'licenses' => $licenses->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'markets' => $markets->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    /** @return array{0:string,1:string,2:int,3:int,4:int} */
    private function bounds(string $kind, string $term, int $page): array
    {
        $kind = $this->token($kind);
        if ($kind !== '' && !in_array($kind, ['car', 'motorcycle', 'moped', 'van', 'truck', 'bus'], true)) {
            $kind = '';
        }
        $page = max(1, $page);
        return [$kind, $this->like($term), $page, self::PAGE_SIZE, 0];
    }

    /** @return array{0:int,1:int,2:int} */
    private function pageWindow(int $page, int $total): array
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
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
    }

    private function like(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[%_\\\\]+/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        if ($value === '') {
            return '';
        }
        return '%' . $value . '%';
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
