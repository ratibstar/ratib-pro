<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmAutomationLog;

/**
 * Phase 10 — Automation safety: cooldown, run locks, notify budget (no external monitoring).
 */
final class CrmAutomationSafetyService
{
    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        try {
            return (new CrmGovernanceService())->setting('automation_safety', [
                'notification_cooldown_hours' => 24,
                'run_lock_minutes' => 10,
                'max_notifies_per_run' => 100,
                'include_legacy_in_revops' => false,
                'block_always_rules_over_max' => true,
            ]);
        } catch (\Throwable $e) {
            return [
                'notification_cooldown_hours' => 24,
                'run_lock_minutes' => 10,
                'max_notifies_per_run' => 100,
                'include_legacy_in_revops' => false,
                'block_always_rules_over_max' => true,
            ];
        }
    }

    public function cooldownHours(): int
    {
        return max(1, (int) ($this->settings()['notification_cooldown_hours'] ?? 24));
    }

    public function maxNotifiesPerRun(): int
    {
        return max(1, (int) ($this->settings()['max_notifies_per_run'] ?? 100));
    }

    public function recentlyFired(string $eventType, ?string $entityType, ?int $entityId, ?int $hours = null): bool
    {
        if ($entityId === null || $entityId <= 0) {
            return false;
        }
        $hours = $hours ?? $this->cooldownHours();
        try {
            $row = (new CrmAutomationLog())->queryOne(
                'SELECT id FROM rateb_crm_automation_log
                 WHERE company_id = :cid AND event_type = :et
                   AND entity_type <=> :enty AND entity_id = :eid
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $hours . ' HOUR)
                 LIMIT 1',
                [
                    'cid' => CrmSupport::requireCompanyId(),
                    'et' => substr($eventType, 0, 60),
                    'enty' => $entityType,
                    'eid' => $entityId,
                ]
            );

            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Acquire a short-lived run lock to prevent overlapping identical jobs.
     */
    public function acquireRunLock(string $runKey): bool
    {
        $minutes = max(1, (int) ($this->settings()['run_lock_minutes'] ?? 10));
        $event = 'run_lock:' . substr($runKey, 0, 50);
        try {
            $row = (new CrmAutomationLog())->queryOne(
                'SELECT id FROM rateb_crm_automation_log
                 WHERE company_id = :cid AND event_type = :et
                   AND entity_type = \'crm_run\' AND entity_id = 1
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $minutes . ' MINUTE)
                 LIMIT 1',
                ['cid' => CrmSupport::requireCompanyId(), 'et' => $event]
            );
            if (is_array($row)) {
                return false;
            }
        } catch (\Throwable $e) {
            // proceed if log table unavailable
        }
        $this->record($event, 'crm_run', 1, ['lock' => true, 'minutes' => $minutes]);

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(string $eventType, ?string $entityType, ?int $entityId, array $payload = [], ?int $userId = null): void
    {
        try {
            (new CrmAutomationLog())->create([
                'company_id' => CrmSupport::requireCompanyId(),
                'event_type' => substr($eventType, 0, 60),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId ?? CrmSupport::userId(),
                'payload_json' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            // never break automation for logging
        }
    }

    /**
     * @return array{allowed:bool,reason:?string}
     */
    public function allowNotify(string $eventType, ?string $entityType, ?int $entityId, int &$budget): array
    {
        if ($budget <= 0) {
            return ['allowed' => false, 'reason' => 'notify_budget_exhausted'];
        }
        if ($this->recentlyFired($eventType, $entityType, $entityId)) {
            return ['allowed' => false, 'reason' => 'cooldown'];
        }
        --$budget;

        return ['allowed' => true, 'reason' => null];
    }
}
