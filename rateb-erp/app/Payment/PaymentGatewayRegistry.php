<?php
declare(strict_types=1);

namespace Rateb\App\Payment;

use Rateb\App\Payment\Contracts\PaymentGatewayInterface;
use Rateb\App\Payment\Exceptions\PaymentException;
use Rateb\App\Payment\Gateways\MoyasarGateway;

final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $drivers = [];

    public function __construct(
        private readonly PaymentConfigService $config,
    ) {
    }

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->drivers[$gateway->slug()] = $gateway;
    }

    public function driver(string $slug, ?int $companyId = null): PaymentGatewayInterface
    {
        if (isset($this->drivers[$slug])) {
            return $this->drivers[$slug];
        }

        return match ($slug) {
            'moyasar' => new MoyasarGateway(
                secretKey: $this->config->secretKey($slug, $companyId),
                webhookSecret: $this->config->webhookSecret($slug, $companyId),
                mode: $this->config->mode($slug, $companyId),
            ),
            default => throw new PaymentException('Unsupported payment gateway: ' . $slug, 'unsupported_gateway'),
        };
    }

    /** @return list<string> */
    public function enabledSlugs(?int $companyId = null): array
    {
        $slugs = [];
        if ($this->config->isEnabled('moyasar', $companyId)) {
            $slugs[] = 'moyasar';
        }

        return $slugs;
    }
}
