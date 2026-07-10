<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Contracts;

interface SyncTransportInterface
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function push(array $items): array;
}
