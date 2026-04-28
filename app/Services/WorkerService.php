<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\EventDispatcher;
use App\Domain\Contracts\EmployerRepositoryInterface;
use App\Domain\Contracts\WorkerRepositoryInterface;
use App\Events\WorkerCreated;
use InvalidArgumentException;

final class WorkerService
{
    public function __construct(
        private readonly WorkerRepositoryInterface $workerRepository,
        private readonly EmployerRepositoryInterface $employerRepository,
        private readonly EventDispatcher $events
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function createWorker(array $payload): array
    {
        if (empty($payload['name']) || empty($payload['passport_number'])) {
            throw new InvalidArgumentException('Worker name and passport number are required.');
        }

        if (!empty($payload['employer_id'])) {
            $employer = $this->employerRepository->findById((int) $payload['employer_id']);
            if ($employer === null) {
                throw new InvalidArgumentException('Employer not found.');
            }
        }

        $workerId = $this->workerRepository->create($payload);
        $worker = $this->workerRepository->findById($workerId);
        if ($worker === null) {
            throw new InvalidArgumentException('Worker creation failed.');
        }

        $this->events->event(new WorkerCreated($workerId, (string) $worker['name']));
        return $worker;
    }
}
