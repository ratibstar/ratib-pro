<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security\Secrets;

interface SecretProviderInterface
{
    public function get(string $scope, string $key): ?string;
}

