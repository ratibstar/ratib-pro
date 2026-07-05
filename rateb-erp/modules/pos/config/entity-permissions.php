<?php
declare(strict_types=1);

/** POS entity → RBAC map (merged into rateb_entity_perms at runtime). */
return [
    'pos' => [
        'module' => 'pos',
        'view' => 'pos.view',
        'manage' => 'pos.manage',
        'export' => 'reports.export',
    ],
    'pos/register' => [
        'module' => 'pos',
        'view' => 'pos.register',
        'manage' => 'pos.register',
    ],
    'pos/terminals' => [
        'module' => 'pos',
        'view' => 'pos.view',
        'manage' => 'pos.terminal.manage',
    ],
    'pos/shifts' => [
        'module' => 'pos',
        'view' => 'pos.view',
        'manage' => 'pos.shift.close',
        'post' => 'pos.shift.open',
    ],
    'pos/cash-drawers' => [
        'module' => 'pos',
        'view' => 'pos.view',
        'manage' => 'pos.cash_drawer.manage',
    ],
    'pos/orders' => [
        'module' => 'pos',
        'view' => 'pos.orders.view',
        'manage' => 'pos.manage',
    ],
    'pos/settings' => [
        'module' => 'pos',
        'view' => 'pos.view',
        'manage' => 'pos.settings.manage',
    ],
    'pos/sync' => [
        'module' => 'pos',
        'view' => 'pos.view',
        'manage' => 'pos.sync.manage',
    ],
    'pos/reports' => [
        'module' => 'pos',
        'view' => 'pos.reports.view',
        'manage' => 'pos.reports.z',
    ],
    'pos/returns' => [
        'module' => 'pos',
        'view' => 'pos.orders.view',
        'manage' => 'pos.returns.manage',
    ],
];
