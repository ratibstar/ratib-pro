<?php
declare(strict_types=1);

namespace App\Domain\Contracts;

interface EmployerRepositoryInterface
{
    public function findById(int $id): ?array;
}
