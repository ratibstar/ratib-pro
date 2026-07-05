<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/**
 * Offline conflict resolution — last-write-wins with server authority (Phase 12+).
 */
final class PosOfflineConflictResolverService
{
    /** @param array<string, mixed> $clientItem @param array<string, mixed>|null $serverItem */
    public function resolve(array $clientItem, ?array $serverItem): array
    {
        if ($serverItem === null) {
            return ['action' => 'accept_client', 'item' => $clientItem];
        }
        $clientVersion = (int) ($clientItem['version'] ?? 0);
        $serverVersion = (int) ($serverItem['version'] ?? 0);
        if ($serverVersion >= $clientVersion) {
            return ['action' => 'reject_client', 'item' => $serverItem, 'reason' => 'server_newer'];
        }
        return ['action' => 'accept_client', 'item' => $clientItem];
    }
}
