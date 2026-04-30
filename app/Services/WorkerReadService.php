<?php
declare(strict_types=1);

namespace App\Services;

use App\Domain\Contracts\WorkerRepositoryInterface;
use InvalidArgumentException;

final class WorkerReadService
{
    public function __construct(private readonly WorkerRepositoryInterface $workers)
    {
    }

    /** @return array<string, mixed> */
    public function getById(int $workerId): array
    {
        if ($workerId <= 0) {
            throw new InvalidArgumentException('worker_id is required');
        }
        $row = $this->workers->findById($workerId);
        if (!is_array($row)) {
            throw new InvalidArgumentException('Worker not found');
        }

        return $row;
    }
}
