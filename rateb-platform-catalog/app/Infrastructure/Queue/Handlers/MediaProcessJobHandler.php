<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaJobReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaJobWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;

final class MediaProcessJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly MediaJobReadRepositoryInterface $mediaJobReadRepository,
        private readonly MediaJobWriteRepositoryInterface $mediaJobWriteRepository,
        private readonly ProductImageReadRepositoryInterface $imageReadRepository,
        private readonly StorageAdapterInterface $storage
    ) {
    }

    public function supports(string $jobType): bool
    {
        return in_array($jobType, ['image_process', 'media_process'], true);
    }

    public function handle(Job $job): void
    {
        $mediaJobUuid = (string) ($job->payload['media_job_uuid'] ?? '');
        if ($mediaJobUuid === '') {
            $imageUuid = (string) ($job->payload['image_uuid'] ?? '');
            if ($imageUuid === '') {
                throw new \InvalidArgumentException('media_job_uuid or image_uuid is required');
            }
            $image = $this->imageReadRepository->findByUuidAndVariant($imageUuid, 'original');
            if ($image === null) {
                throw new \RuntimeException('Product image not found', 404);
            }
            $mediaJobUuid = $this->mediaJobWriteRepository->create((int) $image['id']);
        }

        $mediaJob = $this->mediaJobReadRepository->findByUuid($mediaJobUuid);
        if ($mediaJob === null) {
            throw new \RuntimeException('Media job not found', 404);
        }

        $this->mediaJobWriteRepository->incrementAttempts($mediaJobUuid);
        $this->mediaJobWriteRepository->updateStatus($mediaJobUuid, 'scanning');

        try {
            $this->runVirusScanHook($mediaJob);
            $this->mediaJobWriteRepository->updateStatus($mediaJobUuid, 'processing');
            $this->mediaJobWriteRepository->updateStatus($mediaJobUuid, 'completed');
        } catch (\Throwable $e) {
            $this->mediaJobWriteRepository->updateStatus($mediaJobUuid, 'scan_failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $mediaJob
     */
    private function runVirusScanHook(array $mediaJob): void
    {
        $storageKey = (string) ($mediaJob['storage_key'] ?? '');
        if ($storageKey === '' || !$this->storage->exists($storageKey)) {
            throw new \RuntimeException('Image storage object missing');
        }

        if (getenv('CATALOG_VIRUS_SCAN_ENABLED') === '1' && is_callable('clamav_scan_file')) {
            $stream = $this->storage->get($storageKey);
            if (!is_resource($stream)) {
                throw new \RuntimeException('Unable to read image for virus scan');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'catalog_scan_');
            if ($tmp === false) {
                fclose($stream);
                throw new \RuntimeException('Unable to create temp file for virus scan');
            }
            $out = fopen($tmp, 'wb');
            if ($out === false) {
                fclose($stream);
                throw new \RuntimeException('Unable to open temp file for virus scan');
            }
            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);
            $clean = @clamav_scan_file($tmp);
            @unlink($tmp);
            if (!$clean) {
                throw new \RuntimeException('Virus scan failed');
            }
        }
    }
}
