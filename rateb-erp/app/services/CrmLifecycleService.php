<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmEntityStatusHistory;
use Rateb\App\Models\CrmLifecycleEvent;
use Rateb\App\Models\Customer;

/**
 * Phase 5 — Customer lifecycle on existing rateb_customers (no new customer master).
 * Prospect → Lead → Opportunity → Customer → Active Customer → Retention / Renewal
 */
final class CrmLifecycleService
{
    public const STAGES = [
        'prospect',
        'lead',
        'opportunity',
        'customer',
        'active_customer',
        'retention',
        'renewal',
    ];

    /** @return list<string> */
    public function stages(): array
    {
        return self::STAGES;
    }

    public function assertStage(string $stage): string
    {
        $stage = strtolower(trim($stage));
        if (!in_array($stage, self::STAGES, true)) {
            throw new \InvalidArgumentException('invalid_lifecycle_stage');
        }

        return $stage;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCustomer(int $customerId): ?array
    {
        $row = (new Customer())->queryOne(
            'SELECT id, company_id, code, name, crm_lifecycle_stage, crm_owner_user_id, crm_team_id,
                    crm_territory_id, crm_last_interaction_at, crm_activity_score, crm_renewal_due_at, crm_at_risk
             FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $customerId, 'cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{customer_id: int, from_stage: ?string, to_stage: string}
     */
    public function transition(int $customerId, string $toStage, ?string $reason = null, array $meta = []): array
    {
        $customer = $this->findCustomer($customerId);
        if ($customer === null) {
            throw new \RuntimeException('customer_not_found');
        }
        $to = $this->assertStage($toStage);
        $from = $customer['crm_lifecycle_stage'] !== null && $customer['crm_lifecycle_stage'] !== ''
            ? (string) $customer['crm_lifecycle_stage']
            : null;
        if ($from === $to) {
            return ['customer_id' => $customerId, 'from_stage' => $from, 'to_stage' => $to];
        }

        (new Customer())->update($customerId, [
            'crm_lifecycle_stage' => $to,
        ]);

        $this->recordEvent($customerId, $from, $to, 'lifecycle_transition', $reason, $meta, [
            'owner_user_id' => CrmSupport::intOrNull($customer['crm_owner_user_id'] ?? null),
            'team_id' => CrmSupport::intOrNull($customer['crm_team_id'] ?? null),
        ]);

        (new CrmEntityStatusHistory())->create([
            'company_id' => CrmSupport::requireCompanyId(),
            'entity_type' => 'customer_lifecycle',
            'entity_id' => $customerId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'created_by' => CrmSupport::userId(),
        ]);

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.lifecycle.transition', 'customer', $customerId, [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);
        }

        return ['customer_id' => $customerId, 'from_stage' => $from, 'to_stage' => $to];
    }

    /**
     * @param array<string, mixed> $input owner_user_id / team_id / territory_id
     */
    public function assignOwnership(int $customerId, array $input): void
    {
        $customer = $this->findCustomer($customerId);
        if ($customer === null) {
            throw new \RuntimeException('customer_not_found');
        }
        $patch = [];
        $fromOwner = CrmSupport::intOrNull($customer['crm_owner_user_id'] ?? null);
        $fromTeam = CrmSupport::intOrNull($customer['crm_team_id'] ?? null);
        if (array_key_exists('crm_owner_user_id', $input) || array_key_exists('owner_user_id', $input)) {
            $patch['crm_owner_user_id'] = CrmSupport::intOrNull($input['crm_owner_user_id'] ?? $input['owner_user_id'] ?? null);
        }
        if (array_key_exists('crm_team_id', $input) || array_key_exists('team_id', $input)) {
            $patch['crm_team_id'] = CrmSupport::intOrNull($input['crm_team_id'] ?? $input['team_id'] ?? null);
        }
        if (array_key_exists('crm_territory_id', $input) || array_key_exists('territory_id', $input)) {
            $patch['crm_territory_id'] = CrmSupport::intOrNull($input['crm_territory_id'] ?? $input['territory_id'] ?? null);
        }
        if ($patch === []) {
            throw new \InvalidArgumentException('ownership_fields_required');
        }
        (new Customer())->update($customerId, $patch);

        $this->recordEvent(
            $customerId,
            (string) ($customer['crm_lifecycle_stage'] ?? 'customer'),
            (string) ($customer['crm_lifecycle_stage'] ?? 'customer'),
            'ownership_change',
            null,
            [
                'from_owner' => $fromOwner,
                'to_owner' => $patch['crm_owner_user_id'] ?? $fromOwner,
                'from_team' => $fromTeam,
                'to_team' => $patch['crm_team_id'] ?? $fromTeam,
                'territory_id' => $patch['crm_territory_id'] ?? null,
            ],
            [
                'owner_user_id' => $patch['crm_owner_user_id'] ?? $fromOwner,
                'team_id' => $patch['crm_team_id'] ?? $fromTeam,
            ]
        );

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.lifecycle.ownership', 'customer', $customerId, $patch);
        }
    }

    /**
     * Ensure a customer has at least the given lifecycle stage (does not downgrade).
     */
    public function ensureAtLeast(int $customerId, string $minStage, ?string $reason = null): void
    {
        $order = array_flip(self::STAGES);
        $customer = $this->findCustomer($customerId);
        if ($customer === null) {
            return;
        }
        $min = $this->assertStage($minStage);
        $current = (string) ($customer['crm_lifecycle_stage'] ?? 'customer');
        if (!isset($order[$current]) || $order[$current] < ($order[$min] ?? 0)) {
            $this->transition($customerId, $min, $reason);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $customerId, int $limit = 50): array
    {
        $safe = max(1, min(100, $limit));
        $rows = (new CrmLifecycleEvent())->query(
            'SELECT * FROM rateb_crm_lifecycle_events
             WHERE company_id = :cid AND customer_id = :cuid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => CrmSupport::requireCompanyId(), 'cuid' => $customerId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $meta
     * @param array{owner_user_id?: ?int, team_id?: ?int} $context
     */
    private function recordEvent(
        int $customerId,
        ?string $from,
        string $to,
        string $eventType,
        ?string $reason,
        array $meta,
        array $context = []
    ): void {
        (new CrmLifecycleEvent())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => CrmSupport::requireCompanyId(),
            'customer_id' => $customerId,
            'from_stage' => $from,
            'to_stage' => $to,
            'event_type' => substr($eventType, 0, 60),
            'owner_user_id' => $context['owner_user_id'] ?? null,
            'team_id' => $context['team_id'] ?? null,
            'reason' => $reason !== null ? substr($reason, 0, 255) : null,
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => CrmSupport::userId(),
        ]);
    }
}
