<?php

declare(strict_types=1);

if (!function_exists('catalog_integration_enabled')) {
    function catalog_integration_enabled(): bool
    {
        return getenv('CATALOG_INTEGRATION_TESTS') === '1';
    }
}

if (!function_exists('catalog_integration_skip')) {
    function catalog_integration_skip(string $reason): void
    {
        if (!catalog_integration_enabled()) {
            echo "[SKIP] {$reason} (set CATALOG_INTEGRATION_TESTS=1)\n";

            return;
        }

        throw new RuntimeException($reason);
    }
}
