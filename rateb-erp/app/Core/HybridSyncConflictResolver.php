<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase C — conflict policy that REUSES OfflineConflictResolverService algorithms.
 * Does not modify Controllers/Services; Core delegates to existing resolver.
 */
final class HybridSyncConflictResolver
{
    /**
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolve(string $entityModule, array $clientItem, ?array $serverItem): array
    {
        if (class_exists(\Rateb\App\Offline\Services\OfflineConflictResolverService::class)) {
            $r = new \Rateb\App\Offline\Services\OfflineConflictResolverService();
            $module = strtolower($entityModule);
            return match (true) {
                str_contains($module, 'inventor') || str_contains($module, 'stock') || str_contains($module, 'warehouse')
                    => $r->resolveInventory($clientItem, $serverItem),
                str_contains($module, 'hr') || str_contains($module, 'employee') || str_contains($module, 'payroll')
                    => $r->resolveHr($clientItem, $serverItem),
                str_contains($module, 'procure') || str_contains($module, 'purchase') || str_contains($module, 'supplier')
                    => $r->resolveProcurement($clientItem, $serverItem),
                str_contains($module, 'account') || str_contains($module, 'journal')
                    => method_exists($r, 'resolveAccounting')
                        ? $r->resolveAccounting($clientItem, $serverItem)
                        : $r->resolve($clientItem, $serverItem),
                str_contains($module, 'pos')
                    => $r->resolve($clientItem, $serverItem),
                default => $r->resolve($clientItem, $serverItem),
            };
        }

        // Fallback LWW if offline module unavailable
        if ($serverItem === null) {
            return ['action' => 'accept_client', 'item' => $clientItem];
        }
        $cv = (int) ($clientItem['version'] ?? 0);
        $sv = (int) ($serverItem['version'] ?? 0);
        if ($sv >= $cv) {
            return ['action' => 'reject_client', 'item' => $serverItem, 'reason' => 'server_newer'];
        }

        return ['action' => 'accept_client', 'item' => $clientItem];
    }
}
