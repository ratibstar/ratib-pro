<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Security\Secrets;

interface SecretProviderInterface
{
    public function get(string $scope, string $key): ?string;
}

