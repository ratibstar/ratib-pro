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

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $id];

        if ($this->tenantScoped) {
            $companyId = TenantContext::companyId();
            if ($companyId === null) {
                return null;
            }
            $sql .= " AND {$this->tenantColumn} = :company_id";
            $params['company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($this->tenantScoped) {
            $companyId = TenantContext::companyId();
            if ($companyId === null) {
                return [];
            }
            $sql .= " AND {$this->tenantColumn} = :company_id";
            $params['company_id'] = $companyId;
        }

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

        if ($this->tenantScoped) {
            $companyId = TenantContext::companyId();
            if ($companyId === null) {
                return 0;
            }
            $sql .= " AND {$this->tenantColumn} = :company_id";
            $params['company_id'] = $companyId;
        }

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
        if ($this->tenantScoped) {
            $data[$this->tenantColumn] = TenantContext::companyId();
        }

        $data = $this->filterFillable($data);
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

        if ($this->tenantScoped) {
            $companyId = TenantContext::companyId();
            if ($companyId === null) {
                return false;
            }
            $sql .= " AND {$this->tenantColumn} = :company_id";
            $data['company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $id];

        if ($this->tenantScoped) {
            $companyId = TenantContext::companyId();
            if ($companyId === null) {
                return false;
            }
            $sql .= " AND {$this->tenantColumn} = :company_id";
            $params['company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
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
