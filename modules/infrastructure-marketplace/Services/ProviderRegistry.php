<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Services;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\DnsProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\HostingProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\SslProviderInterface;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;

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
        /** @phpstan-ignore-next-line */
        return new self(
            self::makeConfiguredProvider('hosting', $map, HostingProviderInterface::class),
            self::makeConfiguredProvider('registrar', $map, RegistrarProviderInterface::class),
            self::makeConfiguredProvider('dns', $map, DnsProviderInterface::class),
            self::makeConfiguredProvider('ssl', $map, SslProviderInterface::class),
        );
    }

    /**
     * Prefer runtime/env bindings, then fill missing roles from ratib_infra_provider_activations.
     */
    public static function fromEnvironmentOrActivationTable(\PDO $pdo, ?int $tenantId = null, ?int $agencyId = null): self
    {
        try {
            $resolved = self::fromEnvironment();
        } catch (\Throwable) {
            $resolved = new self();
        }

        if ($resolved->hosting !== null
            && $resolved->registrar !== null
            && $resolved->dns !== null
            && $resolved->ssl !== null) {
            return $resolved;
        }

        $activations = new ProviderActivationRegistry($pdo);

        /** @var array<string, class-string<object>> $ifaceMap */
        $ifaceMap = [
            'hosting' => HostingProviderInterface::class,
            'registrar' => RegistrarProviderInterface::class,
            'dns' => DnsProviderInterface::class,
            'ssl' => SslProviderInterface::class,
        ];

        foreach ($ifaceMap as $role => $iface) {
            if ($resolved->roleResolved($role)) {
                continue;
            }
            $rows = $activations->activeForScope($role, $tenantId, $agencyId);
            $row = $rows[0] ?? null;
            if (!is_array($row) || !isset($row['provider_class'])) {
                continue;
            }
            $provider = self::makeProviderInstance((string) $row['provider_class'], $iface);
            if ($provider === null) {
                continue;
            }
            $resolved->assignRole($role, $provider);
        }

        return $resolved;
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

    /**
     * @param array<string, mixed> $map
     */
    private static function makeConfiguredProvider(string $role, array $map, string $iface): ?object
    {
        if (!isset($map[$role]['class'])) {
            return null;
        }

        return self::makeProviderInstance((string) $map[$role]['class'], $iface, true);
    }

    private static function makeProviderInstance(string $class, string $iface, bool $strict = false): ?object
    {
        if (!class_exists($class)) {
            if ($strict) {
                throw new \RuntimeException('Configured provider class not found: ' . $class);
            }

            return null;
        }

        try {
            $obj = new $class();
        } catch (\Throwable $e) {
            if ($strict) {
                throw new \RuntimeException('Unable to instantiate provider class: ' . $class, 0, $e);
            }

            return null;
        }

        if (!$obj instanceof $iface) {
            if ($strict) {
                throw new \RuntimeException('Configured provider does not implement ' . $iface);
            }

            return null;
        }

        return $obj;
    }

    private function roleResolved(string $role): bool
    {
        return match ($role) {
            'hosting' => $this->hosting !== null,
            'registrar' => $this->registrar !== null,
            'dns' => $this->dns !== null,
            'ssl' => $this->ssl !== null,
            default => false,
        };
    }

    private function assignRole(string $role, object $provider): void
    {
        switch ($role) {
            case 'hosting':
                if ($provider instanceof HostingProviderInterface) {
                    $this->hosting = $provider;
                }
                break;
            case 'registrar':
                if ($provider instanceof RegistrarProviderInterface) {
                    $this->registrar = $provider;
                }
                break;
            case 'dns':
                if ($provider instanceof DnsProviderInterface) {
                    $this->dns = $provider;
                }
                break;
            case 'ssl':
                if ($provider instanceof SslProviderInterface) {
                    $this->ssl = $provider;
                }
                break;
        }
    }
}
