<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Conflict resolver — server-authoritative last-write-wins + inventory qty check.
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

    /**
     * Inventory-specific: reject when expected on-hand quantity drifted.
     *
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveInventory(array $clientItem, ?array $serverItem): array
    {
        $base = $this->resolve($clientItem, $serverItem);
        if (($base['action'] ?? '') === 'reject_client') {
            return $base;
        }
        if ($serverItem === null) {
            return $base;
        }
        $expected = $clientItem['expected_quantity'] ?? null;
        if ($expected === null) {
            return $base;
        }
        if (!array_key_exists('quantity', $serverItem)) {
            return $base;
        }
        if (abs((float) $serverItem['quantity'] - (float) $expected) > 0.0001) {
            return [
                'action' => 'reject_client',
                'item' => $serverItem,
                'reason' => 'quantity_changed',
            ];
        }

        return $base;
    }

    /**
     * HR-specific: reject when expected attendance status/check-in drifted.
     *
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveHr(array $clientItem, ?array $serverItem): array
    {
        $base = $this->resolve($clientItem, $serverItem);
        if (($base['action'] ?? '') === 'reject_client') {
            return $base;
        }
        if ($serverItem === null) {
            return $base;
        }

        $expectedStatus = $clientItem['expected_status'] ?? null;
        if ($expectedStatus !== null && array_key_exists('status', $serverItem)) {
            if ((string) $serverItem['status'] !== (string) $expectedStatus) {
                return [
                    'action' => 'reject_client',
                    'item' => $serverItem,
                    'reason' => 'status_changed',
                ];
            }
        }

        $expectedCheckIn = $clientItem['expected_check_in'] ?? null;
        if ($expectedCheckIn !== null && array_key_exists('check_in', $serverItem)) {
            $serverIn = substr((string) ($serverItem['check_in'] ?? ''), 0, 5);
            $clientIn = substr((string) $expectedCheckIn, 0, 5);
            if ($serverIn !== '' && $clientIn !== '' && $serverIn !== $clientIn) {
                return [
                    'action' => 'reject_client',
                    'item' => $serverItem,
                    'reason' => 'attendance_conflict',
                ];
            }
        }

        return $base;
    }

    /**
     * Procurement-specific: reject when expected draft status drifted.
     *
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveProcurement(array $clientItem, ?array $serverItem): array
    {
        $base = $this->resolve($clientItem, $serverItem);
        if (($base['action'] ?? '') === 'reject_client') {
            return $base;
        }
        if ($serverItem === null) {
            return $base;
        }

        $expectedStatus = $clientItem['expected_status'] ?? null;
        if ($expectedStatus !== null && array_key_exists('status', $serverItem)) {
            if ((string) $serverItem['status'] !== (string) $expectedStatus) {
                return [
                    'action' => 'reject_client',
                    'item' => $serverItem,
                    'reason' => 'status_changed',
                ];
            }
        }

        return $base;
    }
}
