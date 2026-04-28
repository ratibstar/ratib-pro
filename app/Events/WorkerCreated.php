<?php
declare(strict_types=1);

namespace App\Events;

final class WorkerCreated
{
    public function __construct(
        public readonly int $workerId,
        public readonly string $name
    ) {
    }
}
