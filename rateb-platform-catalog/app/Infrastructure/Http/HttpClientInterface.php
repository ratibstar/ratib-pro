<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Http;

interface HttpClientInterface
{
    /**
     * @param array<int, string> $headers Full header lines: "Header-Name: value"
     * @return array{status: int, body: ?string}
     */
    public function postRaw(
        string $url,
        string $body,
        array $headers,
        int $timeoutSeconds = 30,
        int $connectTimeoutSeconds = 10
    ): array;
}

