<?php

declare(strict_types=1);

/**
 * Entity → API route mapping for future replay (Phase 2A stub).
 * No Inventory/HR/Procurement replay wired yet.
 */
return [
    'offline_ack' => [
        'module' => 'offline_meta',
        'method' => 'POST',
        'path' => null,
        'replay' => 'noop',
    ],
    'pos_checkout' => [
        'module' => 'pos',
        'method' => 'POST',
        'path' => '/api/v1/pos/register/checkout',
        'replay' => 'delegate_pos',
    ],
];
