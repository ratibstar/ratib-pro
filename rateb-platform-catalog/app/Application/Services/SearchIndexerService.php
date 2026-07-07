<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Support\CatalogLocales;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Search\ReindexReport;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterInterface;
use Rateb\PlatformCatalog\Support\Uuid;

final class SearchIndexerService
{
    public function __construct(
        private readonly SearchAdapterInterface $searchAdapter,
        private readonly SearchIndexReadRepositoryInterface $indexReadRepository,
        private readonly SearchIndexQueueReadRepositoryInterface $queueReadRepository,
        private readonly SearchIndexQueueWriteRepositoryInterface $queueWriteRepository
    ) {
    }

    public function indexProductEverywhere(string $productUuid): void
    {
        foreach (CatalogLocales::supported() as $locale) {
            $this->indexProduct($productUuid, $locale);
        }
    }

    public function indexVariantEverywhere(string $variantUuid): void
    {
        foreach (CatalogLocales::supported() as $locale) {
            $this->indexVariant($variantUuid, $locale);
        }
    }

    public function deleteProductEverywhere(string $productUuid): void
    {
        foreach (CatalogLocales::supported() as $locale) {
            $this->searchAdapter->deleteProduct($productUuid, $locale);
            $this->enqueueProduct($productUuid, $locale, 'delete');
        }
    }

    public function deleteVariantEverywhere(string $variantUuid): void
    {
        foreach (CatalogLocales::supported() as $locale) {
            $this->searchAdapter->deleteVariant($variantUuid, $locale);
            $this->enqueueVariant($variantUuid, $locale, 'delete');
        }
    }

    public function enqueueProductEverywhere(string $productUuid, string $action = 'upsert'): void
    {
        foreach (CatalogLocales::supported() as $locale) {
            $this->enqueueProduct($productUuid, $locale, $action);
        }
    }

    public function enqueueVariantEverywhere(string $variantUuid, string $action = 'upsert'): void
    {
        foreach (CatalogLocales::supported() as $locale) {
            $this->enqueueVariant($variantUuid, $locale, $action);
        }
    }

    public function indexProduct(string $productUuid, string $locale): void
    {
        $document = $this->indexReadRepository->buildProductDocument($productUuid, $locale);
        if ($document === null) {
            $this->searchAdapter->deleteProduct($productUuid, $locale);

            return;
        }

        $this->searchAdapter->indexProduct($document, $locale);
        foreach ($this->indexReadRepository->listVariantsForProduct($productUuid, $locale) as $variant) {
            $this->searchAdapter->indexVariant($variant, $locale);
        }
    }

    public function indexVariant(string $variantUuid, string $locale): void
    {
        $document = $this->indexReadRepository->buildVariantDocument($variantUuid, $locale);
        if ($document === null) {
            $this->searchAdapter->deleteVariant($variantUuid, $locale);

            return;
        }

        $this->searchAdapter->indexVariant($document, $locale);
    }

    public function enqueueProduct(string $productUuid, string $locale, string $action = 'upsert'): string
    {
        return $this->queueWriteRepository->enqueue('product', $productUuid, $locale, $action);
    }

    public function enqueueVariant(string $variantUuid, string $locale, string $action = 'upsert'): string
    {
        return $this->queueWriteRepository->enqueue('variant', $variantUuid, $locale, $action);
    }

    public function processSearchIndexQueue(int $limit = 100): int
    {
        $processed = 0;
        foreach ($this->queueReadRepository->listPending($limit) as $item) {
            try {
                if ($item['action'] === 'delete') {
                    if ($item['entity_type'] === 'variant') {
                        $this->searchAdapter->deleteVariant((string) $item['entity_uuid'], (string) $item['locale']);
                    } else {
                        $this->searchAdapter->deleteProduct((string) $item['entity_uuid'], (string) $item['locale']);
                    }
                } elseif ($item['entity_type'] === 'variant') {
                    $this->indexVariant((string) $item['entity_uuid'], (string) $item['locale']);
                } else {
                    $this->indexProduct((string) $item['entity_uuid'], (string) $item['locale']);
                }
                $this->queueWriteRepository->markCompleted((string) $item['uuid']);
                $processed++;
            } catch (\Throwable $e) {
                $this->queueWriteRepository->markFailed((string) $item['uuid'], $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * @return array<string, mixed>
     */
    public function reindexLocale(string $locale, int $batchSize = 500, int $afterId = 0): array
    {
        $productsIndexed = 0;
        $variantsIndexed = 0;
        $lastId = $afterId;

        while (true) {
            $batch = $this->indexReadRepository->listProductsForIndex($locale, $lastId, $batchSize);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $productUuid = (string) $row['uuid'];
                $this->indexProduct($productUuid, $locale);
                $productsIndexed++;
                $variantsIndexed += count($this->indexReadRepository->listVariantsForProduct($productUuid, $locale));
                $lastId = (int) $row['id'];
            }

            if (count($batch) < $batchSize) {
                break;
            }
        }

        $report = new ReindexReport($locale, $productsIndexed, $variantsIndexed, $lastId);

        return $report->toArray();
    }
}
