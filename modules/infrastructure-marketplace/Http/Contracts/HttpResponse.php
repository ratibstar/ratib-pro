<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Http\Contracts;

final class HttpResponse
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body,
        private readonly ?array $json = null
    ) {}

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        return $this->json;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}

