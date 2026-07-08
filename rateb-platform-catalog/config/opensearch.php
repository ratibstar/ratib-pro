<?php

declare(strict_types=1);

return [
    'OPENSEARCH_HOST' => getenv('OPENSEARCH_HOST') ?: '',
    'OPENSEARCH_USER' => getenv('OPENSEARCH_USER') ?: null,
    'OPENSEARCH_PASSWORD' => getenv('OPENSEARCH_PASSWORD') ?: null,
    'OPENSEARCH_INDEX_PREFIX' => getenv('OPENSEARCH_INDEX_PREFIX') ?: 'catalog',
];
