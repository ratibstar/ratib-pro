<?php
declare(strict_types=1);

namespace App\Controllers\Http;

use App\Services\WorkerService;

final class WorkerController
{
    public function __construct(private readonly WorkerService $workerService)
    {
    }

    /** @param array<string, mixed> $payload */
    public function store(array $payload): array
    {
        return [
            'data' => $this->workerService->createWorker($payload),
            'message' => 'Worker created successfully.',
        ];
    }
}
