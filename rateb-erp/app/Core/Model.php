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
    /** @var array<int, string> Columns used for list text search (defaults to fillable). */
    protected array $searchable = [];
    protected bool $tenantScoped = false;
    protected string $tenantColumn = 'company_id';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function isTenantScoped(): bool
    {
        return $this->tenantScoped;
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

    /** Treat 0 / empty string as missing tenant id (common from unresolved ops company). */
    protected function normalizeTenantInput(array $data): array
    {
        if (!$this->tenantScoped || !array_key_exists($this->tenantColumn, $data)) {
            return $data;
        }
        $value = $data[$this->tenantColumn];
        if ($value === '' || $value === 0 || $value === '0') {
            unset($data[$this->tenantColumn]);
        }
        return $data;
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
        if ($filterId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $filterId = rateb_resolve_ops_company_id();
        }
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

    public function all(int $limit = 100, int $offset = 0, array $filters = [], string $search = ''): array
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
            if (!preg_match('/^[a-z_]+$/', (string) $column)) {
                continue;
            }
            $sql .= " AND {$column} = :f_{$column}";
            $params["f_{$column}"] = $value;
        }

        $sql .= $this->buildSearchClause($search, $params);

        $sql .= ' ORDER BY ' . $this->listOrderSql() . ' LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count(array $filters = [], string $search = ''): int
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
            if (!preg_match('/^[a-z_]+$/', (string) $column)) {
                continue;
            }
            $sql .= " AND {$column} = :f_{$column}";
            $params["f_{$column}"] = $value;
        }

        $sql .= $this->buildSearchClause($search, $params);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** @return array<int, string> */
    protected function searchableColumns(): array
    {
        if ($this->searchable !== []) {
            return $this->searchable;
        }
        $skip = ['password', 'password_hash', 'token', 'payload', 'remember_token', 'api_token'];
        $cols = ['id'];
        foreach ($this->fillable as $col) {
            if (!in_array($col, $skip, true)) {
                $cols[] = $col;
            }
        }
        return array_values(array_unique($cols));
    }

    /** @param array<string, mixed> $params */
    protected function buildSearchClause(string $search, array &$params, string $alias = ''): string
    {
        $term = trim($search);
        if ($term === '') {
            return '';
        }
        $cols = $this->searchableColumns();
        if ($cols === []) {
            return '';
        }
        $prefix = $alias !== '' ? $alias . '.' : '';
        $parts = [];
        foreach ($cols as $col) {
            if (!preg_match('/^[a-z_]+$/', $col)) {
                continue;
            }
            $key = 'search_' . $col;
            $parts[] = "CAST({$prefix}{$col} AS CHAR) LIKE :{$key}";
            $params[$key] = '%' . $term . '%';
        }
        return $parts === [] ? '' : ' AND (' . implode(' OR ', $parts) . ')';
    }

    public function create(array $data): int
    {
        $tenantValue = null;
        $tenantExplicitNull = false;
        if ($this->tenantScoped) {
            $data = $this->normalizeTenantInput($data);
            if (array_key_exists($this->tenantColumn, $data)
                && ($data[$this->tenantColumn] === null || $data[$this->tenantColumn] === '')) {
                if (!TenantContext::isSuperAdmin()) {
                    throw new \RuntimeException('Company context required for tenant-scoped create.');
                }
                $tenantExplicitNull = true;
            } elseif (!empty($data[$this->tenantColumn])) {
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
            if ($tenantExplicitNull) {
                $data[$this->tenantColumn] = null;
            } elseif ($tenantValue !== null && $tenantValue > 0) {
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
        try {
            $stmt->execute($data);
        } catch (\PDOException $e) {
            throw \Rateb\App\Services\DatabaseErrorService::toRuntimeException($e);
        }
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
        try {
            return $stmt->execute($data);
        } catch (\PDOException $e) {
            throw \Rateb\App\Services\DatabaseErrorService::toRuntimeException($e);
        }
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $id];

        [$extra, $extraParams] = $this->tenantFilterClause();
        $sql .= $extra;
        $params = array_merge($params, $extraParams);

        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            throw \Rateb\App\Services\DatabaseErrorService::toRuntimeException($e);
        }
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

    /** Newest records first — used by all list queries. */
    protected function listOrderSql(string $alias = ''): string
    {
        if (function_exists('rateb_list_order_sql')) {
            return rateb_list_order_sql($alias, $this->tableHasCreatedAt());
        }
        $prefix = $alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '';
        $pk = $prefix . $this->primaryKey;
        if ($this->tableHasCreatedAt()) {
            return "{$prefix}created_at DESC, {$pk} DESC";
        }
        return "{$pk} DESC";
    }

    protected function tableHasCreatedAt(): bool
    {
        static $cache = [];
        $table = $this->table;
        if (!array_key_exists($table, $cache)) {
            $stmt = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE 'created_at'");
            $cache[$table] = (bool) $stmt->fetch();
        }
        return $cache[$table];
    }

    /**
     * Next document number for this tenant: PREFIX-00001 style.
     * Uses MAX(suffix)+1 so gaps/deletes do not reuse existing numbers.
     */
    protected function nextSequentialNo(string $prefix, string $numberColumn, int $padLength = 4): string
    {
        if (!preg_match('/^[a-z_]+$/', $numberColumn)) {
            throw new \InvalidArgumentException('Invalid number column');
        }
        $companyId = (int) (TenantContext::companyId() ?? 0);
        $startPos = strlen($prefix) + 1;
        $sql = sprintf(
            'SELECT MAX(CAST(SUBSTRING(%s, %d) AS UNSIGNED)) AS m FROM %s WHERE %s = :cid AND %s LIKE :like',
            $numberColumn,
            $startPos,
            $this->table,
            $this->tenantColumn,
            $numberColumn
        );
        $row = $this->queryOne($sql, ['cid' => $companyId, 'like' => $prefix . '%']);
        $next = (int) ($row['m'] ?? 0) + 1;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix . str_pad((string) ($next + $attempt), $padLength, '0', STR_PAD_LEFT);
            $exists = $this->queryOne(
                sprintf(
                    'SELECT id FROM %s WHERE %s = :cid AND %s = :no LIMIT 1',
                    $this->table,
                    $this->tenantColumn,
                    $numberColumn
                ),
                ['cid' => $companyId, 'no' => $candidate]
            );
            if ($exists === null) {
                return $candidate;
            }
        }

        return $prefix . str_pad((string) ($next + 10), $padLength, '0', STR_PAD_LEFT);
    }

    public function generateDocumentCode(string $prefix, string $numberColumn, int $padLength = 4): string
    {
        return $this->nextSequentialNo($prefix, $numberColumn, $padLength);
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $this->executePrepared($stmt, $sql, $params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $this->executePrepared($stmt, $sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $params */
    private function executePrepared(\PDOStatement $stmt, string $sql, array $params): void
    {
        if (!preg_match_all('/:(\w+)/', $sql, $matches) || $matches[1] === []) {
            try {
                $stmt->execute();
            } catch (\PDOException $e) {
                throw \Rateb\App\Services\DatabaseErrorService::toRuntimeException($e);
            }
            return;
        }
        $pos = 1;
        foreach ($matches[1] as $name) {
            if (!array_key_exists($name, $params)) {
                throw new \InvalidArgumentException("Missing SQL parameter :{$name}");
            }
            $stmt->bindValue($pos++, $params[$name], $this->pdoParamType($params[$name]));
        }
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            throw \Rateb\App\Services\DatabaseErrorService::toRuntimeException($e);
        }
    }

    /** @param mixed $value */
    private function pdoParamType($value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }
        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }
        if ($value === null) {
            return PDO::PARAM_NULL;
        }
        return PDO::PARAM_STR;
    }
}
