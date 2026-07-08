<?php

declare(strict_types=1);

return [
    'REDIS_HOST' => getenv('REDIS_HOST') ?: '127.0.0.1',
    'REDIS_PORT' => (int) (getenv('REDIS_PORT') ?: 6379),
    'REDIS_PASSWORD' => getenv('REDIS_PASSWORD') ?: null,
    'REDIS_DATABASE' => (int) (getenv('REDIS_DATABASE') ?: 0),
    'REDIS_PREFIX' => getenv('REDIS_PREFIX') ?: 'catalog:',
    'REDIS_TIMEOUT' => (float) (getenv('REDIS_TIMEOUT') ?: 2.0),
];
