<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface MediaChecksumReadRepositoryInterface
{
    public function imageChecksumExists(string $checksum, ?string $excludeImageUuid = null): bool;

    public function fileChecksumExists(string $checksum, ?string $excludeFileUuid = null): bool;
}
