<?php
declare(strict_types=1);

/** Guest menu entity → RBAC map (merged into rateb_entity_perms at runtime). */
return [
    'guest-menu' => [
        'module' => 'pos',
        'view' => 'pos.view',
        'manage' => 'pos.settings.manage',
    ],
];
