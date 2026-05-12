<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Commerce;

final class PlanRepository
{
    public function __construct(private \PDO $pdo) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ratib_infra_plans WHERE product_id = :p ORDER BY id ASC');
        $stmt->execute(['p' => $productId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByProductAndPlanCode(int $productId, string $planCode): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ratib_infra_plans WHERE product_id = :p AND plan_code = :c LIMIT 1'
        );
        $stmt->execute(['p' => $productId, 'c' => $planCode]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        $cols = [
            'product_id', 'plan_code', 'display_name', 'billing_cycle', 'currency', 'base_price', 'setup_fee',
            'commerce_state', 'provisioning_profile', 'metadata_json',
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
        $sql = 'INSERT INTO ratib_infra_plans (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $this->pdo->lastInsertId();
    }
}
