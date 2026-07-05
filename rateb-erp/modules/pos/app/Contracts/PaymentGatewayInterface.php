<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

/** Payment gateway abstraction — card/bank/wallet (Phase 5+). */
interface PaymentGatewayInterface
{
    /** @param array<string, mixed> $payload */
    public function authorize(array $payload): array;

    /** @param array<string, mixed> $payload */
    public function capture(array $payload): array;

    /** @param array<string, mixed> $payload */
    public function refund(array $payload): array;

    public function gatewayId(): string;
}
