<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security\Secrets;

/**
 * Preparation abstraction only. Real decryption backend can be added without changing callers.
 */
final class PreparedEncryptedDbSecretProvider implements SecretProviderInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    public function get(string $scope, string $key): ?string
    {
        $sql = 'SELECT encrypted_value FROM ratib_infra_secret_refs
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
        return null;
    }
}

