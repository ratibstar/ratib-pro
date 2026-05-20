<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security\Secrets;

final class ProviderSecretStore
{
    private \PDO $pdo;
    private ProviderSecretCipher $cipher;

    public function __construct(\PDO $pdo, ?ProviderSecretCipher $cipher = null)
    {
        $this->pdo = $pdo;
        $this->cipher = $cipher ?? new ProviderSecretCipher();
    }

    public function upsert(
        string $providerScope,
        string $secretKey,
        string $plainValue,
        ?int $tenantId = null,
        ?int $agencyId = null,
        string $actor = 'system'
    ): void {
        $providerScope = strtolower(trim($providerScope));
        $secretKey = strtoupper(trim($secretKey));
        if ($providerScope === '' || $secretKey === '') {
            throw new \RuntimeException('providerScope and secretKey are required.');
        }
        $enc = $this->cipher->encrypt($plainValue);
        $sql = 'INSERT INTO ratib_infra_provider_secrets
                (provider_scope, secret_key, encrypted_value, tenant_id, agency_id, is_active, updated_by, created_at, updated_at)
                VALUES
                (:provider_scope, :secret_key, :encrypted_value, :tenant_id, :agency_id, 1, :updated_by, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                encrypted_value = VALUES(encrypted_value),
                is_active = 1,
                updated_by = VALUES(updated_by),
                updated_at = NOW()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'provider_scope' => $providerScope,
            'secret_key' => $secretKey,
            'encrypted_value' => $enc,
            'tenant_id' => $tenantId,
            'agency_id' => $agencyId,
            'updated_by' => substr($actor, 0, 120),
        ]);
    }

    public function get(
        string $providerScope,
        string $secretKey,
        ?int $tenantId = null,
        ?int $agencyId = null
    ): ?string {
        $providerScope = strtolower(trim($providerScope));
        $secretKey = strtoupper(trim($secretKey));
        if ($providerScope === '' || $secretKey === '') {
            return null;
        }
        $sql = 'SELECT encrypted_value
                FROM ratib_infra_provider_secrets
                WHERE provider_scope = :provider_scope
                  AND secret_key = :secret_key
                  AND is_active = 1
                  AND (tenant_id IS NULL OR tenant_id <=> :tenant_id)
                  AND (agency_id IS NULL OR agency_id <=> :agency_id)
                ORDER BY
                  CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END ASC,
                  CASE WHEN agency_id IS NULL THEN 1 ELSE 0 END ASC,
                  id DESC
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'provider_scope' => $providerScope,
            'secret_key' => $secretKey,
            'tenant_id' => $tenantId,
            'agency_id' => $agencyId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row) || !isset($row['encrypted_value'])) {
            return null;
        }

        return $this->cipher->decrypt((string) $row['encrypted_value']);
    }
}
