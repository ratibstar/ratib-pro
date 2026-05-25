<?php
/**
 * Zero-dependency CORS handler for mobile portal endpoints.
 * Included before any bootstrap/config so OPTIONS preflight always succeeds.
 */
declare(strict_types=1);

function rateb_mobile_send_cors_headers(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
    header('Access-Control-Max-Age: 86400');
}

rateb_mobile_send_cors_headers();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
