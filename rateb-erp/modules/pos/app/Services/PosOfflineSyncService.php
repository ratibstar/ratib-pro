<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Contracts\PosOfflineSyncTransportInterface;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Services\Drivers\NullPosOfflineSyncTransport;

final class PosOfflineSyncService
{
    public function __construct(
        private PosOfflineConflictResolverService $conflicts = new PosOfflineConflictResolverService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosOfflineSyncTransportInterface $transport = new NullPosOfflineSyncTransport(),
    ) {
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return [
            'queue_depth' => 0,
            'last_sync' => null,
            'online' => true,
            'scaffold' => true,
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    public function pushQueue(array $items): array
    {
        return $this->transport->push($items);
    }
}
