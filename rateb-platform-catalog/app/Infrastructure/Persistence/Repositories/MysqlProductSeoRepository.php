<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoRepositoryInterface;

/**
 * Combined MySQL repository implementing read and write SEO operations.
 */
final class MysqlProductSeoRepository extends MysqlProductSeoReadRepository implements ProductSeoRepositoryInterface
{
    private ?MysqlProductSeoWriteRepository $writer = null;

    public function upsertForProduct(
        string $productUuid,
        ?string $canonicalUrl,
        array $translations,
        ?int $actorId = null
    ): string {
        return $this->writer()->upsertForProduct($productUuid, $canonicalUrl, $translations, $actorId);
    }

    public function replaceFromSnapshot(string $productUuid, array $seoData, ?int $actorId = null): void
    {
        $this->writer()->replaceFromSnapshot($productUuid, $seoData, $actorId);
    }

    private function writer(): MysqlProductSeoWriteRepository
    {
        return $this->writer ??= new MysqlProductSeoWriteRepository($this->readPdo, $this->writePdo);
    }
}
