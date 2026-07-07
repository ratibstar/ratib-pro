<?php

declare(strict_types=1);

return [
    'SEARCH_ADAPTER' => getenv('SEARCH_ADAPTER') ?: 'meilisearch',
    'MEILISEARCH_HOST' => getenv('MEILISEARCH_HOST') ?: '',
    'MEILISEARCH_API_KEY' => getenv('MEILISEARCH_API_KEY') ?: '',
    'QUEUE_ADAPTER' => getenv('QUEUE_ADAPTER') ?: 'database',
    'STORAGE_ADAPTER' => getenv('STORAGE_ADAPTER') ?: 'local',
    'SEARCH_REINDEX_BATCH_SIZE' => (int) (getenv('SEARCH_REINDEX_BATCH_SIZE') ?: 500),
    'SEARCH_MAINTENANCE_MODE' => (bool) (getenv('SEARCH_MAINTENANCE_MODE') ?: false),
];
