<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Hosting\DTOs;

final class HostingOperationResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly bool $ok,
        private readonly string $operation,
        private readonly ?string $reference,
        private readonly array $data = [],
        private readonly ?string $error = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'operation' => $this->operation,
            'reference' => $this->reference,
            'error' => $this->error,
            'data' => $this->data,
        ];
    }
}

