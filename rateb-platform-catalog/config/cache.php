<?php

declare(strict_types=1);

return [
    'CACHE_ADAPTER' => getenv('CACHE_ADAPTER') ?: 'file',
    'CACHE_PREFIX' => getenv('CACHE_PREFIX') ?: 'cat:',
    'CACHE_DEFAULT_TTL' => (int) (getenv('CACHE_DEFAULT_TTL') ?: 300),
    'CACHE_PATH' => getenv('CACHE_PATH') ?: (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage/cache' : ''),
];
