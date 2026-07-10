<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services\Drivers;

use Rateb\App\Offline\Contracts\SyncTransportInterface;

/** Fallback when offline tables are not migrated yet. */
final class NullOfflineSyncTransport implements SyncTransportInterface
{
    /** @param array<int, array<string, mixed>> $items */
    public function push(array $items): array
    {
        $rejectedKeys = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (['client_id', 'idempotency_key'] as $field) {
                $value = trim((string) ($item[$field] ?? ''));
                if ($value !== '') {
                    $rejectedKeys[] = substr($value, 0, 64);
                    break;
                }
            }
        }

        return [
            'accepted' => 0,
            'duplicate' => 0,
            'conflict' => 0,
            'rejected' => count($items),
            'accepted_keys' => [],
            'duplicate_keys' => [],
            'conflict_keys' => [],
            'rejected_keys' => $rejectedKeys,
            'errors' => ['migration_required' => true],
            'scaffold' => true,
        ];
    }
}
