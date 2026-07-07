<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Logging;

use Rateb\PlatformCatalog\Application\Contracts\StructuredLoggerInterface;

final class NullStructuredLogger implements StructuredLoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
    }
}
