<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Logging;

use Rateb\PlatformCatalog\Application\Contracts\StructuredLoggerInterface;

final class FileStructuredLogger implements StructuredLoggerInterface
{
    public function __construct(
        private readonly string $logFilePath
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => 'error',
            'message' => $message,
            'context' => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        $dir = dirname($this->logFilePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($this->logFilePath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
