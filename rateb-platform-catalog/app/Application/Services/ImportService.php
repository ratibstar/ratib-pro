<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\ImportPolicy;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Application\Validators\SkuBarcodeUniquenessValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchWriteRepositoryInterface;

final class ImportService
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly ImportBatchReadRepositoryInterface $readRepository,
        private readonly ImportBatchWriteRepositoryInterface $writeRepository,
        private readonly ImportPolicy $policy,
        private readonly QueueService $queueService,
        private readonly SkuBarcodeUniquenessValidator $uniquenessValidator,
        private readonly PlatformIdentityResolver $identityResolver
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function createBatch(array $payload): array
    {
        $this->policy->manage();
        $sourceCode = (string) ($payload['source_code'] ?? 'manual');
        $sourceId = $this->readRepository->findSourceIdByCode($sourceCode);
        if ($sourceId === null) {
            throw new \InvalidArgumentException('Unknown import source: ' . $sourceCode);
        }

        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            throw new \InvalidArgumentException('rows array is required');
        }

        $checksum = hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE) ?: '[]');
        $batchUuid = $this->writeRepository->createBatch([
            'import_source_id' => $sourceId,
            'source_file_path' => $payload['source_file_path'] ?? null,
            'source_checksum' => $checksum,
            'status' => 'uploaded',
            'total_rows' => count($rows),
            'parser_config' => is_array($payload['parser_config'] ?? null) ? $payload['parser_config'] : [],
            'created_by' => $this->identityResolver->resolveActorId(),
        ]);

        $normalizedRows = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalizedRows[] = [
                'row_number' => $index + 1,
                'raw_payload' => $row,
                'status' => 'pending',
                'created_by' => $this->identityResolver->resolveActorId(),
            ];
        }
        $this->writeRepository->insertRows($batchUuid, $normalizedRows);

        $item = $this->readRepository->findByUuid($batchUuid);

        return ['item' => $item ?? ['uuid' => $batchUuid], 'meta' => []];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function validate(string $batchUuid): array
    {
        $this->policy->manage();
        $batch = $this->readRepository->findByUuid($batchUuid);
        if ($batch === null) {
            throw new \RuntimeException('Import batch not found', 404);
        }

        $this->writeRepository->updateBatchStatus($batchUuid, 'validating');
        $rows = $this->readRepository->listRows($batchUuid, 'pending', self::CHUNK_SIZE, 0);
        $valid = 0;
        $errors = 0;

        foreach ($rows as $row) {
            $validation = $this->validateRow($row['raw_payload'] ?? []);
            $this->writeRepository->updateRowValidation(
                (string) $row['uuid'],
                $validation['status'],
                $validation['mapped_payload'],
                $validation['validation_errors']
            );
            if ($validation['status'] === 'valid') {
                $valid++;
            } else {
                $errors++;
            }
        }

        $status = $errors > 0 && $valid === 0 ? 'validation_failed' : 'preview_ready';
        $this->writeRepository->updateBatchStatus($batchUuid, $status, [
            'valid_rows' => $valid,
            'error_rows' => $errors,
        ]);

        return [
            'item' => $this->readRepository->findByUuid($batchUuid),
            'meta' => ['validated' => count($rows), 'valid_rows' => $valid, 'error_rows' => $errors],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function preview(string $batchUuid, ?string $status, int $limit, int $offset): array
    {
        $this->policy->view();
        if ($this->readRepository->findByUuid($batchUuid) === null) {
            throw new \RuntimeException('Import batch not found', 404);
        }

        $items = $this->readRepository->listRows($batchUuid, $status, $limit, $offset);
        $total = $this->readRepository->countRows($batchUuid, $status);

        return [
            'items' => $items,
            'meta' => ['count' => count($items), 'total' => $total, 'limit' => $limit, 'offset' => $offset],
        ];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function commit(string $batchUuid): array
    {
        $this->policy->manage();
        $batch = $this->readRepository->findByUuid($batchUuid);
        if ($batch === null) {
            throw new \RuntimeException('Import batch not found', 404);
        }
        if (!in_array((string) ($batch['status'] ?? ''), ['preview_ready', 'commit_failed'], true)) {
            throw new \InvalidArgumentException('Batch is not ready for commit');
        }

        $this->writeRepository->updateBatchStatus($batchUuid, 'committing');
        $validCount = $this->readRepository->countRows($batchUuid, 'valid');
        $chunks = (int) max(1, ceil($validCount / self::CHUNK_SIZE));

        for ($chunk = 0; $chunk < $chunks; $chunk++) {
            $this->queueService->enqueueSystem('import', 'import_chunk', [
                'batch_uuid' => $batchUuid,
                'chunk_index' => $chunk,
            ], 'import_chunk:' . $batchUuid . ':' . $chunk);
        }

        return [
            'item' => $this->readRepository->findByUuid($batchUuid),
            'meta' => ['chunks_enqueued' => $chunks, 'valid_rows' => $validCount],
        ];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function rollback(string $batchUuid): array
    {
        $this->policy->manage();
        $batch = $this->readRepository->findByUuid($batchUuid);
        if ($batch === null) {
            throw new \RuntimeException('Import batch not found', 404);
        }
        if ((string) ($batch['status'] ?? '') !== 'committed') {
            throw new \InvalidArgumentException('Only committed batches can be rolled back');
        }

        $committedAt = $batch['committed_at'] ?? null;
        if ($committedAt !== null) {
            $committedTime = new \DateTimeImmutable((string) $committedAt);
            if ($committedTime < new \DateTimeImmutable('-24 hours')) {
                throw new \InvalidArgumentException('Rollback window expired (24 hours)');
            }
        }

        $this->writeRepository->markBatchRolledBack($batchUuid);
        $this->queueService->enqueueSystem('search', 'search_full_reindex', [
            'reason' => 'import_rollback',
            'batch_uuid' => $batchUuid,
        ], 'import_rollback:' . $batchUuid);

        return [
            'item' => $this->readRepository->findByUuid($batchUuid),
            'meta' => ['rolled_back' => true],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{status: string, mapped_payload: array<string, mixed>|null, validation_errors: list<array<string, string>>|null}
     */
    private function validateRow(array $raw): array
    {
        $errors = [];
        $sku = trim((string) ($raw['sku'] ?? ''));
        if ($sku === '') {
            $errors[] = ['field' => 'sku', 'message' => 'SKU is required'];
        } else {
            try {
                $this->uniquenessValidator->assertSkuAvailable($sku);
            } catch (\InvalidArgumentException $e) {
                $errors[] = ['field' => 'sku', 'message' => $e->getMessage()];
            }
        }

        $barcode = trim((string) ($raw['barcode'] ?? $raw['primary_barcode'] ?? ''));
        if ($barcode !== '') {
            try {
                $this->uniquenessValidator->assertBarcodeAvailable($barcode);
            } catch (\InvalidArgumentException $e) {
                $errors[] = ['field' => 'barcode', 'message' => $e->getMessage()];
            }
        }

        if ($errors !== []) {
            return ['status' => 'invalid', 'mapped_payload' => null, 'validation_errors' => $errors];
        }

        return [
            'status' => 'valid',
            'mapped_payload' => $raw,
            'validation_errors' => null,
        ];
    }
}
