<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class WebhookHmacSigner
{
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public static function verify(string $secret, int $timestamp, string $body, string $signature, int $maxSkewSeconds = 300): bool
    {
        if (abs(time() - $timestamp) > $maxSkewSeconds) {
            return false;
        }
        $expected = self::sign($secret, $timestamp, $body);

        return hash_equals($expected, $signature);
    }
}
