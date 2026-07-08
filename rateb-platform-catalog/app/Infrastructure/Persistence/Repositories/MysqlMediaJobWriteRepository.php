<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaJobWriteRepositoryInterface;

final class MysqlMediaJobWriteRepository extends BaseRepository implements MediaJobWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'media_jobs';
    }

    public function create(int $productImageId): string
    {
        $uuid = $this->newUuid();
        $this->writePdo->prepare(
            'INSERT INTO media_jobs (uuid, product_image_id, status)
             VALUES (:uuid, :product_image_id, :status)'
        )->execute([
            'uuid' => $uuid,
            'product_image_id' => $productImageId,
            'status' => 'uploaded',
        ]);

        return $uuid;
    }

    public function updateStatus(string $uuid, string $status, ?string $errorMessage = null): void
    {
        $this->writePdo->prepare(
            'UPDATE media_jobs
             SET status = :status, error_message = :error_message, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid'
        )->execute([
            'uuid' => $uuid,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    public function incrementAttempts(string $uuid): void
    {
        $this->writePdo->prepare(
            'UPDATE media_jobs
             SET attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid'
        )->execute(['uuid' => $uuid]);
    }
}
