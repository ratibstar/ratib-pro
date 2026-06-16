<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Hosting\DTOs;

final class HostingOperationResult
{
    private bool $ok;
    private string $operation;
    private ?string $reference;
    private array $data;
    private ?string $error;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(bool $ok, string $operation, ?string $reference, array $data = [], ?string $error = null) {
        $this->ok = $ok;
        $this->operation = $operation;
        $this->reference = $reference;
        $this->data = $data;
        $this->error = $error;
    }


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

