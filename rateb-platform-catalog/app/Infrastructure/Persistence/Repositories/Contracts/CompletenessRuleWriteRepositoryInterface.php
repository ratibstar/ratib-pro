<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface CompletenessRuleWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function updateByCode(string $code, array $data): bool;
}
