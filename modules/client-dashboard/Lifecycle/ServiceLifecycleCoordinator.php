<?php
/**
 * Validates lifecycle transitions; execution remains adapter/runtime responsibility.
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_ServiceLifecycleCoordinator
{
    /** @var array<string, list<string>> */
    private const SERVICE_STATUS_GRAPH = [
        'pending' => ['active', 'failed', 'cancelled'],
        'active' => ['suspended', 'terminated', 'pending'],
        'suspended' => ['active', 'terminated'],
        'failed' => ['pending', 'terminated'],
    ];

    /** @var array<string, list<string>> */
    private const ACTION_TO_LIFECYCLE = [
        'renew' => ['renew'],
        'suspend' => ['suspend'],
        'restart' => ['activate'],
        'upgrade' => ['activate'],
        'cancel' => ['terminate'],
        'retry_payment' => ['retry'],
        'open_ticket' => ['recovery'],
    ];

    /**
     * @return array{allowed: bool, lifecycle_event: string, notes: string}
     */
    public function mapActionToLifecycle(string $verb, ?string $serviceStatus): array
    {
        $events = self::ACTION_TO_LIFECYCLE[$verb] ?? ['noop'];
        $event = $events[0];

        if ($verb === 'open_ticket') {
            return ['allowed' => true, 'lifecycle_event' => $event, 'notes' => 'support_channel'];
        }

        if ($serviceStatus === null || $serviceStatus === '') {
            return ['allowed' => true, 'lifecycle_event' => $event, 'notes' => 'target_unscoped'];
        }

        $st = strtolower($serviceStatus);
        if ($verb === 'suspend' && $st === 'suspended') {
            return ['allowed' => false, 'lifecycle_event' => $event, 'notes' => 'already_suspended'];
        }
        if ($verb === 'restart' && $st === 'terminated') {
            return ['allowed' => false, 'lifecycle_event' => $event, 'notes' => 'terminated'];
        }

        return ['allowed' => true, 'lifecycle_event' => $event, 'notes' => 'ok'];
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    public function canTransitionStatus(string $from, string $to): array
    {
        $from = strtolower($from);
        $to = strtolower($to);
        $allowed = self::SERVICE_STATUS_GRAPH[$from] ?? [];
        if (in_array($to, $allowed, true)) {
            return ['allowed' => true, 'reason' => ''];
        }

        return ['allowed' => false, 'reason' => 'invalid_status_transition'];
    }
}
