<?php

declare(strict_types=1);

/**
 * Enterprise offline feature flags (Phase 2A).
 * Master default is OFF — no behavior change until explicitly enabled.
 */
return [
    'defaults' => [
        'offline.enabled' => false,
        'offline.pos.complete' => true,
        'offline.inventory.movements' => false,
        'offline.hr.attendance' => false,
        'offline.procurement' => false,
        'offline.monitoring' => false,
        'offline.read_cache' => false,
        'offline.auth.unlock' => false,
        'offline.rbac.cache' => false,
    ],

    /** Env var overrides (string "1"/"true"/"yes" → true). */
    'env' => [
        'offline.enabled' => 'RATEB_OFFLINE_ENABLED',
        'offline.pos.complete' => 'RATEB_OFFLINE_POS_COMPLETE',
        'offline.inventory.movements' => 'RATEB_OFFLINE_INVENTORY_MOVEMENTS',
        'offline.hr.attendance' => 'RATEB_OFFLINE_HR_ATTENDANCE',
        'offline.procurement' => 'RATEB_OFFLINE_PROCUREMENT',
        'offline.monitoring' => 'RATEB_OFFLINE_MONITORING',
        'offline.read_cache' => 'RATEB_OFFLINE_READ_CACHE',
        'offline.auth.unlock' => 'RATEB_OFFLINE_AUTH_UNLOCK',
        'offline.rbac.cache' => 'RATEB_OFFLINE_RBAC_CACHE',
    ],
];