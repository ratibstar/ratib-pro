<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security\Secrets;

final class SecretManager
{
    /**
     * @param list<SecretProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers
    ) {}

    public static function withEnvProvider(): self
    {
        return new self([new EnvSecretProvider()]);
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

