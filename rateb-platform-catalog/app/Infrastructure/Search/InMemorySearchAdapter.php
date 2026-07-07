<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

use Rateb\PlatformCatalog\Application\Support\ArabicNormalizer;

/**
 * In-memory search adapter for unit tests only.
 */
final class InMemorySearchAdapter implements SearchAdapterInterface
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $indexes = [];

    public function indexProduct(array $document, string $locale): void
    {
        $index = $this->productIndexName($locale);
        $this->indexes[$index][(string) $document['uuid']] = $document;
    }

    public function deleteProduct(string $productUuid, string $locale): void
    {
        unset($this->indexes[$this->productIndexName($locale)][$productUuid]);
    }

    public function indexVariant(array $document, string $locale): void
    {
        $index = $this->variantIndexName($locale);
        $this->indexes[$index][(string) $document['variant_uuid']] = $document;
    }

    public function deleteVariant(string $variantUuid, string $locale): void
    {
        unset($this->indexes[$this->variantIndexName($locale)][$variantUuid]);
    }

    public function resolveBarcode(string $barcode, string $locale): ?BarcodeResolveResult
    {
        foreach ($this->indexes[$this->variantIndexName($locale)] ?? [] as $doc) {
            $barcodes = $doc['barcodes'] ?? [];
            if (is_array($barcodes) && in_array($barcode, $barcodes, true)) {
                return new BarcodeResolveResult('variant', $doc);
            }
        }

        foreach ($this->indexes[$this->productIndexName($locale)] ?? [] as $doc) {
            $barcodes = $doc['barcodes'] ?? [];
            if (is_array($barcodes) && in_array($barcode, $barcodes, true)) {
                return new BarcodeResolveResult('product', $doc);
            }
        }

        return null;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $index = $query->indexType === 'variant'
            ? $this->variantIndexName($query->locale)
            : $this->productIndexName($query->locale);

        $docs = array_values($this->indexes[$index] ?? []);
        $needle = $this->normalizeQuery($query->query, $query->locale);
        $hits = [];

        foreach ($docs as $doc) {
            if (!$this->matchesQuery($doc, $needle)) {
                continue;
            }
            if (!$this->matchesFacets($doc, $query->facets)) {
                continue;
            }
            $hits[] = $doc;
        }

        usort($hits, function (array $a, array $b) use ($query): int {
            if ($query->sort === 'name') {
                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }

            return ((float) ($b['boost_score'] ?? 0)) <=> ((float) ($a['boost_score'] ?? 0));
        });

        $total = count($hits);
        $hits = array_slice($hits, $query->offset, $query->limit);

        return new SearchResult($hits, $total, $this->buildFacets($docs, $query->facets));
    }

    public function reindexLocale(string $locale, ?callable $progress = null): ReindexReport
    {
        unset($progress);

        return new ReindexReport(
            $locale,
            count($this->indexes[$this->productIndexName($locale)] ?? []),
            count($this->indexes[$this->variantIndexName($locale)] ?? [])
        );
    }

    public function healthCheck(): bool
    {
        return true;
    }

    private function productIndexName(string $locale): string
    {
        return 'catalog_products_' . $locale;
    }

    private function variantIndexName(string $locale): string
    {
        return 'catalog_variants_' . $locale;
    }

    private function normalizeQuery(string $query, string $locale): string
    {
        $query = $this->toLower(trim($query));
        if ($locale === 'ar') {
            return ArabicNormalizer::normalizeForSearch($query);
        }

        return $query;
    }

    private function toLower(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function matchesQuery(array $doc, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $haystack = $this->toLower(implode(' ', array_filter([
            (string) ($doc['name'] ?? ''),
            (string) ($doc['sku'] ?? ''),
            (string) ($doc['product_sku'] ?? ''),
            (string) ($doc['description'] ?? ''),
            (string) ($doc['short_description'] ?? ''),
        ])));

        return str_contains($haystack, $needle);
    }

    /**
     * @param array<string, mixed> $doc
     * @param array<string, list<string>> $facets
     */
    private function matchesFacets(array $doc, array $facets): bool
    {
        foreach ($facets as $field => $values) {
            if ($values === []) {
                continue;
            }
            $docValue = $doc[$field] ?? ($doc['option_values'][$field] ?? null);
            if (is_array($docValue)) {
                if (array_intersect($values, $docValue) === []) {
                    return false;
                }
                continue;
            }
            if (!in_array((string) $docValue, $values, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $docs
     * @param array<string, list<string>> $requestedFacets
     * @return array<string, array<string, int>>
     */
    private function buildFacets(array $docs, array $requestedFacets): array
    {
        $facets = [];
        foreach (array_keys($requestedFacets) as $field) {
            $facets[$field] = [];
            foreach ($docs as $doc) {
                $value = $doc[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $key = is_array($value) ? json_encode($value) : (string) $value;
                $facets[$field][$key] = ($facets[$field][$key] ?? 0) + 1;
            }
        }

        return $facets;
    }
}
