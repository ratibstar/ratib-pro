<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Http\Contracts;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, scalar|null> $query
     */
    public function get(string $url, array $headers = [], array $query = []): HttpResponse;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $jsonBody
     */
    public function post(string $url, array $headers = [], array $jsonBody = []): HttpResponse;
}

