<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Execution;

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\State\StateNamespaceRegistry;

/**
 * Duplicate activation / replay / drift guards (warnings-first).
 */
final class ExecutionSafetyLayer
{
    public function __construct(
        private \PDO $pdo,
        private ?InfrastructureAuditLogger $audit = null
    ) {
    }

    /**
     * @param array<string, mixed> $order rateb_infra_orders row
     *
     * @return array{warnings: list<string>, blockers: list<string>, replay_detected: bool}
     */
    public function assessOrderActivation(array $order, TenantContext $tenant, string $orderPublicId): array
    {
        $warnings = [];
        $blockers = [];
        $replay = $this->hasCompletedActivationAudit($orderPublicId);
        if ($replay) {
            $warnings[] = 'commerce_activation_completed audit already present for this order (idempotent replay).';
        }
        $ot = isset($order['tenant_id']) ? (int) $order['tenant_id'] : null;
        if ($tenant->tenantId() !== null && $ot !== null && $tenant->tenantId() !== $ot) {
            $blockers[] = 'TenantContext tenant_id does not match order.tenant_id.';
        }
        $status = strtoupper(trim((string) ($order['status'] ?? '')));
        if ($status === 'FAILED' || $status === 'DEAD_LETTER' || $status === 'CANCELLED') {
            $warnings[] = 'Order status ' . $status . ' resembles queue vocabulary on order row — verify order vs job namespace.';
        }
        $created = (string) ($order['created_at'] ?? '');
        if ($created !== '' && $status === 'PENDING') {
            try {
                $ts = strtotime($created) ?: 0;
                if ($ts > 0 && (time() - $ts) > 86400) {
                    $warnings[] = 'Stale PENDING order (>24h) — confirm payment / settlement before activation.';
                }
            } catch (\Throwable $e) {
            }
        }
        $warnings = array_merge($warnings, StateNamespaceRegistry::validateQueueState($status));

        return ['warnings' => $warnings, 'blockers' => $blockers, 'replay_detected' => $replay];
    }

    /**
     * @return list<string>
     */
    public function detectDuplicateProvisioningIntent(string $orderPublicId, ?string $intentId): array
    {
        $w = [];
        if ($intentId !== null && $this->auditHasIntent($orderPublicId, $intentId)) {
            $w[] = 'Intent id already recorded in audit for this order.';
        }

        return $w;
    }

    /**
     * @return list<string>
     */
    public function providerMismatchWarnings(string $expectedProfileFragment, string $resolvedBinderTarget): array
    {
        if ($expectedProfileFragment === '' || $resolvedBinderTarget === '') {
            return ['Provider target or provisioning profile empty — cannot verify match.'];
        }
        if (!str_contains(strtolower($resolvedBinderTarget), strtolower($expectedProfileFragment))) {
            return ['Provider binding target may not match plan provisioning_profile fragment: ' . $expectedProfileFragment];
        }

        return [];
    }

    public function logSafetyScan(
        string $orderPublicId,
        array $payload,
        string $actor,
        ?InfrastructureAuditLogger $audit = null
    ): void {
        $logger = $audit ?? $this->audit;
        if ($logger === null) {
            return;
        }
        $logger->appendImmutable('execution_safety_scan', array_merge($payload, [
            'actor' => $actor,
            'order_public_id' => $orderPublicId,
        ]));
    }

    private function hasCompletedActivationAudit(string $orderPublicId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM rateb_infra_audit_entries
                 WHERE action_type = :a
                   AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.order_public_id\')) = :p
                 LIMIT 1'
            );
            $stmt->execute([
                'a' => 'commerce_activation_completed',
                'p' => $orderPublicId,
            ]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (\Throwable $e) {
            $like = '%"order_public_id":"' . str_replace(['%', '_'], ['\\%', '\\_'], $orderPublicId) . '"%';
            $stmt = $this->pdo->prepare(
                'SELECT id FROM rateb_infra_audit_entries WHERE action_type = :a AND payload_json LIKE :l LIMIT 1'
            );
            $stmt->execute(['a' => 'commerce_activation_completed', 'l' => $like]);

            return (bool) $stmt->fetchColumn();
        }

        return false;
    }

    private function auditHasIntent(string $orderPublicId, string $intentId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM rateb_infra_audit_entries
                 WHERE action_type = :a
                   AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.intent_id\')) = :i
                   AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.order_public_id\')) = :p
                 LIMIT 1'
            );
            $stmt->execute([
                'a' => 'commerce_activation_intent',
                'i' => $intentId,
                'p' => $orderPublicId,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
