<?php
/**
 * Replay / reconcile hints (safe defaults; workers hook later).
 */
declare(strict_types=1);

final class Ratib_ClientDashboard_RecoveryLayer
{
    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function hintsFromSnapshot(array $snapshot): array
    {
        $obs = $snapshot['observability'] ?? [];
        $flags = $obs['degraded_flags'] ?? [];
        $jobs = $snapshot['governance']['async']['recent_jobs'] ?? [];

        $replayables = [];
        foreach ($flags as $f) {
            if (strpos((string) $f, 'adapter:') === 0) {
                $replayables[] = [
                    'type' => 'adapter_refresh',
                    'target' => substr((string) $f, strlen('adapter:')),
                ];
            }
        }

        $reconcile = [];
        $warnings = $snapshot['governance']['consistency']['warnings'] ?? [];
        foreach ($warnings as $w) {
            if (($w['code'] ?? '') === 'active_service_with_failed_payments') {
                $reconcile[] = ['type' => 'billing_service_reconcile', 'priority' => 'high'];
            }
        }

        return [
            'replayable_actions' => $replayables,
            'reconcile_suggestions' => $reconcile,
            'recent_async_jobs' => $jobs,
            'note' => 'Connect workers to ratib_client_hub_jobs for automatic replay.',
        ];
    }
}
