<?php
/**
 * EN: Handles core framework/runtime behavior in `core/query/StructuredQuery.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/query/StructuredQuery.php`.
 */

/**
 * Lightweight structured SQL envelope (not a full SQL parser).
 */
final class StructuredQuery
{
    private string $type;
    private ?string $table;
    /** @var array<int, string> */
    private array $columns;
    /** @var array<int, string> */
    private array $where;
    /** @var mixed */
    private $params;
    private bool $structured;
    private string $reason;
    private string $rawSql;

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $where
     * @param mixed $params
     */
    private function __construct(
        string $type,
        ?string $table,
        array $columns,
        array $where,
        $params,
        bool $structured,
        string $reason,
        string $rawSql
    ) {
        $this->type = $type;
        $this->table = $table;
        $this->columns = $columns;
        $this->where = $where;
        $this->params = $params;
        $this->structured = $structured;
        $this->reason = $reason;
        $this->rawSql = $rawSql;
    }

    /**
     * Minimal parser:
     * - detect statement type (SELECT/INSERT/UPDATE/DELETE)
     * - detect primary table
     * - detect basic WHERE/columns tenant_id presence
     */
    public static function fromSql($sql, $params): self
    {
        $rawSql = (string) $sql;
        $normalized = trim((string) preg_replace('/\s+/', ' ', $rawSql));
        if ($normalized === '') {
            return new self('UNSTRUCTURED', null, [], [], $params, false, 'empty_sql', $rawSql);
        }

        $lower = strtolower($normalized);
        $type = self::detectType($lower);
        if ($type === null) {
            return new self('UNSTRUCTURED', null, [], [], $params, false, 'unsupported_statement', $rawSql);
        }

        $table = self::extractTable($normalized, $type);
        if ($table === null || $table === '') {
            return new self('UNSTRUCTURED', null, [], [], $params, false, 'table_not_detected', $rawSql);
        }

        $columns = self::extractInsertColumns($normalized, $type);
        $where = self::extractWhereFragments($normalized);

        return new self($type, $table, $columns, $where, $params, true, 'ok', $rawSql);
    }

    private static function detectType(string $lowerSql): ?string
    {
        if (preg_match('/^\s*(select|insert|update|delete)\b/i', $lowerSql, $m)) {
            return strtoupper((string) $m[1]);
        }
        if (preg_match('/^\s*with\b[\s\S]*?\)\s*(select|insert|update|delete)\b/i', $lowerSql, $m)) {
            return strtoupper((string) $m[1]);
        }
        return null;
    }

    private static function extractTable(string $sql, string $type): ?string
    {
        $pattern = '';
        if ($type === 'SELECT') {
            $pattern = '/\bfrom\s+([`"\w\.]+)/i';
        } elseif ($type === 'INSERT') {
            $pattern = '/\binsert\s+into\s+([`"\w\.]+)/i';
        } elseif ($type === 'UPDATE') {
            $pattern = '/\bupdate\s+([`"\w\.]+)/i';
        } elseif ($type === 'DELETE') {
            $pattern = '/\bdelete\s+from\s+([`"\w\.]+)/i';
        }

        if ($pattern !== '' && preg_match($pattern, $sql, $m)) {
            return trim((string) $m[1], " \t\n\r\0\x0B`\"'");
        }
        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function extractInsertColumns(string $sql, string $type): array
    {
        if ($type !== 'INSERT') {
            return [];
        }
        if (!preg_match('/\binsert\s+into\s+[^\(]+\(([^)]*)\)\s*values\s*\(/i', $sql, $m)) {
            return [];
        }
        $cols = array_map('trim', explode(',', (string) $m[1]));
        return array_map(static function ($c) {
            return strtolower(trim((string) $c, " \t\n\r\0\x0B`\"'"));
        }, $cols);
    }

    /**
     * @return array<int, string>
     */
    private static function extractWhereFragments(string $sql): array
    {
        if (!preg_match('/\bwhere\b([\s\S]*)$/i', $sql, $m)) {
            return [];
        }
        $tail = trim((string) $m[1]);
        if ($tail === '') {
            return [];
        }
        return [$tail];
    }

    public function isStructured(): bool
    {
        return $this->structured;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * @return array<int, string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, string>
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * @return mixed
     */
    public function getParams()
    {
        return $this->params;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getRawSql(): string
    {
        return $this->rawSql;
    }

    public function whereContainsTenantId(): bool
    {
        foreach ($this->where as $frag) {
            if (preg_match('/\btenant_id\b/i', $frag)) {
                return true;
            }
        }
        return false;
    }

    public function insertContainsTenantIdColumn(): bool
    {
        return in_array('tenant_id', $this->columns, true);
    }
}

