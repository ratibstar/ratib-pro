<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Sanitizes client queue payloads — never trust client URL/method for replay.
 */
final class OfflinePayloadSanitizer
{
    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function normalize(array $item): array
    {
        $inner = is_array($item['payload'] ?? null) ? $item['payload'] : [];
        // Strip nested transport hints if present.
        unset($inner['url'], $inner['method'], $inner['headers']);

        return [
            'client_id' => $item['client_id'] ?? null,
            'idempotency_key' => $item['idempotency_key'] ?? null,
            'module' => $item['module'] ?? 'offline_meta',
            'action' => $item['action'] ?? 'offline.ack',
            'payload' => $inner,
            'occurred_at' => $item['occurred_at'] ?? null,
            'version' => max(1, (int) ($item['version'] ?? 1)),
            'depends_on' => is_array($item['depends_on'] ?? null) ? $item['depends_on'] : [],
        ];
    }
}
