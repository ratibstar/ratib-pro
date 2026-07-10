<?php

declare(strict_types=1);

/**
 * Module tier + operation classification registry (Phase 2A).
 * Tier 1/2 adapters are registered but not activated until later phases.
 */
return [
    'tiers' => [
        'T0' => ['pos'],
        'T1' => ['inventory', 'hr', 'procurement'],
        'T2' => ['erp_shell'],
        'T3' => ['platform'],
    ],

    /** Phase 2A: only foundation + POS bridge. */
    'active_modules' => ['offline_meta', 'pos'],

    'operations' => [
        'offline.ping' => ['class' => 'OC', 'module' => 'offline_meta'],
        'offline.ack' => ['class' => 'RS', 'module' => 'offline_meta'],
        'pos.checkout' => ['class' => 'RS', 'module' => 'pos'],
        'pos.process_return' => ['class' => 'RS', 'module' => 'pos'],
        'pos.process_exchange' => ['class' => 'RS', 'module' => 'pos'],
    ],
];
