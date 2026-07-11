<?php

declare(strict_types=1);

/**
 * Module tier + operation classification registry (Phase 4).
 * Tier 1/2 adapters are registered; Procurement not activated.
 */
return [
    'tiers' => [
        'T0' => ['pos'],
        'T1' => ['inventory', 'hr', 'procurement'],
        'T2' => ['erp_shell'],
        'T3' => ['platform'],
    ],

    /** Phase 4: foundation + POS + Inventory + HR (flag-gated). */
    'active_modules' => ['offline_meta', 'pos', 'inventory', 'hr'],

    'operations' => [
        'offline.ping' => ['class' => 'OC', 'module' => 'offline_meta'],
        'offline.ack' => ['class' => 'RS', 'module' => 'offline_meta'],
        'pos.checkout' => ['class' => 'RS', 'module' => 'pos'],
        'pos.process_return' => ['class' => 'RS', 'module' => 'pos'],
        'pos.process_exchange' => ['class' => 'RS', 'module' => 'pos'],
        'inventory.stock_movement' => ['class' => 'RS', 'module' => 'inventory'],
        'inventory.stock_count' => ['class' => 'RS', 'module' => 'inventory'],
        'inventory.warehouse_transfer' => ['class' => 'RS', 'module' => 'inventory'],
        'hr.attendance' => ['class' => 'RS', 'module' => 'hr'],
        'hr.attendance.bulk' => ['class' => 'RS', 'module' => 'hr'],
        'hr.leave_draft' => ['class' => 'RS', 'module' => 'hr'],
    ],
];
