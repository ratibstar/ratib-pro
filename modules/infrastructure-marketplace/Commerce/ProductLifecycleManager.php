<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Commerce;

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\State\StateNamespaceRegistry;

/**
 * Customer-facing commerce lifecycle for plans/products metadata — does not touch queue workers.
 */
final class ProductLifecycleManager
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'ACTIVE' => ['SUSPENDED', 'CANCELLED', 'EXPIRED'],
        'TRIAL' => ['ACTIVE', 'SUSPENDED', 'CANCELLED', 'EXPIRED'],
        'PENDING_ACTIVATION' => ['ACTIVE', 'CANCELLED'],
        'SUSPENDED' => ['ACTIVE', 'CANCELLED', 'EXPIRED'],
        'EXPIRED' => ['ACTIVE', 'CANCELLED'],
        'CANCELLED' => [],
    ];

    public function __construct(
        private \PDO $pdo,
        private ?InfrastructureAuditLogger $audit = null
    ) {
        if ($this->audit === null) {
            $this->audit = new InfrastructureAuditLogger($pdo, new InfrastructureEventEmitter());
        }
    }

    /**
     * @return list<string> warnings
     */
    public function assertCommerceTransition(string $from, string $to): array
    {
        $f = StateNamespaceRegistry::normalize($from);
        $t = StateNamespaceRegistry::normalize($to);
        $warnings = array_merge(
            StateNamespaceRegistry::validateCommerceState($f),
            StateNamespaceRegistry::validateCommerceState($t)
        );
        $next = self::ALLOWED[$f] ?? null;
        if ($next === null) {
            $warnings = array_merge($warnings, StateNamespaceRegistry::validateCommerceState($f));
            $warnings[] = 'Unknown commerce from-state: ' . $f;

            return $warnings;
        }
        if (!in_array($t, $next, true) && $f !== $t) {
            $warnings[] = 'Disallowed commerce transition ' . $f . ' → ' . $t;
        }

        return $warnings;
    }

    /**
     * Updates rateb_infra_plans.commerce_state only (additive column already on plans table).
     *
     * @return list<string> warnings
     */
    public function transitionPlanCommerceState(
        int $planId,
        string $fromState,
        string $toState,
        string $actor,
        ?string $correlationId = null,
        ?string $traceId = null
    ): array {
        $warnings = $this->assertCommerceTransition($fromState, $toState);
        $stmt = $this->pdo->prepare('SELECT id, commerce_state FROM rateb_infra_plans WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $planId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $warnings[] = 'Plan not found: ' . $planId;

            return $warnings;
        }
        $current = StateNamespaceRegistry::normalize((string) ($row['commerce_state'] ?? ''));
        if ($current !== StateNamespaceRegistry::normalize($fromState)) {
            $warnings[] = 'Plan ' . $planId . ' commerce_state drift: expected ' . $fromState . ', got ' . $current;
        }
        $warnings = array_merge($warnings, StateNamespaceRegistry::validateCommerceState($toState));
        if ($this->hasBlockingWarnings($warnings)) {
            return $warnings;
        }
        $upd = $this->pdo->prepare('UPDATE rateb_infra_plans SET commerce_state = :s, updated_at = NOW() WHERE id = :id');
        $upd->execute(['s' => StateNamespaceRegistry::normalize($toState), 'id' => $planId]);

        $this->audit->appendImmutable('commerce_plan_lifecycle', [
            'actor' => $actor,
            'tenant_id' => null,
            'plan_id' => $planId,
            'from' => $fromState,
            'to' => $toState,
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
        ]);

        return $warnings;
    }

    /**
     * @param list<string> $warnings
     */
    private function hasBlockingWarnings(array $warnings): bool
    {
        foreach ($warnings as $w) {
            if (str_contains((string) $w, 'Disallowed')
                || str_contains((string) $w, 'collides')
                || str_contains((string) $w, 'Unknown commerce from-state')) {
                return true;
            }
        }

        return false;
    }
}
