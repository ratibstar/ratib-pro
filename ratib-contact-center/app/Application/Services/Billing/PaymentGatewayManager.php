<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Billing;

use Ratib\ContactCenter\App\Application\Contracts\Payment\PaymentGatewayDriverInterface;
use Ratib\ContactCenter\App\Infrastructure\Payment\Drivers\HyperPayGateway;
use Ratib\ContactCenter\App\Infrastructure\Payment\Drivers\MoyasarGateway;
use Ratib\ContactCenter\App\Infrastructure\Payment\Drivers\PayPalGateway;
use Ratib\ContactCenter\App\Infrastructure\Payment\Drivers\StripeGateway;
use Ratib\ContactCenter\App\Infrastructure\Payment\Drivers\TabbyGateway;
use Ratib\ContactCenter\App\Infrastructure\Payment\Drivers\TamaraGateway;

final class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayDriverInterface> */
    private array $drivers;

    public function __construct()
    {
        $this->drivers = [
            'stripe' => new StripeGateway(),
            'paypal' => new PayPalGateway(),
            'moyasar' => new MoyasarGateway(),
            'hyperpay' => new HyperPayGateway(),
            'tabby' => new TabbyGateway(),
            'tamara' => new TamaraGateway(),
        ];
    }

    public function driver(string $slug): ?PaymentGatewayDriverInterface
    {
        return $this->drivers[strtolower($slug)] ?? null;
    }

    /** @return list<string> */
    public function supportedGateways(): array
    {
        return array_keys($this->drivers);
    }
}
