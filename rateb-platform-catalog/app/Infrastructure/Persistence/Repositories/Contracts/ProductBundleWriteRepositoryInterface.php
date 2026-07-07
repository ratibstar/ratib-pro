<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductBundleWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param list<array<string, mixed>> $components
     */
    public function replaceBundle(string $bundleProductUuid, array $components, ?int $actorId = null): void;
}
