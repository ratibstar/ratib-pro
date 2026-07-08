<?php

declare(strict_types=1);

if (!function_exists('catalog_adapter_tests_enabled')) {
    function catalog_adapter_tests_enabled(string $adapter): bool
    {
        return strtolower((string) (getenv('CATALOG_ADAPTER_TESTS') ?: '')) === strtolower($adapter);
    }
}
