<?php
declare(strict_types=1);

namespace App\Services;

use App\Events\ViolationDetected;
use App\Events\WorkflowExecutionFailed;
use App\Repositories\AlertRepository;

final class AlertService
{
    public function __construct(private readonly AlertRepository $alerts)
    {
    }

    public function ingestViolationDetected(ViolationDetected $event): array
    {
        $severity = $this->normalizeSeverity($event->severity);
        $score = $this->severityScore($severity, 'violation.' . $event->violationType);
        $message = sprintf('Violation detected: %s (%s)', $event->violationType, $severity);
        $eventType = 'violation.' . $event->violationType;
        $groupKey = $this->buildGroupKey($eventType, $event->workerId, 0, $severity);
        $dedupeHash = $this->buildDedupeHash($eventType, $event->workerId, 0, $severity);

        return $this->ingest([
            'event_type' => $eventType,
            'worker_id' => $event->workerId,
            'workflow_id' => 0,
            'severity' => $severity,
            'severity_score' => $score,
            'message' => $message,
            'group_key' => $groupKey,
            'dedupe_hash' => $dedupeHash,
        ]);
    }

    public function ingestWorkflowFailed(WorkflowExecutionFailed $event): array
    {
        $severity = $this->severityFromWorkflowFailure($event);
        $score = $this->severityScore($severity, 'workflow.failed');
        $message = sprintf('Workflow failed: %s (#%d) - %s', $event->workflowName, $event->workflowId, $event->error);
        $eventType = 'workflow.failed';
        $groupKey = $this->buildGroupKey($eventType, 0, $event->workflowId, $severity);
        $dedupeHash = $this->buildDedupeHash($eventType, 0, $event->workflowId, $severity);

        return $this->ingest([
            'event_type' => $eventType,
            'worker_id' => 0,
            'workflow_id' => $event->workflowId,
            'severity' => $severity,
            'severity_score' => $score,
            'message' => $message,
            'group_key' => $groupKey,
            'dedupe_hash' => $dedupeHash,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function ingest(array $payload): array
    {
        $dedupeWindowSec = (int) (getenv('ALERT_DEDUPE_WINDOW_SEC') ?: 120);
        $groupWindowMin = (int) (getenv('ALERT_GROUP_WINDOW_MIN') ?: 15);

        $dup = $this->alerts->findRecentByDedupeHash((string) $payload['dedupe_hash'], $dedupeWindowSec);
        if (is_array($dup) && isset($dup['id'])) {
            $alertId = (int) $dup['id'];
            $this->alerts->touchDuplicate($alertId);

            return [
                'alert_id' => $alertId,
                'status' => 'deduplicated',
                'severity' => (string) ($dup['severity'] ?? $payload['severity']),
                'severity_score' => (int) ($dup['severity_score'] ?? $payload['severity_score']),
            ];
        }

        $group = $this->alerts->findOpenGroup((string) $payload['group_key'], $groupWindowMin);
        if (is_array($group) && isset($group['id'])) {
            $groupId = (int) $group['id'];
            $this->alerts->incrementGroup($groupId);
            $groupCount = ((int) ($group['alerts_count'] ?? 1)) + 1;
        } else {
            $groupId = $this->alerts->createGroup((string) $payload['group_key'], (string) $payload['severity']);
            $groupCount = 1;
        }
        $payload['group_id'] = $groupId;
        $payload['status'] = 'open';
        $alertId = $this->alerts->createAlert($payload);

        $escalation = $this->evaluateEscalation((string) $payload['severity'], (int) $payload['severity_score'], $groupCount);
        if ($escalation['escalate']) {
            $this->alerts->escalateAlert($alertId, $escalation['reason']);
            $this->alerts->closeGroupAsEscalated($groupId);
            return [
                'alert_id' => $alertId,
                'status' => 'escalated',
                'severity' => (string) $payload['severity'],
                'severity_score' => (int) $payload['severity_score'],
                'reason' => $escalation['reason'],
            ];
        }

        return [
            'alert_id' => $alertId,
            'status' => 'open',
            'severity' => (string) $payload['severity'],
            'severity_score' => (int) $payload['severity_score'],
            'group_id' => $groupId,
            'group_count' => $groupCount,
        ];
    }

    private function normalizeSeverity(string $severity): string
    {
        $severity = strtolower(trim($severity));
        return in_array($severity, ['low', 'medium', 'high'], true) ? $severity : 'medium';
    }

    private function severityScore(string $severity, string $eventType): int
    {
        $base = match ($severity) {
            'high' => 90,
            'medium' => 60,
            default => 30,
        };
        if (str_contains($eventType, 'workflow.failed')) {
            $base += 5;
        }
        return min(100, max(1, $base));
    }

    private function severityFromWorkflowFailure(WorkflowExecutionFailed $event): string
    {
        $err = strtolower($event->error);
        if (str_contains($err, 'security') || str_contains($err, 'unauthorized')) {
            return 'high';
        }
        if (str_contains($err, 'timeout') || str_contains($err, 'retry')) {
            return 'medium';
        }
        return 'high';
    }

    private function buildGroupKey(string $eventType, int $workerId, int $workflowId, string $severity): string
    {
        return sha1(implode('|', [$eventType, $workerId, $workflowId, $severity]));
    }

    private function buildDedupeHash(string $eventType, int $workerId, int $workflowId, string $severity): string
    {
        return sha1(implode('|', [$eventType, $workerId, $workflowId, $severity]));
    }

    /** @return array{escalate: bool, reason: string} */
    private function evaluateEscalation(string $severity, int $score, int $groupCount): array
    {
        if ($severity === 'high' || $score >= 90) {
            return ['escalate' => true, 'reason' => 'high_severity'];
        }
        if ($severity === 'medium' && $groupCount >= 3) {
            return ['escalate' => true, 'reason' => 'repeat_medium_alerts'];
        }
        if ($severity === 'low' && $groupCount >= 5) {
            return ['escalate' => true, 'reason' => 'high_volume_low_alerts'];
        }

        return ['escalate' => false, 'reason' => ''];
    }
}
