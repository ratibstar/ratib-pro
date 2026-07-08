<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ErpSyncWriteRepositoryInterface
{
    public function upsertSyncRecord(int $companyId, int $productId, ?int $variantId, int $version, string $status): void;

    public function writeSyncLog(int $companyId, string $productUuid, int $fromVersion, int $toVersion, string $action, ?array $payload): void;
}
