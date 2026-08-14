<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Company extends Model
{
    protected string $table = 'rateb_companies';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'name', 'slug', 'email', 'phone', 'address', 'country', 'logo_path',
        'status', 'plan_id', 'storage_limit_mb', 'user_limit', 'modules', 'settings',
    ];

    /**
     * PERF-P0.3-B — wire Model::find into existing rateb_ops_company_request_state rows memo.
     * No new cache layer; same request-scoped bag used by rateb_ops_company_exists().
     */
    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        if (function_exists('rateb_ops_company_request_state')) {
            $state = &rateb_ops_company_request_state();
            if (!isset($state['rows']) || !is_array($state['rows'])) {
                $state['rows'] = [];
            }
            if (array_key_exists($id, $state['rows'])) {
                return $state['rows'][$id];
            }
        }

        $row = parent::find($id);

        if (function_exists('rateb_ops_company_request_state')) {
            $state = &rateb_ops_company_request_state();
            if (!isset($state['rows']) || !is_array($state['rows'])) {
                $state['rows'] = [];
            }
            $state['rows'][$id] = $row;
            $state['exists'][$id] = is_array($row) && (int) ($row['id'] ?? 0) === $id;
        }

        return $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rateb_companies WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function suspend(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE rateb_companies SET status = 'suspended' WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function activate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE rateb_companies SET status = 'active' WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /** @param list<string> $modules */
    public function updateModules(int $id, array $modules): bool
    {
        $json = json_encode(array_values($modules), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE rateb_companies SET modules = :modules WHERE id = :id');
        return $stmt->execute(['modules' => $json, 'id' => $id]);
    }

    public function getStats(): array
    {
        $row = $this->queryOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended_count
             FROM rateb_companies"
        );
        if (!is_array($row)) {
            return ['total' => 0, 'active' => 0, 'suspended' => 0];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active_count'] ?? $row['active'] ?? 0),
            'suspended' => (int) ($row['suspended_count'] ?? $row['suspended'] ?? 0),
        ];
    }
}
