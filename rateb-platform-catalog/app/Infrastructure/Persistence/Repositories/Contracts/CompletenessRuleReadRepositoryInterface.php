<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface CompletenessRuleReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(?string $entityType = 'product'): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array;
}
