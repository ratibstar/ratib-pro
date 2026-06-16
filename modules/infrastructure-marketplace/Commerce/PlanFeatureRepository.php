<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Commerce;

final class PlanFeatureRepository
{
    public function __construct(private \PDO $pdo) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPlan(int $planId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rateb_infra_plan_features WHERE plan_id = :p ORDER BY feature_key ASC');
        $stmt->execute(['p' => $planId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertFeature(int $planId, string $featureKey, string $featureValue, string $featureType = 'string'): int
    {
        $sql = 'INSERT INTO rateb_infra_plan_features (plan_id, feature_key, feature_value, feature_type, created_at)
                VALUES (:plan_id, :feature_key, :feature_value, :feature_type, NOW())
                ON DUPLICATE KEY UPDATE feature_value = VALUES(feature_value), feature_type = VALUES(feature_type)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'plan_id' => $planId,
            'feature_key' => $featureKey,
            'feature_value' => $featureValue,
            'feature_type' => $featureType,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
