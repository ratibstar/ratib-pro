<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security\Secrets;

final class SecretManager
{
    private array $providers;

    /**
     * @param list<SecretProviderInterface> $providers
     */
    public function __construct(array $providers) {
        $this->providers = $providers;
    }


    public static function withEnvProvider(): self
    {
        return new self([new EnvSecretProvider()]);
    }

    public static function withDefaultProviders(?\PDO $pdo = null, ?int $tenantId = null, ?int $agencyId = null): self
    {
        $providers = [new EnvSecretProvider()];
        if ($pdo !== null) {
            $providers[] = new PreparedEncryptedDbSecretProvider($pdo);
            $providers[] = new ProviderSecretDbProvider(new ProviderSecretStore($pdo), $tenantId, $agencyId);
        }

        return new self($providers);
    }

    public function getSecret(string $scope, string $key): ?string
    {
        foreach ($this->providers as $provider) {
            $value = $provider->get($scope, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    public static function masked(?string $value): string
    {
        if ($value === null || $value === '') {
            return '[empty]';
        }
        if (strlen($value) <= 6) {
            return '***';
        }
        return substr($value, 0, 2) . str_repeat('*', max(2, strlen($value) - 4)) . substr($value, -2);
    }
}

