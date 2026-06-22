<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor\SupervisorAlertRepository;

final class SupervisorAlertService
{
    private const DEDUP_MINUTES = 15;

    public function __construct(
        private readonly SupervisorAlertRepository $alerts = new SupervisorAlertRepository(),
        private readonly SupervisorAuditService $audit = new SupervisorAuditService()
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, bool $openOnly = true): array
    {
        return $this->alerts->list($tenantId, $openOnly);
    }

    public function acknowledge(int $tenantId, int $alertId, ?int $userId): bool
    {
        $ok = $this->alerts->acknowledge($tenantId, $alertId, $userId);
        if ($ok) {
            $this->audit->log($tenantId, 'supervisor.alert.ack', $userId, 'alert', $alertId);
            EventBus::instance()->emit([
                'type' => EventType::SUPERVISOR_ALERT_ACKNOWLEDGED,
                'tenant_id' => $tenantId,
                'payload' => ['alert_id' => $alertId],
            ]);
        }
        return $ok;
    }

    /** @return list<array<string, mixed>> */
    public function listRules(int $tenantId): array
    {
        return $this->alerts->listRules($tenantId);
    }

    /** @param array<string, mixed> $config */
    public function saveRule(int $tenantId, string $ruleKey, bool $enabled, array $config, ?int $userId): void
    {
        $this->alerts->saveRule($tenantId, $ruleKey, $enabled, $config);
        $this->audit->log($tenantId, 'supervisor.alert.rule', $userId, 'rule', null, [
            'rule_key' => $ruleKey, 'enabled' => $enabled,
        ]);
    }

    /**
     * Raise alert with deduplication and optional rule gate.
     *
     * @param array<string, mixed> $data
     */
    public function raise(int $tenantId, array $data, ?string $ruleKey = null): int
    {
        if ($ruleKey !== null && !$this->alerts->isRuleEnabled($tenantId, $ruleKey)) {
            return 0;
        }

        $alertType = (string) ($data['alert_type'] ?? 'general');
        $queueId = isset($data['queue_id']) ? (int) $data['queue_id'] : null;
        $agentId = isset($data['agent_id']) ? (int) $data['agent_id'] : null;

        if ($this->alerts->hasRecentOpenAlert($tenantId, $alertType, $queueId ?: null, $agentId ?: null, self::DEDUP_MINUTES)) {
            return 0;
        }

        $id = $this->alerts->create($tenantId, $data);
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_ALERT_RAISED,
            'tenant_id' => $tenantId,
            'queue_id' => $queueId,
            'agent_id' => $agentId,
            'payload' => array_merge($data, ['alert_id' => $id]),
        ]);
        return $id;
    }

    /** @return array<string, mixed> */
    public function ruleConfig(int $tenantId, string $ruleKey): array
    {
        return $this->alerts->ruleConfig($tenantId, $ruleKey);
    }

    public function evaluateLongBreaks(int $tenantId): void
    {
        if (!$this->alerts->isRuleEnabled($tenantId, 'agent_long_break')) {
            return;
        }
        $cfg = $this->alerts->ruleConfig($tenantId, 'agent_long_break');
        $maxMinutes = (int) ($cfg['max_break_minutes'] ?? 30);
        foreach ($this->alerts->openBreaksExceedingMinutes($tenantId, $maxMinutes) as $break) {
            $agentId = (int) $break['agent_id'];
            $mins = (int) ($break['break_minutes'] ?? $maxMinutes);
            $this->raise($tenantId, [
                'alert_type' => 'agent_long_break',
                'severity' => 'warning',
                'title' => 'Agent break exceeded limit',
                'title_ar' => 'تجاوز الوكيل مدة الاستراحة',
                'message' => sprintf(
                    '%s on %s break for %d+ minutes',
                    (string) ($break['display_name'] ?? 'Agent'),
                    (string) ($break['break_type'] ?? 'other'),
                    $mins
                ),
                'source_event' => EventType::SUPERVISOR_BREAK_STARTED,
                'agent_id' => $agentId,
                'payload' => $break,
            ], 'agent_long_break');
        }
    }
}
