<?php
declare(strict_types=1);

/** Logistics entity → RBAC map (merged into rateb_entity_perms at runtime). */
return [
    'logistics' => [
        'module' => 'logistics',
        'view' => 'logistics.view',
        'manage' => 'logistics.manage',
        'export' => 'logistics.report',
    ],
    'logistics/vehicles' => [
        'module' => 'logistics',
        'view' => 'logistics.view',
        'manage' => 'logistics.manage',
    ],
    'logistics/drivers' => [
        'module' => 'logistics',
        'view' => 'logistics.view',
        'manage' => 'logistics.manage',
    ],
    'logistics/trips' => [
        'module' => 'logistics',
        'view' => 'logistics.view',
        'manage' => 'logistics.manage',
    ],
    'logistics/shipments' => [
        'module' => 'logistics',
        'view' => 'logistics.view',
        'manage' => 'logistics.manage',
        'post' => 'logistics.dispatch',
    ],
    'logistics/routes' => [
        'module' => 'logistics',
        'view' => 'logistics.view',
        'manage' => 'logistics.manage',
    ],
    'logistics/expenses' => [
        'module' => 'logistics',
        'view' => 'logistics.view',
        'manage' => 'logistics.expense',
    ],
    'logistics/reports' => [
        'module' => 'logistics',
        'view' => 'logistics.report',
        'manage' => 'logistics.report',
        'export' => 'logistics.report',
    ],
];
