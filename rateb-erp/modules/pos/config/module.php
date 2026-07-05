<?php
declare(strict_types=1);

return [
    'slug' => 'pos',
    'version' => '1.0.0-phase1',
    'namespace' => 'Rateb\\App\\Pos',
    'route_prefix' => 'admin/ops/pos',
    'features' => [
        'sessions' => true,
        'inventory_reservation' => true,
        'offline_sync' => true,
        'hardware_manager' => true,
        'pricing_engine' => true,
    ],
];
