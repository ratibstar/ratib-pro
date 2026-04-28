<?php
declare(strict_types=1);

return [
    // Allowed: government, commercial
    'default_mode' => getenv('SYSTEM_MODE') ?: 'commercial',
];
