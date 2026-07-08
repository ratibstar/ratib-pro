<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface MediaJobWriteRepositoryInterface
{
    public function create(int $productImageId): string;

    public function updateStatus(string $uuid, string $status, ?string $errorMessage = null): void;

    public function incrementAttempts(string $uuid): void;
}
