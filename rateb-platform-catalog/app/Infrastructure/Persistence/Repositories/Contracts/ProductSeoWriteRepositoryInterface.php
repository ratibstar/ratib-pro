<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductSeoWriteRepositoryInterface
{
    /**
     * @param list<array<string, mixed>> $translations
     */
    public function upsertForProduct(
        string $productUuid,
        ?string $canonicalUrl,
        array $translations,
        ?int $actorId = null
    ): string;

    /**
     * @param array<string, mixed> $seoData canonical_url + translations (list or locale-keyed)
     */
    public function replaceFromSnapshot(string $productUuid, array $seoData, ?int $actorId = null): void;
}
