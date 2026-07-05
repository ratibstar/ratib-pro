<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

interface PosOfflineSyncTransportInterface
{
    /** @param array<int, array<string, mixed>> $items */
    public function push(array $items): array;

    public function pull(): array;
}
