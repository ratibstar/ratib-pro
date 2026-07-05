<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosOfflineSyncTransportInterface;

final class NullPosOfflineSyncTransport implements PosOfflineSyncTransportInterface
{
    public function push(array $items): array
    {
        return ['accepted' => 0, 'rejected' => count($items)];
    }

    public function pull(): array
    {
        return [];
    }
}
