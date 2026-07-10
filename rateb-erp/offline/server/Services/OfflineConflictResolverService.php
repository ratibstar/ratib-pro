<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Phase 2A conflict resolver — server-authoritative last-write-wins.
 * Mirrors POS pattern without coupling to POS services.
 */
final class OfflineConflictResolverService
{
    /**
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolve(array $clientItem, ?array $serverItem): array
    {
        if ($serverItem === null) {
            return ['action' => 'accept_client', 'item' => $clientItem];
        }
        $clientVersion = (int) ($clientItem['version'] ?? 0);
        $serverVersion = (int) ($serverItem['version'] ?? 0);
        if ($serverVersion >= $clientVersion) {
            return [
                'action' => 'reject_client',
                'item' => $serverItem,
                'reason' => 'server_newer',
            ];
        }

        return ['action' => 'accept_client', 'item' => $clientItem];
    }
}
