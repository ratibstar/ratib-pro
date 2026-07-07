<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Contracts;

interface StructuredLoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;
}
