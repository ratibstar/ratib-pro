<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Services;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\DnsProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\HostingProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\SslProviderInterface;

/**
 * Resolves interface implementations from RATIB_INFRA_PROVIDER_BINDINGS JSON (class names only).
 */
final class ProviderRegistry
{
    private ?HostingProviderInterface $hosting;
    private ?RegistrarProviderInterface $registrar;
    private ?DnsProviderInterface $dns;
    private ?SslProviderInterface $ssl;

    public function __construct(?HostingProviderInterface $hosting = null, ?RegistrarProviderInterface $registrar = null, ?DnsProviderInterface $dns = null, ?SslProviderInterface $ssl = null) {
        $this->hosting = $hosting;
        $this->registrar = $registrar;
        $this->dns = $dns;
        $this->ssl = $ssl;
    }


    public static function fromEnvironment(): self
    {
        $map = ModuleConfig::providerBindings();
        $make = static function (string $role, array $map, string $iface): ?object {
            if (!isset($map[$role]['class'])) {
                return null;
            }
            /** @var class-string<object> $class */
            $class = (string) $map[$role]['class'];
            if (!class_exists($class)) {
                throw new \RuntimeException('Configured provider class not found: ' . $class);
            }
            $obj = new $class();
            if (!$obj instanceof $iface) {
                throw new \RuntimeException('Configured provider does not implement ' . $iface);
            }

            return $obj;
        };

        /** @phpstan-ignore-next-line */
        return new self(
            $make('hosting', $map, HostingProviderInterface::class),
            $make('registrar', $map, RegistrarProviderInterface::class),
            $make('dns', $map, DnsProviderInterface::class),
            $make('ssl', $map, SslProviderInterface::class),
        );
    }

    public function hosting(): ?HostingProviderInterface
    {
        return $this->hosting;
    }

    public function registrar(): ?RegistrarProviderInterface
    {
        return $this->registrar;
    }

    public function dns(): ?DnsProviderInterface
    {
        return $this->dns;
    }

    public function ssl(): ?SslProviderInterface
    {
        return $this->ssl;
    }
}
