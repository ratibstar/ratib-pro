<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Providers\Activation;

final class ProviderActivationRegistry
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    /**
     * @return list<array<string, mixed>>
     */
    public function activeForScope(string $providerType, ?int $tenantId, ?int $agencyId): array
    {
        $sql = 'SELECT *
                FROM rateb_infra_provider_activations
                WHERE provider_type = :provider_type
                  AND is_enabled = 1
                  AND (tenant_id IS NULL OR tenant_id <=> :tenant_id)
                  AND (agency_id IS NULL OR agency_id <=> :agency_id)
                ORDER BY priority_weight DESC, id ASC';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'provider_type' => strtolower($providerType),
                'tenant_id' => $tenantId,
                'agency_id' => $agencyId,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setEnabled(int $activationId, bool $enabled, string $adminActor): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_provider_activations
             SET is_enabled = :is_enabled, updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute([
            'is_enabled' => $enabled ? 1 : 0,
            'updated_by' => substr($adminActor, 0, 120),
            'id' => $activationId,
        ]);
    }

    public function upsertActivation(
        string $providerType,
        string $providerCode,
        string $providerClass,
        ?int $tenantId,
        ?int $agencyId,
        int $priorityWeight,
        bool $enabled,
        string $adminActor
    ): void {
        $sql = 'INSERT INTO rateb_infra_provider_activations
                (provider_type, provider_code, provider_class, tenant_id, agency_id, priority_weight, is_enabled, created_at, updated_at, updated_by)
                VALUES
                (:provider_type, :provider_code, :provider_class, :tenant_id, :agency_id, :priority_weight, :is_enabled, NOW(), NOW(), :updated_by)
                ON DUPLICATE KEY UPDATE
                provider_class = VALUES(provider_class),
                priority_weight = VALUES(priority_weight),
                is_enabled = VALUES(is_enabled),
                updated_at = NOW(),
                updated_by = VALUES(updated_by)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'provider_type' => strtolower($providerType),
            'provider_code' => strtolower($providerCode),
            'provider_class' => $providerClass,
            'tenant_id' => $tenantId,
            'agency_id' => $agencyId,
            'priority_weight' => $priorityWeight,
            'is_enabled' => $enabled ? 1 : 0,
            'updated_by' => substr($adminActor, 0, 120),
        ]);
    }

    public function emergencyDisableByType(string $providerType, string $adminActor): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_provider_activations
             SET is_enabled = 0, updated_at = NOW(), updated_by = :updated_by
             WHERE provider_type = :provider_type'
        );
        $stmt->execute([
            'updated_by' => substr($adminActor, 0, 120),
            'provider_type' => strtolower($providerType),
        ]);
        return $stmt->rowCount();
    }

    /**
     * All activation rows (admin UI).
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT * FROM rateb_infra_provider_activations ORDER BY provider_type ASC, priority_weight DESC, id ASC'
            );
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function deleteById(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM rateb_infra_provider_activations WHERE id = :id');

            return $stmt !== false && $stmt->execute(['id' => $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

