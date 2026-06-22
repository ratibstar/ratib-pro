<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor\SupervisorAlertRepository;

final class SupervisorAlertService
{
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

    /** @param array<string, mixed> $data */
    public function raise(int $tenantId, array $data): int
    {
        $id = $this->alerts->create($tenantId, $data);
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_ALERT_RAISED,
            'tenant_id' => $tenantId,
            'queue_id' => $data['queue_id'] ?? null,
            'agent_id' => $data['agent_id'] ?? null,
            'payload' => array_merge($data, ['alert_id' => $id]),
        ]);
        return $id;
    }
}
