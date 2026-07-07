<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Tests\Support;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoReadRepositoryInterface;

final class EmptyProductSeoReadRepository implements ProductSeoReadRepositoryInterface
{
    public function findByProductUuid(string $productUuid, ?LocaleContext $locale = null): ?array
    {
        return null;
    }

    public function buildSnapshotData(string $productUuid): array
    {
        return [
            'canonical_url' => null,
            'translations' => [],
        ];
    }

    public function listTranslationsByLocale(string $productUuid): array
    {
        return [];
    }

    public function slugExistsForLanguage(string $slug, string $languageCode, ?string $excludeProductUuid = null): bool
    {
        return false;
    }
}
