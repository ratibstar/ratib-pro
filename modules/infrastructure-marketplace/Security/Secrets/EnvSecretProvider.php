<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security\Secrets;

final class EnvSecretProvider implements SecretProviderInterface
{
    public function get(string $scope, string $key): ?string
    {
        $envKey = strtoupper(trim($scope . '_' . $key));
        $v = getenv($envKey);
        return is_string($v) && $v !== '' ? $v : null;
    }
}

