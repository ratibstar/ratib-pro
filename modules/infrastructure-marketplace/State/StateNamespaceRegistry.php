<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\State;

use RATEB\InfrastructureMarketplace\Provisioning\Lifecycle\ProvisioningState;

/**
 * Enforces separation between queue (job), commerce (subscription/sellable), and provisioning phase vocabulary.
 * Does NOT mutate enums or DB values.
 */
final class StateNamespaceRegistry
{
    public const NS_QUEUE = 'queue_state';
    public const NS_COMMERCE = 'commerce_state';
    public const NS_PROVISIONING_PHASE = 'provisioning_phase';
    public const NS_OWNERSHIP = 'ownership_state';
    /** Provider health / remote state — not queue, commerce, ownership, or DB job row. */
    public const NS_PROVIDER_STATE = 'provider_state';

    /** @var list<string> */
    private const COMMERCE_STATES = [
        'ACTIVE', 'TRIAL', 'SUSPENDED', 'EXPIRED', 'CANCELLED', 'PENDING_ACTIVATION',
        'TRANSFERRED',
    ];

    /** Ownership overlay on rateb_tenant_resources — literals distinct from commerce plan states where ambiguous. */
    private const OWNERSHIP_STATES = ['OWNED', 'UNCLAIMED', 'DISABLED', 'PENDING_LINK'];

    /**
     * Provisioning **phase** labels for high-level orchestration (not rateb_infra_provisioning_jobs.status).
     * Note: WAITING_EXTERNAL overlaps the queue literal in this codebase — disambiguate with context or use WAITING_PROVIDER.
     */
    private const PROVISIONING_PHASES = [
        'VALIDATING', 'DNS_SETUP', 'SSL_PENDING', 'WAITING_PROVIDER',
    ];

    /** Advisory literals for adapter health / remote API state (extend as needed). */
    private const PROVIDER_STATES = ['HEALTHY', 'DEGRADED', 'UNKNOWN', 'OFFLINE', 'RATE_LIMITED', 'AUTH_ERROR'];

    /**
     * @return list<string>
     */
    public static function validateOwnershipState(string $state): array
    {
        $s = strtoupper(trim($state));
        $warnings = [];
        if (!in_array($s, self::OWNERSHIP_STATES, true)) {
            $warnings[] = 'Unknown ownership_state: ' . $state;
        }
        if (in_array($s, ProvisioningState::all(), true)) {
            $warnings[] = 'ownership_state must not reuse queue_state literal ' . $s;
        }

        return $warnings;
    }

    /**
     * @return list<string> warnings (non-fatal)
     */
    public static function validateQueueState(string $state): array
    {
        $s = strtoupper(trim($state));
        $warnings = [];
        if (!in_array($s, ProvisioningState::all(), true)) {
            $warnings[] = 'Unknown queue_state: ' . $state . ' (expected ProvisioningState constants).';
        }
        if (in_array($s, self::COMMERCE_STATES, true)) {
            $warnings[] = 'Value ' . $s . ' looks like commerce_state; do not use as queue_state.';
        }
        return $warnings;
    }

    /**
     * @return list<string>
     */
    public static function validateCommerceState(string $state): array
    {
        $s = strtoupper(trim($state));
        $warnings = [];
        if (!in_array($s, self::COMMERCE_STATES, true)) {
            $warnings[] = 'Unknown commerce_state: ' . $state;
        }
        if (in_array($s, ProvisioningState::all(), true)) {
            $warnings[] = 'Value ' . $s . ' collides with queue_state vocabulary; commerce layer should not reuse queue literals.';
        }
        return $warnings;
    }

    /**
     * @return list<string>
     */
    /**
     * @return list<string>
     */
    public static function validateProviderState(string $state): array
    {
        $s = strtoupper(trim($state));
        $warnings = [];
        if (!in_array($s, self::PROVIDER_STATES, true)) {
            $warnings[] = 'Unknown provider_state: ' . $state;
        }
        if (in_array($s, ProvisioningState::all(), true)) {
            $warnings[] = 'provider_state must not reuse queue_state literal ' . $s;
        }
        if (in_array($s, self::COMMERCE_STATES, true)) {
            $warnings[] = 'provider_state must not reuse commerce_state literal ' . $s;
        }
        if (in_array($s, self::OWNERSHIP_STATES, true)) {
            $warnings[] = 'provider_state must not reuse ownership_state literal ' . $s;
        }

        return $warnings;
    }

    public static function validateProvisioningPhase(string $state): array
    {
        $s = strtoupper(trim($state));
        $warnings = [];
        if (in_array($s, self::PROVISIONING_PHASES, true)) {
            return [];
        }
        if ($s === 'WAITING_EXTERNAL') {
            return ['Ambiguous: WAITING_EXTERNAL is used as rateb_infra_provisioning_jobs.status (queue namespace). Prefer WAITING_PROVIDER for non-queue provisioning phases.'];
        }
        $warnings[] = 'Unknown provisioning_phase: ' . $state;
        if (in_array($s, self::COMMERCE_STATES, true)) {
            $warnings[] = 'Provisioning phase must not reuse commerce_state value ' . $s . '.';
        }
        return $warnings;
    }

    public static function namespaceFor(string $state): ?string
    {
        $s = strtoupper(trim($state));
        if (in_array($s, ProvisioningState::all(), true)) {
            return self::NS_QUEUE;
        }
        if (in_array($s, self::PROVIDER_STATES, true)) {
            return self::NS_PROVIDER_STATE;
        }
        if (in_array($s, self::COMMERCE_STATES, true)) {
            return self::NS_COMMERCE;
        }
        if (in_array($s, self::OWNERSHIP_STATES, true)) {
            return self::NS_OWNERSHIP;
        }
        if (in_array($s, self::PROVISIONING_PHASES, true)) {
            return self::NS_PROVISIONING_PHASE;
        }
        if ($s === 'WAITING_EXTERNAL') {
            return null;
        }
        return null;
    }

    /**
     * Uppercase trim only; does not change semantics across namespaces.
     */
    public static function normalize(string $state): string
    {
        return strtoupper(trim($state));
    }
}
