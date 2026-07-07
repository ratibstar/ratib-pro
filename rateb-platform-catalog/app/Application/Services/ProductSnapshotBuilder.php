<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\ProductMapper;
use Rateb\PlatformCatalog\Application\Support\CatalogLocales;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessDataReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotGraphReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationReadRepositoryInterface;

final class ProductSnapshotBuilder
{
    public function __construct(
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductTranslationReadRepositoryInterface $translationReadRepository,
        private readonly ProductAttributeReadRepositoryInterface $attributeReadRepository,
        private readonly ProductRelationReadRepositoryInterface $relationReadRepository,
        private readonly ProductSeoReadRepositoryInterface $seoReadRepository,
        private readonly CompletenessDataReadRepositoryInterface $completenessDataReadRepository,
        private readonly ProductSnapshotGraphReadRepositoryInterface $graphReadRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $productUuid, ?int $entityVersion = null): array
    {
        $translations = [];
        foreach (CatalogLocales::supported() as $localeCode) {
            $locale = new LocaleContext($localeCode, CatalogLocales::supported()[0] ?? 'en');
            $rows = $this->translationReadRepository->listForProduct($productUuid, $locale);
            foreach ($rows as $row) {
                $translations[(string) $row['language_code']] = $row;
            }
        }

        $defaultLocale = new LocaleContext('en', 'ar');
        $productRow = $this->productReadRepository->findByUuid($productUuid, $defaultLocale);

        $seo = $this->seoReadRepository->buildSnapshotData($productUuid);
        $graph = $this->graphReadRepository->buildForProduct($productUuid);

        return [
            'product' => $productRow !== null ? ProductMapper::toProductDto($productRow)->toArray() : [],
            'translations' => array_values($translations),
            'attributes' => $this->attributeReadRepository->listByProductUuid($productUuid, $defaultLocale),
            'relations' => $this->relationReadRepository->listByProductUuid($productUuid, $defaultLocale),
            'seo' => $seo,
            'variants' => $graph['variants'],
            'product_barcodes' => $graph['product_barcodes'],
            'bundle_components' => $graph['bundle_components'],
            'images' => $graph['images'],
            'files' => $graph['files'],
            'videos' => $graph['videos'],
            'seo_translations' => $this->completenessDataReadRepository->listSeoTranslationsByLocale($productUuid),
            'image_translations' => $this->completenessDataReadRepository->listImageTranslationsByLocale($productUuid),
            'variant_translations' => $this->completenessDataReadRepository->listVariantTranslationsByLocale($productUuid),
            'entity_version' => $entityVersion,
        ];
    }
}
