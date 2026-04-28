<?php
declare(strict_types=1);

namespace App\Domain\Contracts;

interface TrackingLogRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int;
}
