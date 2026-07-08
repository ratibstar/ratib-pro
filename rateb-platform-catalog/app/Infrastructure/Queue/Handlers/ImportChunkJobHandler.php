<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductCreated;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class ImportChunkJobHandler implements JobHandlerInterface
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly ImportBatchReadRepositoryInterface $batchReadRepository,
        private readonly ImportBatchWriteRepositoryInterface $batchWriteRepository,
        private readonly ProductWriteRepositoryInterface $productWriteRepository,
        private readonly EventDispatcher $events
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'import_chunk';
    }

    public function handle(Job $job): void
    {
        $batchUuid = (string) ($job->payload['batch_uuid'] ?? '');
        if ($batchUuid === '') {
            throw new \InvalidArgumentException('batch_uuid is required');
        }

        $rows = $this->batchWriteRepository->claimRowsForCommit($batchUuid, self::CHUNK_SIZE);
        foreach ($rows as $row) {
            $mapped = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
            $productUuid = $this->productWriteRepository->createWithTranslations(
                $mapped,
                is_array($mapped['translations'] ?? null) ? $mapped['translations'] : [],
                null
            );
            $this->batchWriteRepository->markRowCommitted((string) $row['uuid'], $productUuid);
            $this->events->dispatch(new ProductCreated($productUuid, (string) ($mapped['locale'] ?? 'en')));
        }

        $remaining = $this->batchReadRepository->countRows($batchUuid, 'valid');
        if ($remaining === 0) {
            $this->batchWriteRepository->markBatchCommitted($batchUuid);
        }
    }
}
