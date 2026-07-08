<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

final class ResponseSentException extends \RuntimeException
{
    /**
     * @param array<string, mixed>|list<mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly array $payload,
        public readonly int $status,
        public readonly array $headers = []
    ) {
        parent::__construct('Response sent');
    }
}
