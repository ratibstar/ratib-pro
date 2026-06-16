<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Provisioning\Lifecycle;

final class StateTransitionValidator
{
    /**
     * @var array<string, list<string>>
     */
    private array $allowed = [
        ProvisioningState::PENDING => [ProvisioningState::QUEUED, ProvisioningState::CANCELLED],
        ProvisioningState::QUEUED => [ProvisioningState::RUNNING, ProvisioningState::CANCELLED],
        ProvisioningState::RUNNING => [
            ProvisioningState::WAITING_EXTERNAL,
            ProvisioningState::RETRYING,
            ProvisioningState::COMPLETED,
            ProvisioningState::FAILED,
            ProvisioningState::RECONCILING,
        ],
        ProvisioningState::RETRYING => [ProvisioningState::QUEUED, ProvisioningState::DEAD_LETTER, ProvisioningState::FAILED],
        ProvisioningState::WAITING_EXTERNAL => [ProvisioningState::RUNNING, ProvisioningState::RECONCILING, ProvisioningState::FAILED],
        ProvisioningState::FAILED => [ProvisioningState::RETRYING, ProvisioningState::DEAD_LETTER, ProvisioningState::RECONCILING],
        ProvisioningState::DEAD_LETTER => [ProvisioningState::RECONCILING, ProvisioningState::CANCELLED],
        ProvisioningState::RECONCILING => [ProvisioningState::RUNNING, ProvisioningState::COMPLETED, ProvisioningState::FAILED, ProvisioningState::CANCELLED],
        ProvisioningState::COMPLETED => [],
        ProvisioningState::CANCELLED => [],
    ];

    public function assertValid(string $fromState, string $toState): void
    {
        $from = strtoupper(trim($fromState));
        $to = strtoupper(trim($toState));
        if (!in_array($from, ProvisioningState::all(), true) || !in_array($to, ProvisioningState::all(), true)) {
            throw new \InvalidArgumentException('Unknown provisioning state transition.');
        }
        $allowed = $this->allowed[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('Invalid state transition from ' . $from . ' to ' . $to . '.');
        }
    }
}

