<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

use Rateb\PlatformCatalog\Application\Support\ArabicNormalizer;

final class MeilisearchAdapter implements SearchAdapterInterface
{
    public function __construct(
        private readonly string $host,
        private readonly ?string $apiKey = null
    ) {
        if ($host === '') {
            throw new \RuntimeException('MEILISEARCH_HOST is required outside testing environment');
        }
    }

    public function indexProduct(array $document, string $locale): void
    {
        $index = $this->productIndexName($locale);
        $this->ensureIndex($index, 'uuid', ['name', 'sku', 'barcodes'], ['barcodes', 'category_id', 'status']);
        $this->upsertDocuments($index, [$document]);
    }

    public function deleteProduct(string $productUuid, string $locale): void
    {
        $this->deleteDocument($this->productIndexName($locale), $productUuid);
    }

    public function indexVariant(array $document, string $locale): void
    {
        $index = $this->variantIndexName($locale);
        $this->ensureIndex($index, 'variant_uuid', ['name', 'sku', 'barcodes'], ['barcodes', 'product_uuid', 'status']);
        $this->upsertDocuments($index, [$document]);
    }

    public function deleteVariant(string $variantUuid, string $locale): void
    {
        $this->deleteDocument($this->variantIndexName($locale), $variantUuid);
    }

    public function resolveBarcode(string $barcode, string $locale): ?BarcodeResolveResult
    {
        $variant = $this->searchIndex(
            $this->variantIndexName($locale),
            '',
            1,
            0,
            ['barcodes = "' . $this->escapeFilterValue($barcode) . '"']
        );
        if ($variant['hits'] !== []) {
            return new BarcodeResolveResult('variant', $variant['hits'][0]);
        }

        $product = $this->searchIndex(
            $this->productIndexName($locale),
            '',
            1,
            0,
            ['barcodes = "' . $this->escapeFilterValue($barcode) . '"']
        );
        if ($product['hits'] !== []) {
            return new BarcodeResolveResult('product', $product['hits'][0]);
        }

        return null;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $index = $query->indexType === 'variant'
            ? $this->variantIndexName($query->locale)
            : $this->productIndexName($query->locale);

        $filters = [];
        foreach ($query->facets as $field => $values) {
            if ($values === []) {
                continue;
            }
            $parts = array_map(
                fn (string $value): string => $field . ' = "' . $this->escapeFilterValue($value) . '"',
                $values
            );
            $filters[] = '(' . implode(' OR ', $parts) . ')';
        }

        $needle = $this->normalizeQuery($query->query, $query->locale);
        $payload = $this->searchIndex($index, $needle, $query->limit, $query->offset, $filters, $query->sort);

        return new SearchResult($payload['hits'], $payload['total'], $payload['facets']);
    }

    public function reindexLocale(string $locale, ?callable $progress = null): ReindexReport
    {
        unset($progress);

        return new ReindexReport($locale, 0, 0);
    }

    public function healthCheck(): bool
    {
        $response = $this->remoteRequest('GET', rtrim($this->host, '/') . '/health', null);

        return is_string($response) && $response !== '';
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
        $query = function_exists('mb_strtolower') ? mb_strtolower(trim($query)) : strtolower(trim($query));
        if ($locale === 'ar') {
            return ArabicNormalizer::normalizeForSearch($query);
        }

        return $query;
    }

    /**
     * @param list<array<string, mixed>> $documents
     */
    private function upsertDocuments(string $index, array $documents): void
    {
        $url = rtrim($this->host, '/') . '/indexes/' . rawurlencode($index) . '/documents';
        $this->remoteRequest('POST', $url, json_encode($documents, JSON_UNESCAPED_UNICODE) ?: '[]');
        $this->waitForLatestTask();
    }

    /**
     * @param list<string> $searchableAttributes
     * @param list<string> $filterableAttributes
     */
    private function ensureIndex(string $index, string $primaryKey, array $searchableAttributes, array $filterableAttributes): void
    {
        $base = rtrim($this->host, '/') . '/indexes';
        $this->remoteRequest('POST', $base, json_encode(['uid' => $index, 'primaryKey' => $primaryKey], JSON_UNESCAPED_UNICODE) ?: '{}');
        $settingsUrl = $base . '/' . rawurlencode($index) . '/settings';
        $this->remoteRequest('PATCH', $settingsUrl, json_encode([
            'searchableAttributes' => $searchableAttributes,
            'filterableAttributes' => $filterableAttributes,
        ], JSON_UNESCAPED_UNICODE) ?: '{}');
        $this->waitForLatestTask();
    }

    private function waitForLatestTask(): void
    {
        $tasksUrl = rtrim($this->host, '/') . '/tasks?limit=1';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $response = $this->remoteRequest('GET', $tasksUrl, null);
            if (!is_string($response) || $response === '') {
                return;
            }
            $decoded = json_decode($response, true);
            $task = is_array($decoded['results'][0] ?? null) ? $decoded['results'][0] : null;
            if ($task === null) {
                return;
            }
            $status = (string) ($task['status'] ?? '');
            if (in_array($status, ['succeeded', 'failed', 'canceled'], true)) {
                if ($status === 'failed') {
                    $message = is_array($task['error'] ?? null) ? (string) ($task['error']['message'] ?? 'Meilisearch task failed') : 'Meilisearch task failed';
                    throw new \RuntimeException($message);
                }

                return;
            }
            usleep(100000);
        }
    }

    private function deleteDocument(string $index, string $id): void
    {
        $url = rtrim($this->host, '/') . '/indexes/' . rawurlencode($index) . '/documents/' . rawurlencode($id);
        $this->remoteRequest('DELETE', $url, null);
    }

    /**
     * @param list<string> $filters
     * @return array{hits: list<array<string, mixed>>, total: int, facets: array<string, array<string, int>>}
     */
    private function searchIndex(
        string $index,
        string $query,
        int $limit,
        int $offset,
        array $filters = [],
        string $sort = 'relevance'
    ): array {
        $body = [
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ];
        if ($filters !== []) {
            $body['filter'] = $filters;
        }
        if ($sort === 'name') {
            $body['sort'] = ['name:asc'];
        }

        $url = rtrim($this->host, '/') . '/indexes/' . rawurlencode($index) . '/search';
        $response = $this->remoteRequest('POST', $url, json_encode($body, JSON_UNESCAPED_UNICODE) ?: '{}');
        if (!is_string($response) || $response === '') {
            return ['hits' => [], 'total' => 0, 'facets' => []];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['hits' => [], 'total' => 0, 'facets' => []];
        }

        $hits = is_array($decoded['hits'] ?? null) ? $decoded['hits'] : [];

        return [
            'hits' => $hits,
            'total' => (int) ($decoded['estimatedTotalHits'] ?? $decoded['nbHits'] ?? count($hits)),
            'facets' => is_array($decoded['facetDistribution'] ?? null) ? $decoded['facetDistribution'] : [],
        ];
    }

    private function escapeFilterValue(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }

    private function remoteRequest(string $method, string $url, ?string $body): ?string
    {
        $headers = "Content-Type: application/json\r\n";
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers .= 'Authorization: Bearer ' . $this->apiKey . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        return is_string($response) ? $response : null;
    }
}
