<?php
/**
 * EN: Handles core framework/runtime behavior in `core/query/TenantQuery.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/query/TenantQuery.php`.
 */

/**
 * TenantQuery
 *
 * Minimal intent-based tenant query contract (no ORM).
 * Generates SQL + params while auto-injecting tenant_id by design.
 */
final class TenantQuery
{
    private string $type = '';
    private string $table = '';
    /** @var array<int, string> */
    private array $selectColumns = ['*'];
    /** @var array<string, mixed> */
    private array $insertData = [];
    /** @var array<string, mixed> */
    private array $updateData = [];
    /** @var array<int, array{sql:string, params:array<int,mixed>}> */
    private array $whereClauses = [];
    private bool $tenantScoped = true;

    private function __construct()
    {
    }

    public static function select(array $columns = ['*']): self
    {
        $q = new self();
        $q->type = 'SELECT';
        $q->selectColumns = !empty($columns) ? array_values($columns) : ['*'];
        return $q;
    }

    public static function insert(string $table, array $data): self
    {
        $q = new self();
        $q->type = 'INSERT';
        $q->table = trim($table);
        $q->insertData = $data;
        return $q;
    }

    public static function update(string $table, array $data): self
    {
        $q = new self();
        $q->type = 'UPDATE';
        $q->table = trim($table);
        $q->updateData = $data;
        return $q;
    }

    public static function delete(): self
    {
        $q = new self();
        $q->type = 'DELETE';
        return $q;
    }

    public function from(string $table): self
    {
        $this->table = trim($table);
        return $this;
    }

    /**
     * Keep for explicit intent readability; tenant scope is on by default.
     */
    public function whereTenantScoped(): self
    {
        $this->tenantScoped = true;
        return $this;
    }

    /**
     * Add raw where fragment with positional params.
     */
    public function where(string $conditionSql, array $params = []): self
    {
        $conditionSql = trim($conditionSql);
        if ($conditionSql !== '') {
            $this->whereClauses[] = [
                'sql' => $conditionSql,
                'params' => array_values($params),
            ];
        }
        return $this;
    }

    /**
     * @return array{sql:string, params:array<int,mixed>}
     */
    public function toSqlAndParams(): array
    {
        if (!class_exists('TenantExecutionContext', false)) {
            require_once __DIR__ . '/../TenantExecutionContext.php';
        }

        $isSystem = TenantExecutionContext::isInitialized() && TenantExecutionContext::isSystemContext();
        $tenantId = null;
        if (!$isSystem) {
            // Guaranteed tenant scope for tenant execution contexts.
            $tenantId = TenantExecutionContext::requireTenantId();
        }

        $table = trim($this->table);
        if ($table === '') {
            throw new RuntimeException('TenantQuery: table is required.');
        }

        $params = [];
        $sql = '';

        if ($this->type === 'SELECT') {
            $cols = implode(', ', array_map(static fn($c) => trim((string) $c), $this->selectColumns));
            $sql = "SELECT {$cols} FROM {$table}";
            [$whereSql, $whereParams] = $this->buildWhere($tenantId, $isSystem);
            if ($whereSql !== '') {
                $sql .= ' WHERE ' . $whereSql;
                $params = array_merge($params, $whereParams);
            }
        } elseif ($this->type === 'INSERT') {
            $data = $this->insertData;
            if (!$isSystem) {
                if (array_key_exists('tenant_id', $data) && (int) $data['tenant_id'] !== (int) $tenantId) {
                    throw new RuntimeException('TenantQuery: tenant_id mismatch in insert payload.');
                }
                $data['tenant_id'] = (int) $tenantId;
            }
            if (empty($data)) {
                throw new RuntimeException('TenantQuery: insert payload is empty.');
            }
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $params = array_values($data);
        } elseif ($this->type === 'UPDATE') {
            $data = $this->updateData;
            if (!$isSystem && array_key_exists('tenant_id', $data)) {
                if ((int) $data['tenant_id'] !== (int) $tenantId) {
                    throw new RuntimeException('TenantQuery: tenant_id mismatch in update payload.');
                }
            }
            if (empty($data)) {
                throw new RuntimeException('TenantQuery: update payload is empty.');
            }
            $setParts = [];
            foreach ($data as $col => $value) {
                $setParts[] = $col . ' = ?';
                $params[] = $value;
            }
            $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $setParts);
            [$whereSql, $whereParams] = $this->buildWhere($tenantId, $isSystem);
            if ($whereSql !== '') {
                $sql .= ' WHERE ' . $whereSql;
                $params = array_merge($params, $whereParams);
            } elseif (!$isSystem) {
                // Tenant updates must stay scoped, even without extra filters.
                $sql .= ' WHERE tenant_id = ?';
                $params[] = (int) $tenantId;
            }
        } elseif ($this->type === 'DELETE') {
            $sql = 'DELETE FROM ' . $table;
            [$whereSql, $whereParams] = $this->buildWhere($tenantId, $isSystem);
            if ($whereSql !== '') {
                $sql .= ' WHERE ' . $whereSql;
                $params = array_merge($params, $whereParams);
            } elseif (!$isSystem) {
                $sql .= ' WHERE tenant_id = ?';
                $params[] = (int) $tenantId;
            }
        } else {
            throw new RuntimeException('TenantQuery: unsupported query type.');
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(?int $tenantId, bool $isSystem): array
    {
        $parts = [];
        $params = [];

        if (!$isSystem && $this->tenantScoped) {
            $parts[] = 'tenant_id = ?';
            $params[] = (int) $tenantId;
        }

        foreach ($this->whereClauses as $clause) {
            $parts[] = '(' . $clause['sql'] . ')';
            $params = array_merge($params, $clause['params']);
        }

        return [implode(' AND ', $parts), $params];
    }
}

