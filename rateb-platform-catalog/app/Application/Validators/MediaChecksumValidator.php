<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaChecksumReadRepositoryInterface;

final class MediaChecksumValidator
{
    public function __construct(
        private readonly MediaChecksumReadRepositoryInterface $checksumReadRepository
    ) {
    }

    public function assertImageChecksumAvailable(?string $checksum, ?string $excludeImageUuid = null): void
    {
        if ($checksum === null || $checksum === '') {
            return;
        }

        if ($this->checksumReadRepository->imageChecksumExists($checksum, $excludeImageUuid)) {
            throw new \InvalidArgumentException('Image checksum already exists');
        }
    }

    public function assertFileChecksumAvailable(?string $checksum, ?string $excludeFileUuid = null): void
    {
        if ($checksum === null || $checksum === '') {
            return;
        }

        if ($this->checksumReadRepository->fileChecksumExists($checksum, $excludeFileUuid)) {
            throw new \InvalidArgumentException('File checksum already exists');
        }
    }
}
