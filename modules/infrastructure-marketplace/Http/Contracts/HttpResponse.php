<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Http\Contracts;

final class HttpResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;
    private ?array $json;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    public function __construct(int $statusCode, array $headers, string $body, ?array $json = null) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->json = $json;
    }


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

