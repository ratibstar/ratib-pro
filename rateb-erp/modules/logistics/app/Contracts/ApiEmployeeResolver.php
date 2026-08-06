<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Contracts;

interface ApiEmployeeResolver
{
    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function resolveCurrentEmployee(?int $userId, ?int $companyId): array;
}
