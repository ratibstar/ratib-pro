<?php
declare(strict_types=1);

namespace App\Domain\Contracts;

interface WorkerRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int;
    public function findById(int $id): ?array;
}
