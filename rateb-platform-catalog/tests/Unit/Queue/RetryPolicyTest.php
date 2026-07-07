<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Queue\RetryPolicy;

catalog_test('RetryPolicy immediate first retry', static function (): void {
    catalog_assert_same(0, RetryPolicy::delaySecondsForAttempt(1));
});

catalog_test('RetryPolicy exponential backoff capped at one hour', static function (): void {
    catalog_assert_same(30, RetryPolicy::delaySecondsForAttempt(2));
    catalog_assert_same(3600, RetryPolicy::delaySecondsForAttempt(10));
});
