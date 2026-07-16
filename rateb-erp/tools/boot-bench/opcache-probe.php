<?php
echo json_encode([
    'sapi' => PHP_SAPI,
    'opcache_enabled' => function_exists('opcache_get_status')
        ? ((opcache_get_status(false)['opcache_enabled'] ?? null))
        : null,
    'opcache_status_available' => function_exists('opcache_get_status'),
    'realpath_cache_size' => ini_get('realpath_cache_size'),
    'realpath_cache_ttl' => ini_get('realpath_cache_ttl'),
    'opcache.validate_timestamps' => ini_get('opcache.validate_timestamps'),
    'opcache.revalidate_freq' => ini_get('opcache.revalidate_freq'),
    'opcache.memory_consumption' => ini_get('opcache.memory_consumption'),
    'note' => 'CLI SAPI settings; FPM may differ. Curl isolated TTFB is the production-path PHP evidence.',
], JSON_PRETTY_PRINT);
