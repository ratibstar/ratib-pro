<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface ProductSeoReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByProductUuid(string $productUuid, ?LocaleContext $locale = null): ?array;

    /**
     * Snapshot payload: canonical_url + translations list.
     *
     * @return array{canonical_url: string|null, translations: list<array<string, mixed>>}
     */
    public function buildSnapshotData(string $productUuid): array;

    /**
     * Locale-keyed rows for completeness scoring.
     *
     * @return array<string, array<string, mixed>>
     */
    public function listTranslationsByLocale(string $productUuid): array;

    public function slugExistsForLanguage(string $slug, string $languageCode, ?string $excludeProductUuid = null): bool;
}
