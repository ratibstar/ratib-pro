<?php
declare(strict_types=1);

namespace App\Events;

final class WorkerMoved
{
    public function __construct(
        public readonly int $workerId,
        public readonly float $latitude,
        public readonly float $longitude
    ) {
    }
}
