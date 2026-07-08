<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\DTO\ProductListFilter;
use Rateb\PlatformCatalog\Application\Mappers\ProductMapper;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;

final class ExportChunkJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly LocaleResolverService $localeResolver,
        private readonly StorageAdapterInterface $storage
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'export_chunk';
    }

    public function handle(Job $job): void
    {
        $locale = $this->localeResolver->resolveFromRequest();
        $chunkSize = max(1, min(500, (int) ($job->payload['chunk_size'] ?? 500)));
        $chunkIndex = max(0, (int) ($job->payload['chunk_index'] ?? 0));
        $offset = $chunkIndex * $chunkSize;
        $format = (string) ($job->payload['format'] ?? 'json');

        $rows = $this->productReadRepository->listFiltered($locale, new ProductListFilter(), $chunkSize, $offset);
        $items = array_map(static fn (array $row): array => ProductMapper::toProductDto($row)->toArray(), $rows);

        $exportKey = 'exports/' . $job->jobId . '_chunk_' . $chunkIndex . '.' . ($format === 'csv' ? 'csv' : 'json');
        $body = $format === 'csv'
            ? $this->toCsv($items)
            : (json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');

        $this->storage->put($exportKey, $body, ['content_type' => $format === 'csv' ? 'text/csv' : 'application/json']);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function toCsv(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $headers = array_keys($items[0]);
        $lines = [implode(',', $headers)];
        foreach ($items as $item) {
            $values = [];
            foreach ($headers as $header) {
                $value = $item[$header] ?? '';
                $values[] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $lines[] = implode(',', $values);
        }

        return implode("\n", $lines);
    }
}
