<?php
declare(strict_types=1);

/** Automotive entity → RBAC map (merged into rateb_entity_perms at runtime). */
return [
    'automotive' => [
        'module' => 'automotive',
        'view' => 'automotive.view',
        'manage' => 'automotive.manage',
    ],
];
