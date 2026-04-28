<?php
declare(strict_types=1);

namespace App\Events;

final class ViolationDetected
{
    public function __construct(
        public readonly int $workerId,
        public readonly string $violationType,
        public readonly string $severity
    ) {
    }
}
