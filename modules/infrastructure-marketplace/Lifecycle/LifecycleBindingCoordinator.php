<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Lifecycle;

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Provisioning\Lifecycle\ProvisioningState;
use RATEB\InfrastructureMarketplace\State\StateNamespaceRegistry;

/**
 * Correlates parallel lifecycles without merging enums (namespace-tagged snapshots).
 */
final class LifecycleBindingCoordinator
{
    public function __construct(
        private ?InfrastructureAuditLogger $audit = null
    ) {
    }

    public static function withPdo(\PDO $pdo): self
    {
        return new self(new InfrastructureAuditLogger($pdo, new InfrastructureEventEmitter()));
    }

    /**
     * @param array{
     *   commerce_state?: string|null,
     *   ownership_state?: string|null,
     *   provisioning_phase?: string|null,
     *   queue_state?: string|null,
     *   provider_state?: string|null
     * } $snapshot
     *
     * @return array{synchronized: bool, namespaces: array<string, string|null>, warnings: list<string>}
     */
    public function synchronize(array $snapshot, string $actor, ?string $correlationId = null, ?string $traceId = null): array
    {
        $warnings = [];
        $namespaces = [];
        if (isset($snapshot['commerce_state']) && (string) $snapshot['commerce_state'] !== '') {
            $s = StateNamespaceRegistry::normalize((string) $snapshot['commerce_state']);
            $namespaces['commerce_state'] = StateNamespaceRegistry::NS_COMMERCE;
            $warnings = array_merge($warnings, StateNamespaceRegistry::validateCommerceState($s));
        }
        if (isset($snapshot['ownership_state']) && (string) $snapshot['ownership_state'] !== '') {
            $s = StateNamespaceRegistry::normalize((string) $snapshot['ownership_state']);
            $namespaces['ownership_state'] = StateNamespaceRegistry::NS_OWNERSHIP;
            $warnings = array_merge($warnings, StateNamespaceRegistry::validateOwnershipState($s));
        }
        if (isset($snapshot['provisioning_phase']) && (string) $snapshot['provisioning_phase'] !== '') {
            $s = StateNamespaceRegistry::normalize((string) $snapshot['provisioning_phase']);
            $namespaces['provisioning_phase'] = StateNamespaceRegistry::NS_PROVISIONING_PHASE;
            $warnings = array_merge($warnings, StateNamespaceRegistry::validateProvisioningPhase($s));
        }
        if (isset($snapshot['queue_state']) && (string) $snapshot['queue_state'] !== '') {
            $s = StateNamespaceRegistry::normalize((string) $snapshot['queue_state']);
            $namespaces['queue_state'] = StateNamespaceRegistry::NS_QUEUE;
            $warnings = array_merge($warnings, StateNamespaceRegistry::validateQueueState($s));
        }
        if (isset($snapshot['provider_state']) && (string) $snapshot['provider_state'] !== '') {
            $s = StateNamespaceRegistry::normalize((string) $snapshot['provider_state']);
            $namespaces['provider_state'] = StateNamespaceRegistry::NS_PROVIDER_STATE;
            $warnings = array_merge($warnings, StateNamespaceRegistry::validateProviderState($s));
        }
        $warnings = array_merge($warnings, $this->emitLifecycleWarnings($snapshot));

        if ($this->audit !== null) {
            $this->audit->appendImmutable('lifecycle_binding_snapshot', [
                'actor' => $actor,
                'tenant_id' => null,
                'snapshot' => $snapshot,
                'namespaces' => $namespaces,
                'warnings' => $warnings,
                'correlation_id' => $correlationId,
                'trace_id' => $traceId,
            ]);
        }

        return [
            'synchronized' => $warnings === [],
            'namespaces' => $namespaces,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $expected keyed snapshot
     * @param array<string, mixed> $actual keyed snapshot
     *
     * @return list<string>
     */
    public function detectStateDrift(array $expected, array $actual): array
    {
        $drift = [];
        foreach (['commerce_state', 'ownership_state', 'provisioning_phase', 'queue_state', 'provider_state'] as $k) {
            $e = isset($expected[$k]) ? StateNamespaceRegistry::normalize((string) $expected[$k]) : '';
            $a = isset($actual[$k]) ? StateNamespaceRegistry::normalize((string) $actual[$k]) : '';
            if ($e !== '' && $a !== '' && $e !== $a) {
                $drift[] = 'Drift on ' . $k . ': expected ' . $e . ' actual ' . $a;
            }
        }

        return $drift;
    }

    /**
     * @return list<string> advisory reconciliation steps (no DB writes)
     */
    public function reconcileLifecycle(array $snapshot): array
    {
        $hints = [];
        $q = strtoupper(trim((string) ($snapshot['queue_state'] ?? '')));
        $c = strtoupper(trim((string) ($snapshot['commerce_state'] ?? '')));
        if ($q === ProvisioningState::COMPLETED && ($c === 'PENDING_ACTIVATION' || $c === '')) {
            $hints[] = 'Consider transitioning plan commerce_state toward ACTIVE after queue COMPLETED.';
        }
        if ($q === ProvisioningState::FAILED && $c === 'ACTIVE') {
            $hints[] = 'Queue FAILED but commerce ACTIVE — run reconciliation / support playbook.';
        }

        return $hints;
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return list<string>
     */
    public function emitLifecycleWarnings(array $snapshot): array
    {
        return $this->reconcileLifecycle($snapshot);
    }

    /**
     * @return list<string>
     */
    public function validateTransitions(string $namespace, string $from, string $to): array
    {
        $w = [];
        $ns = strtoupper(trim($namespace));
        if ($ns === StateNamespaceRegistry::NS_QUEUE) {
            $w = array_merge($w, StateNamespaceRegistry::validateQueueState($from));
            $w = array_merge($w, StateNamespaceRegistry::validateQueueState($to));
        } elseif ($ns === StateNamespaceRegistry::NS_COMMERCE) {
            $w = array_merge($w, StateNamespaceRegistry::validateCommerceState($from));
            $w = array_merge($w, StateNamespaceRegistry::validateCommerceState($to));
        } elseif ($ns === StateNamespaceRegistry::NS_OWNERSHIP) {
            $w = array_merge($w, StateNamespaceRegistry::validateOwnershipState($from));
            $w = array_merge($w, StateNamespaceRegistry::validateOwnershipState($to));
        } elseif ($ns === StateNamespaceRegistry::NS_PROVISIONING_PHASE) {
            $w = array_merge($w, StateNamespaceRegistry::validateProvisioningPhase($from));
            $w = array_merge($w, StateNamespaceRegistry::validateProvisioningPhase($to));
        } elseif ($ns === StateNamespaceRegistry::NS_PROVIDER_STATE) {
            $w = array_merge($w, StateNamespaceRegistry::validateProviderState($from));
            $w = array_merge($w, StateNamespaceRegistry::validateProviderState($to));
        } else {
            $w[] = 'Unknown namespace for transition validation: ' . $namespace;
        }

        return $w;
    }
}
