<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\BulkPolicy;
use Rateb\PlatformCatalog\Application\Policies\ImportPolicy;
use Rateb\PlatformCatalog\Support\Uuid;

final class BulkService
{
    public function __construct(
        private readonly BulkPolicy $bulkPolicy,
        private readonly ImportPolicy $importPolicy,
        private readonly QueueService $queueService,
        private readonly ImportService $importService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function startImport(array $payload): array
    {
        $this->importPolicy->manage();
        $result = $this->importService->createBatch($payload);
        $batchUuid = (string) ($result['item']['uuid'] ?? '');
        if ($batchUuid !== '') {
            $this->importService->validate($batchUuid);
            $commit = $this->importService->commit($batchUuid);
            $result['item'] = $commit['item'];
            $result['meta'] = array_merge($result['meta'], $commit['meta']);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function startExport(array $payload): array
    {
        $this->bulkPolicy->manage();
        $jobId = $this->queueService->enqueueSystem('export', 'export_chunk', [
            'format' => (string) ($payload['format'] ?? 'json'),
            'filters' => is_array($payload['filters'] ?? null) ? $payload['filters'] : [],
            'chunk_index' => 0,
            'chunk_size' => (int) ($payload['chunk_size'] ?? 500),
        ], 'bulk_export:' . Uuid::v4());

        return [
            'item' => ['job_id' => $jobId, 'status' => 'pending'],
            'meta' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function bulkPublish(array $payload): array
    {
        $this->bulkPolicy->manage();
        $productUuids = $this->normalizeProductUuids($payload);
        $jobId = $this->queueService->enqueueSystem('workflow', 'bulk_publish', [
            'product_uuids' => $productUuids,
        ], 'bulk_publish:' . Uuid::v4());

        return [
            'item' => ['job_id' => $jobId, 'status' => 'pending', 'total' => count($productUuids)],
            'meta' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function bulkArchive(array $payload): array
    {
        $this->bulkPolicy->manage();
        $productUuids = $this->normalizeProductUuids($payload);
        $jobId = $this->queueService->enqueueSystem('workflow', 'bulk_archive', [
            'product_uuids' => $productUuids,
        ], 'bulk_archive:' . Uuid::v4());

        return [
            'item' => ['job_id' => $jobId, 'status' => 'pending', 'total' => count($productUuids)],
            'meta' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function normalizeProductUuids(array $payload): array
    {
        $uuids = $payload['product_uuids'] ?? $payload['uuids'] ?? [];
        if (!is_array($uuids) || $uuids === []) {
            throw new \InvalidArgumentException('product_uuids array is required');
        }

        return array_values(array_filter(array_map(static fn ($u): string => trim((string) $u), $uuids)));
    }
}
