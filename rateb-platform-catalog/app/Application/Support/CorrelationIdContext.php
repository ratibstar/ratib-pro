<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class CorrelationIdContext
{
    private static ?string $correlationId = null;

    public static function get(): ?string
    {
        return self::$correlationId;
    }

    public static function set(?string $correlationId): void
    {
        self::$correlationId = $correlationId;
    }

    public static function resolveFromHeader(?string $headerValue): string
    {
        $value = trim((string) $headerValue);
        if ($value !== '' && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $value) === 1) {
            self::$correlationId = $value;

            return $value;
        }

        $generated = \Rateb\PlatformCatalog\Support\Uuid::v4();
        self::$correlationId = $generated;

        return $generated;
    }
}
