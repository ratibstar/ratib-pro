<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class Worker
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $passportNumber,
        public readonly ?int $employerId,
        public readonly string $status
    ) {
    }
}
