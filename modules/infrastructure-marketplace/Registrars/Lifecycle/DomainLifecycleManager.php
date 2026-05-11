<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Registrars\Lifecycle;

final class DomainLifecycleManager
{
    /**
     * @return array<string, mixed>
     */
    public function registrationPlan(string $fqdn, int $years): array
    {
        return [
            'fqdn' => strtolower($fqdn),
            'years' => max(1, $years),
            'state' => 'REGISTRATION_PREPARED',
            'reconcile_required' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transferPlan(string $fqdn): array
    {
        return [
            'fqdn' => strtolower($fqdn),
            'state' => 'TRANSFER_PREPARED',
            'grace_period_awareness' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function renewalPlan(string $fqdn, \DateTimeInterface $expiresAt): array
    {
        return [
            'fqdn' => strtolower($fqdn),
            'state' => 'RENEWAL_PREPARED',
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'expiration_monitoring' => true,
        ];
    }
}

