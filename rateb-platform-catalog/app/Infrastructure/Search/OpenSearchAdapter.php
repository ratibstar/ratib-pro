<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

use Rateb\PlatformCatalog\Application\Support\ArabicNormalizer;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSearchIndexReadRepository;

final class OpenSearchAdapter implements SearchAdapterInterface
{
    private readonly string $host;
    private readonly ?string $user;
    private readonly ?string $password;
    private readonly string $indexPrefix;
    private readonly SearchAdapterInterface $fallback;

    public function __construct(
        ?string $host = null,
        ?string $user = null,
        ?string $password = null,
        ?string $indexPrefix = null,
        ?SearchAdapterInterface $fallback = null
    ) {
        $config = self::config();
        $this->host = rtrim($host ?? (string) ($config['OPENSEARCH_HOST'] ?? ''), '/');
        $this->user = $user ?? (isset($config['OPENSEARCH_USER']) ? (string) $config['OPENSEARCH_USER'] : null);
        $this->password = $password ?? (isset($config['OPENSEARCH_PASSWORD']) ? (string) $config['OPENSEARCH_PASSWORD'] : null);
        $this->indexPrefix = $indexPrefix ?? (string) ($config['OPENSEARCH_INDEX_PREFIX'] ?? 'catalog');
        $this->fallback = $fallback ?? (self::isTesting()
            ? new InMemorySearchAdapter()
            : new DatabaseSearchAdapter(new MysqlSearchIndexReadRepository()));

        if ($this->host === '' && !self::isTesting()) {
            throw new \RuntimeException('OPENSEARCH_HOST is required when SEARCH_ADAPTER=opensearch');
        }
    }

    public function indexProduct(array $document, string $locale): void
    {
        if ($this->host === '') {
            $this->fallback->indexProduct($document, $locale);

            return;
        }

        $index = $this->productIndex($locale);
        $id = (string) ($document['uuid'] ?? '');
        $this->request('PUT', $index . '/_doc/' . rawurlencode($id), json_encode($document, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    public function deleteProduct(string $productUuid, string $locale): void
    {
        if ($this->host === '') {
            $this->fallback->deleteProduct($productUuid, $locale);

            return;
        }

        $this->request('DELETE', $this->productIndex($locale) . '/_doc/' . rawurlencode($productUuid));
    }

    public function indexVariant(array $document, string $locale): void
    {
        if ($this->host === '') {
            $this->fallback->indexVariant($document, $locale);

            return;
        }

        $index = $this->variantIndex($locale);
        $id = (string) ($document['variant_uuid'] ?? $document['uuid'] ?? '');
        $this->request('PUT', $index . '/_doc/' . rawurlencode($id), json_encode($document, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    public function deleteVariant(string $variantUuid, string $locale): void
    {
        if ($this->host === '') {
            $this->fallback->deleteVariant($variantUuid, $locale);

            return;
        }

        $this->request('DELETE', $this->variantIndex($locale) . '/_doc/' . rawurlencode($variantUuid));
    }

    public function resolveBarcode(string $barcode, string $locale): ?BarcodeResolveResult
    {
        if ($this->host === '') {
            return $this->fallback->resolveBarcode($barcode, $locale);
        }

        $query = [
            'size' => 1,
            'query' => [
                'term' => ['barcodes.keyword' => $barcode],
            ],
        ];
        $variant = $this->searchIndex($this->variantIndex($locale), $query);
        if ($variant['hits'] !== []) {
            return new BarcodeResolveResult('variant', $variant['hits'][0]);
        }

        $product = $this->searchIndex($this->productIndex($locale), $query);
        if ($product['hits'] !== []) {
            return new BarcodeResolveResult('product', $product['hits'][0]);
        }

        return null;
    }

    public function search(SearchQuery $query): SearchResult
    {
        if ($this->host === '') {
            return $this->fallback->search($query);
        }

        $index = $query->indexType === 'variant' ? $this->variantIndex($query->locale) : $this->productIndex($query->locale);
        $must = [];
        $needle = $this->normalizeQuery($query->query, $query->locale);
        if ($needle !== '') {
            $must[] = [
                'multi_match' => [
                    'query' => $needle,
                    'fields' => ['name^3', 'sku^2', 'barcodes', 'description'],
                ],
            ];
        }
        foreach ($query->facets as $field => $values) {
            if ($values === []) {
                continue;
            }
            $must[] = ['terms' => [$field => array_values($values)]];
        }

        $body = [
            'from' => max(0, $query->offset),
            'size' => max(1, min(200, $query->limit)),
            'query' => $must === [] ? ['match_all' => new \stdClass()] : ['bool' => ['must' => $must]],
        ];
        $payload = $this->searchIndex($index, $body);

        return new SearchResult($payload['hits'], $payload['total'], $payload['facets']);
    }

    public function reindexLocale(string $locale, ?callable $progress = null): ReindexReport
    {
        if ($progress !== null) {
            $progress(['locale' => $locale, 'status' => 'opensearch_reindex_started']);
        }

        return $this->fallback->reindexLocale($locale, $progress);
    }

    public function healthCheck(): bool
    {
        if ($this->host === '') {
            return true;
        }

        $response = $this->request('GET', '/_cluster/health');

        return is_string($response) && str_contains($response, '"status"');
    }

    private function productIndex(string $locale): string
    {
        return $this->indexPrefix . '_products_' . $locale;
    }

    private function variantIndex(string $locale): string
    {
        return $this->indexPrefix . '_variants_' . $locale;
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
     * @param array<string, mixed> $body
     * @return array{hits: list<array<string, mixed>>, total: int, facets: array<string, mixed>}
     */
    private function searchIndex(string $index, array $body): array
    {
        $response = $this->request('POST', $index . '/_search', json_encode($body, JSON_UNESCAPED_UNICODE) ?: '{}');
        if (!is_string($response) || $response === '') {
            return ['hits' => [], 'total' => 0, 'facets' => []];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['hits' => [], 'total' => 0, 'facets' => []];
        }
        $hits = [];
        foreach ($decoded['hits']['hits'] ?? [] as $hit) {
            if (is_array($hit['_source'] ?? null)) {
                $hits[] = $hit['_source'];
            }
        }

        return [
            'hits' => $hits,
            'total' => (int) ($decoded['hits']['total']['value'] ?? count($hits)),
            'facets' => [],
        ];
    }

    private function request(string $method, string $path, ?string $body = null): ?string
    {
        if ($this->host === '') {
            return null;
        }

        $url = $this->host . (str_starts_with($path, '/') ? $path : '/' . $path);
        $headers = ['Content-Type: application/json'];
        if ($this->user !== null && $this->user !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($this->user . ':' . ($this->password ?? ''));
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        return is_string($response) ? $response : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        $path = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/config/opensearch.php' : dirname(__DIR__, 3) . '/config/opensearch.php';

        return is_file($path) ? (require $path) : [];
    }

    private static function isTesting(): bool
    {
        return defined('RATEB_CATALOG_TESTING') && RATEB_CATALOG_TESTING;
    }
}
