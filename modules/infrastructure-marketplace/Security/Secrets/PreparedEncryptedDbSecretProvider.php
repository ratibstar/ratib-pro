<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Security\Secrets;

/**
 * Preparation abstraction only. Real decryption backend can be added without changing callers.
 */
final class PreparedEncryptedDbSecretProvider implements SecretProviderInterface
{
    private \PDO $pdo;
    private ProviderSecretCipher $cipher;

    public function __construct(\PDO $pdo, ?ProviderSecretCipher $cipher = null) {
        $this->pdo = $pdo;
        $this->cipher = $cipher ?? new ProviderSecretCipher();
    }


    public function get(string $scope, string $key): ?string
    {
        try {
            $sql = 'SELECT encrypted_value FROM rateb_infra_secret_refs
                    WHERE scope_key = :scope_key AND secret_key = :secret_key AND is_active = 1
                    LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'scope_key' => $scope,
                'secret_key' => $key,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row) || !isset($row['encrypted_value'])) {
                return null;
            }

            return $this->cipher->decrypt((string) $row['encrypted_value']);
        } catch (\PDOException $e) {
            // Table optional until legacy secret_refs migration is applied; fall through to ProviderSecretDbProvider.
            return null;
        }
    }
}

