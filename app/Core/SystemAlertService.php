<?php
declare(strict_types=1);

namespace App\Core;

final class SystemAlertService
{
    /** @return list<array<string, mixed>> */
    public function evaluate(array $healthSnapshot): array
    {
        $alerts = [];
        $checks = is_array($healthSnapshot['checks'] ?? null) ? $healthSnapshot['checks'] : [];

        $failureRate = (float) (($checks['metrics']['failure_rate_pct'] ?? 0) ?: 0);
        $failureThreshold = (float) (($checks['metrics']['threshold_pct'] ?? 0) ?: 0);
        if ($failureThreshold > 0 && $failureRate > $failureThreshold) {
            $alerts[] = $this->alert('high', 'failure_rate_high', 'Failure rate exceeded threshold.', [
                'value' => $failureRate,
                'threshold' => $failureThreshold,
            ]);
        }

        $activeWorkflows = (int) (($checks['workflows']['active'] ?? 0) ?: 0);
        $workflowThreshold = (int) (($checks['workflows']['threshold'] ?? 0) ?: 0);
        if ($workflowThreshold > 0 && $activeWorkflows > $workflowThreshold) {
            $alerts[] = $this->alert('medium', 'workflows_stuck_or_backlogged', 'Active workflows exceeded safe threshold.', [
                'value' => $activeWorkflows,
                'threshold' => $workflowThreshold,
            ]);
        }

        $webhookBacklog = (int) (($checks['webhooks']['backlog'] ?? 0) ?: 0);
        $webhookThreshold = (int) (($checks['webhooks']['threshold'] ?? 0) ?: 0);
        if ($webhookThreshold > 0 && $webhookBacklog > $webhookThreshold) {
            $alerts[] = $this->alert('medium', 'webhook_backlog_high', 'Webhook delivery backlog exceeded threshold.', [
                'value' => $webhookBacklog,
                'threshold' => $webhookThreshold,
            ]);
        }

        return $alerts;
    }

    public function dispatchFailSafe(array $alerts): void
    {
        foreach ($alerts as $alert) {
            $line = '[system-alert] ' . json_encode($alert, JSON_UNESCAPED_SLASHES);
            if ($line !== false) {
                error_log($line);
            }
        }
    }

    /** @return array<string, mixed> */
    private function alert(string $severity, string $code, string $message, array $context): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'context' => $context,
            'timestamp' => gmdate('c'),
        ];
    }
}
