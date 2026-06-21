<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

use Ratib\ContactCenter\App\Domain\Queue\QueueRealtimeService;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\RoutingLogRepository;

/**
 * Rule-based SLA risk prediction (MVP — no ML).
 */
final class SlaPredictor
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly ?QueueRealtimeService $queueRealtime = null,
        private readonly ?RoutingLogRepository $routingLog = null,
        ?array $config = null
    ) {
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @param array<string, mixed> $erpContext
     * @return array{level: string, factors: array<string, mixed>}
     */
    public function predict(int $tenantId, int $queueId, array $erpContext = []): array
    {
        $snapshot = ($this->queueRealtime ?? new QueueRealtimeService())->computeSnapshot($tenantId, $queueId);
        if ($snapshot === []) {
            return ['level' => 'green', 'factors' => ['reason' => 'queue_not_found']];
        }

        $slaTarget = (int) ($snapshot['sla_target_seconds'] ?? 300);
        $longestWait = (int) ($snapshot['longest_wait_seconds'] ?? 0);
        $waiting = (int) ($snapshot['waiting_count'] ?? 0);
        $available = (int) ($snapshot['available_agents'] ?? 0);
        $load = (float) ($snapshot['distribution_load'] ?? 0.0);

        $thresholds = $this->config['sla_thresholds'] ?? [];
        $yellowRatio = (float) ($thresholds['yellow_ratio'] ?? 0.7);
        $redRatio = (float) ($thresholds['red_ratio'] ?? 1.0);

        $ratio = $slaTarget > 0 ? $longestWait / $slaTarget : 0.0;
        $historicalBreaches = ($this->routingLog ?? new RoutingLogRepository())
            ->countHistoricalBreaches($tenantId, $queueId);

        $level = 'green';
        if ($waiting === 0) {
            $level = 'green';
        } elseif ($ratio >= $redRatio || ($waiting > 0 && $available === 0) || $historicalBreaches >= 5) {
            $level = 'red';
        } elseif ($ratio >= $yellowRatio || $load >= 2.0 || $historicalBreaches >= 2) {
            $level = 'yellow';
        }

        if (($erpContext['flags']['open_sla_breach'] ?? false) === true && $level === 'green') {
            $level = 'yellow';
        }
        if (($erpContext['flags']['open_sla_breach'] ?? false) === true && $level === 'yellow') {
            $level = 'red';
        }

        $priorityMultiplier = (float) ($erpContext['priority_multiplier'] ?? 1.0);
        if ($priorityMultiplier >= 1.4 && $level === 'green' && $waiting > 0) {
            $level = 'yellow';
        }

        return [
            'level' => $level,
            'factors' => [
                'longest_wait_seconds' => $longestWait,
                'sla_target_seconds' => $slaTarget,
                'wait_ratio' => round($ratio, 3),
                'waiting_count' => $waiting,
                'available_agents' => $available,
                'distribution_load' => $load,
                'historical_breaches_7d' => $historicalBreaches,
                'erp_priority_multiplier' => $priorityMultiplier,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/routing.php';
        if (!is_file($path)) {
            return [];
        }
        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }
}
