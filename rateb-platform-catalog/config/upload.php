<?php

declare(strict_types=1);

return [
    'max_bytes_by_category' => [
        'image' => (int) (getenv('CATALOG_UPLOAD_MAX_IMAGE_BYTES') ?: 20 * 1024 * 1024),
        'document' => (int) (getenv('CATALOG_UPLOAD_MAX_DOCUMENT_BYTES') ?: 50 * 1024 * 1024),
        'video' => (int) (getenv('CATALOG_UPLOAD_MAX_VIDEO_BYTES') ?: 500 * 1024 * 1024),
        'archive' => (int) (getenv('CATALOG_UPLOAD_MAX_ARCHIVE_BYTES') ?: 100 * 1024 * 1024),
        'model_3d' => (int) (getenv('CATALOG_UPLOAD_MAX_MODEL_BYTES') ?: 100 * 1024 * 1024),
        'firmware' => (int) (getenv('CATALOG_UPLOAD_MAX_FIRMWARE_BYTES') ?: 50 * 1024 * 1024),
        'other' => (int) (getenv('CATALOG_UPLOAD_MAX_OTHER_BYTES') ?: 25 * 1024 * 1024),
    ],
    'image_max_width' => (int) (getenv('CATALOG_UPLOAD_IMAGE_MAX_WIDTH') ?: 10000),
    'image_max_height' => (int) (getenv('CATALOG_UPLOAD_IMAGE_MAX_HEIGHT') ?: 10000),
    'image_min_width' => 1,
    'image_min_height' => 1,
    'forbidden_extensions' => [],
];
