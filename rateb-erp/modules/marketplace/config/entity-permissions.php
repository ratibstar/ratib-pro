<?php
declare(strict_types=1);

/** Marketplace entity → RBAC map (merged into rateb_entity_perms at runtime). */
return [
    'marketplace' => [
        'module' => 'marketplace',
        'view' => 'marketplace.view',
        'manage' => 'marketplace.manage',
    ],
    'marketplace/providers' => [
        'module' => 'marketplace',
        'view' => 'marketplace.view',
        'manage' => 'marketplace.manage',
    ],
    'marketplace/services' => [
        'module' => 'marketplace',
        'view' => 'marketplace.view',
        'manage' => 'marketplace.manage',
    ],
    'marketplace/orders' => [
        'module' => 'marketplace',
        'view' => 'marketplace.view',
        'manage' => 'marketplace.manage',
    ],
    'marketplace/reviews' => [
        'module' => 'marketplace',
        'view' => 'marketplace.view',
        'manage' => 'marketplace.manage',
    ],
];
