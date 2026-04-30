<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AlertRepository;

final class AlertQueryService
{
    public function __construct(private readonly AlertRepository $alerts)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 100): array
    {
        return $this->alerts->listRecent($limit);
    }
}
