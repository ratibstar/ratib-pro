<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ErpSyncReadRepositoryInterface
{
    /**
     * @return array{items: list<array<string, mixed>>, since: ?string}
     */
    public function syncStatusForCompany(int $companyId, ?string $sinceToken, int $limit): array;
}
