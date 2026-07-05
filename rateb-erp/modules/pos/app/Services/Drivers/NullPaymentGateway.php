<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PaymentGatewayInterface;

final class NullPaymentGateway implements PaymentGatewayInterface
{
    public function authorize(array $payload): array
    {
        return ['ok' => false, 'reason' => 'not_implemented'];
    }

    public function capture(array $payload): array
    {
        return ['ok' => false, 'reason' => 'not_implemented'];
    }

    public function refund(array $payload): array
    {
        return ['ok' => false, 'reason' => 'not_implemented'];
    }

    public function gatewayId(): string
    {
        return 'null';
    }
}
