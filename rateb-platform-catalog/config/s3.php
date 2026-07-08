<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(getenv('CATALOG_S3_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'endpoint' => (string) (getenv('S3_ENDPOINT') ?: ''),
    'bucket' => (string) (getenv('S3_BUCKET') ?: ''),
    'key' => (string) (getenv('S3_KEY') ?: ''),
    'secret' => (string) (getenv('S3_SECRET') ?: ''),
    'region' => (string) (getenv('S3_REGION') ?: 'us-east-1'),
    'use_path_style' => filter_var(getenv('S3_USE_PATH_STYLE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'signed_urls_enabled' => filter_var(getenv('CATALOG_SIGNED_URLS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
];
