<?php
declare(strict_types=1);

/** Automotive entity → RBAC map (merged into rateb_entity_perms at runtime). */
return [
    'automotive' => [
        'module' => 'automotive',
        'view' => 'automotive.view',
        'manage' => 'automotive.manage',
    ],
    'automotive_reference' => [
        'module' => 'automotive',
        'view' => 'automotive.reference.view',
        'manage' => 'automotive.reference.manage',
    ],
    'automotive_selection' => [
        'module' => 'automotive',
        'view' => 'automotive.selection.view',
        'manage' => 'automotive.selection.manage',
    ],
    'automotive_link' => [
        'module' => 'automotive',
        'view' => 'automotive.inventory_link.view',
        'manage' => 'automotive.inventory_link.manage',
    ],
    'automotive_supplier' => [
        'module' => 'automotive',
        'view' => 'automotive.supplier_map.view',
        'manage' => 'automotive.supplier_map.manage',
    ],
];
