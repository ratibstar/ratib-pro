<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Commerce;

/**
 * PDO access for ratib_infra_products (additive commerce layer).
 */
final class ProductRepository
{
    public function __construct(private \PDO $pdo) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $sql = 'SELECT * FROM ratib_infra_products WHERE active = 1 ORDER BY id ASC';
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByProductCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ratib_infra_products WHERE product_code = :c LIMIT 1');
        $stmt->execute(['c' => $code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row columns matching table (excluding id if autoincrement)
     */
    public function insert(array $row): int
    {
        $cols = [
            'product_code', 'product_type', 'display_name', 'description', 'active', 'visibility_state',
            'lifecycle_state', 'provider_binding', 'tenant_scope_mode', 'agency_scope_mode',
            'feature_flags_json', 'metadata_json',
        ];
        $fields = [];
        $params = [];
        foreach ($cols as $c) {
            if (!array_key_exists($c, $row)) {
                continue;
            }
            $fields[] = $c;
            $params[$c] = $row[$c];
        }
        if ($fields === []) {
            throw new \InvalidArgumentException('No columns for insert.');
        }
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $fields);
        $sql = 'INSERT INTO ratib_infra_products (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $this->pdo->lastInsertId();
    }
}
