<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    /** @var array<int, string> */
    protected array $fillable = [];
    protected bool $tenantScoped = false;
    protected string $tenantColumn = 'company_id';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Super Admin on admin portal sees all tenants; company users stay scoped. */
    protected function appliesTenantScope(): bool
    {
        if (!$this->tenantScoped) {
            return false;
        }
        if (TenantContext::isSuperAdmin()) {
            return false;
        }
        return TenantContext::companyId() !== null;
    }

    /** @return array{0:string,1:array<string,mixed>} */
    protected function tenantFilterClause(string $alias = ''): array
    {
        if (!$this->appliesTenantScope()) {
            $adminFilter = $this->adminCompanyFilterClause($alias);
            if ($adminFilter[0] !== '') {
                return $adminFilter;
            }
            return ['', []];
        }

        $col = ($alias !== '' ? $alias . '.' : '') . $this->tenantColumn;
        return [" AND {$col} = :company_id", ['company_id' => TenantContext::companyId()]];
    }

    /** Optional ?company_id= filter for Super Admin list views. */
    /** @return array{0:string,1:array<string,mixed>} */
    protected function adminCompanyFilterClause(string $alias = ''): array
    {
        if (!TenantContext::isSuperAdmin() || !$this->tenantScoped) {
            return ['', []];
        }
        $filterId = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
        if ($filterId < 1) {
            return ['', []];
        }
        $col = ($alias !== '' ? $alias . '.' : '') . $this->tenantColumn;
        return [" AND {$col} = :admin_company_filter", ['admin_company_filter' => $filterId]];
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $id];

        [$extra, $extraParams] = $this->tenantFilterClause();
        $sql .= $extra;
        $params = array_merge($params, $extraParams);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        [$extra, $extraParams] = $this->tenantFilterClause();
        $sql .= $extra;
        $params = array_merge($params, $extraParams);

        foreach ($filters as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $sql .= " AND {$column} = :f_{$column}";
            $params["f_{$column}"] = $value;
        }

        $sql .= " ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE 1=1";
        $params = [];

        [$extra, $extraParams] = $this->tenantFilterClause();
        $sql .= $extra;
        $params = array_merge($params, $extraParams);

        foreach ($filters as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $sql .= " AND {$column} = :f_{$column}";
            $params["f_{$column}"] = $value;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    public function create(array $data): int
    {
        $tenantValue = null;
        if ($this->tenantScoped) {
            if (!empty($data[$this->tenantColumn])) {
                $tenantValue = (int) $data[$this->tenantColumn];
            } else {
                $companyId = TenantContext::companyId();
                if ($companyId !== null && $companyId > 0) {
                    $tenantValue = $companyId;
                    $data[$this->tenantColumn] = $companyId;
                } elseif (!TenantContext::isSuperAdmin()) {
                    throw new \RuntimeException('Company context required for tenant-scoped create.');
                }
            }
        }

        $data = $this->filterFillable($data);

        if ($this->tenantScoped) {
            if ($tenantValue !== null && $tenantValue > 0) {
                $data[$this->tenantColumn] = $tenantValue;
            } elseif (empty($data[$this->tenantColumn]) || (int) $data[$this->tenantColumn] < 1) {
                throw new \RuntimeException('Company context required for tenant-scoped create.');
            }
        }

        if ($data === []) {
            throw new \RuntimeException('No data to insert.');
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        if (empty($data)) {
            return false;
        }

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = :{$column}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;

        [$extra, $extraParams] = $this->tenantFilterClause();
        $sql .= $extra;
        $data = array_merge($data, $extraParams);

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $id];

        [$extra, $extraParams] = $this->tenantFilterClause();
        $sql .= $extra;
        $params = array_merge($params, $extraParams);

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /** @param array<int, int> $ids */
    public function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete($id)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
