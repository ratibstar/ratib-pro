<?php

declare(strict_types=1);

/**
 * Offline sync policy — retry, batch, TTL (Phase 2A foundation).
 */
return [
    'max_retries' => 5,
    'batch_size' => 50,
    'backoff_seconds' => [30, 60, 120, 300, 600],
    'client_queue_max' => 500,
    'catalog_ttl_hours' => 24,
    'entity_cache_ttl_hours' => 12,
    'probe_interval_online_ms' => 12000,
    'probe_interval_offline_ms' => 20000,
    'probe_timeout_ms' => 3500,
];
