<?php
declare(strict_types=1);

namespace Rateb\App\Subscription\Admin;

/**
 * Immutable dashboard counters for the subscription ops console.
 */
final readonly class SubscriptionAdminDashboard
{
    public function __construct(
        private int $totalTenants,
        private int $active,
        private int $warning,
        private int $grace,
        private int $suspended,
        private int $expiringSoon,
    ) {
    }

    public function totalTenants(): int
    {
        return $this->totalTenants;
    }

    public function active(): int
    {
        return $this->active;
    }

    public function warning(): int
    {
        return $this->warning;
    }

    public function grace(): int
    {
        return $this->grace;
    }

    public function suspended(): int
    {
        return $this->suspended;
    }

    public function expiringSoon(): int
    {
        return $this->expiringSoon;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'total_tenants' => $this->totalTenants,
            'active' => $this->active,
            'warning' => $this->warning,
            'grace' => $this->grace,
            'suspended' => $this->suspended,
            'expiring_soon' => $this->expiringSoon,
        ];
    }
}
