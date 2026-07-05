<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Contracts\PaymentGatewayInterface;
use Rateb\App\Pos\Services\Drivers\NullPaymentGateway;

final class PosPaymentGatewayService
{
    private PaymentGatewayInterface $gateway;

    public function __construct(?PaymentGatewayInterface $gateway = null)
    {
        $this->gateway = $gateway ?? new NullPaymentGateway();
    }

    public function gateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }
}
