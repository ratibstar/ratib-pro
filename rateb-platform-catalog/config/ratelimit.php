<?php

declare(strict_types=1);

return [
    'RATE_LIMIT_ENABLED' => filter_var(getenv('RATE_LIMIT_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL),
    'RATE_LIMIT_ADMIN_PER_MIN' => (int) (getenv('RATE_LIMIT_ADMIN_PER_MIN') ?: 300),
    'RATE_LIMIT_API_READ_PER_MIN' => (int) (getenv('RATE_LIMIT_API_READ_PER_MIN') ?: 300),
    'RATE_LIMIT_API_WRITE_PER_MIN' => (int) (getenv('RATE_LIMIT_API_WRITE_PER_MIN') ?: 60),
    'RATE_LIMIT_BULK_CONCURRENT' => (int) (getenv('RATE_LIMIT_BULK_CONCURRENT') ?: 10),
    'RATE_LIMIT_MEDIA_PER_MIN' => (int) (getenv('RATE_LIMIT_MEDIA_PER_MIN') ?: 1000),
];
