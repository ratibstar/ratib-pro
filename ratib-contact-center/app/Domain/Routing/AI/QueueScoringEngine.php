<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\Queue\QueueRealtimeService;

/**
 * Ranks queues when multiple targets are viable or IVR hint is suboptimal.
 */
final class QueueScoringEngine
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly SkillMatcher $skillMatcher,
        private readonly ?QueueRealtimeService $queueRealtime = null,
        ?array $config = null
    ) {
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @return list<array{queue_id: int, queue_code: string, score: float, snapshot: array<string, mixed>}>
     */
    public function scoreQueues(int $tenantId, string $requiredSkill, ?string $preferredQueueCode = null): array
    {
        $queues = $this->listActiveQueues($tenantId);
        if ($queues === []) {
            return [];
        }

        $weights = $this->config['queue_score_weights'] ?? [];
        $realtime = $this->queueRealtime ?? new QueueRealtimeService();
        $scored = [];

        foreach ($queues as $queue) {
            $queueId = (int) $queue['id'];
            $code = (string) $queue['code'];
            $snapshot = $realtime->computeSnapshot($tenantId, $queueId);
            if ($snapshot === []) {
                continue;
            }

            $skillMatch = $this->skillMatcher->resolveRequiredSkill(null, $code) === $requiredSkill ? 1.0 : 0.4;
            $waitPenalty = min(1.0, (int) ($snapshot['longest_wait_seconds'] ?? 0) / max(1, (int) ($snapshot['sla_target_seconds'] ?? 300)));
            $availability = min(1.0, (int) ($snapshot['available_agents'] ?? 0) / max(1, (int) ($snapshot['waiting_count'] ?? 1)));
            $slaRiskScore = match ($snapshot['sla_risk'] ?? 'green') {
                'red' => 0.0,
                'yellow' => 0.5,
                default => 1.0,
            };

            $score = (
                ((1.0 - $waitPenalty) * (float) ($weights['wait_penalty'] ?? 0.35))
                + ($availability * (float) ($weights['availability'] ?? 0.30))
                + ($skillMatch * (float) ($weights['skill_match'] ?? 0.20))
                + ($slaRiskScore * (float) ($weights['sla_risk'] ?? 0.15))
            );

            if ($preferredQueueCode !== null && $code === $preferredQueueCode) {
                $score += 0.05;
            }

            $scored[] = [
                'queue_id' => $queueId,
                'queue_code' => $code,
                'score' => round($score, 4),
                'snapshot' => $snapshot,
            ];
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /** @return list<array<string, mixed>> */
    private function listActiveQueues(int $tenantId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id, code, name FROM rcc_queues WHERE tenant_id = :tid AND status = \'active\''
            );
            $stmt->execute(['tid' => $tenantId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
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
