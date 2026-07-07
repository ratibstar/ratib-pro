<?php

declare(strict_types=1);

/**
 * Trusted gateway identity — v1.3.1 production hardening.
 *
 * When enabled, X-Platform-User-Id is accepted only when the gateway presents
 * a matching X-Platform-Gateway-Token. Direct client header injection is rejected.
 */
if (!defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED')) {
    define(
        'RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED',
        filter_var(getenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)
    );
}

if (!defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET')) {
    define(
        'RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET',
        (string) (getenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET') ?: '')
    );
}

if (!defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_TOKEN_HEADER')) {
    define('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_TOKEN_HEADER', 'X-Platform-Gateway-Token');
}

if (!defined('RATEB_PLATFORM_CATALOG_PLATFORM_USER_ID_HEADER')) {
    define('RATEB_PLATFORM_CATALOG_PLATFORM_USER_ID_HEADER', 'X-Platform-User-Id');
}
